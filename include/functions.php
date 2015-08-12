<?php

function rvm_load_language($source=false)
{
	global $lang,$lang_info,$conf,$template;
	if ( !isset($lang['Map']) )
	{
		if ( $lang_info['code']!='en' || $conf['debug_l10n'] || $source!==false)
			load_language('lang', dirname(__FILE__).'/../');
		else
			$template->smarty->registerFilter('pre', 'rvm_load_language');
	}
	return $source;
}

function rvm_items_have_latlon($items)
{
  $query = '
SELECT id FROM '.IMAGES_TABLE.'
WHERE latitude IS NOT NULL
  AND id IN ('.implode(',', $items).')
ORDER BY NULL
LIMIT 0,1';
	if ( pwg_db_num_rows(pwg_query($query))> 0)
		return true;
	return false;
}

function rvm_make_map_picture_url($params)
{
	$map_url = make_picture_url($params);
	return add_url_params($map_url, array('map'=>null) );
}

function rvm_duplicate_map_picture_url()
{
	$map_url = duplicate_picture_url();
	return add_url_params($map_url, array('map'=>null) );
}

function rvm_make_map_index_url($params=array())
{
	global $conf, $rvm_dir;
	$url = get_root_url().'map';
	if ($conf['php_extension_in_urls'])
		$url .= '.php';
	if ($conf['question_mark_in_urls'])
		$url .= '?';
	$url .= make_section_in_url($params);
	$url = add_well_known_params_in_url($url, array_intersect_key($params, array('flat'=>1) ) );
	return $url;
}

function rvm_duplicate_map_index_url($redefined=array(), $removed=array())
{
	return rvm_make_map_index_url(
		params_for_duplication($redefined, $removed)
		);
}

function rvm_duplicate_kml_index_url($redefined=array(), $removed=array())
{
	return rvm_make_kml_index_url(
		params_for_duplication($redefined, $removed)
		);
}

function rvm_make_kml_index_url($params)
{
	global $conf, $rvm_dir;
	$url = get_root_url().'plugins/'.$rvm_dir.'/kml.php';
	if ($conf['question_mark_in_urls'])
		$url .= '?';

	$url .= make_section_in_url($params);
	unset( $params['start'] );
	if ( 'categories'!=$params['section']) unset( $params['flat'] );
	$url = add_well_known_params_in_url($url, $params);
	$get_params = array();
	if ( isset($params['box']) and !empty($params['box']) )
	{
		include_once( dirname(__FILE__).'/functions_map.php' );
		if ( ! bounds_is_world($params['box']) )
			$get_params['box'] = bounds_to_url($params['box']);
	}
	if ( isset($params['ll']) and !empty($params['ll']) )
		$get_params['ll'] = $params['ll']['lat'].','.$params['ll']['lon'];
	$url = add_url_params($url, $get_params );
	return $url;
}
?>