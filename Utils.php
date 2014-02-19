<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 27.01.14
 * Time: 15:33
 */

namespace misc;


class Utils {
	static function isValueSet($array, $var){
		return isset($array[$var]) && isset($array[$var][0]) && isset($array[$var][0]['value']);
	}

	static function getValue($array, $var){
		if(self::isValueSet($array, $var)){
			return $array[$var][0]['value'];
		}
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