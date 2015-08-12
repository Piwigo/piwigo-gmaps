<?php
function rvgm_drop_old_columns()
{
  $q = '
ALTER TABLE '.IMAGES_TABLE.' DROP COLUMN `lat`';
  pwg_query( $q );

  $q = '
ALTER TABLE '.IMAGES_TABLE.' DROP COLUMN `lon`';
  pwg_query( $q );
}

function plugin_install()
{
  $q = '
INSERT INTO '.CONFIG_TABLE.' (param,value,comment)
  VALUES
  ("gmaps_api_key","","Google Maps API key")
;';
  pwg_query($q);
}

function plugin_activate()
{
  global $conf;

	$query = 'SHOW COLUMNS FROM '. IMAGES_TABLE .' LIKE "lat";';
	if (pwg_db_num_rows(pwg_query($query)))
		rvgm_drop_old_columns();
	pwg_query('DELETE FROM '.CONFIG_TABLE.' WHERE param IN("gmaps_auto_sync")');

  if (!isset($conf['rv_gmaps_add_map.php']) or $conf['rv_gmaps_add_map.php'])
  {
    $dir_name = basename( dirname(__FILE__) );
    $c = <<<EOD
<?php
define('PHPWG_ROOT_PATH','./');
include_once( PHPWG_ROOT_PATH. 'plugins/$dir_name/map.php');
?>
EOD;
    $fp = fopen( PHPWG_ROOT_PATH.'map.php', 'w' );
    fwrite( $fp, $c);
    fclose( $fp );
  }

	$old = dirname(__FILE__).'/_rvgm_config.dat';
	if ( is_file($old) )
	{
	  global $conf;
		$dest = PHPWG_ROOT_PATH.$conf['data_location'].'/plugins/'.basename(dirname(__FILE__)).'.dat';
	  if (!file_exists($dest) )
	  {
	    mkgetdir( dirname($dest) );
	  	copy( $old, $dest );
	  }
	  unlink( $old );
	}
}

function plugin_deactivate()
{
  global $conf;

  if (!isset($conf['rv_gmaps_remove_map.php']) or $conf['rv_gmaps_remove_map.php'])
  {
    @unlink( PHPWG_ROOT_PATH.'map.php' );
  }
}

function plugin_uninstall()
{
  rvgm_drop_old_columns();

  $q = '
DELETE FROM '.CONFIG_TABLE.' WHERE param IN("gmaps_api_key","gmaps_auto_sync")';
  pwg_query( $q );
  
  global $conf;
	$dest = PHPWG_ROOT_PATH.$conf['data_location'].'/plugins/'.basename(dirname(__FILE__)).'.dat';
  @unlink( $dest );
}

?>