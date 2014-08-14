<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 14.08.14
 * Time: 11:58
 */
namespace misc\CURL;

class FormDataVariable extends FormDataRow{
	private $value;
	function __construct($name, $value) {
		$this->value = $value;
		parent::__construct($name);
	}

	protected function getValueSize(){
		return strlen($this->value);
	}
	protected function readValue($len) {
		return \Utils::shiftString($this->value, $len);
	}
}
