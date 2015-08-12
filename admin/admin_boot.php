<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

include_once( dirname(dirname(__FILE__)) .'/include/functions.php');

add_event_handler('get_admin_plugin_menu_links', 'rvm_plugin_admin_menu' );
function rvm_plugin_admin_menu($menu)
{
	array_push($menu,
			array(
				'NAME' => 'Maps & Earth',
				'URL' => get_admin_plugin_menu_link(dirname(__FILE__).'/admin.php')
			)
		);
	return $menu;
}

add_event_handler('get_batch_manager_prefilters', 'rvm_get_batch_manager_prefilters');
function rvm_get_batch_manager_prefilters($prefilters)
{
	rvm_load_language();
	$prefilters[] = array('ID' => 'geotagged', 'NAME' => l10n('Geotagged'));
	$prefilters[] = array('ID' => 'not geotagged', 'NAME' => l10n('Not geotagged'));
	return $prefilters;
}

add_event_handler('perform_batch_manager_prefilters', 'rvm_perform_batch_manager_prefilters', 50, 2);
function rvm_perform_batch_manager_prefilters($filter_sets, $prefilter)
{
	if ($prefilter==="geotagged")
		$query = 'latitude IS NOT NULL AND longitude IS NOT NULL';
	elseif ($prefilter==="not geotagged")
		$query = 'latitude IS NULL OR longitude IS NULL';

	if ( isset($query) )
	{
    $query = '
SELECT id
  FROM '.IMAGES_TABLE.'
  WHERE '.$query;
		$filter_sets[] = array_from_query($query, 'id');
	}
	return $filter_sets;
}

add_event_handler('loc_end_element_set_global', 'rvm_loc_end_element_set_global');
function rvm_loc_end_element_set_global()
{
	rvm_load_language();
	global $template;
	$template->append('element_set_global_plugins_actions',
		array('ID' => 'geotag', 'NAME'=>l10n('Geotag'), 'CONTENT' => '
  <label>'.l10n('Latitude').' (-90=S to 90=N)
    <input type="text" size="8" name="lat">
  </label>
  <label>'.l10n('Longitude').' (-180=E to 180=W)
    <input type="text" size="9" name="lon">
  </label> (Empty values will erase coordinates)
'));
}

add_event_handler('element_set_global_action', 'rvm_element_set_global_action', 50, 2);
function rvm_element_set_global_action($action, $collection)
{
	if ($action!=="geotag")
		return;
	global $page;
	$lat = trim($_POST['lat']);
	$lon = trim($_POST['lon']);
	if ( strlen($lat)>0 and strlen($lon)>0 )
	{
		if ( (double)$lat<=90 and (double)$lat>=-90
				and (double)$lon<=180 and (double)$lon>=-180 )
			$update_query = 'latitude='.$lat.', longitude='.$lon;
		else
			$page['errors'][] = 'Invalid lat or lon value';
	}
	elseif ( strlen($lat)==0 and strlen($lon)==0 )
		$update_query = 'latitude=NULL, longitude=NULL';
	else
		$page['errors'][] = 'Both lat/lon must be empty or not empty';

	if (isset($update_query))
	{
		$update_query = '
UPDATE '.IMAGES_TABLE.' SET '.$update_query.'
  WHERE id IN ('.implode(',',$collection).')';
    pwg_query($update_query);
  }
}

add_event_handler('loc_begin_element_set_unit', 'rvm_loc_begin_element_set_unit');
function rvm_loc_begin_element_set_unit()
{
  global $page;

  if (!isset($_POST['submit']))
    return;

  $collection = explode(',', $_POST['element_ids']);

  $datas = array();
  $errors = array();
  $form_errors = 0;

  $query = '
SELECT id, name
  FROM '.IMAGES_TABLE.'
  WHERE id IN ('.implode(',', $collection).')
;';
  $result = pwg_query($query);

  while ($row = pwg_db_fetch_assoc($result))
  {
    if (!isset($_POST['lat-'.$row['id']]))
    {
      $form_errors++;
      continue;
    }
    $error = false;
    $data = array(
      'id' => $row['id'],
      'latitude' => trim($_POST['lat-'.$row['id']]),
      'longitude' => trim($_POST['lon-'.$row['id']])
    );

    if ( strlen($data['latitude'])>0 and strlen($data['longitude'])>0 )
    {
      if ( (double)$data['latitude']>90 or (double)$data['latitude']<-90
          or (double)$data['longitude']>180 or (double)$data['longitude']<-180 )
        $error = true;
    }
    elseif ( strlen($data['latitude'])==0 and strlen($data['longitude'])==0 )
    {
      // nothing
    }
    else
    {
      $error = true;
    }

    if ($error)
      $errors[] = $row['name'];
    else
      $datas[] = $data;
  }

  mass_updates(
    IMAGES_TABLE,
    array(
      'primary' => array('id'),
      'update' => array('latitude', 'longitude')
      ),
    $datas
    );

  if (count($errors)>0)
  {
    $page['errors'][] = 'Invalid lat or lon value for files: '.implode(', ', $errors);
  }
  if ($form_errors)
    $page['errors'][] = 'Maps & Earth: Invalid form submission for '.$form_errors.' photos';
}

add_event_handler('loc_end_element_set_unit', 'rvm_loc_end_element_set_unit');
function rvm_loc_end_element_set_unit()
{
  global $template, $conf, $page, $is_category, $category_info;

  $template->set_prefilter('batch_manager_unit', 'rvm_prefilter_batch_manager_unit');
}

function rvm_prefilter_batch_manager_unit($content)
{
  $needle = '</table>';
  $pos = strpos($content, $needle);
  if ($pos!==false)
  {
    $add = '<tr><td><strong>{\'Geotag\'|@translate}</strong></td>
      <td>
        <label>{\'Latitude\'|@translate}
          <input type="text" size="8" name="lat-{$element.id}" value="{$element.latitude}">
        </label>
        <label>{\'Longitude\'|@translate}
          <input type="text" size="9" name="lon-{$element.id}" value="{$element.longitude}">
        </label>
      </td>
    </tr>';
    $content = substr_replace($content, $add, $pos, 0);
  }
  return $content;
}
?>
