<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 24.01.14
 * Time: 16:29
 */

namespace misc;


class ReturnData {
	const STATUS_ERROR          = 'ERROR';
	const STATUS_OK             = 'OK';

	const RETURN_FORMAT_JSON    = 'json';
	const RETURN_FORMAT_ERLANG  = 'erlang';

	private $status;
	private $code;
	private $data;

	private function erlang_encode_object($object){
		$string = '';
		foreach($object as $key => $value){
			$string .= '{'.strtolower($key).', "'.(is_array($value)?$this->erlang_encode_object($value):$value).'"}';
		}
		$string .= '';
		return $string;
	}

	private function erlang_encode($data){
		return '{'.strtolower($data['status']).', '.strtolower($data['code']).', '.$this->erlang_encode_object($data['data']).'}';
	}

	function __toString(){
		switch(RETURN_FORMAT){
			case self::RETURN_FORMAT_JSON:
				return json_encode(['status' => $this->status, 'code' => $this->code, 'data' => $this->data]);
			case self::RETURN_FORMAT_ERLANG:
				return $this->erlang_encode(['status' => $this->status, 'code' => $this->code, 'data' => $this->data]);
		}
	}

	static function get($status, $code, $data){
		$rd = new ReturnData();
		$rd->status = $status;
		$rd->code = $code;
		$rd->data = DynamicData::getArrayRecursive($data);
		return $rd;
	}

}

