<?php
/**
 * Project: webos
 * User: semen
 * Date: 14.08.13
 * Time: 16:29
 */

namespace misc\DB;



interface DBEngineInterface {

	function connect($host, $port, $user, $pass, $base);
	function disconnect();

	//	Transactions
	function begin();
	function commit();
	function rollback();

	/**
	 * @param string $tableName
	 * @param array[string]scalar $conditions
	 * @param int $fetchStyle
	 * @param array $options
	 * @param string $colName
	 * @return array|mixed
	 */
	function select($tableName, array $conditions, $fetchStyle = DB::SELECT_ARR, array $options = [], $colName = null);

	/**
	 *
	 * @param string $query
	 * @param array[]scalar $params
	 * @param int $fetchStyle
	 * @return array|mixed
	 */
	function selectBySQL($query, $fetchStyle = DB::SELECT_ARR, $colName = null);

	/**
	 * @param string $query
	 */
	function executeSql($query);

	/**
	 * @param string $tableName
	 * @param array[]scalar $data
	 * @param int $onDuplicate
	 * @return int
	 */
	function insert($tableName, array $data, $onDuplicate = DB::INSERT_DEFAULT);


	/**
	 * @param string $tableName
	 * @param array[]scalar $conditions
	 * @return bool|int
	 */
	function delete($tableName, array $conditions);

	/**
	 * @param string $tableName
	 * @param array[]scalar $values
	 * @param array[]scalar $conditions
	 * @param array $options
	 * @return bool|int
	 */
	function update($tableName, array $values, array $conditions);

}