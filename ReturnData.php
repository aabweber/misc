<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 24.01.14
 * Time: 16:29
 */

namespace misc{


class ReturnData {
	const STATUS_ERROR              = 'ERROR';
	const STATUS_OK                 = 'OK';

	const RETURN_FORMAT_JSON        = 'json';
	const RETURN_FORMAT_ERLANG      = 'erlang';
	const RETURN_FORMAT_YAML        = 'yaml';
	const RETURN_FORMAT_TEMPLATE    = 'template';

    static $ALLOWED_FORMATS         = [self::RETURN_FORMAT_JSON, self::RETURN_FORMAT_ERLANG, self::RETURN_FORMAT_YAML, self::RETURN_FORMAT_TEMPLATE];

	private $status;
	private $code;
	private $data;

    function isError(){
        return $this->status == self::STATUS_ERROR;
    }

    function getErrorCode(){
        return $this->code;
    }

    function getErrorMessage(){
        return isset($this->data['message']) ? $this->data['message'] : '';
    }

	public static function implodeResults($results) {
		switch(RETURN_FORMAT){
			case self::RETURN_FORMAT_JSON:
				$str = '';
				foreach($results as $result){
					$str .= $result.',';
				}
				return '['.rtrim($str, ',').']';
			case self::RETURN_FORMAT_TEMPLATE:
				$str = '';
				foreach($results as $result){
					$str .= $result;
				}
				return $str;
			case self::RETURN_FORMAT_YAML:
			case self::RETURN_FORMAT_ERLANG:
				error_log('ReturnData:implode: not implemented yet');
				break;
			default:
				error_log('ReturnData: unknown format');
				break;
		}
	}

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

    private function getFormat(){
        $format = RETURN_FORMAT;
        if(isset($_REQUEST['format']) && in_array($_REQUEST['format'], self::$ALLOWED_FORMATS)){
            $format = $_REQUEST['format'];
        }
        return $format;
    }

	function __toString(){
        $format = $this->getFormat();
        switch($format){
			case self::RETURN_FORMAT_JSON:
				return json_encode(['status' => $this->status, 'code' => $this->code, 'data' => $this->data]);
			case self::RETURN_FORMAT_YAML:
				return yaml_emit(['status' => $this->status, 'code' => $this->code, 'data' => $this->data]);

			case self::RETURN_FORMAT_ERLANG:
				return $this->erlang_encode(['status' => $this->status, 'code' => $this->code, 'data' => $this->data]);
			case self::RETURN_FORMAT_TEMPLATE:
				$data = $this->data;
                $data['returnData'] = $this;
                return Template::apply('index', $data);
			default:
				error_log('ReturnData: unknown format');
				break;
		}
		return null;
	}

	static function get($status, $code, $data){
		$rd = new ReturnData();
		$rd->status = $status;
		$rd->code = $code;
		if(RETURN_FORMAT==self::RETURN_FORMAT_TEMPLATE){
			$rd->data = $data;
		}else{
			$rd->data = DynamicData::getArrayRecursive($data);
		}
		return $rd;
	}

}
}


namespace {
	define('OK', \misc\ReturnData::STATUS_OK);
	define('ERROR', \misc\ReturnData::STATUS_ERROR);

	function Ret($status, $code='', $data=[]){
		return \misc\ReturnData::get($status, $code, $data);
	}

	function RetOK($data = []){
		return Ret(OK, '', $data);
	}

	function RetError($code, $data=[]){
		return Ret(ERROR, $code, $data);
	}

	function RetErrorWithMessage($code, $message){
		return RetError($code, ['message' => $message]);
	}
}