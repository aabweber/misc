<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 01.07.14
 * Time: 14:42
 */

namespace misc;


class MultiCURL {
	private $curls = [];
	private $mh = null;

	function __construct() {
		$this->mh = curl_multi_init();
	}


	/**
	 * @param CURL $curl
	 * @param callable $cb
	 */
	function add(CURL $curl, callable $cb=null){
		$ch = $curl->prepare();
		$this->curls[(int)$ch] = ['curl'=>$curl, 'cb'=>$cb];
		curl_multi_add_handle($this->mh, $ch);
		$active = null;
		do {
			$mrc = curl_multi_exec($this->mh, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);
	}

	public function loop() {
		do{
			$status = curl_multi_exec($this->mh, $active);
			$info = curl_multi_info_read($this->mh, $queue);
			if(!$info) return;
			if($info!=false){
				$ch = $info['handle'];
				$c = curl_multi_getcontent($ch);
				if($this->curls[(int)$ch]['cb']){
					call_user_func($this->curls[(int)$ch]['cb'], $c);
				}
				curl_multi_remove_handle($this->mh, $ch);
				unset($this->curls[(int)$ch]);
			}
		} while ($status === CURLM_CALL_MULTI_PERFORM || $active);
	}

	function __destruct() {
		curl_multi_close($this->mh);
	}


}

