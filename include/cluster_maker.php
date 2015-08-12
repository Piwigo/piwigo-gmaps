<?php

function cluster_area_compare( &$a, &$b )
{
	$aa = bounds_lat_range($a->bounds) * bounds_lon_range($a->bounds);
	$ab = bounds_lat_range($b->bounds) * bounds_lon_range($b->bounds);
	return -$aa+$ab; // biggest first
}

final class Cluster
{
	public $bounds;
	public $items;

	function __construct($b = array(), $i = array())
	{
		$this->bounds = $b;
		$this->items = $i;
	}
}

class ClusterMaker
{
	private $_image_map;
	private $_image_ranks;

	var $bounds;
	var $debug_str;

	function make_clusters($images, $maxLatPrecision, $maxLonPrecision, $maxNbMarkers)
	{
		$this->bounds = array();
		foreach($images as &$img)
		{
			$img['lat'] = floatval($img['lat']);
			$img['lon'] = floatval($img['lon']);
			$this->bounds = bounds_add($this->bounds, $img['lat'], $img['lon'] );
		}
		unset($img);

		$this->_image_map = $images;
		$this->_image_ranks = array();
		$this->debug_str = '';

		$start = get_moment();
		$total_iterations = 0;
		$total_generations = 0;

		$pending_split = array( new Cluster($this->bounds, array_keys($this->_image_map) ) );
		$result = array();

		while ( count($pending_split) )
		{
			$total_generations++;
			$next_level_to_split = array();
			while ( count($pending_split) )
			{
				$current = array_shift( $pending_split );
				$splitted = $this->_split_cluster($current, $maxLatPrecision, $maxLonPrecision );
				if ( count($splitted)>1 )
				{
					/*echo('splitted: ' . var_export($current['bounds'],true). ' in '. count($splitted)."\n" );
					foreach( $splitted as $k => $split )
						echo("sub $k: " . var_export($split['bounds'],true)."\n" );*/
					$total_iterations += count($current->items);
					$next_level_to_split = array_merge($next_level_to_split, $splitted );
					if ( count($result)+count($pending_split)+count($next_level_to_split) >= $maxNbMarkers )
					{
						$result = array_merge($result, $pending_split, $next_level_to_split );
						$pending_split = $next_level_to_split = array();
						break;
					}
				}
				else
					$result[] = $current;
			}
			$pending_split = $next_level_to_split;
			usort( $pending_split, 'cluster_area_compare' );
		}

		$merged = $this->_try_merge_clusters( $result, $maxLatPrecision, $maxLonPrecision );
		$this->debug_str = get_elapsed_time($start, get_moment()) .' gen:'.$total_generations.' alg:'.count($this->_image_map).'+'.$total_iterations;
		if ($merged)
			$this->debug_str .= " merged:$merged";
		$this->_image_map = null;
		return $result;
	}

	function _try_merge_clusters(&$clusters, $maxLatPrecision, $maxLonPrecision)
	{
		$ret = 0;
		for ($i=0; $i<count($clusters)-1; $i++)
		{
			$ci = bounds_center( $clusters[$i]->bounds );
			for ($j=$i+1; $j<count($clusters); $j++)
			{
				$cj = bounds_center( $clusters[$j]->bounds );
				$rlat = abs($ci['lat']-$cj['lat']) / $maxLatPrecision;
				$rlon = abs($ci['lon']-$cj['lon']) / $maxLonPrecision;
				if ( $rlat<1 && $rlon<1
						&& $rlat+$rlon<1.1)
				{
					//print_r( "i=$i; j=$j \n"); var_export( $clusters[$i]['bounds'] ); var_export( $clusters[$j]['bounds'] );
					$clusters[$i]->items = array_merge( $clusters[$i]->items, $clusters[$j]->items );
					if ( empty($this->_image_ranks) )
						$this->_image_ranks = array_flip( array_keys($this->_image_map) );
					usort( $clusters[$i]->items, array($this, '_image_rank_compare') );
					$clusters[$i]->bounds = bounds_union($clusters[$i]->bounds, $clusters[$j]->bounds );
					array_splice( $clusters, $j, 1);
					$j--;
					$ci = bounds_center( $clusters[$i]->bounds );
					$ret++;
				}
			}
		}
		return $ret;
	}

	function _image_rank_compare($a, $b)
	{
		return $this->_image_ranks[$a] - $this->_image_ranks[$b];
	}

	function _split_cluster($cluster, $maxLatPrecision, $maxLonPrecision)
	{
		$latRange = bounds_lat_range($cluster->bounds);
		$lonRange = bounds_lon_range($cluster->bounds);

		$lat_nb_tiles = max( 1, $latRange/$maxLatPrecision );
		$lon_nb_tiles = max( 1, $lonRange/$maxLonPrecision );
		//echo('lat_nb '.$lat_nb_tiles.' lon_nb '.$lon_nb_tiles."\n");
		if ($lat_nb_tiles<2 and $lon_nb_tiles<2)
			return array();

		$ll_tile_factor = $lat_nb_tiles/$lon_nb_tiles;

		if ($ll_tile_factor > 3)
		{ // 1x3
			$lat_step = $latRange/3;
			$lon_step = $lonRange;
		}
		elseif ($ll_tile_factor < 1/3)
		{ // 3x1
			$lat_step = $latRange;
			$lon_step = $lonRange/3;
		}
		elseif ($ll_tile_factor > 2)
		{ // 2x3
			$lat_step = max( $latRange/3, $maxLatPrecision );
			$lon_step = max( $lonRange/2, $maxLonPrecision );
		}
		elseif ($ll_tile_factor < 1/2)
		{ // 3x2
			$lat_step = max( $latRange/2, $maxLatPrecision );
			$lon_step = max( $lonRange/3, $maxLonPrecision );
		}
		else
		{ // 3x3
			$lat_step = max( $latRange/3, $maxLatPrecision );
			$lon_step = max( $lonRange/3, $maxLonPrecision );
		}

		if ( $cluster->bounds['count'] > 200 )
		{
			if ($lat_step>4*$maxLatPrecision) $lat_step = $lat_step/2;
			if ($lon_step>4*$maxLonPrecision) $lon_step = $lon_step/2;
		}

		$lat_step += 1e-7;
		$lon_step += 1e-7;
		//echo ( "$lat_step $latRange x $lon_step $lonRange tiles $lat_nb_tiles x $lon_nb_tiles\n" );

		$lon_nb_tiles = ceil( $lonRange / $lon_step );

		$clusters = array();
		foreach ( $cluster->items as $id )
		{
			$lon = $this->_image_map[$id]['lon'];
			$lat = $this->_image_map[$id]['lat'];

			$idx_lon = floor ( ( $lon - $cluster->bounds['w'] ) / $lon_step );
			$idx_lat = floor ( ( $lat - $cluster->bounds['s'] ) / $lat_step );

			$idx = $lon_nb_tiles * $idx_lat + $idx_lon;

			if ( !isset($clusters[$idx]) )
			{
				$clusters[$idx] = new Cluster();
			}
			$clusters[$idx]->items[] = $id;
			$clusters[$idx]->bounds = bounds_add( $clusters[$idx]->bounds , $lat, $lon );
		}
		return $clusters;
	}
}

?>