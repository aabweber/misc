<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 27.03.14
 * Time: 16:48
 */

namespace misc;


class APIClient {
	private $apiURI;
	private $curl;

	function __construct($base_api_uri) {
		$this->apiURI = trim($base_api_uri, '/');
		$this->curl = new CURL($this->apiURI);
	}

	/**
	 * @param string $command
	 * @param array[string]scalar $args
	 * @param array[string]scalar $curlModifiers
	 * @return mixed|null
	 */
	function request($command, $args, $curlModifiers = []){
		$this->curl->modify(array_merge(['url' => $this->apiURI.'/'.$command], $curlModifiers));
		$reply = $this->curl->request($args, true);
		return $reply;
	}
} 