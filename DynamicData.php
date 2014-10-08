<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 27.01.14
 * Time: 17:21
 */

namespace misc;



use JsonSerializable;

trait DynamicData {
	use Observable;

    public function jsonSerialize(){
        return $this->objectData;
    }

	private $objectData     = [];

	function __set($var, $val){
		$this->objectData[$var] = $val;
		$this->event($var.'_changed', $val);
	}

	function &__get($name) {
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
		return null;
	}

	/**
	 * Проходит рекурсивно и все объекты приводит к массивам
	 * @param mixed $data
	 * @return array
	 */
	static function getArrayRecursive($data){
		$out = [];
		foreach($data as $key => $value){
			if(is_object($value) && method_exists($value, 'getData')){
				$out[$key] = $value->getData();
			}elseif(is_array($value)){
				$out[$key] = self::getArrayRecursive($value);
			}else{
				$out[$key] = $value;
			}
		}
		return $out;
	}

	/**
	 * @return array
	 */
	function getData(){
		return self::getArrayRecursive($this->objectData);
	}

	/**
	 * Генерируем объект на основе массива с данными
	 * @param mixed $data
	 * @return static
	 */
	static function genOnData($data){
		/** @var DynamicData $instance */
		$instance = new static();
		if(($res = $instance->setData($data)) instanceof ReturnData){
			return $res;
		}
		return $instance;
	}

}







