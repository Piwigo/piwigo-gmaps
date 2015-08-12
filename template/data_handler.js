
function PwgDataHandler( map, opts )
{
	this._map = map;
	this._infoWindow = new google.maps.InfoWindow();
	this.styler = opts.styler;
	this.options = jQuery.fn.extend(
		{
			show_all_img_src: null
		}
		, opts || {} );

	google.maps.event.bind( this._map, "click", this, this._onMapClick);
}

PwgDataHandler.prototype =  {

_map: null,
_infoWindow: null,
options: {},
_markers: [],
_navHtmlIds: ["gotoPrevImage", "gotoNextImage"],
_navHtmlHandles: [],
_prevResult: { nb_items:0 },


terminate: function()
{
	this._map = null;
	try {
		for (i=0; i<this._markers.length; i++)
			this._markers[i].pwg = null;
		this._markers.length = 0;
	}
	catch(e){}
},

handle: function( data )
{
	var i;
	if (document.title != data.title )
		document.title = data.title;
	var elt = document.getElementById("aPageUrl");
	if (elt && elt.href!=data.page_url.urlNoHtm() )
	{
		elt.href = data.page_url.urlNoHtm();
		elt.innerHTML = data.title;
		elt.title = Localization.fmt1("go to %s", data.title);
	}
	var elt = document.getElementById("aBlowup");
	if (elt && elt.href!=data.blowup_url.urlNoHtm())
		 elt.href = data.blowup_url.urlNoHtm();

	var elt = document.getElementById("aKml");
	if (elt && elt.href!=data.kml_url.urlNoHtm())
	{
		 elt.href = data.kml_url.urlNoHtm();
		 elt.title = Localization.fmt1("opens %s in Google Earth", data.title);
	}
	var changed = true;
	if (this._prevResult.nb_items==data.nb_items && this._markers.length==data.image_clusters.length )
	{
		changed = false;
		if (this._markers.length>0 && !this._markers[0].getPosition().equals( data.image_clusters[0].position ) )
			changed=true;
	}

	if (changed)
	{
		var markersToRemove=[], clustersToAdd=[], newMarkers=[], cluster, marker;
		data.image_clusters.sort( function(a,b) { return PwgDataHandler.cmpll( a.position, b.position); } );
		for (i=0; i<data.image_clusters.length; i++)
		{
			cluster = data.image_clusters[i];
			while(this._markers.length)
			{
				marker = this._markers[0];
				if (PwgDataHandler.cmpll(cluster.position, marker.getPosition()) == 0)
				{
					marker.setOptions(this.styler.changeStyle(cluster, marker.pwg));
					marker.pwg = {
						nb_items: cluster.nb_items,
						images: cluster.items,
						bounds: cluster.bounds,
						blowup_url: cluster.blowup_url
						};
					newMarkers.push(marker);
					this._markers.shift();
					cluster = null;
					break;
				}
				else if (PwgDataHandler.cmpll(cluster.position, marker.getPosition()) < 0)
				{
					break;
				}
				else
				{
					markersToRemove.push(marker);
					this._markers.shift();
				}
			}

			if (cluster)
				clustersToAdd.push(cluster);
		}

		if (document.is_debug) glog('reused ' + newMarkers.length + ' exact markers');
		markersToRemove = markersToRemove.concat( this._markers );

		var infoWindowMarker = this._infoWindow.getAnchor();
		for (i=0; i<clustersToAdd.length; i++)
		{
			cluster = clustersToAdd[i];
			
			var theTitle = (cluster.nb_items>1) ? Localization.fmt1("%d photos", cluster.nb_items) : cluster.items[0].name;
			marker = markersToRemove.pop();
			if (marker && marker==infoWindowMarker)
			{
				marker.setMap(null);
				google.maps.event.clearInstanceListeners(marker);
				if (document.is_debug) glog('removed marker with infoWindow');
				marker = this._markers.pop();
			}

			if (!marker)
			{
				marker = new google.maps.Marker(this.styler.getStyle(cluster));
				marker.setPosition(cluster.position);
				marker.setTitle(theTitle);
				google.maps.event.addListener( marker, "click", pwgBind(this, this._onMarkerClick, marker) );
				google.maps.event.addListener( marker, "dblclick", pwgBind(this, this._onMarkerDblClick, marker) );
				marker.setMap(this._map);
			}
			else
			{
				marker.currentImageIndex=0;
				marker.setPosition( cluster.position );
				marker.setTitle(theTitle);
				marker.setOptions(this.styler.changeStyle(cluster, marker.pwg));
			}

			newMarkers.push(marker);

			marker.pwg = {
				nb_items: cluster.nb_items,
				images: cluster.items,
				bounds: cluster.bounds,
				blowup_url: cluster.blowup_url
				};
		}

		for (i=0; i<markersToRemove.length; i++)
		{
			markersToRemove[i].setMap(null);
			google.maps.event.clearInstanceListeners(markersToRemove[i]);
		}

		this._markers = newMarkers;
		this._markers.sort( function(a,b) { return PwgDataHandler.cmpll( a.getPosition(), b.getPosition()); } );
		this._prevResult.nb_items=data.nb_items;
	}

	document.getElementById("dataLoadStatus").innerHTML = Localization.fmt1("%d photos", data.nb_items);
},

_onMarkerClick: function( marker )
{
	if (this._infoWindow.getAnchor() == marker )
		return; // already open
	var content = "";
	if ( !marker.currentImageIndex )
		marker.currentImageIndex = 0;
	content += '<div class="gmiw_header" style="' + (marker.pwg.images.length>1 ? '': 'display:none')+ '">';
	content += '<span id="pwgImageCounters">'+this.buildCurrentPictureCounterHtml(marker)+'</span> ';
	content += '<a href="javascript:void(0);" id="'+this._navHtmlIds[0]+'">';
	content +=    '<span>'+ "&laquo; " + Localization.get('Prev') + '</span>';
	content += '</a>';
	content += " ";
	content += '<a href="javascript:void(0);" id="'+this._navHtmlIds[1]+'">';
	content +=    '<span>'+Localization.get('Next') + " &raquo;"+'</span>';
	content += '</a>';
	content += " ";
	var imgShowAll = '';
	if (this.options.show_all_img_src)
		imgShowAll = '<img src="'+this.options.show_all_img_src+'" alt="" style="border:0" /> ';
	content += '<a id="pwgImageBlowup" href="'+marker.pwg.blowup_url+'" onclick="return PwgDataHandler.blowupUrl(this.href);" title='+Localization.getQ('show all photos around this location')+'>'+
						imgShowAll+ '<span>'+Localization.get('Show all')+'</span>'+
					'</a>';
	content += '</div>';
	content += '<div id="pwgImageDetail">' + this.buildCurrentPictureHtml( marker ) + '</div>';

	var h;
	while (h = this._navHtmlHandles.pop())
		google.maps.event.removeListener(h);

	google.maps.event.addListenerOnce( this._infoWindow, "domready", pwgBind(this, this._onInfoWindowDomReady) );
	this._infoWindow.setContent( content );
	this._infoWindow.setPosition( marker.getPosition() );
	this._infoWindow.open( this._map, marker );
},

_onMarkerDblClick: function( marker )
{
	this._map.fitBounds( marker.pwg.bounds );
},

_onMapClick: function( marker )
{
	this._infoWindow.close();
},

buildCurrentPictureHtml: function( marker )
{
	var imageDetail = marker.pwg.images[marker.currentImageIndex],
		dpr = window.devicePixelRatio || 1,
		w = Math.round(imageDetail.w/dpr),
		h = Math.round(imageDetail.h/dpr);
	
	var res = "";
	res += '<div class="gmiw_imageTitle">' + imageDetail.t + "</div>";
	res +=
'<div class="gmiw_imageContent">' +
'<div class="gmiw_imageWrap">'+
  '<a href="'+imageDetail.url+'">' +
  '<img src="' + imageDetail.tn + '" alt="thumb" width='+w+' height='+h+'>'+
'</a></div>' +
'<div class="gmiw_imageComment">' + imageDetail.d + '</div>' +
'</div>';
	return res;
},

buildCurrentPictureCounterHtml: function( marker )
{
	var res =
		'<b>'+(marker.currentImageIndex+1)+'</b>'+
		'/' +
		'<b>'+marker.pwg.images.length+'</b>';
	if (marker.pwg.nb_items>marker.pwg.images.length)
		res+= " "+Localization.fmt1("out of %d", marker.pwg.nb_items );
	return res;
},


_onInfoWindowDomReady: function()
{
	if (!this._infoWindow.getAnchor() || this._infoWindow.getAnchor().pwg.images.length<2)
		return;
	for (var i=0; i< this._navHtmlIds.length; i++)
	{
		var elt = document.getElementById( this._navHtmlIds[i] );
		if (elt)
			this._navHtmlHandles.push( google.maps.event.addDomListener(elt, "click", pwgBind(this, this._onPictureNavigate, this._infoWindow.getAnchor(), i) ) );
	}
},

_onPictureNavigate: function(marker, dir )
{
	if (dir==0) dir=-1;
	marker.currentImageIndex += dir;
	if (marker.currentImageIndex<0)
		marker.currentImageIndex = marker.pwg.images.length-1;
	else if (marker.currentImageIndex >= marker.pwg.images.length)
		marker.currentImageIndex = 0;

	try {
		var elt = document.getElementById( "pwgImageDetail" );
		elt.innerHTML = this.buildCurrentPictureHtml( marker );
	}
	catch (e)
	{
		alert (e.message);
	}

	try {
		var elt = document.getElementById( "pwgImageCounters" );
		elt.innerHTML = this.buildCurrentPictureCounterHtml( marker );
	}
	catch (e)
	{
		alert (e.message);
	}
	return false;
}

}

PwgDataHandler.blowupUrl = function(theUrl)
{
	jQuery.colorbox( {
		href: theUrl,
		iframe: 1,
		width: "98%", height: "99%"
	});
	return false;
}

PwgDataHandler.cmpll = function(a,b) {
	var d= a.lng() - b.lng();
	if (d < -.5e-6)
		return -1;
	else if (d > .5e-6)
		return 1;
	d= a.lat() - b.lat();
	if (d < -.5e-6)
		return -1;
	else if (d > .5e-6)
		return 1;
	return 0;
}

String.prototype.urlNoHtm = function()
{
	return this.replace( "&amp;", "&" );
}
