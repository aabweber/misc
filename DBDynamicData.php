<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 05.02.14
 * Time: 13:02
 */

namespace misc;


use misc\DB\DB;
use misc\DB\DBFunction;

trait DBDynamicData {
	use DynamicData{
		DynamicData::__set as d__set;
		DynamicData::genOnData as _d_genOnData;
	}

	static $cached                  = false;    // collection
	static $cache                   = [];

	static protected $fields        = [];
	protected $modifiedFields       = [];
	static protected $table         = null;
	static protected $object_inited = false;
	protected $instance_generated   = false;

    /** @var DB */
    static protected $db            = null;

	static $CACHE_DIR               = 'DBStructCache';

	function __construct() {
		static::init();
	}

    /**
     * @param string $name
     * @return bool
     */
    function hasProperty($name){
        return isset(static::$fields[$name]);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param bool $saveInDB
     */
    function setValue($name, $value, $saveInDB){
        $this->{$name} = $value;
        if($saveInDB) $this->saveInDB();
    }

    /**
     * @param string $name
     * @return Mixed
     */
    function getValue($name){
        if(isset($this->{$name})) return $this->{$name};
        return null;
    }

	static function getTable(){
		if(!static::$object_inited){
			new static();
		}
		return static::$table;
	}

    static function getMysqlFields(){
        $fields = [];
        $columns = static::$db->selectBySQL('SHOW COLUMNS FROM `'.static::$table.'`');
        foreach($columns as $column_row){
            $field_name = $column_row['Field'];
            $fields[$field_name] = ['default' => '', 'values' => [], 'type'=>''];
            if(preg_match('/enum\((.+?)\)/si', $column_row['Type'], $ms)){
                $enums = $ms[1];
                preg_match_all('/\'([^\']+)\'/si', $enums, $ms);
                foreach($ms[1] as &$value){
                    $value = strtoupper($value);
                }
                $fields[$field_name]['values'] = $ms[1];
                $fields[$field_name]['default'] = $column_row['Default'];
                $fields[$field_name]['type'] = 'enum';
            }
        }
        return $fields;
    }

    static function getCassandraFields(){
        $fields = [];
        $rows = static::$db->select('system.schema_columns', ['keyspace_name' => CASSANDRA_DB, 'columnfamily_name' => static::$table]);
        foreach($rows as $row){
            $field_name = $row['column_name'];
            $fields[$field_name] = ['default' => '', 'values' => [], 'type'=>''];
        }
        return $fields;
    }

    static function initFields(){
        $dirname = BASE_DIR.'/'.self::$CACHE_DIR;
        $filename = $dirname.'/'.static::$table.'.php';
        if(is_file($filename) && !__DEBUG__){
            static::$fields = unserialize(file_get_contents($filename));
        }else{
            if(static::$db->getEngineType()==DB::ENGINE_TYPE_MYSQL){
                static::$fields = self::getMysqlFields();
            }elseif(static::$db->getEngineType()==DB::ENGINE_TYPE_CASSANDRA){
                static::$fields = self::getCassandraFields();
            }
            if(is_dir($dirname) || @mkdir($dirname)){
                @file_put_contents($filename, serialize(static::$fields));
            }
        }
    }

	/**
	 * Initialize DB table information
	 * @param string $table
	 */
	static function init($table = null){
		if(static::$object_inited){
			return;
		}
        if(!static::$db){
            static::$db = DB::get();
        }
		if($table){
			static::$table = $table;
		}else{
			$class = get_called_class();
			static::$table = strtolower(preg_replace('/.+\\\/si', '', $class).'s');
		}
        self::initFields();
		static::$object_inited = true;
	}

	/**
	 * It calls after fetching data from DB, before object creating
	 * @param string $row[string]
	 * @return void
	 */
	protected static function afterGetFromDB(&$row){}

	/**
	 * It fires on clone of object before saving data in DB
	 * @return void
	 */
	protected function beforeSaveInDB(){}

	/**
	 * Save modified fields
	 * @param string $var
	 * @param mixed $val
	 */
	function __set($var, $val){
		$this->d__set($var, $val);
		if($this->instance_generated){
			$this->modifiedFields[$var] = true;
		}
	}

	/**
	 * @param Mixed $data
	 * @return static
	 */
	private static function checkCache($data){
		if( isset($data['id']) && $data['id']>0 && static::$cached && isset(static::$cache[$data['id']]) ){
			return static::$cache[$data['id']];
		}
		return null;
	}

	/**
	 * @param Mixed $data[string]
	 * @return static
	 */
	static function genOnData($data) {
		$instance = self::checkCache($data);
		if(!$instance){
			$instance = self::_d_genOnData($data);
			if(static::$cached && isset($data['id']) && $data['id']>0){
				static::$cache[$data['id']] = $instance;
			}
		}
		$instance->instance_generated = true;
		return $instance;
	}


	/**
	 * Save data in DB
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
                exit;
			}
			if($data){
                static::$db->update(static::$table, $data, ['id' => $this->id]);
			}
		}else{
//			print_r(static::$fields);
//			print_r($data);
			$this->id = static::$db->insert(static::$table, $data, $onDuplicate);
			if(static::$cached){
				static::$cache[$this->id] = $this;
			}
		}
		return $this->id;
	}

	/**
	 * Set object data with validation
	 * @param $data
	 * @return ReturnData
	 */
	function setData($data){
		if(!is_array($data)){
			var_dump($data);
			exit;
		}
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
	 * Get object based on data from DB
	 * @param $id
	 * @param bool $returnError
	 * @return static
	 */
	static function get($id, $returnError = false){
		if($instance = self::checkCache(['id'=>$id])) return $instance;
        $table = static::getTable();
        $row = static::$db->select($table, ['id' => $id], DB::SELECT_ROW, [DB::OPTION_BY_INDEX=>true]);
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
		unset(static::$cache[$this->id]);
        static::$db->delete(static::$table, ['id' => $this->id]);
	}



	static function getOneForProcessing($condition, $newValues, $order='', $options = []){
//		UPDATE messages SET id = LAST_INSERT_ID(id), status="PROCESSING" WHERE status="NONE" LIMIT 1;
//		SELECT LAST_INSERT_ID();
		$newValues['id'] = new DBFunction('LAST_INSERT_ID(id)');
        $table = static::getTable();
        $affected = static::$db->update($table, $newValues, $condition, [DB::OPTION_LIMIT => 1]);
		if($affected){
			$record = static::$db->select($table, ['id' => static::$db->getLastInsertId()], DB::SELECT_ROW);
			$instance = static::genOnData($record);
			return $instance;
		}
		return null;
	}


	/**
	 * Get list of object by conditions
	 * @param array $conditions
	 * @param array $options
	 * @return static[]
	 */
	public static function getList($conditions = [], $options=[]) {
        $table = static::getTable();
        $rows = static::$db->select($table, $conditions, DB::SELECT_ARR, $options);
		$list = [];
		foreach($rows as $row){
			$list[] = static::genOnData($row);
		}
		return $list;
	}

	/**
	 * Get object by conditions
	 * @param Mixed[string] $conditions
	 * @param bool $returnError
	 * @return static
	 */
	public static function getByConditions($conditions, $returnError = false){
        $table = static::getTable();
        $row = static::$db->select($table, $conditions, DB::SELECT_ROW);
		if(!$row){
			if($returnError){
				return RetErrorWithMessage('CANT_FIND_OBJECT', 'Can\'t find object('.get_called_class().') with conditions: '.print_r($conditions, true).'"');
			}else{
				return null;
			}
		}
		$object = static::genOnData($row);
		return $object;
	}

	/**
	 * Get 1 object by field value
	 * @param string $field_name
	 * @param Mixed $field_value
	 * @param bool $returnError
	 * @return static
	 */
	public static function getByField($field_name, $field_value, $returnError = false) {
		return self::getByConditions([$field_name=>$field_value], $returnError);
	}

	/**
	 * Get an object by name
	 * @param string $name
	 * @return static
	 */
	public static function getByName($name) {
		return self::getByField('name', $name);
	}

	/**
	 * @param mixed $data
	 * @return static
	 */
	public static function create($data){
        unset($data['id']);
        $obj = static::genOnData($data);
        if($obj instanceof ReturnData){
            print_r($data);
            print_r($obj);
            exit;
        }
		$obj->saveInDB();
		return $obj;
	}

	public static function getCount($conditions){
        $table = static::getTable();
        $cnt = static::$db->select($table, $conditions, DB::SELECT_COUNT);
		return $cnt;
	}
}







