<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 03/11/14
 * Time: 11:50
 */

namespace misc\DB;


use Exception;
use PDO;
use PDOStatement;

class CassandraEngine implements DBEngineInterface{
    /** @var PDO*/
    private $link;

    /** @var PDOStatement[] */
    private $statements = [];

    function connect($host, $port, $user, $pass, $base){
        $this->link = new PDO('cassandra:host='.$host.';port='.$port.',host='.$host.',port='.$port);
        $this->link->exec('USE '.$base);
        return true;
    }

    function disconnect(){
    }

    function begin(){
        throw new Exception('Cassandra does not supports transactions(begin method)');
    }

    function commit(){
        throw new Exception('Cassandra does not supports transactions(commit method)');
    }

    function rollback(){
        throw new Exception('Cassandra does not supports transactions(rollback method)');
    }

    function disableAutocommit(){
        throw new Exception('Cassandra does not supports transactions(disableAutocommit method)');
    }

    function enableAutocommit(){
        throw new Exception('Cassandra does not supports transactions(enableAutocommit method)');
    }

    /**
     * @param string $tableName
     * @param array [string]scalar $conditions
     * @param int $fetchStyle
     * @param array $options
     * @param string $colName
     * @return array|mixed
     */
    function select($tableName, array $conditions, $fetchStyle = DB::SELECT_ARR, array $options = [], $colName = '*'){
        if($fetchStyle == DB::SELECT_COUNT){
            $select = 'COUNT(id) as cnt';
            $fetchStyle = DB::SELECT_COL;
            $colName = 'cnt';
        }elseif($colName){
            $select = $colName;
        }
        $sql = 'SELECT '.$select.' FROM '.$tableName;
        $conditionsStr = $this->genConditionsString($conditions);
        if($conditionsStr){
            $sql .= ' WHERE '.$conditionsStr;
        }
        $sql .= $this->genOptionsString($options);
        $statement = $this->executeSql($sql, $conditions, false);
        $result = $this->prepareSelectedValue($statement, $fetchStyle, $colName);
        $statement->closeCursor();
        return $result;
    }

    /**
     *
     * @param string $query
     * @param array[] scalar $params
     * @param int $fetchStyle
     * @return array|mixed
     */
    function selectBySQL($query, $fetchStyle = DB::SELECT_ARR, $colName = null){
        $statement = $this->executeSql($query, [], false);
        $result = $this->prepareSelectedValue($statement, $fetchStyle, $colName);
        $statement->closeCursor();
        return $result;
    }

    /**
     * @param string $sql
     * @param array $params
     * @param bool $close
     * @return \PDOStatement
     */
    function executeSql($sql, $params = [], $close = true){
        $md5 = md5($sql);
        if(!isset($this->statements[$md5])){
            $this->statements[$md5] = $this->link->prepare($sql);
        }
        $statement = $this->statements[$md5];
        foreach($params as $var => $val){
            $type = $this->chooseVariableType($var, $val);
            if(is_array($val)){
                if(!empty($val)){
                    $val = '{\''.implode('\',\'', $val).'\'}';
                }else{
                    $val = '{}';
                }
            }
            $statement->bindValue(':'.$var, $val, $type);
        }
        if(!$statement->execute()){
            var_dump($statement->errorInfo());
            exit;
        }
        if($close){
            $statement->closeCursor();
        }
        return $statement;
    }

    /**
     * @param string $tableName
     * @param array[] scalar $data
     * @param int $onDuplicate
     * @return int
     */
    function insert($tableName, array $data, $onDuplicate = DB::INSERT_DEFAULT){
        $this->cutNulls($data);
        $vars = array_keys($data);
        $fields = '';
        for($i=0, $l=count($vars); $i<$l; $i++){
            $fields .= '"'.$vars[$i].'"'.($i==$l-1?'':',');
        }
        $sql = 'INSERT INTO '.$tableName.' ('. $fields .') VALUES(:'.implode(',:', array_keys($data)).')';
        $this->executeSql($sql, $data);
        return isset($data['id']) ? $data['id'] : null;
    }

    /**
     * @param string $tableName
     * @param array[] scalar $conditions
     * @return bool|int
     */
    function delete($tableName, array $conditions){
        $sql = 'DELETE FROM '.$tableName;
        $conditionsStr = $this->genConditionsString($conditions);
        if($conditionsStr){
            $sql .= ' WHERE '.$conditionsStr;
        }
        $this->executeSql($sql, $conditions);
    }

    /**
     * @param string $tableName
     * @param array[] scalar $values
     * @param array[] scalar $conditions
     * @param array $options
     * @return bool|int
     */
    function update($tableName, array $values, array $conditions, array $options = []){
        $sql = 'UPDATE '.$tableName.' SET ';
        $valuesString = '';
        foreach($values as $var => $val){
            $valuesString .= $var.'=:v_'.$var.',';
            unset($values[$var]);
            $values['v_'.$var] = $val;
        }
        $sql .= trim($valuesString, ',');
        $conditionsStr = $this->genConditionsString($conditions);
        if($conditionsStr){
            $sql .= ' WHERE '.$conditionsStr;
        }
        $this->executeSql($sql, array_merge($conditions, $values));
    }

    /**
     * @return int
     */
    function getLastInsertId(){
        throw new Exception('Cassandra does not supports this(getLastInsertId method)');
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

            $sql .= '"'.$var.'" '.$operator.' :'.$var.'';

            if($i++ != $cnt-1) $sql .= ' AND ';
        }
        return $sql;
    }

    private function genOptionsString($options) {
        $optionsString = '';
        if(isset($options[DB::OPTION_ORDER_BY])){
            $optionsString .= ' ORDER BY '.$options[DB::OPTION_ORDER_BY];
        }
        if(isset($options[DB::OPTION_LIMIT])){
            $optionsString .= ' LIMIT '.intval($options[DB::OPTION_LIMIT]);
        }
        return $optionsString;
    }

    private function prepareSelectedValue(PDOStatement $result, $fetchStyle, $colName){
        $res = null;
        switch($fetchStyle){
            case DB::SELECT_ARR:
                $res = $result->fetchAll();
                break;
            case DB::SELECT_ARR_COL:
                $res = [];
                $fetched = $result->fetchAll();
                if($colName=='*'){
                    foreach($fetched as $row){
                        $res[] = $row[0];
                    }
                }else{
                    foreach($fetched as $row){
                        $res[] = $row[$colName];
                    }
                }
                break;
            case DB::SELECT_ROW:
                $res = $row = $result->fetch();
                break;
            case DB::SELECT_COL:
                if($colName=='*'){
                    $res_ = $result->fetch();
                    if($res_){
                        $res = $res_[0];
                    }
                }else{
                    $res_ = $result->fetch();
                    if($res_){
                        $res = $res_[$colName];
                    }
                }
                break;
            default:
                error_log('DB: Unknown fetch style ('.$fetchStyle.')');
        }
        return $res;
    }

    private function chooseVariableType($var, $val){
        if(is_array($val)){
            return PDO::CASSANDRA_SET;
        }elseif($var=='id'){
            return PDO::CASSANDRA_UUID;
        }elseif(is_int($val)){
            return PDO::PARAM_INT;
        }elseif(is_double($val) || is_float($val)){
            return PDO::CASSANDRA_FLOAT;
        }
        return PDO::CASSANDRA_STR;
    }

    private function cutNulls(&$data){
        foreach($data as $var => $val){
            if(NULL === $val){
                unset($data[$var]);
            }
        }
    }
}











