<?php
function parse_fract( $f )
{
	$nd = explode( '/', $f );
	return $nd[0] ? ($nd[0]/$nd[1]) : 0;
}

function parse_lat_lon( $arr )
{
	$v=0;
	$v += parse_fract( $arr[0] );
	$v += parse_fract( $arr[1] )/60;
	$v += parse_fract( $arr[2] )/3600;
	return $v;
}

function exif_to_lat_lon( $exif )
{
	$exif = array_intersect_key( $exif, array_flip( array('GPSLatitudeRef', 'GPSLatitude', 'GPSLongitudeRef', 'GPSLongitude') ) );
	if ( count($exif)!=4 )
		return '';
	if ( !in_array($exif['GPSLatitudeRef'], array('S', 'N') ) )
		return 'GPSLatitudeRef not S or N';
	if ( !in_array($exif['GPSLongitudeRef'], array('W', 'E') ) )
		return 'GPSLongitudeRef not W or E';
	if (!is_array($exif['GPSLatitude']) or !is_array($exif['GPSLongitude']) )
		return 'GPSLatitude and GPSLongitude are not arrays';
		
	$lat = parse_lat_lon( $exif['GPSLatitude'] );
	if ( $exif['GPSLatitudeRef']=='S' )
		$lat = -$lat;
	$lon = parse_lat_lon( $exif['GPSLongitude'] );
	if ( $exif['GPSLongitudeRef']=='W' )
		$lon = -$lon;
	return array ($lat,$lon);
}

?>