{html_head}
<script src="//maps.googleapis.com/maps/api/js?sensor=false&amp;language={$lang_info.code}{if !empty($GMAPS_API_KEY)}&amp;key={$GMAPS_API_KEY}{/if}" type="text/javascript"></script>
{combine_script id='jquery' load='header' path='themes/default/js/jquery.min.js'}
{combine_script id='jquery.colorbox' load='async' require='jquery' path='themes/default/js/plugins/jquery.colorbox.min.js'}
{combine_css path='themes/default/js/plugins/colorbox/style2/colorbox.css'}
{/html_head}{html_style}
{$thumb_size=$current.derivatives.thumb->get_size()}
#mapPicture{
	float:left;
	width:{max(240,5+$thumb_size[0])}px;
}

#map{
	position:relative;
	height:512px;
	min-width:300px;
}

#linkPrev,#linkNext{
	display:none
}
@media screen and (-webkit-min-device-pixel-ratio:1.3){
	#thumbImg{
		width:{($thumb_size[0]/1.5)|intval}px;
		height:{($thumb_size[1]/1.5)|intval}px
	}
}
@media screen and (-webkit-min-device-pixel-ratio:2){
	#thumbImg{
		width:{($thumb_size[0]/2)|intval}px;
		height:{($thumb_size[1]/2)|intval}px
	}
}

@media screen and (max-height:512px){
	#map{	height:400px;}
}
@media screen and (max-height:360px){
	#map{	height:250px;}
}
@media screen and (max-height:320px){
	#map{	height:200px;}
}
{/html_style}

<div id="mapPicture">
<a href="{$U_NO_MAP}" title="{'return to normal view mode'|@translate}" rel="nofollow"><img id="thumbImg" src="{$current.derivatives.thumb->get_url()}" width={$thumb_size[0]} height={$thumb_size[1]} alt="thumb"></a>
<br/>
<a href="{$U_BLOWUP}" onclick="return blowupUrl(this.href);">{'More photos near this location'|@translate}</a>
<br/>
{$COMMENT_IMG}
<br/>
</div>

<div id="map"></div>

<div style="clear:both"></div>
<script type="text/javascript">{literal}
//<![CDATA[
jQuery(document).ready( function () {
	var mapElement = document.getElementById("map");
	var mapOpts = {
{/literal}{if isset($coordinates)}
		center: new google.maps.LatLng( {$coordinates.LAT}, {$coordinates.LON} ),
		zoom : 12,
{else}
		center: new google.maps.LatLng(0,0),
		zoom : 1,
{/if}{literal}
		mapTypeId: google.maps.MapTypeId.{/literal}{$MAP_TYPE}{literal}
	};
	
	var map = new google.maps.Map( mapElement, mapOpts );

{/literal}{if isset($coordinates)}
	var marker = new google.maps.Marker( {ldelim}
		position: map.getCenter(),
		map: map
		});
{/if}{literal}
}
);

function blowupUrl(theUrl)
{
	jQuery.colorbox({href: theUrl, iframe: 1, width: "99%", height: "99%"});
	return false;
}

//]]>
{/literal}</script>