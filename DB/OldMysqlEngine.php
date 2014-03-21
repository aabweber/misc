<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 21.03.14
 * Time: 17:19
 */

namespace misc\DB;


use misc\Singleton;

class OldMysqlEngine extends Singleton implements DBEngineInterface  {

	/** @var Resource $link */
	private $link = null;

	/**
	 * Connect to MySQL
	 * @param $host
	 * @param $port
	 * @param $user
	 * @param $pass
	 * @param $base
	 */
	function connect($host, $port, $user, $pass, $base){
		$this->link = @mysql_connect($host.':'.$port, $user, $pass, true);
		if (!$this->link) {
			printf("Can't connect to MySQL: %s\n", mysql_error());
			exit();
		}
		if(!mysql_select_db($base, $this->link)){
			printf("Can't select MySQL base: %s\n", mysql_error());
			exit();
		}
		if(!mysql_set_charset("utf8", $this->link)){
			printf("MySQL: Cant set utf8 charset, error: %s\n", mysql_error());
			exit();
		}
	}

	/**
	 * Disconnect from MySQL
	 */
	function disconnect(){
		mysql_close($this->link);
		unset($this->link);
	}

	//	Transactions
	function begin(){
		$this->executeSql('BEGIN');
	}

	function commit(){
		$this->executeSql('COMMIT');
	}

	function rollback(){
		$this->executeSql('ROLLBACK');
	}

	/**
	 * @param string $tableName
	 * @param array[string]scalar $conditions
	 * @param int $fetchStyle
	 * @param array $options
	 * @param string $colName
	 * @return array|mixed
	 */
	function select($tableName, array $conditions, $fetchStyle = DB::SELECT_ARR, array $options = [], $colName = null){
		if($colName){
			$colName = '`'.$colName.'`';
		}else{
			$colName = '*';
		}
		$sql = 'SELECT '.$colName.' FROM `'.$tableName.'` WHERE '.$this->genConditionsString($conditions).$this->genOptionsString($options);
		return $this->selectBySQL($sql, $fetchStyle, trim($colName, '`'));
	}

	/**
	 * @param string $query
	 * @param array[]scalar $params
	 * @param int $fetchStyle
	 * @return array|mixed
	 */
	function selectBySQL($query, $fetchStyle = DB::SELECT_ARR, $colName = null){
		$r = $this->executeSql($query, false);
		$res = null;
		switch($fetchStyle){
			case DB::SELECT_ARR:
				$res = [];
				while ($row = mysql_fetch_assoc($r)){
					$res[] = $row;
				}
				break;
			case DB::SELECT_ARR_COL:
				$res = [];
				if($colName=='*'){
					while ($row = mysql_fetch_array($r)){
						$res[] = $row[0];
					}
				}else{
					while ($row = mysql_fetch_assoc($r)){
						$res[] = $row[$colName];
					}
				}
				break;
			case DB::SELECT_ROW:
				$res = $row = mysql_fetch_assoc($r);
				break;
			case DB::SELECT_COL:
				if($colName=='*'){
					$res = mysql_fetch_array($r);
					if($res){
						$res = $res[0];
					}
				}else{
					$res = mysql_fetch_assoc($r);
					if($res){
						$res = $res[$colName];
					}
				}
				break;
			default:
				error_log('DB: Unknown fetch style ('.$fetchStyle.')');
		}
		return $res;
	}

	/**
	 * @param string $query
	 * @param bool $closeStatement
	 * @return \mysqli_stmt
	 */
	function executeSql($query, $closeStatement = true){
		if(!($res = mysql_query($query, $this->link)) && defined('__DEBUG__') && __DEBUG__){
			echo 'CANT EXECUTE SQL, ERROR: '.mysql_error($this->link).' SQL: '.$query;
			exit;
		}
		return $res;
	}

	/**
	 * @param string $tableName
	 * @param array[]scalar $data
	 * @param int $onDuplicate
	 * @return int
	 */
	function insert($tableName, array $data, $onDuplicate = DB::INSERT_DEFAULT){
		$fields = '';
		foreach(array_keys($data) as $f){
			$fields .= '`'.$f.'`,';
		}
		$fields = trim($fields, ',');
		$sql = 'INSERT '.($onDuplicate==DB::INSERT_IGNORE?'IGNORE ':'').'INTO `'.$tableName.'`('.$fields.') VALUES ('.$this->genInsertValuesString(array_values($data)).')';
		if($onDuplicate==DB::INSERT_UPDATE){
			$sql .= ' ON DUPLICATE KEY UPDATE '.$this->genUpdateValuesString($data);
		}
		$this->executeSql($sql);
		if($onDuplicate == DB::INSERT_UPDATE){
			$updated_id = $this->select($tableName, $data, DB::SELECT_COL, [], 'id');
			return $updated_id;
		}
		return mysql_insert_id($this->link);
	}


	/**
	 * @param string $tableName
	 * @param array[]scalar $conditions
	 * @return bool|int
	 */
	function delete($tableName, array $conditions){
		$sql = 'DELETE FROM `'.$tableName.'` WHERE '.$this->genConditionsString($conditions);
		$this->executeSql($sql);
	}

	/**
	 * @param string $tableName
	 * @param array[]scalar $values
	 * @param array[]scalar $conditions
	 * @param array $options
	 * @return bool|int
	 */
	function update($tableName, array $values, array $conditions){
		$sql = 'UPDATE `'.$tableName.'` SET '.$this->genUpdateValuesString($values).' WHERE '.$this->genConditionsString($conditions);
		$this->executeSql($sql);
	}

	/**
	 * Prepare value to use in mysql query
	 * @param Mixed $val
	 * @return string
	 */
	private function prepareValue($val){
		if( $val instanceof DBFunction ){
			$val = strval($val);
		}elseif($val===NULL){
			$val = 'NULL';
		}elseif($val===TRUE || $val===FALSE){
			$val = $val ? 'TRUE' : 'FALSE';
		}elseif(is_array($val)){
			foreach($val as &$val_elem){
				$val_elem = $this->prepareValue($val_elem);
			}
			$val  ='('.implode(',', $val).')';
		}else{
			$val = '"'.addslashes($val).'"';
		}
		return $val;
	}

	/**
	 * @param array $data
	 * @return string
	 */
	private function genConditionsString(array $data){
		$sql = '';
		$cnt = count($data);
		$i = 0;
		foreach($data as $var => $val){
			$operator = '=';

			if($val === NULL){
				$operator = 'IS';
			}elseif(is_array($val)){
				list($val, $operator) = $val;
			}

			if(is_array($val)){
				if($operator != 'NOT IN'){
					$operator = 'IN';
				}
			}

			$sql .= '`'.$var.'` '.$operator.' '.$this->prepareValue($val);

			if($i++ != $cnt-1) $sql .= ' AND ';
		}
		if(!$sql){
			$sql = '1';
		}
		return $sql;
	}

	/**
	 * @param array $data
	 * @return string
	 */
	private function genUpdateValuesString(array $data){
		$sql = '';
		$cnt = count($data);
		$i = 0;
		foreach($data as $var => $val){
			$sql .= '`'.$var.'` = '.$this->prepareValue($val);

			if($i++ != $cnt-1) $sql .= ', ';
		}
		return $sql;
	}

	/**
	 * Generate string with values based on passed values array
	 * @param array[]scalar $values
	 * @return string
	 */
	private function genInsertValuesString(array $values){
		$sql = '';
		$i = 0;
		$cnt = count($values);
		foreach($values as $val){
			$sql .= $this->prepareValue($val);
			if($i++ != $cnt-1) $sql .= ', ';
		}
		return $sql;
	}


	/**
	 * Generate options string for select query
	 * @param array[int]mixed $options
	 * @return string
	 */
	private function genOptionsString($options) {
		$optionsString = '';
		$optionsString .= ' ORDER BY '.(isset($options[DB::OPTION_ORDER_BY]) ? $options[DB::OPTION_ORDER_BY] : DB::DEFAULT_ORDER);
		if(isset($options[DB::OPTION_LIMIT])){
			$optionsString .= ' LIMIT ';
			if(isset($options[DB::OPTION_OFFSET])){
				$optionsString .= intval($options[DB::OPTION_OFFSET]).', '.intval($options[DB::OPTION_LIMIT]);
			}else{
				$optionsString .= intval($options[DB::OPTION_LIMIT]);
			}
		}
		if(isset($options[DB::OPTION_FOR_UPDATE])){
			$optionsString .= ' FOR UPDATE';
		}
		return $optionsString;
	}

} 