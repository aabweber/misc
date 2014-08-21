<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 20.08.14
 * Time: 18:06
 */

namespace misc\CURL;

class Reader{
	/** @var CURL  */
	public $curl;
	/** @var int */
	public $position;
	/** @var callable */
	public $callback;
	/** @var bool */
	public $finished        = false;
	/** @var bool */
	public $paused          = false;

	function __construct(CURL $curl, $position, callable $callback) {
		$this->curl = $curl;
		$this->position = $position;
		$this->callback = $callback;
		$this->curl->modify(['timeout'=>0]);
	}

	function onFinish($c, $info){
		$this->finished = true;
		if($this->paused){
			$this->resumeSending();
		}
		call_user_func($this->callback, $c, $info);
	}

	function pauseSending(){
//		echo "PAUSED\n";
		curl_pause($this->curl->getHandler(), CURLPAUSE_SEND);
		$this->paused = true;
	}

	function resumeSending(){
//		echo "RESUMED\n";
		curl_pause($this->curl->getHandler(), CURLPAUSE_SEND_CONT);
		$this->paused = false;
	}
}