<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 19.03.14
 * Time: 15:26
 */

namespace misc\DB;


use misc\Singleton;
use mysqli;

class MysqlEngine extends Singleton implements DBEngineInterface {

	/** @var mysqli $link */
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
		$this->link = @new mysqli($host, $user, $pass, $base, $port);
		if (mysqli_connect_errno()) {
			printf("Can't connect to MySQL: %s\n", mysqli_connect_error());
			exit();
		}
		if(!$this->link->set_charset("utf8")){
			printf("MySQL: Cant set utf8 charset, error: %s\n", $this->link->error);
			exit();
		}
	}

	/**
	 * Disconnect from MySQL
	 */
	function disconnect(){
		$this->link->close();
		unset($this->link);
	}

	//	Transactions
	function begin(){
		$this->link->begin_transaction();
	}

	function commit(){
		$this->link->commit();
	}

	function rollback(){
		$this->link->rollback();
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
		$conditionString = $this->genStringOnData($conditions, 'AND');
		if(!$conditionString) $conditionString = '1';
		$sql = 'SELECT '.$colName.' FROM `'.$tableName.'` WHERE '.$conditionString.$this->genOptionsString($options);
		return $this->selectBySQL($sql, $fetchStyle, trim($colName, '`'));
	}

	/**
	 * @param string $query
	 * @param array[]scalar $params
	 * @param int $fetchStyle
	 * @return array|mixed
	 */
	function selectBySQL($query, $fetchStyle = DB::SELECT_ARR, $colName = null){
		echo "$query\n";
		$stmt = $this->executeSql($query, false);
		$result = $stmt->get_result();
		$res = null;
		switch($fetchStyle){
			case DB::SELECT_ARR:
				$res = [];
				while ($row = $result->fetch_assoc()){
					$res[] = $row;
				}
				break;
			case DB::SELECT_ARR_COL:
				$res = [];
				if($colName=='*'){
					while ($row = $result->fetch_array(MYSQLI_BOTH)){
						$res[] = $row[0];
					}
				}else{
					while ($row = $result->fetch_assoc()){
						$res[] = $row[$colName];
					}
				}
				break;
			case DB::SELECT_ROW:
				$res = $row = $result->fetch_assoc();
				break;
			case DB::SELECT_COL:
				if($colName=='*'){
					$res = $result->fetch_array(MYSQLI_BOTH);
					if($res){
						$res = $res[0];
					}
				}else{
					$res = $result->fetch_assoc();
					if($res){
						$res = $res[$colName];
					}
				}
				break;
			default:
				error_log('DB: Unknown fetch style ('.$fetchStyle.')');
		}
		$result->close();
		$stmt->close();
		return $res;
	}

	/**
	 * @param string $query
	 * @param bool $closeStatement
	 * @return \mysqli_stmt
	 */
	function executeSql($query, $closeStatement = true){
		$stmt = $this->link->prepare($query);
		if(!$stmt && defined('__DEBUG__') && __DEBUG__){
			echo 'CANT PREPARE SQL, ERROR: '.$this->link->error.' SQL: '.$query;
			exit;
		}
		$success = $stmt->execute();
		if(!$success && defined('__DEBUG__') && __DEBUG__){
			echo 'CANT EXECUTE SQL, ERROR: '.$this->link->error.' SQL: '.$query;
			exit;
		}
		if($closeStatement){
			$stmt->close();
		}
		return $stmt;
	}

	/**
	 * @param string $tableName
	 * @param array[]scalar $data
	 * @param int $onDuplicate
	 * @return int
	 */
	function insert($tableName, array $data, $onDuplicate = DB::INSERT_DEFAULT){
		$sql = 'INSERT '.($onDuplicate==DB::INSERT_IGNORE?'IGNORE ':'').'INTO `'.$tableName.'`('.implode(',', array_keys($data)).') VALUES ('.$this->genDataValuesString(array_values($data)).')';
		if($onDuplicate==DB::INSERT_UPDATE){
			$sql .= ' ON DUPLICATE KEY UPDATE '.$this->genStringOnData($data, ',');
		}
		$this->executeSql($sql);
		if($onDuplicate == DB::INSERT_UPDATE){
			$updated_id = $this->select($tableName, $data, DB::SELECT_COL, [], 'id');
			return $updated_id;
		}
		return $this->link->insert_id;
	}


	/**
	 * @param string $tableName
	 * @param array[]scalar $conditions
	 * @return bool|int
	 */
	function delete($tableName, array $conditions){
		$sql = 'DELETE FROM `'.$tableName.'` WHERE '.$this->genStringOnData($conditions, 'AND');;
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
		$sql = 'UPDATE `'.$tableName.'` SET '.$this->genStringOnData($values, ',').' WHERE '.$this->genStringOnData($conditions, 'AND');
		$this->executeSql($sql);
	}

	/**
	 * Generate string based on data
	 * @param array[string]scalar $conditions
	 * @param string $separator
	 * @return string
	 */
	private function genStringOnData(array $data, $separator) {
		$sql = '';
		$cnt = count($data);
		$i = 0;
		foreach($data as $var => $val){
			if( is_string($val) && $val[0] == '"' && $val[strlen($val)-1] == '"' ){
				$sql .= '`'.$var.'` = '.substr($val, 1, strlen($val)-2);
			}elseif($val===NULL){
				$sql .= '`'.$var.'` IS NULL';
			}elseif($val===TRUE || $val===FALSE){
				$sql .= '`'.$var.'` = '.($val?'TRUE':'FALSE');
			}else{
				$sql .= '`'.$var.'` = "'.addslashes($val).'"';
			}
			$last = $i == $cnt-1;
			if(!$last){
				$sql .= ' '.$separator.' ';
			}
			$i++;
		}
		return $sql;
	}

	/**
	 * Generate string with values based on passed values array
	 * @param array[]scalar $values
	 * @return string
	 */
	private function genDataValuesString(array $values){
		$sql = '';
		$i = 0;
		$cnt = count($values);
		foreach($values as $val){
			if( is_string($val) && $val[0] == '"' && $val[strlen($val)-1] == '"' ){
				$sql .= substr($val, 1, strlen($val)-2);
			}elseif($val===NULL){
				$sql .= 'NULL';
			}elseif($val===TRUE || $val===FALSE){
				$sql .= $val?'TRUE':'FALSE';
			}else{
				$sql .= '"'.addslashes($val).'"';
			}
			$last = $i == $cnt-1;
			if(!$last){
				$sql .= ', ';
			}
			$i++;
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
		if(isset($options[DB::OPTION_ORDER_BY])){
			$optionsString .= ' ORDER BY '.$options[DB::OPTION_ORDER_BY];
		}
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