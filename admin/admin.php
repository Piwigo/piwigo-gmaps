<?php 
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');
include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');

$template->set_filename('plugin_admin_content', dirname(__FILE__).'/admin.tpl');

if (!isset($_GET['tab']))
  $page['tab'] = 'config';
else
  $page['tab'] = $_GET['tab'];

$my_base_url = get_admin_plugin_menu_link(__FILE__);

$tabsheet = new tabsheet();
$tabsheet->add( 'config', 'Configuration', add_url_params( $my_base_url, array('tab'=>'config') ) );
$tabsheet->select($page['tab']);

$tabsheet->assign();

$my_base_url = $tabsheet->sheets[ $page['tab'] ]['url'];
$template->set_filename( 'tab_data', dirname(__FILE__).'/admin_'.$page['tab'].'.tpl' );
include_once( dirname(__FILE__).'/admin_'.$page['tab'].'.php');
$template->assign_var_from_handle( 'TAB_DATA', 'tab_data');
$template->assign_var_from_handle( 'ADMIN_CONTENT', 'plugin_admin_content');

/*if ( empty($conf['gmaps_api_key']) )
  $page['infos'][] = 'Please define the Google maps api key';*/

?>
