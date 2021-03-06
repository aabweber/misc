<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 01.07.14
 * Time: 14:42
 */

namespace misc\CURL;


use misc\Observable;

class MultiCURL {
	use Observable;

	const EVENT_CURL_ADDED      = 'CURL_ADDED';
	const EVENT_CURL_INITIATED  = 'CURL_INITIATED';
	private $curls              = [];
	private $mh                 = null;

	function __construct() {
		$this->mh = curl_multi_init();
	}

	/**
	 * @param resource $ch
	 * @return bool
	 */
	function haveHandler($ch){
		return isset($this->curls[(int)$ch]);
	}

	function initCURL(CURL $curl){
		curl_multi_add_handle($this->mh, $curl->getHandler());
		$active = null;
		do {
			$mrc = curl_multi_exec($this->mh, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		$this->event(self::EVENT_CURL_INITIATED, $curl);
	}

	/**
	 * @param CURL $curl
	 * @param callable $cb
	 */
	function add(CURL $curl, callable $cb=null){
		$ch = $curl->prepare([], $this);
		$this->curls[(int)$ch] = ['curl'=>$curl, 'cb'=>$cb];
		$this->initCURL($curl);
		$this->event(self::EVENT_CURL_ADDED, $curl);
	}

	/**
	 * @param resource $ch
	 */
	private function removeCURLHandler($ch){
//		echo "\n--------------removeCURLHandler $ch ------------\n";
		@curl_multi_remove_handle($this->mh, $ch);
		@curl_close($ch);
		unset($this->curls[(int)$ch]);
	}

	/**
	 * @param CURL $curl
	 */
	public function remove(CURL $curl) {
		$this->removeCURLHandler($curl->getHandler());
		$readers = $curl->getReaders();
//		echo "\n--------------REMOVE (cnt: ".count($readers).")------------\n";
		$curl->clearReaders();
		foreach($readers as $reader){
			$this->removeCURLHandler($reader->curl->getHandler());
		}
	}

	public function loop() {
		foreach($this->curls as $curlInfo){
			/** @var CURL $curl */
			$curl = $curlInfo['curl'];
			$curl->setActionInLoop(false);
		}
		do{
			$status = curl_multi_exec($this->mh, $active);
			$info = curl_multi_info_read($this->mh, $queue);
			if(!$info){
				foreach($this->curls as $curlInfo){
					/** @var CURL $curl */
					$curl = $curlInfo['curl'];
					if($curl->getActionInLoop()){
						return true;
						break;
					}
				}
				return false;
			}
			$ch = $info['handle'];
			curl_multi_getcontent($ch);
			$curlInfo = $this->curls[(int)$ch];
			/** @var CURL $curl */
			$curl = $curlInfo['curl'];
			$curl->onFinish();
			if($curlInfo['cb']){
				call_user_func($curlInfo['cb'], $curl->getReply(), $info, $curl);
			}
//			if(isset($this->curls[(int)$ch])){
				$this->removeCURLHandler($curl->getHandler());
//			}
		} while ($status === CURLM_CALL_MULTI_PERFORM || $active);
		return false;
	}

	function __destruct() {
		curl_multi_close($this->mh);
	}



}

