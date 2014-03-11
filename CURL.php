<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 29.01.14
 * Time: 12:43
 */

namespace misc;


class CURL {
	private $url;
	private $post = 1;

	private $header = 0;
    private $headers = [];
	private $nobody = false;
	private $verbose = 0;
	private $returnTransfer = true;
	private $followLocation = true;
	private $connection_timeout = 10;
	private $timeout = 15;

	function __construct($url) {
		$this->url = $url;
	}

	function modify($args){
		foreach($args as $name => $value){
			$this->{$name} = $value;
		}
	}

	function request($request, $reply_is_json = true){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->url);
		curl_setopt($ch, CURLOPT_POST, $this->post);
		if($this->post){
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request));
		}
		curl_setopt($ch, CURLOPT_HEADER, $this->header);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($ch, CURLOPT_NOBODY, $this->nobody);
		curl_setopt($ch, CURLOPT_VERBOSE, $this->verbose);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, $this->returnTransfer);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->followLocation);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connection_timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		$result = curl_exec($ch);
		curl_close ($ch);
		if(!$reply_is_json){
			return $result;
		}
		$reply = @json_decode($result, true);
		if(!$result || !$reply){
			error_log('CURL: Cant get content('.$this->url.') or cant json_decode: '.$result.', POST:'.print_r($request, true));
			return null;
		}
		return $reply;
	}
}