function PwgDataLoader( map, opts )
{
	this._map = map;
	this.options = jQuery.fn.extend(
		{
			reload_data_timeout: 200,
			rectangle_of_confusion: new google.maps.Size(16,32)
		}
		, opts || {} );
	this.options.rectangle_of_confusion = new google.maps.Size( this.options.rectangle_of_confusion.width, this.options.rectangle_of_confusion.height );
	if (this.options.rectangle_of_confusion.width<16) this.options.rectangle_of_confusion.width=16;
	if (this.options.rectangle_of_confusion.height<16) this.options.rectangle_of_confusion.height=16;
}


// returns the number of digits (between 0 and 6) to round a lat/lon so that it does not vary by more than factor*pow(-exp)
getLatLonDigits = function(d, factor, exp)
{
	if (d<0) return getLatLonDigits(-d,factor,exp);
	var digits = Math.ceil( exp - Math.log(d*factor)/Math.LN10 );
	return digits<0 ? 0 : (digits>6 ? 6 : digits);
}

Math.roundN = function(d, digits) { var c = Math.pow(10,digits); return Math.round(d*c)/c; }


PwgDataLoader.prototype =  {


_urlMapData: null,
_timerReloadData: null,
_dataLoading: false,

_previousLoadDataReq : {box: null, zoom:0, resultBounds:null},

start: function(urlMapData)
{
	this._urlMapData = urlMapData;
	google.maps.event.bind( this._map, "dragstart", this, this.clearTimerReloadData );
	google.maps.event.bind( this._map, "idle", this, this._onIdle );
	//this._loadData();
},

terminate: function()
{
	this.clearTimerReloadData();
	this._map = null;
},

clearTimerReloadData : function()
{
	if (this._timerReloadData)
	{
		clearTimeout( this._timerReloadData );
		this._timerReloadData = null;
		return true;
	}
	return false;
},

_onIdle: function()
{
	this.clearTimerReloadData();
	this._timerReloadData = setTimeout(  pwgBind(this, this._onTimeoutLoadData), this.options.reload_data_timeout );
},


_onTimeoutLoadData: function ()
{
	if (this._dataLoading) return; // still in progress
	this.clearTimerReloadData();
	this._loadData();
},

_loadData: function()
{
	var bounds = new google.maps.LatLngBounds( this._map.getBounds().getSouthWest(), this._map.getBounds().getNorthEast() );
	
	//BEGIN BUG in maps api as of v3.7 - when map wraps horizontally more than 360 deg - the getBounds is wrong
	/*var isOver = false;
	if (bounds.getSouthWest().lng() < bounds.getNorthEast().lng())
	{
		if ( this._map.getCenter().lng()<bounds.getSouthWest().lng() || this._map.getCenter().lng()>bounds.getNorthEast().lng() )
		{
			isOver = true;
		}
	}
	else if ( this._map.getCenter().lng()>bounds.getNorthEast().lng() && this._map.getCenter().lng()<bounds.getSouthWest().lng() )
	{
		isOver = true;
	}
	if (!isOver)
	{//very emprical tests
		if (this._map.getDiv().offsetWidth / this._map.getDiv().offsetHeight > 1.3
				&& this._map.getZoom()<3
				&& bounds.getSouthWest().lat() < -50
				&& bounds.getNorthEast().lat() > 50)
		{
			isOver = true;
		}
	}
	
	if (isOver)
		bounds = new google.maps.LatLngBounds( new google.maps.LatLng(this._map.getBounds().getSouthWest().lat(),-180), new google.maps.LatLng(this._map.getBounds().getNorthEast().lat(), 179.99) );*/
	//END bug
	
	
	var latRange = bounds.toSpan().lat();
	var latPrec = latRange * this.options.rectangle_of_confusion.height / this._map.getDiv().offsetHeight;

	var lonRange = bounds.toSpan().lng();
	var lonPrec = ( lonRange>=0 ? lonRange : 360-lonRange )* this.options.rectangle_of_confusion.width / this._map.getDiv().offsetWidth;

	if ( this._previousLoadDataReq.box!=null )
	{ // not the first time
		if ( this._previousLoadDataReq.box.contains( bounds.getNorthEast() )
					&& this._previousLoadDataReq.box.contains( bounds.getSouthWest() ))
		{
			if ( this._previousLoadDataReq.resultBounds == null )
				return; // no images here
			if ( this._map.getZoom() <= this._previousLoadDataReq.zoom )
				return;
		}
	}

	var nd=0, sd=0, ed=0, wd=0;
	/*if ( !bounds.isFullLat() )*/
	{
		nd = latRange*0.12;
		sd = latRange*0.04;
	}
	/*if ( !bounds.isFullLng() )*/
	{
		ed = lonRange*0.09;
		wd = lonRange*0.07;
	}
	var digits = Math.max( getLatLonDigits(latRange,4,2), getLatLonDigits(lonRange,4,2) );
	var box = new google.maps.LatLngBounds( bounds.getSouthWest(), bounds.getNorthEast() );
	box.extend( new google.maps.LatLng( Math.roundN(bounds.getSouthWest().lat()-sd,digits), Math.roundN( bounds.getSouthWest().lng()-wd,digits ) ) );
	box.extend( new google.maps.LatLng( Math.roundN(bounds.getNorthEast().lat()+nd,digits), Math.roundN( bounds.getNorthEast().lng()+ed,digits) ) );

	var url = this._urlMapData;
	url += "&box=" + box.getSouthWest().toUrlValue(5) + "," + box.getNorthEast().toUrlValue(5);
	url += "&lap=" +  Math.roundN( latPrec, getLatLonDigits(latPrec,1,1) ).toString();
	url += "&lop=" +  Math.roundN( lonPrec, getLatLonDigits(lonPrec,1,1) ).toString();

	if (document.is_debug) {
		glog("sd="+sd+" wd="+wd+" nd="+nd+" ed="+ed);
		glog( url );
	}

	this._previousLoadDataReq.box = box;
	this._previousLoadDataReq.zoom = this._map.getZoom();
	this._previousLoadDataReq.resultBounds = null;
	this._dataLoading = true;

	try {
		google.maps.event.trigger( this, "dataloading" );
		jQuery.ajax( {
			url: url,
			success: pwgBind(this, this._onAjaxSuccess),
			error: pwgBind(this, this._onAjaxError),
			});
	}
	catch (e) {
		this._dataLoading = false;
		this._previousLoadDataReq.box=null;
		google.maps.event.trigger( this, "dataloadfailed", 600, e );
	}
},

_onAjaxSuccess: function(data, textStatus, xhr)
{
	var resp;
	try
	{
		eval('resp = ' + data);
		if (resp.nb_items == undefined)
			throw new Error( "DATA DECODING ERROR" );
		this._previousLoadDataReq.resultBounds = resp.bounds;
		if (document.is_debug && resp.debug) glog( resp.debug );
		google.maps.event.trigger( this, "dataloaded", resp );
	}
	catch (e)	{
		this._previousLoadDataReq.box=null;
		google.maps.event.trigger( this, "dataloadfailed", textStatus, e );
		var s = e.message;
		s += '\n' + data.substr(0,1000);
		alert( s );
	}
	finally {
		this._dataLoading = false;
	}
},

_onAjaxError: function(xhr, textStatus, exc)
{
	try {
		google.maps.event.trigger( this, "dataloadfailed", textStatus + xhr.status, exc );
	}
	catch (e) {
	}
	finally {
		this._dataLoading = false;
	}
},

}
