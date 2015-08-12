<?php
define('PHPWG_ROOT_PATH','../../');

include_once( PHPWG_ROOT_PATH.'include/common.inc.php' );

if (!isset($rvm_dir))
  access_denied( 'Plugin not installed' );

include_once( dirname(__FILE__) .'/include/functions_map.php');
include_once( dirname(__FILE__) .'/include/functions.php');

rvm_load_language();
set_make_full_url();

$section = '';
if ( $conf['question_mark_in_urls']==false and
     isset($_SERVER["PATH_INFO"]) and !empty($_SERVER["PATH_INFO"]) )
{
  $section = $_SERVER["PATH_INFO"];
  $section = str_replace('//', '/', $section);
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
$result = rvm_parse_kml_url($tokens, $next_token);
$page = array_merge( $page, $result );

$order_by=null;
if ( isset($page['ll']) )
  $where_sql = rvm_ll_to_sql($page['ll'], $order_by);
else
  $where_sql = rvm_bounds_to_sql( $page['box'] );


$img_fields = ' i.id,i.representative_ext,i.name,i.comment,i.file,i.path,i.latitude AS lat,i.longitude AS lon,i.date_creation';

rvm_build_section_items($img_fields, $where_sql, RVM_BUILD_ARRAY, $order_by);


$dataTpl = new Template( PHPWG_ROOT_PATH );
$dataTpl->set_filename('main', dirname(__FILE__).'/template/earth_kml.tpl' );

$dataTpl->assign(
  array(
    'PAGE_TITLE' => strip_tags(@$page['title']),
    'CONTENT_ENCODING' => get_pwg_charset(),
    'PAGE_COMMENT' => strip_tags( @$page['comment'], '<a><br><p><b><i><small><strong><font>'),
    'U_INDEX' => duplicate_index_url( array('start'=>0) ),
  )
);

if ( !empty($page['items']) )
  $dataTpl->assign( 'NB_ITEMS_DESC', sprintf( l10n('%d photos'), count($page['items']) ).'<br/>' );


// +-----------------------------------------------------------------------+
// |              sub categories network links                             |
// +-----------------------------------------------------------------------+
if ( 'categories'==$page['section'] and !isset($page['flat']) )
{
  $query = '
SELECT id, name, permalink, comment, uppercats, representative_picture_id
  FROM '.CATEGORIES_TABLE.' INNER JOIN '.USER_CACHE_CATEGORIES_TABLE.'
  ON id = cat_id and user_id = '.$user['id'].'
  WHERE id_uppercat '.
  (!isset($page['category']) ? 'is NULL' : '= '.$page['category']['id']).'
  ORDER BY rank
;';
//  echo "<pre>" . var_export($query,true);
  $result = pwg_query($query);
  $categories = array();
  $thumbnail_ids=array();
  while ($row = pwg_db_fetch_assoc($result))
  {
    $categories[] = $row;
    if (isset($row['representative_picture_id']) and is_numeric($row['representative_picture_id']))
      $thumbnail_ids[] = $row['representative_picture_id'];
  }
  $thumbnail_src_of = array();
  if ( count($thumbnail_ids) )
  {
    $query = '
SELECT id, path, representative_ext
  FROM '.IMAGES_TABLE.'
  WHERE id IN ('.implode(',', $thumbnail_ids).')';
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result))
      $thumbnail_src_of[$row['id']] = DerivativeImage::thumb_url($row);
    unset($thumbnail_ids);
  }

  $cat_bounds = rvm_get_cat_bounds();

  foreach ($categories as $category)
  {
    if ( !isset( $cat_bounds[ $category['id'] ] ) )
      continue; // without geo tagged images

    $bounds = $cat_bounds[ $category['id'] ];

    if ( isset($bounds['self']) )
    {
      $count_desc = l10n_dec('%d photo', '%d photos', $bounds['self']['count']);
      if ( $bounds['self']['count'] == $bounds['count'] )
        $count_desc .= ' '.l10n('images_available_cpl');
      else
        $count_desc .= '/'.l10n_dec('%d photo', '%d photos', $bounds['count']).' '.l10n_dec('images_available_cat','images_available_cats', $bounds['nb_cats'] );
    }
    else
      $count_desc = l10n_dec('%d photo', '%d photos', $bounds['count'])
        .' '.l10n_dec('images_available_cat','images_available_cats', $bounds['nb_cats'] );

		$tpl_var = array(
				'NAME'    => $category['name'],
				'COMMENT' => trigger_change('render_category_literal_description', trigger_change('render_category_description', @$category['comment']) ),
				'U_CATEGORY' => make_index_url( array('category'=>$category) ),
				'U_KML'   => rvm_make_kml_index_url( array('section'=>'categories', 'category'=>$category) ),
				'U_MAP'   => rvm_make_map_index_url( array('section'=>'categories', 'category'=>$category) ),
				'COUNT_DESC' => $count_desc,
				'region'  => $bounds,
      );
    if ( isset( $thumbnail_src_of[$category['representative_picture_id']] ) )
      $tpl_var['TN_SRC'] = $thumbnail_src_of[$category['representative_picture_id']];

    $dataTpl->append( 'categories', $tpl_var );
  }
}



// +-----------------------------------------------------------------------+
// | generate content for items                                            |
// +-----------------------------------------------------------------------+
$bounds = array();
foreach( $page['items'] as $img )
{
	$bounds = bounds_add($bounds, $img['lat'], $img['lon']);
	$page_url = duplicate_picture_url(array('image_id' => $img['id'],'image_file' => $img['file']),  array('start') );

  $tpl_var = array(
      'U_PAGE'=> $page_url,
      'TN_SRC'  => DerivativeImage::url(IMG_THUMB, $img),
      'TITLE'  => render_element_name($img),
      'DESCRIPTION'  => render_element_description($img),
      'LAT'   => $img['lat'],
      'LON'   => $img['lon'],
      'DATE_CREATION' => $img['date_creation'],
    );
	$dataTpl->append('images', $tpl_var);
}

if ( !empty($bounds) )
  $dataTpl->assign( 'region', $bounds );

$dataTpl->smarty->registerPlugin( 'modifier', 'xmle', 'rvm_xmle');
function rvm_xmle($s) {
	if( strcspn($s, '&<') != strlen($s))
		return "<![CDATA[$s]]>";
	return $s;
}

/*header('Cache-Control: public' );
header('Expires: '.gmdate('D, d M Y H:i:s', time()+600).' GMT');
header('Pragma:');*/
header('Content-Disposition: inline; filename="'.str2url($page['title']).'.kml";' );
header('Content-Type: application/vnd.google-earth.kml+xml; charset='.get_pwg_charset());
if (isset($_GET['debug']))
  header('Content-Type: text/xml; charset='.get_pwg_charset());


$dataTpl->pparse('main');

?>
