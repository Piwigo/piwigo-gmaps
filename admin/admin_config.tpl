
You have {$NB_GEOTAGGED} geotagged images.

<form method="post" action="" class="properties">
<fieldset>
	<legend>Map configuration</legend>
	<ul>

	<!--<li>
	 <label>
		Google Maps API Key:
		<input type="text" size="48" name="gmaps_api_key" value="{$GMAPS_API_KEY}" />
	</label>
		<br/>Signup for the key here: <a href="http://www.google.com/apis/maps/signup.html" target="_blank">http://www.google.com/apis/maps/signup.html</a>.
	</li>
	<br/> -->

	<!--<li>
	 <label>
		Automatically sync from exif
		<input type="checkbox" name="gmaps_auto_sync" {if $GMAPS_AUTO_SYNC}checked="checked"{/if}>
		</label>
		<br/><small>When metadata is synchronized, tour manual set coordinates might be overriden.</small>
	</li>
	<br/> -->

  <li>
  <label>
    Maximum number of markers to show:
    <input type="text" size="3" name="nb_markers" value="{$NB_MARKERS}">
  </label>
    <br/><small>The images will be "clustered" based on this number and the visible region on the map.</small>
  </li>

  <br/>

  <li>
  <label>
    Maximum number of images per marker:
    <input type="text" size="3" name="nb_images_per_marker" value="{$NB_IMAGES_PER_MARKER}">
  </label>
    <br/><small>When a marker is clicked, the user will be able to navigate images in the info window. This data is sent to the browser once for all the markers. If you have many images, reduce this number in order to reduce traffic and speed up data decoding on the browser side.</small>
  </li>

  <br/>

  <li>
  <label>
    Marker style:
    <select name="marker_icon">
      <option value="">Default</option>
      {html_options options=$marker_icons selected=$selected_marker_icon}
    </select>
  </label>
  </li>

  <li>
  <label>
    Default map type:
    <select name="map_type">
      {html_options options=$map_types selected=$MAP_TYPE}
    </select>
  </label>
  </li>

  </ul>

  <p>
    <input type="submit" value="{'Submit'|@translate}" name="submit">
  </p>
</fieldset>
</form>