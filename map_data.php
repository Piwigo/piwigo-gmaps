<?php
if ( !defined('PHPWG_ROOT_PATH') )
  define('PHPWG_ROOT_PATH','../../');
include_once( PHPWG_ROOT_PATH.'include/common.inc.php' );

if (!isset($rvm_dir))
  access_denied( 'Plugin not installed' );

include_once( dirname(__FILE__) .'/include/functions_map.php');
include_once( dirname(__FILE__) .'/include/functions.php');


//set_make_full_url();
$page['root_path']=get_absolute_root_url(false);

$rewritten = '';
foreach (array_keys($_GET) as $key)
{
  $rewritten = $key;
  break;
}
// deleting first "/" if displayed
$tokens = explode(
  '/',
  preg_replace('#^/#', '', $rewritten)
  );
$next_token = 0;
$result = rvm_parse_map_data_url($tokens, $next_token);
$page = array_merge( $page, $result );

$where_sql = rvm_bounds_to_sql( $page['box'] );

$img_fields = ' i.id,i.representative_ext,i.name,i.comment,i.file,i.path,i.latitude AS lat,i.longitude AS lon,i.width,i.height,i.rotation';

$was_flat = @$page['flat'];
$page['flat']=true;
rvm_build_section_items($img_fields, $where_sql, RVM_BUILD_HASH);
if (!$was_flat) unset($page['flat']);

/*header('Cache-Control: public' );
header('Expires: '.gmdate('D, d M Y H:i:s', time()+600).' GMT');
header('Pragma:');*/
header('Content-Type: text/plain; charset='.get_pwg_charset());
header('X-Robots-Tag: noindex');

$clusters = array();
$cluster_debug = '';
if ( !empty($page['items']) )
{
  include_once( dirname(__FILE__) .'/include/cluster_maker.php');
  $cm = new ClusterMaker();
  $clusters = $cm->make_clusters(
      $page['items'],
      isset($_GET['lap']) ? $_GET['lap'] : 0.01,
      isset($_GET['lop']) ? $_GET['lop'] : 0.01,
      isset($_GET['n']) ? $_GET['n'] : rvm_get_config_var('nb_markers',40)
    );
  $cluster_debug .= ' cluster: '. $cm->debug_str. ';';
}

function jsgm_position( $position )
{
  return 'new google.maps.LatLng(' . $position['lat'] . ',' . $position['lon'] . ')';
}

function jsgm_bounds( $bounds )
{
  return 'new google.maps.LatLngBounds(' . jsgm_position(bounds_sw($bounds)) . ',' . jsgm_position(bounds_ne($bounds)) . ')';
}

function jsgm_str( $str )
{
  return '"'. str_replace(array("\\",'"',"\n","\r","\t"), array("\\\\",'\"',"\\n","\\r","\\t"), $str) .'"';
}

$start_output = get_moment();


echo "{\ntitle:", jsgm_str( strip_tags(trigger_change('render_element_description', $page['title']) ) )
  , ",\npage_url:", jsgm_str( duplicate_index_url( array('start'=>0) ) )
  , ",\nblowup_url:", jsgm_str( rvm_duplicate_blowup_url( array('start'=>0) ) )
  , ",\nkml_url:", jsgm_str( rvm_duplicate_kml_index_url( array('start'=>0, 'flat'=>1) ) )
  , ",\nnb_items:", count($page['items']);

echo ",\nbounds:";
if ( isset($cm) )
  echo jsgm_bounds( $cm->bounds );
else
  echo "null";

echo ",\n\nnb_clusters:", count($clusters);

echo ",\nimage_clusters:[\n";
$i=0;
$thumb_params = ImageStdParams::get_by_type(IMG_THUMB);
$page_url_model = duplicate_picture_url(
		array(
			'image_id' => 123456789,
			'image_file' => 'dummy_file.txt',
			'flat' => 1,
		),
		array('start')
	);

foreach( $clusters as $c )
{
  if ($i) echo ",\n\n" ;
  echo '{position:', jsgm_position( bounds_center($c->bounds) );
  echo ",\nbounds:", jsgm_bounds( $c->bounds );
  echo ",\nnb_items:", count($c->items);
  echo ",\n";

  echo 'blowup_url:"', rvm_duplicate_blowup_url(array('box'=>$c->bounds), array('start') ), '"';

  $max_per_cluster = rvm_get_config_var('nb_images_per_marker',20);
  if ( count($c->items) >  $max_per_cluster )
    $c->items = array_slice($c->items, 0, $max_per_cluster);

  echo ",\nitems:[";
  for ($j=0; $j<count($c->items); $j++)
  {
    $img = $page['items'] [ $c->items[$j] ];

		$page_url = str_replace(array('123456789','dummy_file'), array($img['id'], get_filename_wo_extension($img['file'])), $page_url_model);
		$thumb = new DerivativeImage($thumb_params, new SrcImage($img));
		$thsize = $thumb->get_size();

    if ($j) echo( "," );
    echo "{tn:", jsgm_str( $thumb->get_url() );
		echo ",w:",$thsize[0],',h:',$thsize[1];
    echo ",t:", jsgm_str( render_element_name($img) );
    echo ",d:", jsgm_str( render_element_description($img) );
    echo ",url:", jsgm_str( $page_url );
    echo "}";
  }
  echo "]\n}";
  $i++;
}
echo "] /*clusters*/\n";

$time = get_elapsed_time($t2, get_moment());
$page['queries_time'] = number_format($page['queries_time'],3,'.',' ');
echo "\n,debug:'$time; out:", get_elapsed_time($start_output,get_moment()), ";$cluster_debug queries:", $page['count_queries'], " in ", $page['queries_time'], "s'\n" ;

echo '}';
?>
