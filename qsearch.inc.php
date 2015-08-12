<?php
include_once( PHPWG_ROOT_PATH .'include/functions_search.inc.php' );

define('LAT_PATT',
"(?<lsig>[+-]?)(?<ldeg>\\d{1,3}\\.?\\d*)\\s*(?:\\xC2\\xB0)?\\s*(?:(?<lmin>\\d+)\\s*'?)?\\s*(?:(?<lsec>\\d+\\.?\\d*)\\s*\"?)?\\s*(?<lhs>[NS])?"
);
define('LON_PATT',
"(?<Lsig>[+-]?)(?<Ldeg>\\d{1,3}\\.?\\d*)\\s*(?:\\xC2\\xB0)?\\s*(?:(?<Lmin>\\d+)\\s*'?)?\\s*(?:(?<Lsec>\\d+\\.?\\d*)\\s*\"?)?\\s*(?<Lhs>[EW])?"
);
define('RAD_PATT',
"(?:,\\s*(?<r>[+-]?\\d+\\.?\\d*)\\s*(?:(?<ru>mi|km|m)[a-z]*)?)?"
);
define('POS_PATT',
LAT_PATT.'\\s*,\\s*'.LON_PATT.'\\s*'.RAD_PATT
);

class QNearScope extends QSearchScope
{
  function __construct($id, $aliases, $nullable=false)
  {
    parent::__construct($id, $aliases, $nullable, true);
  }

	static function tollr($matches)
	{
		foreach( array('l','L') as $l)
		{
			$val = 0 + $matches["${l}deg"];
			$val += $matches["${l}min"] /60;
			$val += $matches["${l}sec"] /3600;
			if ($matches["${l}sig"] == '-')
				$val =-$val;
			if (strpbrk($matches["${l}hs"], 'sSwW')!==false)
				$val =-$val;
			$vals[] = $val;
		}
		if (abs($vals[0]) > 90 || abs($vals[1]) > 180)
			return false;
		$ret =  array(
			'lat' => $vals[0],
			'lon' => $vals[1],
			);
		if (!empty($matches['r']))
		{
			$r = 0 + $matches['r'];
			$mult = 1000;
			if (!empty($matches['ru']))
			{
				if (0===stripos($matches['ru'], 'mi'))
					$mult = 1609.344;
				elseif ('m'==$matches['ru'][0])
					$mult=1;
			}
			$r *= $mult;
			if ($r<=0)
				return false;
			$ret['r'] = $r;
		}
		return $ret;
	}

  function parse($token)
  {
		$pat = '#'.POS_PATT.'#i';
		if (preg_match($pat, $token->term, $matches))
		{
			$ll = self::tollr($matches);
			if ($ll)
			{
				$r = @$ll['r'];
				if (!$r) $r = 10000;

  $cos_lat = max( 1e-2, cos($ll['lat']*M_PI/180) );
  $dlat = $r/113000; // 1 degree is approx between 111 and 116 km
  $dlon = min( $r/(113000*$cos_lat), 180 );
  $bounds = array(
      's' => $ll['lat'] - $dlat,
      'w' => $ll['lon'] - $dlon,
      'n' => $ll['lat'] + $dlat,
      'e' => $ll['lon'] + $dlon,
    );
  if ($bounds['s']<-90) $bounds['s']=-90;
  if ($bounds['w']<-180) $bounds['w']+=360;
  if ($bounds['n']>90) $bounds['n']=90;
  if ($bounds['e']>180) $bounds['e']-=360;

				$token->scope_data = $bounds;
				return true;
			}
		}
		elseif (strlen($token->term) > 2)
		{
			$key = 'geo_'.urlencode(transliterate($token->term));
			global $persistent_cache;
			if (!$persistent_cache->get($key, $geo))
			{
				include_once( PHPWG_ROOT_PATH .'admin/include/functions.php' );
				$url = 'http://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($token->term);
				$ret = fetchRemote($url, $resp);

				$persistent_cache->set($key.'_json', $resp);
				$geo = null;
				if ($ret)
				{
					$geo = json_decode($resp, true);
					$persistent_cache->set($key, $geo);
				}
			}

			if (count(@$geo['results'])) {
					$gb = $geo['results'][0]['geometry']['viewport'];
  				$bounds = array(
      			's' => $gb['southwest']['lat'],
      			'w' => $gb['southwest']['lng'],
      			'n' => $gb['northeast']['lat'],
      			'e' => $gb['northeast']['lng'],
    			);
					$add = @$geo['results'][0]['formatted_address'];
					if (strlen($add))
					{
						if (strpbrk($add, ' ,')!==false)
							$token->modifier |= QST_QUOTED;
						$token->term = $add;
					}
					$token->scope_data = $bounds;
					$token->geo = $geo['results'];
					add_event_handler('qsearch_results', 'rvm_qsearch_results');
					return true;
			}

		}
    return false;
  }
}

function rvm_qsearch_pre($q)
{
	if (preg_match('#^'.POS_PATT.'$#i', $q))
		$q = 'near:"'.str_replace('"','',$q).'"';

	$q = preg_replace_callback('#(?<pre>^|[^a-z0-9\x80-\xFF])near:(?<pos>'.POS_PATT.')#i', function ($matches) {
	$pre = $matches['pre'];
	$pos = $matches['pos'];
	$post = ' ';
	$pos = str_replace('"','', rtrim($pos));
	return "${pre}near:\"${pos}\"${post}";
}, $q);

	$off = 0;
	while (preg_match("#(?:^|(?<=[ \\(-]))near[: ](?<loc>[a-z0-9 ,-]+)#i", $q, $matches, PREG_OFFSET_CAPTURE, $off))
	{
		$loc = $matches['loc'][0];
//todo check space-char

		//check or and not
		if (preg_match("# (and|or|not)($| )#i", $loc, $m2, PREG_OFFSET_CAPTURE))
		{
			$loc = substr($loc, 0, $m2[0][1]);
		}

		//check following : after match
		if (':' == @$q[ $matches[0][1] + strlen($matches[0][0])])
			$loc = substr($loc, 0, strrpos($loc, ' '));
		$off += 5;

		$quote='';
		if (strpbrk($loc, ' ,')!==false)
			$quote='"';

		$q = substr_replace($q,
			'near:'.$quote.$loc.$quote.' ', $matches[0][1],
			5+strlen($loc));
		$q = rtrim($q);
	}

	return $q;
}

function rvm_qsearch_get_scopes($scopes)
{
	$scopes[] = new QNearScope('near', array(), true);
	add_event_handler('qsearch_get_images_sql_scopes', 'rvm_qsearch_get_images_sql_scopes');
	return $scopes;
}

function rvm_qsearch_get_images_sql_scopes($clauses, $token)
{
	switch ($token->scope->id)
	{
		case 'near':
		include_once( dirname(__FILE__) .'/include/functions_map.php');

		$bounds = $token->scope_data;
		$clauses[] = rvm_bounds_to_sql( $bounds );
			break;
	}
	return $clauses;
}

function rvm_build_addr($comps, $remove)
{
	$keep = array(
		'neighborhood',
		'sublocality',
		'locality',
		'administrative_area_level_2',
		'administrative_area_level_1',
		'country',
	);
	$res = array();

	foreach($comps as $comp) {
		$common = array_intersect($keep, $comp['types']);
		if (empty($common)) {
			$remove--;
			continue;
		}
		if (!in_array($comp['long_name'],$res))
			$res[current($common)] = $comp['long_name'];
	}

	if ($remove>0)
		array_shift($res);
	return $res;
}

function rvm_qsearch_results($sr, $exp, $qsr)
{
//echo __FUNCTION__.'<br>';
	for ($i=0; $i<count($exp->stokens); $i++)
	{
		$token = $exp->stokens[$i];
		if ( !($geo=@$token->geo))
			continue;
		$aacomp = array();
		$acomp = rvm_build_addr($geo[0]['address_components'],1);
		if (!empty($acomp))
			$aacomp[] = $acomp;
		for ($j=1; $j<count($geo); $j++)
			$aacomp[] = array($geo[$j]['formatted_address']);

		if (!empty($aacomp)) {
			$sr['qs']['geo'][] = array(
				'needle' => (string)$token,
				'haystack' => (string)$exp,
				'alt' => $aacomp);
		}
	}
	return $sr;
}

function rvm_qsearch_show_alt($geo)
{
	global $page, $template;
//echo __FUNCTION__.'<br>';
	$base_url = get_root_url().'qsearch.php?q=';

	foreach( $geo as $pseudo_token)
	{
		$haystack = $pseudo_token['haystack'];
		$needle = $pseudo_token['needle'];
		foreach($pseudo_token['alt'] as $acomp)
		{
			$line = array();
			$acomp = array_values($acomp);
			for ($i=0; $i<count($acomp); $i++)
			{
				$text = $link = $acomp[$i];
				for ($j=$i+1; $j<count($acomp); $j++)
					$link .= ','.$acomp[$j];
				$line[] = '<a href="'.$base_url.urlencode( str_replace($needle, 'near:"'.$link.'"',$haystack) ).'">'.htmlspecialchars($text).'</a>';
			}
			$lines[] = implode(', ', $line);
		}
	}
	$template->assign('PLUGIN_INDEX_CONTENT_BEGIN', '<p class=search_results>See also: '.implode(" &mdash; \n", $lines).'</p>');
}

function rvm_get_popup_help($content, $page)
{
	$needle = '</table>';
	if ('quick_search'==$page &&
		($pos=strpos($content,$needle)) &&
		($pos=strpos($content,$needle,$pos+1)) )
	{
		$e = '';

		$e .= "<tr>
<td>
<q>near:</q>
</td>
<td>
<q>near:lat,lon,distance</q>; Distance is optional (default: 10km). If specified it should be a number followed by an optional unit (m, km, mi).
<br><q>near:location</q>
</td>
</tr>
";
		$content = substr_replace($content, $e, $pos, 0);
	}
	return $content;
}

?>