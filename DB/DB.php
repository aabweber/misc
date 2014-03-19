<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 19.03.14
 * Time: 12:47
 */

namespace misc\DB;


use misc\Singleton;

class DB extends Singleton implements DBEngineInterface{
	/**
	 * Proxy all available methods to DB engine
	 */


	function connect($host, $port, $user, $pass, $base){$this->proxy(__FUNCTION__, func_get_args());}
	function disconnect(){$this->proxy(__FUNCTION__, func_get_args());}
	function begin(){$this->proxy(__FUNCTION__, func_get_args());}
	function commit(){$this->proxy(__FUNCTION__, func_get_args());}
	function rollback(){$this->proxy(__FUNCTION__, func_get_args());}
	function select($tableName, array $conditions, $fetchStyle = DB::SELECT_ARR, array $options = [], $colName = null){return $this->proxy(__FUNCTION__, func_get_args());}
	function selectBySQL($query, $fetchStyle = DB::SELECT_ARR, $colName = null){return $this->proxy(__FUNCTION__, func_get_args());}
	function executeSql($query){return $this->proxy(__FUNCTION__, func_get_args());}
	function insert($tableName, array $data, $onDuplicate = DB::INSERT_DEFAULT){return $this->proxy(__FUNCTION__, func_get_args());}
	function delete($tableName, array $conditions){$this->proxy(__FUNCTION__, func_get_args());}
	function update($tableName, array $values, array $conditions){$this->proxy(__FUNCTION__, func_get_args());}
	private function proxy($method, $args) {
		return call_user_func_array([$this->engine, $method], $args);
	}

	/**
	 * Proxy non-existing in DBEngineInterface methods to DB engine
	 * @param $name
	 * @param $arguments
	 */
	function __call($name, $arguments){
		return $this->proxy($name, $arguments);
	}

	/**
	 * Init DB instance and init DB engine and connect to DB server
	 * @param $host
	 * @param $port
	 * @param $user
	 * @param $pass
	 * @param $base
	 */
	public function initInstance($host, $port, $user, $pass, $base){
		$this->engine = MysqlEngine::get();
		$this->connect($host, $port, $user, $pass, $base);
	}

	/** @var DBEngineInterface $engine */
	private $engine;

	const SELECT_COL		= 1;
	const SELECT_ROW		= 2;
	const SELECT_ARR		= 3;
	const SELECT_ARR_COL	= 4;

	const INSERT_DEFAULT		= 1;
	const INSERT_IGNORE			= 2;
	const INSERT_UPDATE			= 3;

	const OPTION_OFFSET 	= 'offset';
	const OPTION_LIMIT 		= 'limit';
	const OPTION_ORDER_BY	= 'order';
	const OPTION_FOR_UPDATE	= 'forUpdate';

	const DEFAULT_ORDER		= '`id` ASC';



}