<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 27.01.14
 * Time: 15:33
 */

namespace misc{

use misc\vendor\Mobile_Detect;

class Utils {
    /** @var Mobile_Detect */
    static private $mobileDetect;

    /**
     * @return bool
     */
    static function isComputer(){
        if(!self::$mobileDetect){
            self::$mobileDetect = new Mobile_Detect();
        }
        return !self::$mobileDetect->isMobile() && !self::$mobileDetect->isTablet();
    }

    static function camelCase($name){
        $ccName = '';
        $words = explode('_', $name);
        foreach($words as $word){
            $ccName .= ucfirst(strtolower($word));
        }
        return $ccName;
    }

//    static function sendEmail($to, $title, $template, $variables = [], $from = null){
//        $headers  = 'MIME-Version: 1.0' . "\r\n";
//        $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
//        if($from){
//            $headers .= 'From: '.$from . "\r\n";
//        }
//        $variables['title'] = $title;
//        $message = Template::apply($template, $variables);
//        mail($to, $title, $message, $headers);
//    }

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

	public static function genRandomString($length = 10) {
		$original_string = array_merge(range(0,9), range('a','z'), range('A', 'Z'));
		$original_string = implode("", $original_string);
		return substr(str_shuffle($original_string), 0, $length);
	}

	public static function shiftString(&$string, $len){
//		$string_part = '';
//		for($i=0; $i<$len; $i++){
//			$string_part .= '';
//		}
		$string_part = substr($string, 0, $len);
		$string = substr($string, isset($string_part[$len-1]) ? $len : strlen($string_part));
		return $string_part;
	}

	public static function getDirFirstFile($dir){
		$dir = rtrim($dir, '/');
		if (is_dir($dir)) {
			if ($dh = opendir($dir)) {
				while (($file = readdir($dh)) !== false) {
					$filename = $dir.'/'.$file;
					if(is_file($filename)){
						return $filename;
					}
				}
				closedir($dh);
			}
		}
		return null;
	}

	public static function decideType($var){
		if(is_numeric($var) && intval($var)==$var){
			$var = intval($var);
		}elseif(in_array(strtolower($var), ['true', 'false'])){
			$var = $var=='true';
		}
		return $var;
	}

	public static function backtrace(){
		$array = debug_backtrace();
		foreach($array as &$row){
			unset($row['object']);
//			echo @'>>> '.sprintf('%04d', $row['line']).': '.$row['class']." -> ".$row['function']."\n";
		}
		print_r($array);
		exit;
	}

	public static function searchInArray($array, callable $func){
		return current(array_filter($array, $func));
	}
}
}

namespace {
	class Utils extends \misc\Utils{

	}
}