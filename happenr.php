<?php
/**
 * Happenr class
 *
 * This source file can be used to communicate with Happenr (http://happenr.com)
 *
 * The class is documented in the file itself. If you find any bugs help me out and report them. Reporting can be done by sending an email to php-happenr-bugs[at]verkoyen[dot]eu.
 * If you report a bug, make sure you give me enough information (include your code).
 *
 * License
 * Copyright (c) 2009, Tijs Verkoyen. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products derived from this software without specific prior written permission.
 *
 * This software is provided by the author "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
 *
 * @author			Tijs Verkoyen <php-happenr@verkoyen.eu>
 * @version			1.0.0
 *
 * @copyright		Copyright (c) 2008, Tijs Verkoyen. All rights reserved.
 * @license			BSD License
 */
class Happenr
{
	// internal constant to enable/disable debugging
	const DEBUG = false;

	// url for the api
	const API_URL = 'http://happenr.com/webservices';

	// port for the API
	const API_PORT = 80;

	// current version
	const VERSION = '1.0.0';


	/**
	 * The login that will be used for authenticating
	 *
	 * @var	string
	 */
	private $login;


	/**
	 * The password that will be used for authenticating
	 *
	 * @var	string
	 */
	private $password;


	/**
	 * The timeout
	 *
	 * @var	int
	 */
	private $timeOut = 60;


	/**
	 * The user agent
	 *
	 * @var	string
	 */
	private $userAgent;


// class methods
	/**
	 * Default constructor
	 *
	 * @return	void
	 * @param	string $login	The login (username) that has to be used for authenticating
	 * @param	string $password	The password that has to be used for authentication
	 */
	public function __construct($login, $password)
	{
		$this->setLogin($login);
		$this->setPassword($password);
	}


	/**
	 * Make the call
	 *
	 * @return	string
	 * @param	string $url
	 * @param	array[optional] $parameters
	 */
	private function doCall($url, $parameters = array())
	{
		// redefine
		$url = (string) $url;
		$parameters = (array) $parameters;

		// add required parameters
		$parameters['username'] = $this->getLogin();
		$parameters['password'] = $this->getPassword();

		// init var
		$queryString = '';

		// loop parameters and add them to the queryString
		foreach($parameters as $key => $value) $queryString .= '&'. $key .'='. urlencode(utf8_encode($value));

		// cleanup querystring
		$queryString = trim($queryString, '&');

		// append to url
		$url .= '?'. $queryString;

		// prepend
		$url = self::API_URL .'/'. $url;

		// set options
		$options[CURLOPT_URL] = $url;
		$options[CURLOPT_PORT] = self::API_PORT;
		$options[CURLOPT_USERAGENT] = $this->getUserAgent();
		$options[CURLOPT_FOLLOWLOCATION] = true;
		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_TIMEOUT] = (int) $this->getTimeOut();

		// init
		$curl = curl_init();

		// set options
		curl_setopt_array($curl, $options);

		// execute
		$response = curl_exec($curl);
		$headers = curl_getinfo($curl);

		// fetch errors
		$errorNumber = curl_errno($curl);
		$errorMessage = curl_error($curl);

		// close
		curl_close($curl);

		// invalid headers
		if(!in_array($headers['http_code'], array(0, 200)))
		{
			// should we provide debug information
			if(self::DEBUG)
			{
				// make it output proper
				echo '<pre>';

				// dump the header-information
				var_dump($headers);

				// dump the raw response
				var_dump($response);

				// end proper format
				echo '</pre>';

				// stop the script
				exit;
			}

			// throw error
			throw new HappenrException('Invalid headers ('. $headers['http_code'] .')', (int) $headers['http_code']);
		}

		// error?
		if($errorNumber != '') throw new HappenrException($errorMessage, $errorNumber);

		// load as XML
		$xml = @simplexml_load_string($response, null, LIBXML_NOCDATA);

		// validate json
		if($xml === false) throw new HappenrException($response);

		// return
		return $xml;
	}


	/**
	 * Convert the eventXML into a readable array
	 *
	 * @return	array
	 * @param	SimpleXMLElement $xml
	 */
	private function eventXMLToArray($xml)
	{
		// init var
		$event = array();

		$event['id'] = (string) $xml['id'];
		$event['record_date'] = (int) strtotime($xml['recorddate']);

		$event['geocode'] = (string) $xml->geocode;
		$event['langcode'] = (string) $xml->langcode;
		$event['source_link'] = (string) $xml->sourcelink;
		$event['detail_link'] = (string) $xml->detaillink;

		$event['title'] = utf8_decode((string) $xml->title);
		$event['content'] = utf8_decode((string) $xml->content);
		$event['image_url'] = (string) $xml->imageurl;

		$event['location']['town'] = utf8_decode((string) $xml->location->town);
		$event['location']['region'] = utf8_decode((string) $xml->location->region);
		$event['location']['country'] = utf8_decode((string) $xml->location->country);
		$event['location']['accuracy'] = utf8_decode((string) $xml->location->accuracy);

		$event['longitude'] = (string) $xml->longitude;
		$event['latitude'] = (string) $xml->latitude;

		$event['date_from'] = (int) strtotime((string) $xml->datefrom);
		$event['date_next'] = ((int) strtotime((string) $xml->datefrom) > 0) ? (int) strtotime((string) $xml->datefrom) : null;
		$event['date_to'] = (int) strtotime((string) $xml->dateto);

		$event['url'] = (string) $xml->url;
		$event['email'] = (string) $xml->email;
		$event['performer'] = utf8_decode((string) $xml->performer);
		$event['organizer'] = utf8_decode((string) $xml->organizer);
		$event['venue'] = utf8_decode((string) $xml->venue);

		$event['dates_text'] = (string) $xml->datestext;
		$event['dates'] = array();

		if(isset($xml->dates->date))
		{
			// loop dates
			foreach($xml->dates->date as $date)
			{
				$startDateString = (string) $date->startdate .' '. (string) $date->starttime;
				$endDateString = (string) (string) $date->enddate .' '. (string) $date->endtime;

				$temp['start_date'] = mktime((int) substr($startDateString, 11,2), (int) substr($startDateString, 14, 2), 00, (int) substr($startDateString, 3, 2), (int) substr($startDateString, 0, 2), (int) substr($startDateString, 6, 4));
				$temp['end_date'] = mktime((int) substr($endDateString, 11,2), (int) substr($endDateString, 14, 2), 00, (int) substr($endDateString, 3, 2), (int) substr($endDateString, 0, 2), (int) substr($endDateString, 6, 4));

				$event['dates'][] = $temp;
			}
		}

		$event['price']['text'] = utf8_decode((string) $xml->price->text);
		$event['price']['min'] = utf8_decode((string) $xml->price->min);
		$event['price']['max'] = utf8_decode((string) $xml->price->max);
		$event['price']['currency'] = utf8_decode((string) $xml->price->currency);
		$event['ticketing_url'] = (string) $xml->ticketingurl;
		$event['free'] = (bool) ((string) $xml->free == 1);
		$event['cancelled'] = (bool) ((string) $xml->cancelled == 1);
		$event['postponed'] = (bool) ((string) $xml->postponed == 1);
		$event['soldout'] = (bool) ((string) $xml->soldout == 1);

		$event['tags'] = array();

		// loop tags
		if(isset($xml->tags->tag))
		{
			foreach($xml->tags->tag as $tag) $event['tags'][] = utf8_decode((string) $tag);
		}

		// return
		return $event;
	}


	/**
	 * Get the login
	 *
	 * @return	string
	 */
	private function getLogin()
	{
		return (string) $this->login;
	}


	/**
	 * Get the password
	 *
	 * @return	string
	 */
	private function getPassword()
	{
		return (string) $this->password;
	}


	/**
	 * Get the timeout that will be used
	 *
	 * @return	int
	 */
	public function getTimeOut()
	{
		return (int) $this->timeOut;
	}


	/**
	 * Get the useragent that will be used. Our version will be prepended to yours.
	 * It will look like: "PHP Happenr/<version> <your-user-agent>"
	 *
	 * @return	string
	 */
	public function getUserAgent()
	{
		return (string) 'PHP Happenr/'. self::VERSION .' '. $this->userAgent;
	}


	/**
	 * Set the login that has to be used
	 *
	 * @return	void
	 * @param	string $login
	 */
	private function setLogin($login)
	{
		$this->login = (string) $login;
	}


	/**
	 * Set the password that has to be used
	 *
	 * @return	void
	 * @param	string $password
	 */
	private function setPassword($password)
	{
		$this->password = (string) $password;
	}


	/**
	 * Set the timeout
	 * After this time the request will stop. You should handle any errors triggered by this.
	 *
	 * @return	void
	 * @param	int $seconds	The timeout in seconds
	 */
	public function setTimeOut($seconds)
	{
		$this->timeOut = (int) $seconds;
	}


	/**
	 * Set the user-agent for you application
	 * It will be appended to ours, the result will look like: "PHP Happenr/<version> <your-user-agent>"
	 *
	 * @return	void
	 * @param	string $userAgent	Your user-agent, it should look like <app-name>/<app-version>
	 */
	public function setUserAgent($userAgent)
	{
		$this->userAgent = (string) $userAgent;
	}


// event methods

	/**
	 * Get events
	 *
	 * @return	array
	 * @param	string[optional] $language	Language to be used for categories (both in querystring and in output).
	 * @param	string[optional] $channelId	Defines the database that will be queried.
	 * @param	string[optional] $sorting	Possible values are: alphabetical, date, distance, eventranking, random, images.
	 * @param	int[optional] $sourceId	Integer value to query only one source.
	 * @param	int[optional] $firstRecord	Integer value to indicate the first record.
	 * @param	int[optional] $limit	Integer value to limit the number of results returned.
	 * @param	bool[optional] $includeDatesXML	If true, detailed XML will be added for each day on which an event takes place.
	 * @param	bool[optional] $includeEvents	If false, no events are returned in XML.
	 * @param	bool[optional] $includeEventDetails	If true, more event fields are returned (e.g. event dates, event content etc.).
	 * @param	bool[optional] $includeFilters	If true, all possible filters (with event count) are included in the XML.
	 * @param	bool[optional] $includeDoubles	If false, event doubles are filtered out to only keep unique events.
	 * @param	bool[optional] $includePermanentEvents	If false, permanent events are filtered out.
	 * @param	string[optional] $country	Filter on one specific country.
	 * @param	string[optional] $region	Filter on one specific region (typically a province or county).
	 * @param	string[optional] $town	Filter on one specific town or municipality.
	 * @param	string[optional] $longitude	If $longitude & $latitude are given, the events closest to the given coordinates will be returned. Use negative values for latitude South and longitude West.
	 * @param	string[optional] $latitude	If $longitude & $latitude are given, the events closest to the given coordinates will be returned. Use negative values for latitude South and longitude West.
	 * @param	int[optional] $maxDistance	Only events will be returned with maximum the given distance (in km) from the given longitude/latitude. Note: if a location is given (town, region and/or country) but no longitude/latitude is given, the location will be converted to a longitude/latitude first, and the search result will no longer be limited to the given location but will be limited to the given maximum distance from the location.
	 * @param	string[optional] $category	Filter on one specific category (type of events).
	 * @param	int[optional] $date	Filter on one specific day.
	 * @param	int[optional] $fromDate	Filter on a period between a from-date and a to-date (both inclusive).
	 * @param	int[optional] $toDate	Filter on a period between a from-date and a to-date (both inclusive).
	 * @param	string[optional] $period	Filter on a period between a from-date and a to-date (both inclusive).
	 * @param	string[optional] $searchText	Free text search on title and content.
	 * @param	string[optional] $venue	Free text search on venue.
	 */
	public function getEvents($language = 'EN', $channelId = null, $sorting = null, $sourceId = null, $firstRecord = 0, $limit = null,
								$includeDatesXML = false, $includeEvents = true, $includeEventDetails = true, $includeFilters = false,
								$includeDoubles = false, $includePermanentEvents = false, $country = null, $region = null, $town = null,
								$longitude = null, $latitude = null, $maxDistance = null, $category = null, $date = null, $fromDate = null,
								$toDate = null, $period = null, $searchText = null, $venue = null)
	{
		// possible values
		$possibleSortingValues = array('alphabetical', 'date', 'distance', 'eventranking', 'random', 'images');

		// validate
		if($sorting !== null && !in_array($sorting, $possibleSortingValues)) throw new HappenrException('Invalid value for sorting.');
		if($limit !== null && $limit > 500) throw new HappenrException('Invalid value for limit.');
		if($longitude !== null && $latitude === null) throw new HappenrException('Latitude is required.');
		if($latitude !== null && $longitude === null) throw new HappenrException('Longitude is required.');
		if($date !== null && $date <= 0) throw new HappenrException('Invalide timestamp for date.');
		if($fromDate !== null && $fromDate <= 0) throw new HappenrException('Invalide timestamp for fromDate.');
		if($toDate !== null && $toDate <= 0) throw new HappenrException('Invalide timestamp for toDate.');

		// build parameters
		$parameters['language'] = (string) $language;
		if($channelId !== null) $parameters['channelid'] = (string) $channelId;
		if($sorting !== null) $parameters['sorting'] = (string) $sorting;
		if($sourceId !== null) $parameters['sourceid'] = (int) $sourceId;
		if($firstRecord !== 0) $parameters['firstrecord'] = (int) $firstRecord;
		if($limit !== null) $parameters['limit'] = (int) $limit;
		if((bool) $includeDatesXML) $parameters['includedatesxml'] = 1;
		if(!(bool) $includeEvents) $parameters['includeevents'] = 0;
		if(!(bool) $includeEventDetails) $parameters['includeeventdetails'] = 0;
		if((bool) $includeFilters) $parameters['includefilters'] = 1;
		if((bool) $includeDoubles) $parameters['includedoubles'] = 1;
		if((bool) $includePermanentEvents) $parameters['includeeventdetails'] = 1;
		if($country !== null) $parameters['country'] = (string) $country;
		if($region !== null) $parameters['region'] = (string) $region;
		if($town !== null) $parameters['town'] = (string) $town;
		if($longitude !== null) $parameters['longitude'] = (string) $longitude;
		if($latitude !== null) $parameters['latitude'] = (string) $latitude;
		if($maxDistance != null) $parameters['maxdistance'] = (int) $maxDistance;
		if($category !== null) $parameters['category'] = (string) $category;
		if($date !== null) $parameters['date'] = date('Y-m-d', (int) $date);
		if($fromDate !== null) $parameters['fromdate'] = date('Y-m-d', (int) $fromDate);
		if($toDate !== null) $parameters['todate'] = date('Y-m-d', (int) $toDate);
		if($period !== null) $parameters['period'] = (string) $period;
		if($searchText !== null) $parameters['searchtext'] = (string) $searchText;
		if($venue !== null) $parameters['venue'] = (string) $venue;

		// make the call
		$response = $this->doCall('getEvents.php', $parameters);

		// init var
		$events = array();

		// any events?
		if(isset($response->events->event))
		{
			// loop events
			foreach($response->events->event as $event)
			{
				$events[] = $this->eventXMLToArray($event);
			}
		}

		// return
		return $events;
	}


	/**
	 * Get more details about an event
	 *
	 * @return	array
	 * @param	string $eventId
	 * @param	string[optional] $channelId	Defines the database that will be queried.
	 * @param	bool[optional] $includeDatesXML	If true, detailed XML will be added for each day on which the event takes place.
	 */
	public function getEventDetails($eventId, $channelId = null, $includeDatesXML = false)
	{
		// build parameters
		$parameters['eventid'] = (string) $eventId;
		if($channelId !== null) $parameters['channelid'] = (string) $channelId;
		if((bool) $includeDatesXML) $parameters['includedatesxml'] = 1;

		// make the call
		$response = $this->doCall('getEventDetails.php', $parameters);

		// validate response
		if(!isset($response->event['id']) || $response->event['id'] == '') throw new HappenrException('No event found.');

		// event found
		return (array) $this->eventXMLToArray($response->event);
	}

}


/**
 * Happenr Exception class
 *
 * @author	Tijs Verkoyen <php-happenr@verkoyen.eu>
 */
class HappenrException extends Exception
{
}

?>