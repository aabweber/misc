<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 24.01.14
 * Time: 15:26
 */

namespace misc;


class SimpleXML {

	/**
	 * Преобразует объект из \SimpleXMLElement в Array
	 * @param \SimpleXMLElement $xmlObject
	 */
	static function toArray($xmlObject){
		$result = [];
		foreach($xmlObject as $k => $v){
			if(!isset($result[$k])){
				$result[$k] = [];
			}
			$result[$k][] = is_object($v) ? self::toArray($v) : $v;
		}
		$attrs = $xmlObject->attributes();
		$attrs_result = [];
		foreach($attrs as $attr => $value){
			if(!isset($attrs_result[$attr])){
				$attrs_result[$attr] = [];
			}
			$attrs_result[$attr] = strval($value);
		}
		if(empty($result)){
			return $attrs_result;
		}
		return array_merge($result, $attrs_result);
	}
} 