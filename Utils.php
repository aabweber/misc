<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 27.01.14
 * Time: 15:33
 */



class Utils {

	static function getArray($arr){
		return $arr;
	}

	static function isValueSet($array, $var){
		return isset($array[$var]) && isset($array[$var][0]) && isset($array[$var][0]['value']);
	}

	static function getValue($array, $var){
		if(self::isValueSet($array, $var)){
			return $array[$var][0]['value'];
		}
		return null;
	}

	static function checkEmail($email){
		if(preg_match('/^[\.\-_A-Za-z0-9]+?@[\.\-A-Za-z0-9]+?\.[A-Za-z0-9]{2,6}$/', $email)){
			return true;
		}else{
			return false;
		}
	}

	public static function checkPassword($password) {
		if(strlen($password)<5){
			return false;
		}
		return true;
	}

	static function hardExit(){
		posix_kill(getmypid(), SIGKILL);
		sleep(1);
		echo "i must don't be here ever\n";
		exit;
	}

	public static function is_in_segment($position, $segment, $left = true, $right=true){
		return (
				$left ?
				$position>=$segment[0]
				:
				$position>$segment[0]
				) && (
				$right ?
				$position<=$segment[1]
				:
				$position<$segment[1]
		);
	}

} 