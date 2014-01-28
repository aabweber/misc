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


} 