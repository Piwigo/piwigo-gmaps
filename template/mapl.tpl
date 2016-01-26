{html_style}
.fullTagCloud{
	text-align: justify;
	padding: 0;
	margin: 0.5em 1em 0.5em 1em
}
.fullTagCloud LI{
	display: inline;white-space: nowrap;
}
{/html_style}

<div id="content" class="content">
{if !$VERY_SIMPLE}
	<div class="titrePage">
		<ul class="categoryActions">
			<li><a href="{$U_KML}" title="{$KML_LINK_TITLE}" class="pwg-state-default pwg-button" rel="nofollow" type="application/vnd.google-earth.kml+xml">
				<span class="pwg-icon pwg-icon-globe"></span><span class="pwg-button-text">earth</span>
			</a><li>
			<li><a target="_top" href="{$U_HOME}" title="{'Home'|@translate}" class="pwg-state-default pwg-button">
				<span class="pwg-icon pwg-icon-home"></span><span class="pwg-button-text">{'Home'|@translate}</span>
			</a><li>
		</ul>
		<h2>{$TITLE}</h2>
	</div>

{assign var='displays_x_on_a_map' value='displays %s on a map'|@translate}
{if not empty($related_categories)}
	<ul class="fullTagCloud">
		<li>{'Albums'|@translate}:</li>
		{foreach from=$related_categories item=cat}
		<li>{strip}
			<a target="_top" href="{$cat.URL}" class="{$cat.CLASS}" title="{$cat.TITLE}">{$cat.NAME}</a>
			<a target="_top" href="{$cat.U_MAP}" title="{$pwg->sprintf($displays_x_on_a_map, $cat.NAME)}" ><img src="{$PLUGIN_ROOT_URL}/icons/map_s.png" alt="map"></a>
		{/strip}&nbsp;</li>
		{/foreach}
	</ul>
{/if}

{if not empty($related_tags)}
	<ul class="fullTagCloud">
		<li>{'Tags'|@translate}:</li>
		{foreach from=$related_tags item=tag}
		<li>{strip}
			<a target="_top" href="{$tag.URL}" class="tagLevel{$tag.level}" title="{$tag.TITLE}">{$tag.name}</a>
			<a target="_top" href="{$tag.U_MAP}" title="{$pwg->sprintf($displays_x_on_a_map, $tag.name)}"><img src="{$PLUGIN_ROOT_URL}/icons/map_s.png" alt="map"></a>
		{/strip}&nbsp;</li>
		{/foreach}
	</ul>
{/if}

{if !empty($navbar)}{include file='navigation_bar.tpl' assign='NAVBAR'}{$NAVBAR}{/if}

{/if}

{if !empty($THUMBNAILS)}
<ul class="thumbnails" id="thumbnails">
{$THUMBNAILS}
</ul>
{/if}

{if !empty($NAVBAR)}{$NAVBAR}{/if}
</div>