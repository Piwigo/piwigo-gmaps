<?php /*
Plugin Name: RV Maps&Earth
Version: 2.10.b
Description: Extend your gallery with Google Maps and Google Earth ...
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=122
Author: rvelices
Author URI: http://www.modusoptimus.com/
*/
define( 'RVM_PLUGIN_VERSION', '2.10.b');
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

add_event_handler('loc_end_index', 'rvm_end_index' );
add_event_handler('loc_begin_index_category_thumbnails', 'rvm_index_cat_thumbs_displayed');
add_event_handler('loc_begin_index_thumbnails', 'rvm_begin_index_thumbnails');

add_event_handler('picture_pictures_data', 'rvm_picture_pictures_data' );
add_event_handler('blockmanager_apply', 'rvm_blockmanager_apply');

add_event_handler('qsearch_pre', 'rvm_qsearch_pre', EVENT_HANDLER_PRIORITY_NEUTRAL, dirname(__FILE__).'/qsearch.inc.php');
add_event_handler('qsearch_get_scopes', 'rvm_qsearch_get_scopes', EVENT_HANDLER_PRIORITY_NEUTRAL, dirname(__FILE__).'/qsearch.inc.php');
add_event_handler('get_popup_help_content', 'rvm_get_popup_help', EVENT_HANDLER_PRIORITY_NEUTRAL, dirname(__FILE__).'/qsearch.inc.php');

global $rvm_dir;
$rvm_dir = basename( dirname(__FILE__) );


function rvm_index_cat_thumbs_displayed()
{
	global $page;
	$page['rvm_cat_thumbs_displayed'] = true;
}

function rvm_begin_index_thumbnails( $pictures )
{
	global $page;
	foreach ($pictures as $picture)
	{
		if ( isset($picture['latitude']) )
		{
			$page['rvm_items_have_latlon'] = true;
			break;
		}
	}
}

define('RVM_ACTION_MODEL', '<a href="%s" title="%s" rel="nofollow" class="pwg-state-default pwg-button"%s><span class="pwg-icon pwg-icon-%s"></span><span class="pwg-button-text">%s</span></a>');

function rvm_end_index()
{
	global $page, $filter, $template, $rvm_dir;

	if ( isset($page['chronology_field']) || $filter['enabled'] )
		return;

	$geo = @$page['qsearch_details']['geo'];
	if (!empty($geo) && !@$page['start'])
	{
		include_once(dirname(__FILE__).'/qsearch.inc.php');
		rvm_qsearch_show_alt($geo);
	}
	if ( 'categories' == @$page['section'])
	{ // flat or no flat ; has subcats or not;  ?
		if ( ! @$page['rvm_cat_thumbs_displayed'] and empty($page['items']) )
			return;
	}
	else
	{
		if (
			!in_array( @$page['section'], array('tags','search','recent_pics','list') )
			)
			return;
		if ( empty($page['items']) )
			return;
	}

	include_once( dirname(__FILE__) .'/include/functions.php');

	if ( !empty($page['items']) )
	{
		if (!@$page['rvm_items_have_latlon'] and ! rvm_items_have_latlon( $page['items'] ) )
			return;
	}
	rvm_load_language();

	$map_url = rvm_duplicate_map_index_url( array(), array('start') );
	$link_title = sprintf( l10n('displays %s on a map'), strip_tags($page['title']) );
	$template->concat( 'PLUGIN_INDEX_ACTIONS' , "\n<li>".sprintf(RVM_ACTION_MODEL,
		$map_url, $link_title, '', 'map', l10n('Map')
		).'</li>');
}

function rvm_picture_pictures_data($pictures)
{
	if ( isset($pictures['current']['latitude']) and isset($pictures['current']['longitude']) )
	{
		global $template;
		$template->append('head_elements', '<meta name=geo.position content='.$pictures['current']['latitude'].';'.$pictures['current']['longitude'].'>' );
		include_once( dirname(__FILE__) .'/include/functions.php');
		rvm_load_language();
		if ( isset($_GET['map']) )
			include_once( dirname(__FILE__) .'/include/picture_map.inc.php');
		else
		{
			global $rvm_dir;
			$map_url = rvm_duplicate_map_picture_url();
			$link_title = sprintf( l10n('displays %s on a map'), strip_tags($pictures['current']['TITLE']) );
			$template->concat( 'PLUGIN_PICTURE_ACTIONS' , sprintf(RVM_ACTION_MODEL,
					$map_url, $link_title, ' target="_top"', 'map', l10n('Map')
				));
		}
	}
	elseif ( isset($_GET['map']) )
			redirect( duplicate_picture_url() );
	return $pictures;
}

function rvm_blockmanager_apply($mb_arr)
{
	if ($mb_arr[0]->get_id() != 'menubar' )
		return;
	if ( ($block=$mb_arr[0]->get_block('mbMenu')) != null )
	{
		include_once( dirname(__FILE__) .'/include/functions.php');
		rvm_load_language();
		global $conf;
		$link_title = sprintf( l10n('displays %s on a map'), strip_tags($conf['gallery_title']) );
		$block->data['rv_gmaps'] = array(
			'URL' => rvm_make_map_index_url( array('section'=>'categories') ),
			'TITLE' => $link_title,
			'NAME' => l10n('World map'),
			'REL'=> 'rel=nofollow'
		);
	}
}

if (defined('IN_ADMIN')) {
	include_once(dirname(__FILE__).'/admin/admin_boot.php');
}
?>