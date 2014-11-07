<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 19.03.14
 * Time: 12:47
 */

namespace misc\DB;


use misc\Singleton;

class DB implements DBEngineInterface{
	use Singleton;

    const ENGINE_TYPE_MYSQL      = 'MYSQL';
    const ENGINE_TYPE_CASSANDRA  = 'CASSANDRA';

    function getEngineType(){
        if($this->engine instanceof MysqlEngine){
            return self::ENGINE_TYPE_MYSQL;
        }elseif($this->engine instanceof CassandraEngine){
            return self::ENGINE_TYPE_CASSANDRA;
        }
        return null;
    }

	/**
	 * Proxy all available methods to DB engine
	 */

	/** {@inheritdoc} */
	function connect($host, $port, $user, $pass, $base){return $this->proxy(__FUNCTION__, func_get_args());}
	/** {@inheritdoc} */
	function disconnect(){return $this->proxy(__FUNCTION__, func_get_args());}
	/** {@inheritdoc} */
	function disableAutocommit(){return $this->proxy(__FUNCTION__, func_get_args());}
	/** {@inheritdoc} */
	function enableAutocommit(){return $this->proxy(__FUNCTION__, func_get_args());}
	/** {@inheritdoc} */
	function begin(){return $this->proxy(__FUNCTION__, func_get_args());}
	/** {@inheritdoc} */
	function commit(){return $this->proxy(__FUNCTION__, func_get_args());}
	/** {@inheritdoc} */
	function rollback(){return $this->proxy(__FUNCTION__, func_get_args());}
	/** {@inheritdoc} */
	function select($tableName, array $conditions, $fetchStyle = DB::SELECT_ARR, array $options = [], $colName = null){return $this->proxy(__FUNCTION__, func_get_args());}
	/** {@inheritdoc} */
	function selectBySQL($query, $fetchStyle = DB::SELECT_ARR, $colName = null){return $this->proxy(__FUNCTION__, func_get_args());}
	/** {@inheritdoc} */
	function executeSql($query){return $this->proxy(__FUNCTION__, func_get_args());}
	/** {@inheritdoc} */
	function insert($tableName, array $data, $onDuplicate = DB::INSERT_DEFAULT, $options = []){return $this->proxy(__FUNCTION__, func_get_args());}
	/** {@inheritdoc} */
	function delete($tableName, array $conditions){return $this->proxy(__FUNCTION__, func_get_args());}
	/** {@inheritdoc} */
	function update($tableName, array $values, array $conditions, array $options=[]){return $this->proxy(__FUNCTION__, func_get_args());}
	/** {@inheritdoc} */
	function getLastInsertId(){return $this->proxy(__FUNCTION__, func_get_args());}
	/**
	 * Proxy all methods to DB engine
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 */
	private function proxy($method, $args) {
//		echo $method.':';
//		print_r($args);
		$result = call_user_func_array([$this->engine, $method], $args);
//		echo "-----$method------\n";
		return $result;
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
//		print_r(debug_backtrace());exit;
		$this->engine = new MysqlEngine();
		if(!$this->connect($host, $port, $user, $pass, $base)){
			return false;
		}
		return true;
	}

	/** @var DBEngineInterface $engine */
	protected $engine;

	/**
	 * Select styles
	 */
	const SELECT_COL		= 1;
	const SELECT_ROW		= 2;
	const SELECT_ARR		= 3;
	const SELECT_ARR_COL	= 4;
	const SELECT_COUNT      = 5;

	/**
	 * Reaction on duplicate insert
	 */
	const INSERT_DEFAULT		= 1;
	const INSERT_IGNORE			= 2;
	const INSERT_UPDATE			= 3;

	/**
	 * Selct options
	 */
	const OPTION_OFFSET 	= 'offset';
	const OPTION_LIMIT 		= 'limit';
	const OPTION_ORDER_BY	= 'order';
	const OPTION_FOR_UPDATE	= 'forUpdate';
	const OPTION_BY_INDEX   = 'tryByIndex';

	/**
	 * Default select order style
	 */
	const DEFAULT_ORDER		= '`id` ASC';



}
