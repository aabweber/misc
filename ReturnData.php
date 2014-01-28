<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 24.01.14
 * Time: 16:29
 */

namespace misc;


class ReturnData {
	const STATUS_ERROR      = 'ERROR';
	const STATUS_OK         = 'OK';

	private $status;
	private $code;
	private $data;

	function __toString(){
		return json_encode(['status' => $this->status, 'code' => $this->code, 'data' => $this->data]);
	}

	static function get($status, $code, $data){
		$rd = new ReturnData();
		$rd->status = $status;
		$rd->code = $code;
		$rd->data = $data;
		return $rd;
	}

}

