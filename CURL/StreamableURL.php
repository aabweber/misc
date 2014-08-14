<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 14.08.14
 * Time: 11:59
 */
namespace misc\CURL;

use misc\Timer;

class StreamableURL extends StreamableContent{
	private $read_curl;
	private $buffer             = '';
	private $finished           = false;

	function __construct(CURL $curl, $url) {
		parent::__construct($curl);
		$this->read_curl = new CURL($url);
		$this->read_curl->modify(['timeout'=>0]);
		$curl->modify(['timeout'=>0]);
		$curlInitiatedInMultiCURL = false;
		$this->read_curl->setDataWriteFunction(function($data) use ($curl, &$curlInitiatedInMultiCURL){
			$this->buffer .= $data;
			if(isset($this->buffer[$this->dataPointer+1])){
				if(!$curlInitiatedInMultiCURL){
					$curlInitiatedInMultiCURL = true;
					$curl->setNeedInitInMultiCURL(true);
					Timer::after(0, function() use ($curl){
						$curl->getMultiCURL()->initCURL($curl);
					});

				}
				if($this->paused){
					$this->resumeSending();
				}
			}
		});
		$curl->on(CURL::EVENT_PREPARED, function(CURL $curl){
			$curl->clearListeners(CURL::EVENT_PREPARED); // adding only once
			$curl->getMultiCURL()->add($this->read_curl, [$this, 'onFinish']);
		});
		$curl->setNeedInitInMultiCURL(false);
	}

	function getReadCURL(){
		return $this->read_curl;
	}


	function getSize() {
		return 0;
	}

	private $dataPointer = 0;
	private function getData($length){
		$str = substr($this->buffer, $this->dataPointer, $length);
		$this->dataPointer += $length;
		$M = 1024*1024;
		if($this->dataPointer > $M){
			$this->buffer = substr($this->buffer, $M);
			$this->dataPointer -= $M;
		}
		return $str;
	}

	function read($length) {
		if(!isset($this->buffer[$length]) && !$this->finished){
			$str = $this->getData(strlen($this->buffer) - 1);
			$this->pauseSending();
		}else{
			$str = $this->getData($length);
		}
		return $str;
	}

	function onFinish(){
		$this->finished = true;
		if($this->paused){
			$this->resumeSending();
		}
	}
}
