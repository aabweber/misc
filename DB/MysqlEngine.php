<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 19.03.14
 * Time: 15:26
 */

namespace misc\DB;


use misc\Singleton;
use misc\Utils;
use mysqli;

class MysqlEngine implements DBEngineInterface {
//	use Singleton;

	const MYSQL_GONE_AWAY = 2006;
	const TABLE_USING_BEFORE_AUTO_CACHE_INFO = 30;

	/** @var mysqli $link */
	private $link = null;


	/** @var HandlerSocket */
	private $handlerSocketRead;
	/** @var HandlerSocket */
	private $handlerSocketWrite;
	/** @var Mixed[] */
	private $tableInfo              = [];
	/** @var int[] */
	private $tableUsing             = [];

	private $host, $port, $user, $pass, $base;

	/**
	 * Connect to MySQL
	 * @param $host
	 * @param $port
	 * @param $user
	 * @param $pass
	 * @param $base
	 */
	function connect($host, $port, $user, $pass, $base){
		$port = intval($port);
		list($this->host, $this->port, $this->user, $this->pass, $this->base) = [$host, $port, $user, $pass, $base];
		$this->link = @new mysqli($host, $user, $pass, $base, $port);
		if (mysqli_connect_errno()) {
			return false;
		}
		if(!$this->link->set_charset("utf8mb4")){
			return false;
		}
		if($this->dataAccessibleByIndex()){
			$readPort = defined('HS_READ_PORT')?HS_READ_PORT:9998;
			$writePort = defined('HS_WRITE_PORT')?HS_WRITE_PORT:9999;
			$this->handlerSocketRead = new \HandlerSocket($host, $readPort);
			$this->handlerSocketWrite = new \HandlerSocket($host, $writePort);
		}
		return true;
	}

	/**
	 * @return bool
	 */
	function dataAccessibleByIndex(){
		if(defined('IGNORE_HANDLER_SOCKET') && IGNORE_HANDLER_SOCKET){
            return false;
        }
        return class_exists('HandlerSocket');
	}

	/**
	 * Disconnect from MySQL
	 */
	function disconnect(){
		$this->link->close();
		unset($this->link);
	}

	function disableAutocommit(){
		$this->link->autocommit(FALSE);
	}

	function enableAutocommit(){
		$this->link->autocommit(TRUE);
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
        if($this->dataAccessibleByIndex()) {
			$this->checkTableUsing($tableName);
			if(isset($options[DB::OPTION_BY_INDEX]) && $options[DB::OPTION_BY_INDEX] && !isset($this->tableInfo[$tableName])){
				$this->tableInfo[$tableName] = $this->getTableInfo($tableName);
			}
			if (isset($this->tableInfo[$tableName]) && $index = $this->indexCanBeUsed($tableName, $conditions, $options)) {
				return $this->getByIndex($tableName, $index, $conditions, $fetchStyle, $options, $colName);
			}
		}
        if($fetchStyle == DB::SELECT_COUNT){
            $select = 'COUNT(id) as cnt';
            $fetchStyle = DB::SELECT_COL;
            $colName = '`cnt`';
        }elseif($colName){
            $select = '`'.$colName.'`';
        }else{
            $select = '*';
        }
        $sql = 'SELECT '.$select.' FROM `'.$tableName.'` WHERE '.$this->genConditionsString($conditions).$this->genOptionsString($options);
		return $this->selectBySQL($sql, $fetchStyle, trim($colName, '`'));
	}

	/**
	 * @param array $values
	 * @return bool
	 */
	private function valuesCanBeUsedByHS(array $values){
		foreach($values as $field => $value) {
			if (!is_scalar($value)) {
				return false;
			}
		}
		return true;
	}
	/**
	 * @param string $tableName
	 * @param Mixed[string] $conditions
	 * @return null|string
	 */
	private function indexCanBeUsed($tableName, $conditions, $options) {
		if(isset($options[DB::OPTION_ORDER_BY]) || isset($options[DB::OPTION_FOR_UPDATE])){
			return null;
		}
		if(!isset($this->tableInfo[$tableName])){
			return null;
		}
		$fields = [];
		foreach($conditions as $field => $value) {
			if (!is_scalar($value)) {
				return null;
			}
			$fields[] = $field;
		}
		sort($fields);
		if(!isset($this->tableInfo[$tableName]['columns2index'][implode(',', $fields)])){
			return null;
		}
		return $this->tableInfo[$tableName]['columns2index'][implode(',', $fields)];
	}


	/**
	 * @param $tableName
	 * @return array|mixed
	 */
	private function getTableIndexes($tableName) {
		$rows = DB::get()->selectBySQL($q = 'SHOW INDEX FROM '.$this->base.'.'.$tableName.'');
		$indexes = [];
		foreach($rows as $row){
			if(!isset($indexes[$row['Key_name']])){
				$indexes[$row['Key_name']] = [];
			}
			$indexes[$row['Key_name']][] = $row['Column_name'];
		}
		return $indexes;
	}

	/**
	 * @param string $tableName
	 * @return array
	 */
	private function getTableInfo($tableName){
		$columns = DB::get()->selectBySQL('SHOW COLUMNS FROM `'.$tableName.'`');
		$columnDefaults = [];
		foreach($columns as $columnInfo){
			$columnDefaults[$columnInfo['Field']] = $columnInfo['Default'] ? $columnInfo['Default'] : NULL;
		}
		$indexes = $this->getTableIndexes($tableName);
		$columns2index = [];
		foreach($indexes as $indexName => $indexFields){
			sort($indexFields);
			$columns2index[implode(',', $indexFields)] = ['name'=>$indexName, 'order'=>$indexFields];
		}
		return [
			'columns2index'     => $columns2index,
			'columnDefaults'    => $columnDefaults,
		];
	}


	/** @var int[] */
	private $openedHSIndexes           = [];


	private function insertByIndex($tableName, $data, $onDuplicate){
		$values = [];
		$colNamesToInsert = '';
		foreach($this->tableInfo[$tableName]['columnDefaults'] as $name => $default){
			$colNamesToInsert .= $name.',';
			$values[] = isset($data[$name]) ? $data[$name] : ($default?$default:null);
		}
		$colNamesToInsert = trim($colNamesToInsert, ',');
		$indexId = 'insert_'.$this->base.'.'.$tableName.':'.$colNamesToInsert;
		if(!isset($this->openedHSIndexes[$indexId])) {
			$index = intval(end($this->openedHSIndexes)) + 1;
			if (!($this->handlerSocketWrite->openIndex($index, $this->base, $tableName, '', $colNamesToInsert))) {
				echo $this->handlerSocketWrite->getError(), PHP_EOL;
				die();
			}
			$this->openedHSIndexes[$indexId] = $index;
		}else{
			$index = $this->openedHSIndexes[$indexId];
		}
		$result = $this->handlerSocketWrite->executeInsert($index, $values);
		if($onDuplicate==DB::INSERT_DEFAULT){
			if($result === false){
				echo 'Cant insert into table "'.$tableName.'" values: ';
				print_r($data);
				echo $this->handlerSocketWrite->getError().PHP_EOL;
				die();
			}
		}
		return $result;
	}

	/**
	 * @param string $tableName
	 * @param Mixed[] $indexInfo
	 * @param Mixed[] $conditions
	 * @param string[string] $values
	 * @return int
	 */
	private function setByIndex($tableName, $indexInfo, $conditions, $values){
		$conditions_ = $conditions;
		$conditions = [];
		foreach($indexInfo['order'] as $field){
			$conditions[] = $conditions_[$field];
		}
		$colNamesToUpdate = implode(',', array_keys($values));
		$indexId = 'update_'.$this->base.'.'.$tableName.'('.$indexInfo['name'].'):'.$colNamesToUpdate;
		if(!isset($this->openedHSIndexes[$indexId])) {
			$index = intval(end($this->openedHSIndexes)) + 1;
			if (!($this->handlerSocketWrite->openIndex($index, $this->base, $tableName, $indexInfo['name'], $colNamesToUpdate))) {
				echo $this->handlerSocketWrite->getError(), PHP_EOL;
				die();
			}
			$this->openedHSIndexes[$indexId] = $index;
		}else{
			$index = $this->openedHSIndexes[$indexId];
		}
		return $this->handlerSocketWrite->executeSingle( $index, '=', $conditions, 1, 0, 'U?', array_values($values));
	}

	/**
	 * @param string $tableName
	 * @param Mixed $indexInfo
	 * @param Mixed[] $conditions
	 * @param int $fetchStyle
	 * @param array $options
	 * @param $colName
	 * @return null
	 */
	private function getByIndex($tableName, $indexInfo, $conditions, $fetchStyle = DB::SELECT_ARR, array $options = [], $colName){
		$conditions_ = $conditions;
		$conditions = [];
		foreach($indexInfo['order'] as $field){
			$conditions[] = $conditions_[$field];
		}
		if(!$colName){
			$colName = implode(',', array_keys($this->tableInfo[$tableName]['columnDefaults']));
		}
		$indexId = 'select_'.$this->base.'.'.$tableName.'('.$indexInfo['name'].'):'.$colName;
		if(!isset($this->openedHSIndexes[$indexId])) {
			$index = intval(end($this->openedHSIndexes)) + 1;
			if (!($this->handlerSocketRead->openIndex($index, $this->base, $tableName, $indexInfo['name'], $colName))) {
				echo $this->handlerSocketRead->getError(), PHP_EOL;
				die();
			}
			$this->openedHSIndexes[$indexId] = $index;
		}else{
			$index = $this->openedHSIndexes[$indexId];
		}
		$fields = explode(',', $colName);
		foreach($fields as $i => $field){
			$fields[$i] = trim($field, '` ');
		}
		switch($fetchStyle){
			case DB::SELECT_ARR_COL:
			case DB::SELECT_ARR:
				$limit = PHP_INT_MAX;
				$offset = 0;
				if(isset($options[DB::OPTION_LIMIT])){
					$limit = $options[DB::OPTION_LIMIT];
					if(isset($options[DB::OPTION_OFFSET])){
						$offset = $options[DB::OPTION_OFFSET];
					}
				}
				$rows = $this->handlerSocketRead->executeSingle($index, '=', $conditions, $limit, $offset);
				if(!$rows){
					return [];
				}
				$result = [];
				if($fetchStyle==DB::SELECT_ARR_COL){
					foreach ($rows as $row) {
						$result[] = $row[0];
					}
				}else {
					foreach ($rows as $row) {
						$r = [];
						foreach ($fields as $field) {
							$r[$field] = array_shift($row);
						}
						$result[] = $r;
					}
				}
				return $result;
				break;
			case DB::SELECT_COL:
			case DB::SELECT_ROW:
				$rows = $this->handlerSocketRead->executeSingle($index, '=', $conditions, 1, 0);
				if(!$rows){
					return null;
				}
				if($fetchStyle==DB::SELECT_COL){
					return $rows[0][0];
				}
				$row = $rows[0];
				$r = [];
//				var_dump($row);
//				var_dump($fields);
//				echo "!!!!!!!! ".$fields[0]." - ".$fields[1]." - ".$fields[2]." - ".$fields[3]." - ".$fields[4]." - ".$fields[5]." - ".$fields[6]." - ".$fields[7]." - ".$fields[8]."  !!!!!!!!\n";
//				$f = ['id', 'name', 'description', 'address', 'port', 'encoding', 'add_date', 'status', 'last_connect_date'];//array_slice($fields, 0);
//				var_dump($f);
				for($i=0, $l=count($fields); $i<$l; $i++){
					$field = $fields[$i];
					$r[$field] = array_shift($row);
//					echo "$i - $field - (".($fields[8]).", ".$f[8].")";
//					echo "\n";
				}
//				echo "$colName\n";
//				print_r($r);
				return $r;
				break;
		}
		return null;
	}

	/**
	 * @param string $query
	 * @param array[]scalar $params
	 * @param int $fetchStyle
	 * @return array|mixed
	 */
	function selectBySQL($query, $fetchStyle = DB::SELECT_ARR, $colName = null){
//		echo "$query\n";
		do{
			$stmt = $this->executeSql($query, false);
			$result = $stmt->get_result();
		}while(!$result && $this->link->errno==1213);
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
				if(!$result){
					echo "ERROR: ".$this->link->error." ($query) --- ".$this->link->errno."\n";
					exit;
				}
				$res = $row = $result->fetch_assoc();
//				echo $res['id']."\n";
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
		while( (!$stmt = @$this->link->prepare($query)) && $this->link->errno == self::MYSQL_GONE_AWAY){
			if(!$this->connect($this->host, $this->port, $this->user, $this->pass, $this->base)){
				sleep(1);
			}
		}
		if(!$stmt && defined('__DEBUG__') && __DEBUG__){
			echo 'CANT PREPARE SQL, ERROR: ('.$this->link->errno.') '.$this->link->error.' SQL: '.$query;
			exit;
		}
		$success = $stmt->execute();
		if(!$success && defined('__DEBUG__') && __DEBUG__){
			echo 'CANT EXECUTE SQL, ERROR: '.$this->link->error.' SQL: '.$query;
			Utils::backtrace();
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
//        $this->tableInfo[$tableName] = $this->getTableInfo($tableName);
		if( $this->dataAccessibleByIndex()){
			$this->checkTableUsing($tableName);
			if(($onDuplicate == DB::INSERT_DEFAULT || $onDuplicate == DB::INSERT_IGNORE) && isset($this->tableInfo[$tableName]) && $this->valuesCanBeUsedByHS($data)) {
				$result = $this->insertByIndex( $tableName, $data, $onDuplicate );
				return $result;
			}
		}
		$fields = '';
		$keys = array_keys($data);
		foreach($keys as $f){
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
		return $this->link->insert_id;
	}

	function getLastInsertId(){
		return $this->link->insert_id;
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
	function update($tableName, array $values, array $conditions, array $options=[]){
		if($this->dataAccessibleByIndex()) {
			$this->checkTableUsing($tableName);
			if(isset($options[DB::OPTION_BY_INDEX]) && $options[DB::OPTION_BY_INDEX] && !isset($this->tableInfo[$tableName])){
				$this->tableInfo[$tableName] = $this->getTableInfo($tableName);
			}
			if (isset($this->tableInfo[$tableName]) && ($index = $this->indexCanBeUsed($tableName, $conditions, $options)) && $this->valuesCanBeUsedByHS($values) ) {
				return $this->setByIndex($tableName, $index, $conditions, $values);
			}
		}

		$optionsString = '';
		if(isset($options[DB::OPTION_LIMIT])){
			$optionsString .= ' LIMIT ';
			if(isset($options[DB::OPTION_OFFSET])){
				$optionsString .= intval($options[DB::OPTION_OFFSET]).', '.intval($options[DB::OPTION_LIMIT]);
			}else{
				$optionsString .= intval($options[DB::OPTION_LIMIT]);
			}
		}

		$sql = 'UPDATE `'.$tableName.'` SET '.$this->genUpdateValuesString($values).' WHERE '.$this->genConditionsString($conditions).$optionsString;
		$stmt = $this->executeSql($sql, false);
		$affected_rows = $stmt->affected_rows;
		$stmt->close();
		return $affected_rows;
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

	private function checkTableUsing($tableName) {
		if(!isset($this->tableUsing[$tableName])){
			$this->tableUsing[$tableName] = 1;
		}
		$this->tableUsing[$tableName]++;
		if($this->tableUsing[$tableName] > self::TABLE_USING_BEFORE_AUTO_CACHE_INFO && !isset($this->tableInfo[$tableName])){
			$this->tableInfo[$tableName] = $this->getTableInfo($tableName);
		}
	}

}