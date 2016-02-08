<?php
if ( !defined('PHPWG_ROOT_PATH') )
  define('PHPWG_ROOT_PATH','../../');

include_once( PHPWG_ROOT_PATH.'include/common.inc.php' );
include_once( dirname(__FILE__) .'/include/functions.php');
include_once( dirname(__FILE__) .'/include/functions_map.php');

check_status(ACCESS_GUEST);
if (!isset($rvm_dir))
  access_denied( 'Plugin not installed' );

rvm_load_language();

$section = '';
if ( $conf['question_mark_in_urls']==false and
     isset($_SERVER["PATH_INFO"]) and !empty($_SERVER["PATH_INFO"]) )
{
  $section = $_SERVER["PATH_INFO"];
  $section = str_replace('//', '/', $section);
  if ( !isset($page['root_path']) )
  {
    $path_count = count( explode('/', $section) );
    $page['root_path'] = PHPWG_ROOT_PATH.str_repeat('../', $path_count-1);
  }
}
else
{
  foreach ($_GET as $key=>$value)
  {
    if (!strlen($value)) $section=$key;
    break;
  }
}
$page['meta_robots']['noindex']=1;

// deleting first "/" if displayed
$tokens = explode(
  '/',
  preg_replace('#^/#', '', $section)
  );
$next_token = 0;
$result = rvm_parse_blowup_url($tokens, $next_token);
$page = array_merge( $page, $result );

$order_by=null;
if ( isset($page['ll']) )
  $where_sql = rvm_ll_to_sql($page['ll'], $order_by);
else
  $where_sql = rvm_bounds_to_sql( $page['box'] );

$img_fields = ' i.id';

$was_flat = @$page['flat'];
$page['flat']=true;
rvm_build_section_items($img_fields, $where_sql, RVM_BUILD_HASH, $order_by);
$page['items']=array_keys($page['items']);
if (!$was_flat) unset($page['flat']);

$template->set_filename( 'map', dirname(__FILE__).'/template/mapl.tpl');

if (!empty($page['items']))
{
/* GENERATE THE CATEGORY LIST *************************************************/
$where_sql = 'i.id IN ('.implode(',', $page['items'] ).')';
$where_sql .= get_sql_condition_FandF(
        array( 'forbidden_categories' => 'category_id' ),
        ' AND'
      );
$query = '
SELECT DISTINCT c.id, c.name, c.permalink, COUNT(DISTINCT i.id) counter
  FROM '.IMAGES_TABLE.' i INNER JOIN '.IMAGE_CATEGORY_TABLE.' AS ic ON i.id=image_id
    INNER JOIN '.CATEGORIES_TABLE.' c ON c.id=category_id
  WHERE '.$where_sql.'
  GROUP BY category_id
  ORDER BY counter DESC
  LIMIT 0,5
;';
$categories = array_from_query($query);
$categories = add_level_to_tags($categories);

foreach( $categories as $category)
{
  $template->append(
    'related_categories', array(
      'U_MAP' => rvm_make_map_index_url( array( 'category' => $category ) ),
      'URL' => make_index_url( array( 'category' => $category ) ),
      'NAME' => trigger_change('render_element_description', $category['name']),
      'TITLE' => l10n_dec( '%d photo', '%d photos', $category['counter'] ),
      'CLASS' => 'tagLevel'.$category['level']
    )
    );
}

/* GENERATE THE TAG LIST ******************************************************/
$tags = get_common_tags( $page['items'], $conf['content_tag_cloud_items_number'], null);
$tags = add_level_to_tags($tags);
function counter_compare($a, $b)
{
  $d = $a['counter'] - $b['counter'];
  if ($d==0)
    return strcmp($a['name'], $b['name']);
  return -$d;
}
usort($tags, 'counter_compare');
foreach ($tags as $tag)
{
  $template->append(
    'related_tags',
    array_merge( $tag,
      array(
        'U_MAP' => rvm_make_map_index_url( array( 'tags' => array($tag) ) ),
        'URL' => make_index_url( array( 'tags' => array($tag) ) ),
        'TITLE' => l10n_dec( '%d photo', '%d photos', $tag['counter'] ),
      )
    )
  );
}
} // end !empty items


$title = '<a target="_top" title="'.sprintf( l10n('go to %s'),$page['title']).'" href="'.duplicate_index_url( array('start'=>0) ).'">'.$page['title'].'</a>';
if ( count($page['items']) > 0)
  $title.=' ['.count($page['items']).']';

$template->assign(
  array(
    'PLUGIN_ROOT_URL' => get_absolute_root_url(false).'plugins/'.$rvm_dir,
    'TITLE'   => $title,
    'U_HOME'  => make_index_url(),
    'U_KML'   => rvm_duplicate_kml_index_url( array('flat'=>1), array('start') ),
    'KML_LINK_TITLE' => sprintf( l10n('opens %s in Google Earth'), strip_tags($page['title']) ),
  )
  );

$url = rvm_duplicate_blowup_url(array('start'=>0));
$page['nb_image_page'] = $user['nb_image_page'];
if ($page['nb_image_page'] < 30)
	$page['nb_image_page'] *= 2;

$navbar = create_navigation_bar($url, count( $page['items'] ), $page['start'], $page['nb_image_page']);
$template->assign('navbar', $navbar);

$page['items'] = array_slice(
  $page['items'],
  $page['start'],
  $page['nb_image_page']
  );

$page['items'] = trigger_change('loc_index_thumbnails_selection', $page['items']);


$pictures = array();
if (count($page['items']) > 0)
{
  $rank_of = array_flip($page['items']);

  $query = '
SELECT *
  FROM '.IMAGES_TABLE.'
  WHERE id IN ('.implode(',', $page['items']).')
;';
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($result))
  {
    $row['rank'] = $rank_of[ $row['id'] ];
    $pictures[] = $row;
  }
  usort($pictures, 'rank_compare');
  unset($rank_of);
}

$tpl_thumbnails_var = null;

foreach ($pictures as $img)
{
  $page_url = duplicate_picture_url(
        array(
          'image_id' => $img['id'],
          'image_file' => $img['file'],
          'flat' => 1,
        ),
        array('start')
      );
  $name = render_element_name($img);
  $desc = render_element_description($img);

  $tpl_thumbnails_var[] = array_merge( $img, array(
		'NAME' => $name,
    'TN_ALT' => htmlspecialchars(strip_tags($name)),
    'TN_TITLE' => get_thumbnail_title($img, $name, $desc),
    'URL' => $page_url,
    'DESCRIPTION' => $desc,
    'src_image' => new SrcImage($img),
    ) );
}
$template->assign( array(
	'derivative_params' => trigger_change('get_index_derivative_params', ImageStdParams::get_by_type( pwg_get_session_var('index_deriv', IMG_THUMB) ) ),
	'SHOW_THUMBNAIL_CAPTION' => false,
	'thumbnails' => $tpl_thumbnails_var,
	'VERY_SIMPLE' => 'smartpocket' === $theme
    ) );
$template->set_filename('index_thumbnails', 'thumbnails.tpl');
$template->assign_var_from_handle('THUMBNAILS', 'index_thumbnails');

$title = $page['title'];
$page['body_id'] = 'theMapListPage';

include(PHPWG_ROOT_PATH.'include/page_header.php');
$template->parse('map');
include(PHPWG_ROOT_PATH.'include/page_tail.php');

?>
