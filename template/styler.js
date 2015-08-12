
function PwgSingleStyler( style, roc )
{
	style = style || {};
	this.roc = roc || new google.maps.Size(16,32);
	
	this.getStyle = function() { return style; }
	this.changeStyle = function() { return null; }
}



function PwgStyler( styles, roc )
{
	this.styles = styles;
	this.roc = roc || new google.maps.Size(16,32);
	for (var i=0; i<this.styles.length; i++)
		this.styles[i].zIndex = 1000 - i;
}

PwgStyler.prototype =  {

getStyle: function(cluster)
{
	return this.styles[this._indexer(cluster, this.styles)];
},

changeStyle: function(cluster, oldcluster)
{
	var idx = this._indexer(cluster, this.styles), idx2=this._indexer(oldcluster, this.styles);
	return idx==idx2 ? null :  this.styles[idx];
},

_indexer: function(cluster, styles)
{
	var n=cluster.nb_items, i=0;
	while (n>1 )
	{
		n=n/10;
		i++;
	}
	return i< styles.length ? i: styles.length-1;
}

}