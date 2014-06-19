<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 05.02.14
 * Time: 13:02
 */

namespace misc;


use misc\DB\DB;

trait DBDynamicData {
	use DynamicData{
		DynamicData::__set as d__set;
		DynamicData::genOnData as _d_genOnData;
	}

	static protected $fields  = [];
	protected $modifiedFields = [];
	static protected $table   = null;
	static protected $object_inited = false;
	protected $instance_generated = false;

	static $CACHE_DIR = 'DBStructCache';

	function __construct() {
		static::init();
	}

	static function getTable(){
		if(!static::$object_inited){
			new static();
		}
		return static::$table;
	}

	/**
	 * Инициализируем информацию о таблице
	 * @param string $table
	 */
	static function init($table = null){
		if(static::$object_inited){
			return;
		}
		if($table){
			static::$table = $table;
		}else{
			$class = get_called_class();
			static::$table = strtolower(preg_replace('/.+\\\/si', '', $class).'s');
		}
		$dirname = BASE_DIR.'/'.self::$CACHE_DIR;
		$filename = $dirname.'/'.static::$table.'.php';
		if(is_file($filename)){
			static::$fields = unserialize(file_get_contents($filename));
		}else{
			$columns = DB::get()->selectBySQL('SHOW COLUMNS FROM `'.static::$table.'`');
			foreach($columns as $column_row){
				$field_name = $column_row['Field'];
				static::$fields[$field_name] = ['default' => '', 'values' => [], 'type'=>''];
				if(preg_match('/enum\((.+?)\)/si', $column_row['Type'], $ms)){
					$enums = $ms[1];
					preg_match_all('/\'([^\']+)\'/si', $enums, $ms);
					foreach($ms[1] as &$value){
						$value = strtoupper($value);
					}
					static::$fields[$field_name]['values'] = $ms[1];
					static::$fields[$field_name]['default'] = $column_row['Default'];
					static::$fields[$field_name]['type'] = 'enum';
				}
			}
			if(is_dir($dirname) || @mkdir($dirname)){
				file_put_contents($filename, serialize(static::$fields));
			}
		}
		static::$object_inited = true;
	}

	/**
	 * Выполняется после выборки объектьа из БД
	 * @return void
	 */
	protected static function afterGetFromDB(&$row){}

	/**
	 * Выполняется на клоне объекта перед сохранении в БД
	 * @return void
	 */
	protected function beforeSaveInDB(){}

	/**
	 * Запоминаем те поля, которые модифицировали
	 * @param string $var
	 * @param mixed $val
	 */
	function __set($var, $val){
		$this->d__set($var, $val);
		if($this->instance_generated){
			$this->modifiedFields[$var] = true;
		}
	}

	static function genOnData($data) {
		$instance = self::_d_genOnData($data);
		$instance->instance_generated = true;
		return $instance;
	}


	/**
	 * Сохраняем данные в БД
	 */
	function saveInDB($onDuplicate = DB::INSERT_DEFAULT){
		/** @var DBDynamicData $clone */
		$clone = clone $this;
		$clone->beforeSaveInDB();
		$data = [];
		foreach(static::$fields as $name => $options){
			if(
					isset($clone->{$name}) &&
					( !isset($this->id) || isset($clone->modifiedFields[$name]) )
			){
				$data[$name] = $clone->{$name};
			}
		}
		if(isset($this->id)){
			if(defined('DEBUG_UPDATE') && DEBUG_UPDATE){
				echo "Updating ".static::$table." table #id=".$this->id."\n";
				print_r($data);
				print_r($clone->modifiedFields);
			}
			DB::get()->update(static::$table, $data, ['id' => $this->id]);
		}else{
//			print_r(static::$fields);
//			print_r($data);
			$this->id = DB::get()->insert(static::$table, $data, $onDuplicate);
		}
		return $this->id;
	}

	/**
	 * Установка данных с проверкой данных
	 * @param $data
	 * @return ReturnData
	 */
	function setData($data){
		foreach($data as $key => $value){
			if(isset(static::$fields[$key]) && static::$fields[$key]['type']=='enum'){
				if($value){
					if(!in_array(strtoupper($value), static::$fields[$key]['values'])){
						return RetErrorWithMessage('INCORRECT_VALUE', preg_replace('/.+\\\/si', '', get_called_class()).': Wrong field value('.$value.'), field "'.$key.'" can be one of: '.print_r(static::$fields[$key]['values'], true));
					}else{
						$value = strtoupper($value);
					}
				}
			}
			$this->{$key} = $value;
		}
		foreach(static::$fields as $field_name => $option){
			if(!isset($this->{$field_name})){
				if($option['default']){
					$this->{$field_name} = $option['default'];
				}
			}
		}
        $this->instance_generated = true;
		return null;
	}

	/**
	 * Создаем объект на основе данных из БД
	 * @param $id
	 * @param bool $returnError
	 * @return static
	 */
	static function get($id, $returnError = false){
		new static();
		$row = DB::get()->select(static::$table, ['id' => $id], DB::SELECT_ROW);
		if(!$row){
			if($returnError){
				return RetErrorWithMessage('WRONG_ID', 'Can\'t find object('.get_called_class().') with id="'.$id.'"');
			}
			return null;
		}
		static::afterGetFromDB($row);
		$instance = static::genOnData($row);
		return $instance;
	}

	/**
	 * Delete the object
	 */
	function delete(){
		DB::get()->delete(static::$table, ['id' => $this->id]);
	}

	/**
	 * Get one record from DB, set as processing and return object
	 * @var Mixed $condition
	 * @return static
	 */
	static function getOneForProcessing($condition, $newValues, $order=''){
		new static();
		DB::get()->begin();
		$options = [DB::OPTION_LIMIT => 1, DB::OPTION_FOR_UPDATE => true];
		if($order){
			$options[DB::OPTION_ORDER_BY] = $order;
		}
		$record_id = DB::get()->select(static::$table, $condition, DB::SELECT_COL, $options);
		if(!$record_id){
			DB::get()->rollback();
			return null;
		}
		DB::get()->update(static::$table, $newValues, ['id' => $record_id]);
		DB::get()->commit();
		$instance = self::get($record_id);
		return $instance;
	}


	/**
	 * Get list of object by conditions
	 * @param array $conditions
	 * @return static[]
	 */
	public static function getList($conditions = []) {
		$rows = DB::get()->select(static::getTable(), $conditions);
		$list = [];
		foreach($rows as $row){
			$list[] = static::genOnData($row);
		}
		return $list;
	}

	/**
	 * Get 1 object by field value
	 * @param string $field_name
	 * @param Mixed $field_value
	 * @return null|static
	 */
	public static function getByField($field_name, $field_value) {
		$domain_row = DB::get()->select(self::getTable(), [$field_name=>$field_value], DB::SELECT_ROW);
		if(!$domain_row){
			return null;
		}
		$domain = static::genOnData($domain_row);
		return $domain;
	}

	/**
	 * Get an object by name
	 * @param string $name
	 * @return static|null
	 */
	public static function getByName($name) {
		return self::getByField('name', $name);
	}

	/**
	 * @param mixed $data
	 * @return static
	 */
	public static function create($data){
		$obj = static::genOnData($data);
		$obj->saveInDB();
		return $obj;
	}
}







