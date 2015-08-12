<?xml version="1.0" encoding="{$CONTENT_ENCODING}"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
<Document>
	<Style id="img_normal">
		<IconStyle> 
			<Icon><href>http://maps.google.com/mapfiles/kml/shapes/camera.png</href></Icon>
			<scale>1</scale>
		</IconStyle> 
	</Style>
	<Style id="img_highlight">
		<IconStyle> 
			<Icon><href>http://maps.google.com/mapfiles/kml/shapes/camera.png</href></Icon>
			<scale>1</scale>
		</IconStyle> 
	</Style>
	<StyleMap id="img">
		<Pair>
			<key>normal</key><styleUrl>#img_normal</styleUrl>
		</Pair>
		<Pair>
			<key>highlight</key><styleUrl>#img_highlight</styleUrl>
		</Pair>
	</StyleMap>
  <name><![CDATA[{$PAGE_TITLE}]]></name>
  <description><![CDATA[{$NB_ITEMS_DESC|@default}<a href="{$U_INDEX}">{'Go to'|@translate} {$PAGE_TITLE}</a><br/>{$PAGE_COMMENT}
]]></description>
{if not empty($region)}
<Region> 
	<LatLonAltBox> 
		<north>{$region.n}</north>
		<south>{$region.s}</south>
		<east>{$region.e}</east>
		<west>{$region.w}</west>
	</LatLonAltBox> 
</Region> 
{/if}

{if not empty($categories)}
{foreach from=$categories item=category}
<NetworkLink>
	<visibility>0</visibility>
	<name><![CDATA[{$category.NAME}]]></name>
	<description><![CDATA[{$category.COMMENT}
	{if not empty($category.TN_SRC)}
		<br/><img src="{$category.TN_SRC}" />
	{/if}
]]></description>
	<Link>
		<href>{$category.U_KML}</href>
	</Link>   
{if not empty($category.region)}
	<Region>
		<LatLonAltBox>
			<north>{$category.region.n}</north>
			<south>{$category.region.s}</south>
			<east>{$category.region.e}</east>
			<west>{$category.region.w}</west>
		</LatLonAltBox>
	</Region>
	<TimeSpan>
		<begin>{$category.region.min_date}</begin>
		<end>{$category.region.max_date}</end>
	</TimeSpan>
{/if}
</NetworkLink>
{/foreach}
{/if}

{if not empty($images)}
{foreach from=$images item=img}
	<Placemark>
		<visibility>1</visibility>
		<open>1</open>
		<name>{$img.TITLE|xmle}</name>
		<Snippet>{$img.DESCRIPTION|@strip_tags:false|@truncate:80|xmle}</Snippet>
		<description><![CDATA[
{$img.DESCRIPTION}<br/>
<a href="{$img.U_PAGE}"><img src="{$img.TN_SRC}"/></a><br/>
]]></description>
		<styleUrl>#img</styleUrl>
		<Point>
			<coordinates>{$img.LON},{$img.LAT},0</coordinates>
		</Point>
		{if not empty($img.date_creation)}
		<TimeStamp>
			<when>{$img.date_creation}</when>
		</TimeStamp>
		{/if}
	</Placemark>
{/foreach}
{/if}
</Document>
</kml>
