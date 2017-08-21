<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset={$CONTENT_ENCODING}" />
<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
<meta name="robots" content="noindex,nofollow" />
<title>{$GALLERY_TITLE}</title>

<script src="//maps.googleapis.com/maps/api/js?sensor=false&amp;language={$lang_info.code}&amp;libraries=places{if !empty($GMAPS_API_KEY)}&amp;key={$GMAPS_API_KEY}{/if}" type="text/javascript"></script>
{combine_script id='jquery' load='header' path='//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js'}
{combine_script id='jquery.colorbox' load='async' require='jquery' path='themes/default/js/plugins/jquery.colorbox.min.js'}
{combine_css path="`$PLUGIN_LOCATION`/template/style.css" version=$RVM_PLUGIN_VERSION}
{combine_css path='themes/default/js/plugins/colorbox/style2/colorbox.css'}
{combine_script id='rvm.dl' load='header' path="`$PLUGIN_LOCATION`/template/data_loader.js" version=$RVM_PLUGIN_VERSION}
{combine_script id='rvm.dh' load='header' path="`$PLUGIN_LOCATION`/template/data_handler.js" version=$RVM_PLUGIN_VERSION}
{combine_script id='rvm.pl' load='header' path="`$PLUGIN_LOCATION`/template/page_linker.js" version=$RVM_PLUGIN_VERSION}
{combine_script id='rvm.st' load='header' path="`$PLUGIN_LOCATION`/template/styler.js" version=$RVM_PLUGIN_VERSION}
{combine_script id='core.scripts' load='header' path='themes/default/js/scripts.js'}
{get_combined_css}
{get_combined_scripts load='header'}
<!--[if lt IE 7]>
<style type="text/css">
	#map {ldelim} position:auto; height: 100%; }
</style>
<![endif]-->

<script type="text/javascript">
//<![CDATA[
var map;

document.is_debug = false;
if ( document.location.search.match(/[\?&]debug/) )
	document.is_debug = true;

function glog(msg) {
	if (console) {
		var b = map.getBounds();
		if (b)
			console.log(msg + " b="+b.toUrlValue() + " c="+map.getCenter().toUrlValue() + " z="+map.getZoom() );
		else
			console.log(msg);
	}
}

function positionMap(){
	$("#map").css('top', ($("#titlebar").height())+"px");
}

function load()
{
	var mapOptions = {
		mapTypeId: google.maps.MapTypeId.{$MAP_TYPE},
		overviewMapControl: true,
		overviewMapControlOptions: { opened: document.documentElement.offsetWidth>640}
	}

	if (!PageLinker.url2Map(mapOptions))
	{
{if isset($initial_bounds)}
		mapOptions.iniBounds = new google.maps.LatLngBounds( new google.maps.LatLng({$initial_bounds.s},{$initial_bounds.w}), new google.maps.LatLng({$initial_bounds.n},{$initial_bounds.e}) );
		mapOptions.center = mapOptions.iniBounds.getCenter();
{else}
		mapOptions.center = new google.maps.LatLng(0,0);
		mapOptions.zoom = 2;
{/if}
	}

	map = new google.maps.Map( document.getElementById("map"), mapOptions );
	
	if (mapOptions.iniBounds)
		map.fitBounds(mapOptions.iniBounds);

{if isset($smarty.get.debug)}
	google.maps.event.addListener(map, "idle", function() { glog("idle"); });
	google.maps.event.addListener(map, "bounds_changed", function() { glog("bounds_changed");} );
	google.maps.event.addListener(map, "center_changed", function() { glog("center_changed");} );
	google.maps.event.addListener(map, "maptypeid_changed", function() { glog("maptypeid_changed");} );
	google.maps.event.addListener(map, "zoom_changed", function() { glog("zoom_changed");} );
	google.maps.event.addListener(map, "drag", function() { glog("drag");} );
{/if}
	pwgPageLinker = new PageLinker(map, "aLinkToThisPage" );

	var pwgStyler = {$MAP_MARKER_ICON_JS};

	map.pwgDataLoader = new PwgDataLoader(map, { rectangle_of_confusion: pwgStyler.roc} );
	google.maps.event.addListener(map.pwgDataLoader, "dataloading", function() {
		var pre = '<img src="{$PLUGIN_ROOT_URL}/icons/progress_s.gif" width="16" height="16" alt="~"> ';
		document.getElementById("dataLoadStatus").innerHTML = pre + Localization.get("Loading");
		}
	);
  
	google.maps.event.addListener(map.pwgDataLoader, "dataloadfailed", function(responseCode) {
		document.getElementById("dataLoadStatus").innerHTML = Localization.get("Failed") + " "+responseCode;
		}
		);

	map.pwgDataHandler = new PwgDataHandler(map, { styler: pwgStyler, show_all_img_src: "{$PLUGIN_ROOT_URL}/icons/pic_s.gif" } );
	google.maps.event.addListener(map.pwgDataLoader, "dataloaded", pwgBind(map.pwgDataHandler, map.pwgDataHandler.handle) );

	map.pwgDataLoader.start( "{$U_MAP_DATA}" );

	positionMap();
	$(window).on("resize", positionMap);
	var ac = new google.maps.places.Autocomplete( document.getElementById('q') );
	ac.bindTo('bounds', map);
	google.maps.event.addListener(ac, 'place_changed', function() {
		var place = ac.getPlace();
		if (place.geometry.viewport) {
			map.fitBounds(place.geometry.viewport);
		} else {
			map.setCenter(place.geometry.location);
			map.setZoom(17);  // Why 17? Because it looks good.
		}
	} );
}

function unload()
{
	if (map)
	{
		!map.pwgDataLoader || map.pwgDataLoader.terminate();
		!map.pwgDataHandler || map.pwgDataHandler.terminate();
	}
}


var Localization =
{
  _strings: {
"go to %s": "{'go to %s'|@translate|@escape:javascript}",
"Next": "{'Next'|@translate|@escape:javascript}",
"Prev": "{'Prev'|@translate|@escape:javascript}",
"out of %d": "{'out of %d'|@translate|@escape:javascript}",
"Loading": "{'Loading'|@translate|@escape:javascript}",
"Failed": "{'Failed'|@translate|@escape:javascript}",
"Show all": "{'Show all'|@translate|@escape:javascript}",
"show all photos around this location": "{'show all photos around this location'|@translate|@escape:javascript}",
"displays %s on a map" : "{'displays %s on a map'|@translate|@escape:javascript}",
"opens %s in Google Earth" : "{'opens %s in Google Earth'|@translate|@escape:javascript}",
"%d photos" : "{'%d photos'|@translate|@escape:javascript}",
dontMindTheComma: ""
    },

  get: function( str ) {
		var lang_str = this._strings[str];
		if (lang_str == undefined)
		{
			if (document.is_debug) glog("Language string undefined '"+ str+"'");
			return str;
		}
		return lang_str;
	},

  getQ: function( str ) {
		return '"'+this.get(str)+'"';
	},

  fmt1: function () {
		var str = arguments[0];
		str = this.get(str);
		str = str.replace( '%s', "%" ).replace( '%d', "%" ).replace('%', arguments[1]);
		return str;
		
	}
}
//]]>
</script>
</head>

<body onload="load()" onunload="unload()">

<div id="titlebar">
	<div class="titlebar_links">
		<span id="dataLoadStatus">{'Loading'|translate}</span>
		<span class="gmnoprint">
			<input type="text" size="24" id="q">
		</span>
		<a id="aKml" href="" type="application/vnd.google-earth.kml+xml" class="gmnoprint"><img src="//maps.google.com/mapfiles/ms/view_as_kml.png" width="16" height="16" alt="kml"><span class="hideable"> KML</span></a>
		<a id="aLinkToThisPage" href="" class="gmnoprint"><img src="//maps.google.com/mapfiles/bar_icon_link.gif" alt="&lt;-&gt;" width="16" height="16"><span class="hideable"> {'Link to this page'|@translate}</span></a>
    <a id="aBlowup" href="" onclick="return PwgDataHandler.blowupUrl(this.href);" class="gmnoprint" title="{'show all photos around this location'|@translate}">
			<img src="{$PLUGIN_ROOT_URL}/icons/pic_s.gif" width="16" height="16" alt="" title="{'show all photos around this location'|@translate}">
			<span>{'Show all'|@translate}</span>
		</a>
  </div>
  <div class="titlebar_title">
    <a href="{$U_HOME}" class="gmnoprint">{'Home'|@translate}</a>
    <a href="{$U_HOME_MAP}" title="{'displays all photos on a map'|@translate}"><img src="{$PLUGIN_ROOT_URL}/icons/map_sw.gif" width="32" height="17" alt="map"></a>
    {'Viewing'|@translate}: <a id="aPageUrl" href=""></a>
  </div>
</div>

<div id="map"> </div>
{get_combined_scripts load='footer'}
</body>
</html>