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
	$path_count = count( explode('/', $section) );
	$page['root_path'] = PHPWG_ROOT_PATH.str_repeat('../', $path_count-1);
	if ( strncmp($page['root_path'], './', 2) == 0 )
	{
		$page['root_path'] = substr($page['root_path'], 2);
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

// deleting first "/" if displayed
$tokens = explode(
  '/',
  preg_replace('#^/#', '', $section)
  );
$next_token = 0;
$result = rvm_parse_map_data_url($tokens, $next_token);
$page = array_merge( $page, $result );


if (isset($page['category']))
  check_restrictions($page['category']['id']);

if ( !isset($_GET['ll']) /*and ($page['section']!='categories' or isset($page['category']) )*/ )
{
  $img_fields = 'MIN(i.latitude) s, MIN(i.longitude) w, MAX(i.latitude) n, MAX(i.longitude) e';
  $page['flat']=true;
  rvm_build_section_items($img_fields, null, RVM_BUILD_AGGREGATE);
  //var_export( $page['items'] );
  if ( isset($page['items'][0]['s']) )
    $template->assign('initial_bounds', $page['items'][0] );
  unset( $page['items'] );
}


$map_data_url  = get_absolute_root_url().'plugins/'.$rvm_dir.'/map_data.php?';
$map_data_url .= $section;

$template->set_filename( 'map', dirname(__FILE__).'/template/map.tpl' );

$template->assign(
  array(
    'CONTENT_ENCODING' => get_pwg_charset(),
    'RVM_PLUGIN_VERSION' => RVM_PLUGIN_VERSION,
    'PLUGIN_ROOT_URL' => get_absolute_root_url().'plugins/'.$rvm_dir,
		'PLUGIN_LOCATION' => 'plugins/'.$rvm_dir,
    'U_MAP_DATA' => $map_data_url,
    'GALLERY_TITLE' => $conf['gallery_title'],
    'U_HOME' => make_index_url(),
    'U_HOME_MAP' => rvm_make_map_index_url(),
		'MAP_TYPE' => rvm_get_config_var('map_type', 'ROADMAP'),
  )
  );

$marker_js_file = rvm_get_config_var('marker_icon', '');
if ( !empty($marker_js_file) )
  $marker_js_file = dirname(__FILE__).'/template/markers/'.$marker_js_file.'.tpl';
if ( !empty($marker_js_file) and file_exists($marker_js_file) )
{
  $template->set_filename( 'map_marker_icon', $marker_js_file );
  $template->assign_var_from_handle('MAP_MARKER_ICON_JS', 'map_marker_icon');
}
else
  $template->assign('MAP_MARKER_ICON_JS', 'new PwgSingleStyler()');

$template->pparse('map');
$template->p();
?>