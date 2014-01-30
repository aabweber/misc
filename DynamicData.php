<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 27.01.14
 * Time: 17:21
 */

namespace misc;


trait DynamicData {
	private $objectData     = [];

	function __set($var, $val){
		$this->objectData[$var] = $val;
	}

	function __get($name) {
		return $this->objectData[$name];
	}


	function __isset($name) {
		return isset($this->objectData[$name]);
	}

	function __unset($name) {
		unset($this->objectData[$name]);
	}

	function __toString() {
		return serialize($this->objectData);
	}

	function setData($data){
		foreach($data as $key => $value){
			$this->{$key} = $value;
		}
	}

	static function getArrayRecursive($data){
		$out = [];
		foreach($data as $key => $value){
			if(is_object($value)){
				$out[$key] = $value->getData();
			}elseif(is_array($value)){
				$out[$key] = self::getArrayRecursive($value);
			}else{
				$out[$key] = $value;
			}
		}
		return $out;
	}

	function getData(){
		return self::getArrayRecursive($this->objectData);
	}

	/**
	 * Генерируем объект на основе данных
	 * @param mixed $data
	 * @return static
	 */
	static function genOnData($data){
		$instance = new static();
		$instance->setData($data);
		return $instance;
	}

}







