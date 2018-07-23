<?php
/* Reminder about urls
map      unused but passes flat to map_data ...
map_data automatic show flat, start is unused
mapl     automatic show flat, start is used in GET only (blowup)
kml      if flat network links, start is unused*/

function rvm_get_config_file_name()
{
  global $page, $conf;
  unset( $page['__rvm_config__'] );
  return PHPWG_ROOT_PATH.$conf['data_location'].'/plugins/'.basename(dirname(dirname(__FILE__))).'.dat';
}

function rvm_get_config()
{
  global $page;
  if (isset($page['__rvm_config__']))
    return $page['__rvm_config__'];
  $x = @file_get_contents( rvm_get_config_file_name() );
  if ($x!==false)
    $page['__rvm_config__'] = unserialize($x);
  if ( !is_array(@$page['__rvm_config__']) )
    $page['__rvm_config__'] = array();
  return $page['__rvm_config__'];
}

function rvm_get_config_var($var, $default)
{
  global $page;
  if (!isset($page['__rvm_config__']))
    rvm_get_config();
  if ( array_key_exists($var,$page['__rvm_config__']) )
    return $page['__rvm_config__'][$var];
  return $default;
}

function rvm_parse_map_data_url($tokens, &$next_token)
{
  $page = parse_section_url($tokens, $next_token);
  if ( !isset($page['section']) )
    $page['section'] = 'categories';

  $page = array_merge( $page, parse_well_known_params_url( $tokens, $next_token) );
  $page['start']=0;
  $page['box'] = rvm_bounds_from_url( @$_GET['box'] );
  return $page;
}

function rvm_parse_kml_url($tokens, &$next_token)
{
  $page = parse_section_url($tokens, $next_token);
  if ( !isset($page['section']) )
    $page['section'] = 'categories';

  $page = array_merge( $page, parse_well_known_params_url( $tokens, $next_token) );
  $page['start']=0;
  $page['box'] = rvm_bounds_from_url( @$_GET['box'] );
  if ( isset($_GET['ll']) )
    $page['ll'] = rvm_ll_from_url( $_GET['ll'] );
  return $page;
}

function rvm_duplicate_blowup_url($redefined=array(), $removed=array())
{
  return rvm_make_blowup_url(
    params_for_duplication($redefined, $removed)
    );
}

function rvm_make_blowup_url($params)
{
  global $conf, $rvm_dir;
  if ( file_exists(PHPWG_ROOT_PATH.'mapl.php') )
    $url = get_root_url().'mapl';
  else
    $url = get_root_url().'plugins/'.$rvm_dir.'/mapl';
  if ($conf['php_extension_in_urls'])
    $url .= '.php';
  if ($conf['question_mark_in_urls'])
    $url .= '?';
  $url .= make_section_in_url($params);
  $url = add_well_known_params_in_url($url, array_intersect_key($params, array('flat'=>1) ) );

  $get_params = array();
  if ( isset($params['box']) and !empty($params['box']) and !bounds_is_world($params['box']) )
    $get_params['box'] = bounds_to_url($params['box']);
  elseif ( isset($params['ll']) and !empty($params['ll']) )
    $get_params['ll'] = $params['ll']['lat'].','.$params['ll']['lon'];
  if ( isset($params['start']) and $params['start']>0 )
    $get_params['start'] = $params['start'];
  $url = add_url_params($url, $get_params );
  return $url;
}

function rvm_parse_blowup_url($tokens, &$next_token)
{
  $page = parse_section_url($tokens, $next_token);
  if ( !isset($page['section']) )
    $page['section'] = 'categories';
  $page = array_merge( $page, parse_well_known_params_url( $tokens, $next_token) );
  $page['start']=0;
  if ( isset($_GET['start']) )
    $page['start']=$_GET['start'];
  $page['box'] = rvm_bounds_from_url( @$_GET['box'] );
  if ( isset($_GET['ll']) )
    $page['ll'] = rvm_ll_from_url( $_GET['ll'] );
  return $page;
}

function rvm_bounds_from_url($str)
{
  if ( !isset($str) or strlen($str)==0 )
    return null;
  $r = explode(',', $str );
  if ( count($r) != 4)
    bad_request( $str.' is not a valid geographical bound' );
  $b = array(
      's' => $r[0],
      'w' => $r[1],
      'n' => $r[2],
      'e' => $r[3],
    );
  return $b;
}

function rvm_bounds_to_sql( $b )
{
  if ( !isset($b) or empty($b) )
    return null;
  $sql_where = 'i.latitude BETWEEN '.$b['s'].' AND '.($b['n']);
  $sql_where .= ' AND ';
  if ($b['e'] >= $b['w'])
    $sql_where .= 'i.longitude BETWEEN '.$b['w'].' AND '.($b['e']);
  else
    $sql_where .= 'i.longitude NOT BETWEEN '.($b['e']+1e-7).' AND '.($b['w']-1e-7);
  return $sql_where;
}

function bounds_to_url($b, $p = 6 )
{
  if ( empty($b) )
    return '';
  return round($b['s'],$p).','.round($b['w'],$p).','.round($b['n'],$p).','.round($b['e'],$p);
}

function rvm_ll_from_url($str)
{
  if ( !isset($str) or strlen($str)==0 )
    return null;
  $r = explode(',', $str );
  if ( count($r) != 2)
    bad_request( $str.' is not a valid geographical position' );
  $b = array('lat'=>$r[0],'lon'=>$r[1]);
  return $b;
}

function rvm_ll_to_sql( $ll, &$order_by )
{
  $cos_lat = max( 1e-2, cos($ll['lat']*M_PI/180) );
  $dlat = 3; // 1 degree is approx between 111 and 116 km
  $dlon = min( 3/$cos_lat, 180 );
  $bounds = array(
      's' => $ll['lat'] - $dlat,
      'w' => $ll['lon'] - $dlon,
      'n' => $ll['lat'] + $dlat,
      'e' => $ll['lon'] + $dlon,
    );
  if ($bounds['s']<-90) $bounds['s']=-90;
  if ($bounds['w']<-180) $bounds['w']+=360;
  if ($bounds['n']>90) $bounds['n']=90;
  if ($bounds['e']>180) $bounds['e']-=360;
  $where_sql = rvm_bounds_to_sql( $bounds );
  $order_by = 'ORDER BY POW('.$ll['lat'].'-i.latitude,2)+POW('.$cos_lat.'*('.$ll['lon'].'-i.longitude),2)';
  return $where_sql;
}


function bounds_is_world( $b )
{
  if (empty($b)) return false;
  return $b['n']>=90 and $b['s']<=-90 and $b['w']<=-180 and $b['e']>=180;
}

function bounds_add($bounds, $lat, $lon)
{
  if ( empty($bounds) )
  {
    $bounds = array(
      's' => $lat,
      'n' => $lat,
      'w' => $lon,
      'e' => $lon,
      'count' => 1
      );
  }
  else
  {
    if ( $lat>$bounds['n'] )
      $bounds['n']=$lat;
    elseif ( $lat<$bounds['s'] )
      $bounds['s']=$lat;
    if ( $lon>$bounds['e'] )
      $bounds['e']=$lon;
    elseif ( $lon<$bounds['w'] )
      $bounds['w']=$lon;
    $bounds['count']=$bounds['count']+1;
  }

  return $bounds;
}

function bounds_union($b1, $b2)
{
  $total_count = $b1['count'] + $b2['count'];
  $res =
    array_merge(
      $b1,
      array(
        's' => min( $b1['s'], $b2['s'] ),
        'n' => max( $b1['n'], $b2['n'] ),
        'w' => min( $b1['w'], $b2['w'] ),
        'e' => max( $b1['e'], $b2['e'] ),
        'count' => $total_count,
      )
    );
  if ( isset($b2['min_date']) )
    $res = array_merge(
      $res,
      array(
        'min_date' => min( $b1['min_date'], $b2['max_date'] ),
        'max_date' => max( $b1['max_date'], $b2['max_date'] ),
      )
    );
  return $res;
}

function bounds_center($b)
{
  if (empty($b))
    return array();
  return array(
    'lat' => ($b['s']+$b['n'])/2,
    'lon' => ($b['w']+$b['e'])/2,
    );
}

function bounds_sw($b)
{
  if (empty($b))
    return array();
  return array(
    'lat' => $b['s'],
    'lon' => $b['w'],
    );
}

function bounds_ne($b)
{
  if (empty($b))
    return array();
  return array(
    'lat' => $b['n'],
    'lon' => $b['e'],
    );
}


function bounds_lat_range($b)
{
  if (empty($b))
    return 0;
  return $b['n'] - $b['s'];
}

function bounds_lon_range($b)
{
  if (empty($b))
    return 0;
  return $b['e'] - $b['w'];
}


function rvm_get_cat_bounds()
{
	global $persistent_cache;
	if ($persistent_cache->get('rvgm_album_bounds', $cat_bounds))
		return $cat_bounds;

  $query = '
SELECT category_id cat_id, uppercats, MAX(latitude) AS n, MIN(latitude) AS s, MAX(longitude) AS e, MIN(longitude) AS w, COUNT(i.id) count, MIN(i.date_creation) min_date, MAX(i.date_creation) max_date
  FROM
    '.CATEGORIES_TABLE.' as c
      INNER JOIN
    '.IMAGE_CATEGORY_TABLE.' ON category_id = c.id
      INNER JOIN
    '.IMAGES_TABLE.' i ON image_id=i.id
  WHERE latitude IS NOT NULL
  GROUP BY category_id';

  $result = pwg_query($query);
  $uppercats_list = array();
  $cat_bounds = array();
  while ($row = pwg_db_fetch_assoc($result))
  {
    $uppercats_list[] = $row['uppercats'];
    $cat_id = $row['cat_id'];
    unset( $row['cat_id'], $row['uppercats'] );
    $cat_bounds[ $cat_id ] = $row;
    $cat_bounds[ $cat_id ]['self'] = $row;
  }
  natsort($uppercats_list);
  //echo "<pre>" . var_export($uppercats_list,true);

  foreach ( array_reverse($uppercats_list) as $uppercats)
  {
    $cat_ids = explode(',', $uppercats);
    $bounds = $cat_bounds[ $cat_ids[count($cat_ids)-1] ];
    for ($i=count($cat_ids)-2; $i>=0; $i--)
    {
      $this_bounds = @$cat_bounds[ $cat_ids[$i] ];
      if ( !isset($this_bounds) )
      {
        $this_bounds = $bounds;
        unset($this_bounds['self']);
      }
      else
      {
//        if ($cat_ids[$i]==43) echo "<pre>Before\n" . var_export($this_bounds,true);
//        if ($cat_ids[$i]==43) echo "<pre>Union\n" . var_export($bounds,true);
        $this_bounds = bounds_union($this_bounds, $bounds);
      }
      $this_bounds['nb_cats'] = 1 + @$this_bounds['nb_cats'];
//      if ($cat_ids[$i]==43) echo "<pre>" . var_export($this_bounds,true);
      $cat_bounds[ $cat_ids[$i] ] = $this_bounds;
    }
  }
  //echo "<pre>" . var_export($cat_bounds,true);

	$persistent_cache->set('rvgm_album_bounds', $cat_bounds);
  return $cat_bounds;
}


define('RVM_BUILD_ARRAY',     0);
define('RVM_BUILD_HASH',      1);
define('RVM_BUILD_AGGREGATE', 2);

function rvm_build_section_items($img_fields, $where_sql, $mode, $order_by=null)
{
  global $page, $conf, $user;

  $page['items'] = array();

  if (empty($where_sql))
    $where_sql .= 'i.latitude IS NOT NULL';
  $where_sql_images_only = $where_sql;
  $where_sql .= get_sql_condition_FandF(
      array
        (
          'forbidden_categories' => 'category_id',
          'forbidden_images' => 'i.id'
        ),
      "\n  AND"
  );

  switch ($mode)
  {
    case RVM_BUILD_ARRAY:
      $func = 'array_from_query';
      $group_by = '
  GROUP BY i.id';
      break;
    case RVM_BUILD_HASH:
      $func = function($q) { return hash_from_query($q, "id"); };
      $group_by = '
  GROUP BY i.id';
      break;
    case RVM_BUILD_AGGREGATE:
      $func = 'array_from_query';
      $group_by = '';
      break;
  }

  if ($mode != RVM_BUILD_AGGREGATE )
  {
    if ($order_by==null and pwg_get_session_var('image_order',0) > 0)
    {
      $orders = get_category_preferred_image_orders();

      $conf['order_by'] = str_replace(
        'ORDER BY ',
        'ORDER BY '.$orders[ pwg_get_session_var('image_order',0) ][1].',',
        $conf['order_by']
        );
      $page['super_order_by'] = true;
    }
    elseif ($order_by!=null)
    {
      $conf['order_by']=$order_by;
      $page['super_order_by'] = true;
    }
    elseif ( 'categories' == $page['section'] and isset($page['category']['image_order']) )
    {
      $conf['order_by'] = 'ORDER BY '.$page['category']['image_order'];
    }
  }
  else
  {
    $conf['order_by'] = 'ORDER BY NULL';
    $page['super_order_by'] = true;
  }

  if ('categories' == $page['section'])
  {
    if ( isset($page['flat']) or isset($page['category']) )
    {
      if (isset($page['flat']))
      {
        if ( isset($page['category']) )
        {
          $subcat_ids = get_subcat_ids( array($page['category']['id']) );
          $where_sql .= ' AND category_id IN ('.implode(',',$subcat_ids).')';
        }
      }
      else
      {
        $where_sql .= ' AND category_id='.$page['category']['id'];
      }

      $query='
SELECT '.$img_fields.'
  FROM '.IMAGES_TABLE.' i INNER JOIN '.IMAGE_CATEGORY_TABLE.' ON i.id=image_id
  WHERE '.$where_sql.$group_by.'
  '.$conf['order_by'];

      $page['items'] = $func($query);
    }
    if ( isset($page['category']) )
    {
      $page['title'] = trigger_change('render_category_name', $page['category']['name']);
      $page['comment'] = $page['category']['comment'];
    }
    else
      $page['title'] = $conf['gallery_title'];
  }
  else if ('tags' == $page['section'])
  {
    $items = get_image_ids_for_tags( array($page['tags'][0]['id']), 'AND', $where_sql_images_only, 'ORDER BY NULL' );
    if ( !empty($items) )
    {
      $query = '
SELECT '.$img_fields.'
  FROM '.IMAGE_CATEGORY_TABLE.' INNER JOIN '.IMAGES_TABLE.' i ON i.id=image_id
  WHERE image_id IN ('.implode(',', $items).')'
  .$group_by.'
  '.$conf['order_by'];

      $page['items'] = $func($query);
    }
    $page['title'] = strip_tags( get_tags_content_title() );
  }
  elseif ('search' == $page['section'])
  {
    include_once( PHPWG_ROOT_PATH .'include/functions_search.inc.php' );
    $search_result = get_search_results($page['search'], @$page['super_order_by'], $where_sql_images_only);
    if ( !empty($search_result['items']) )
    {
      $query = '
SELECT '.$img_fields.'
  FROM '.IMAGES_TABLE.' i
  WHERE id IN ('.implode(',', $search_result['items']).')'
  .$group_by.'
  '.$conf['order_by'].'
;';

      if ($mode != RVM_BUILD_AGGREGATE )
      {
        $page['items'] = hash_from_query($query, 'id' );

        global $item_ranks;
        $item_ranks = array_flip($search_result['items']);
        function cmp_item_hash($a, $b)
        {
          global $item_ranks;
          return $item_ranks [ $a['id'] ] - $item_ranks [ $b['id'] ];
        }
        uasort( $page['items'], 'cmp_item_hash' );
        unset( $item_ranks );
      }
      else
        $page['items'] = $func($query);
    }

    $page['title'] = l10n('Search results');
  }
  elseif ('recent_pics' == $page['section'])
  {
    $conf['order_by'] = ' ORDER BY hit DESC, file ASC';

    $query ='
SELECT '.$img_fields.'
  FROM '.IMAGES_TABLE.' i
    INNER JOIN '.IMAGE_CATEGORY_TABLE.' AS ic ON id = ic.image_id
  WHERE date_available >= SUBDATE(
      CURRENT_DATE,INTERVAL '.$user['recent_period'].' DAY)
    AND '.$where_sql
  .$group_by.'
  '.$conf['order_by'].'
  LIMIT 0, '.$conf['top_number'].'
;';

    $page['items'] = $func($query);
    $page['title'] = l10n('Recent photos');
  }
  elseif ('list'==$page['section'])
  {
    $query ='
SELECT '.$img_fields.'
  FROM '.IMAGES_TABLE.' i
    INNER JOIN '.IMAGE_CATEGORY_TABLE.' AS ic ON id = ic.image_id
  WHERE image_id IN ('.implode(',', $page['list']).')
    AND '.$where_sql
  .$group_by.'
  '.$conf['order_by'].'
;';

    $page['items'] = $func($query);
    $page['title'] = l10n('Random photos');
  }
  else
    fatal_error( 'section '.$page['section']. ' not handled '. __FILE__);
}

?>