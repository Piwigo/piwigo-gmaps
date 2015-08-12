function PageLinker(map, aElementId)
{
	this._map = map;
	this._elementId = aElementId;

	google.maps.event.bind( this._map, "idle", this, this._regenerateUrl );
	google.maps.event.bind( this._map, "maptypeid_changed", this, this._regenerateUrl );
}

PageLinker.getQueryVars = function()
{
	var vars = {};
	var qString = unescape( top.location.search.substring(1) );
	if (qString.length==0)
		return vars;
	var pairs = qString.split(/\&/);
	for (var i=0; i<pairs.length; i++ )
	{
		var nameVal = pairs[i].split(/\=/);
		vars[nameVal[0]] = nameVal[1];
	}
	return vars;
};

PageLinker.map2Url = function( map )
{
	var vars = PageLinker.getQueryVars();
	vars['ll'] = map.getCenter().toUrlValue(5);
	vars['z'] = map.getZoom();
	vars['t']=map.getMapTypeId();

	var url = document.location.protocol+'//'+document.location.hostname+document.location.pathname;
	var bFirst = true;
	for (var key in vars)
	{
		url += bFirst ? "?" : "&";
		bFirst = false;
		url += key;
		if (vars[key]!=null)
			url += "="+vars[key];
	}
	return url;
};

PageLinker.url2Map = function( mapOptions )
{
	var vars = PageLinker.getQueryVars();
	if ( !( (vars['z'] && vars['ll']) || vars['t'] ) )
		return false;

	var mapType = google.maps.MapTypeId.ROADMAP;
	if  (vars['t'])
		mapOptions.mapTypeId = vars['t'];
	
	if (vars['z'] && vars['ll'])
	{
		mapOptions.zoom = parseFloat(vars['z']);
		var ll = vars['ll'].split( "," );
		if (ll.length==2)
		{
			mapOptions.center = new google.maps.LatLng( ll[0], ll[1] );
		}
		return true;
	}
	return false;
}


PageLinker.prototype = {

_regenerateUrl: function()
{
  var elt = document.getElementById( this._elementId );
  if (!elt) return;
  elt.href = PageLinker.map2Url(this._map);
}

}
