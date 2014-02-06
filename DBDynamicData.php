<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 05.02.14
 * Time: 13:02
 */

namespace misc;


use DB\DB;

trait DBDynamicData {
	use DynamicData;

	static private $fields  = [];
	static private $table   = null;
	static private $object_inited = false;

	/**
	 * Инициализируем информацию о таблице
	 * @param string $table
	 */
	static function init($table = null){
		if(self::$object_inited){
			return;
		}
		if($table){
			self::$table = $table;
		}else{
			$class = get_called_class();
			self::$table = strtolower(preg_replace('/.+\\\/si', '', $class).'s');
		}
		$columns = DB::get()->selectBySQL('SHOW COLUMNS FROM `'.self::$table.'`');
		foreach($columns as $column_row){
			$field_name = $column_row['Field'];
			self::$fields[$field_name] = ['default' => '', 'values' => [], 'type'=>''];
			if(preg_match('/enum\((.+?)\)/si', $column_row['Type'], $ms)){
				$enums = $ms[1];
				preg_match_all('/\'([^\']+)\'/si', $enums, $ms);
				foreach($ms[1] as &$value){
					$value = strtolower($value);
				}
				self::$fields[$field_name]['values'] = $ms[1];
				self::$fields[$field_name]['default'] = $column_row['Default'];
				self::$fields[$field_name]['type'] = 'enum';
			}
		}
		self::$object_inited = true;
	}

	/**
	 * Сохраняем данные в БД
	 */
	function saveInDB(){
		$data = [];
		foreach(self::$fields as $name => $options){
			if(isset($this->{$name})){
				$data[$name] = $this->{$name};
			}
		}
		$this->id = DB::get()->insert(self::$table, $data);
		return $this->id;
	}

	/**
	 * Установка данных с проверкой данных
	 * @param $data
	 * @return ReturnData
	 */
	function setData($data){
		foreach($data as $key => $value){
			if(isset(self::$fields[$key]) && self::$fields[$key]['type']=='enum'){
				if($value){
					if(!in_array(strtolower($value), self::$fields[$key]['values'])){
						return RetErrorWithMessage('INCORRECT_VALUE', preg_replace('/.+\\\/si', '', get_called_class()).': Wrong field value('.$value.'), field "'.$key.'" can be one of: '.print_r(self::$fields[$key]['values'], true));
					}
				}
			}
			$this->{$key} = $value;
		}
		foreach(self::$fields as $field_name => $option){
			if(!isset($this->{$field_name})){
				if($option['default']){
					$this->{$field_name} = $option['default'];
				}
			}
		}
		return null;
	}

	/**
	 * Создаем объект на основе данных из БД
	 * @param $id
	 * @return static
	 */
	function get($id){
		$row = DB::get()->select(self::$table, ['id' => $id], DB::SELECT_ROW);
		$instance = self::genOnData($row);
		return $instance;
	}

}







