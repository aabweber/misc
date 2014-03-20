<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 20.03.14
 * Time: 12:36
 */

namespace misc\DB;


class DBFunction {
	private $sql = '';

	function __construct($sql) {
		$this->sql = $sql;
	}

	function __toString() {
		return $this->sql;
	}


	/**
	 * @param int|string $interval
	 * @param string $operation
	 * @param string $what
	 * @return string
	 */
	static function now($operation='+', $interval = 0, $what = 'SECOND'){
		$intervalString = '';
		if($interval){
			if(is_string($interval)){
				$intervalString .= $operation.'INTERVAL `'.$interval.'` '.$what;
			}elseif(is_numeric($interval)){
				$intervalString .= $operation.'INTERVAL '.$interval.' '.$what;
			}else{
				error_log('DBFunction: unknown interval type');
				exit;
			}
		}
		return new static('NOW()'.$intervalString);
	}

	/**
	 * @param string $password
	 * @return string
	 */
	static function password($password){
		return new static('PASSWORD("'.addslashes($password).'")');
	}
} 