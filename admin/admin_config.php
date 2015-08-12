<?php 
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

include_once( dirname(__FILE__).'/../include/functions_map.php' );

if ( isset($_POST['submit']) )
{
  if (isset($_POST['gmaps_api_key']))
  {
    $query = '
UPDATE '.CONFIG_TABLE.'
  SET value="'.$_POST['gmaps_api_key'].'"
  WHERE param="gmaps_api_key"
  LIMIT 1';
    pwg_query($query);
    list($conf['gmaps_api_key']) = array_from_query('SELECT value FROM '.CONFIG_TABLE.' WHERE param="gmaps_api_key"', 'value');
  }

  $gm_config = rvm_get_config();

  $n  = intval($_POST['nb_markers']);
  if ($n>0)
    $gm_config['nb_markers'] = $n;
  else
    $page['errors'][] = 'The number of markers must be >0';

  $n  = intval($_POST['nb_images_per_marker']);
  if ($n>1)
    $gm_config['nb_images_per_marker'] = $n;
  else
    $page['errors'][] = 'The number of iamges per marker must be >1';

  $gm_config['marker_icon'] = $_POST['marker_icon'];
	$gm_config['map_type'] = $_POST['map_type'];

  mkgetdir( dirname(rvm_get_config_file_name()) );
  $fp = fopen( rvm_get_config_file_name(), 'w');
  fwrite( $fp, serialize($gm_config) );
  fclose($fp);
}

$query = 'SELECT COUNT(*) FROM '.IMAGES_TABLE.' WHERE latitude IS NOT NULL';
list($nb_geotagged) = pwg_db_fetch_array( pwg_query($query) );

$template->assign(
    array(
      'NB_GEOTAGGED' => $nb_geotagged,
      'GMAPS_API_KEY' => $conf['gmaps_api_key'],
      'NB_MARKERS' => rvm_get_config_var('nb_markers',40),
      'NB_IMAGES_PER_MARKER' => rvm_get_config_var('nb_images_per_marker',20),
			'MAP_TYPE' => rvm_get_config_var('map_type','ROADMAP'),
    )
  );

$files = array();
$path=PHPWG_PLUGINS_PATH.$rvm_dir.'/template/markers/';
$dir_contents = opendir($path);
while (($filename = readdir($dir_contents)) !== false)
{
  if (!is_file($path.'/'.$filename) or get_extension($filename)!='tpl')
    continue;
	$files[] = get_filename_wo_extension($filename);
 }
closedir($dir_contents);

sort($files);
foreach($files as $file)
{
	$template->append('marker_icons',
      array(
        $file => str_replace( '_', ' ', $file),
      ),
      true
    );
}

$template->assign('selected_marker_icon', rvm_get_config_var('marker_icon', '') );

$map_types = array(
	"ROADMAP" => "Roadmap (Default)",
	"SATELLITE" => "Satellite",
	"HYBRID" => "Hybrid",
	"TERRAIN" => "Terrain",
);
$template->assign('map_types', $map_types);
?>
