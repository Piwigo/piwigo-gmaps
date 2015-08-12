<?php
global $page, $template, $conf, $rvm_dir;

$template->concat( 'PLUGIN_PICTURE_ACTIONS' , sprintf(RVM_ACTION_MODEL,
	duplicate_picture_url(), l10n('return to normal view mode'), '', 'map', 'map'
));

$template->assign(
  array(
    'RVM_PLUGIN_VERSION' => RVM_PLUGIN_VERSION,
    'PLUGIN_ROOT_URL' => get_absolute_root_url().'plugins/'.$rvm_dir,
		'PLUGIN_LOCATION' => 'plugins/'.$rvm_dir,
  )
  );

$page['meta_robots']=array('noindex'=>1, 'nofollow'=>1);


add_event_handler(
  'render_element_content',
  'rvm_picture_map_content',
  EVENT_HANDLER_PRIORITY_NEUTRAL-5,
  2
  );

add_event_handler( 'loc_end_picture', 'rvm_end_picture' );


function rvm_picture_map_content($content, $picture)
{
  include_once( dirname(__FILE__) .'/functions_map.php');
  global $template;
  $template->set_filename( 'map_content', dirname(__FILE__).'/../template/picture_map_content.tpl' );
  $template->assign(
    array(
			'MAP_TYPE' => rvm_get_config_var('map_type', 'ROADMAP'),
      'U_NO_MAP' => duplicate_picture_url(),
      'U_BLOWUP' => rvm_make_blowup_url( array('ll'=> array('lat'=>$picture['latitude'],'lon'=>$picture['longitude'])), array('start','box') ),
      'COMMENT_IMG' => trigger_change('render_element_description', $picture['comment'])
    )
  );
  if ( isset($picture['latitude']) )
  {
    $template->assign( 'coordinates',
        array(
          'LAT' => $picture['latitude'],
          'LON' => $picture['longitude'],
        )
      );
  }
  return $template->parse( 'map_content', true);
}

function rvm_end_picture()
{
  global $template;
  $template->assign( 'COMMENT_IMG', null);  // no legend below picture (we put it left)
}

?>
