<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 14.08.14
 * Time: 11:58
 */
namespace misc\CURL;

abstract class StreamableContent{
	/** @var CURL */
	private $curl;
	protected $paused = false;

	function __construct($curl) {
		$this->curl = $curl;
	}

	/**
	 * @return int
	 */
	abstract function getSize();

	/**
	 * @param int $length
	 * @return string
	 */
	abstract function read($length);

	/*
	 * CURLPAUSE_SEND, CURLPAUSE_ALL, CURLPAUSE_CONT
	 * CURL_READFUNC_PAUSE
	 */

	protected function pauseSending(){
//		echo "PAUSED\n";
		curl_pause($this->curl->getHandler(), CURLPAUSE_SEND);
		$this->paused = true;
	}

	protected function resumeSending(){
//		echo "UNPAUSED\n";
		curl_pause($this->curl->getHandler(), CURLPAUSE_SEND_CONT);
		$this->paused = false;
	}
}
