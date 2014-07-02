<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 22.01.14
 * Time: 15:00
 */


function is_hhvm() {
	return defined('HHVM_VERSION');
}

if(is_hhvm()){
	define('BASE_DIR', $_SERVER['DOCUMENT_ROOT']);
}else{
	if(isset($_SERVER['PWD'])){
		define('BASE_DIR', dirname($_SERVER['PWD'].'/'.$_SERVER['SCRIPT_FILENAME']));
	}else{
		define('BASE_DIR', dirname($_SERVER['SCRIPT_FILENAME']));
	}
}

spl_autoload_register(function ($class_name) {
	$class_name = str_replace('\\', '/', $class_name);
	if(is_hhvm()){
		// web server - HHVM
		$is_phar = strpos(__FILE__, 'phar://')!==FALSE;
		if($is_phar){
			$fname = 'phar://'.$_SERVER['SCRIPT_FILENAME'].'/'.$class_name.'.php';
		}else{
			$fname = BASE_DIR.'/'.$class_name.'.php';
		}
	}else{
		// command line - PHP-CLI
		if(Phar::running()){
			$fname = Phar::running().'/'.$class_name.'.php';
		}else{
			$fname = BASE_DIR.'/'.$class_name.'.php';
		}
	}
    if(!is_file($fname)){
//	    echo "Can't include file $fname\n";
//        print_r(debug_backtrace());
//        exit;
	    return null;
    }
    include $fname;
});

set_error_handler(function($errno, $errstr, $errfile, $errline, $errcontext){
        if($errno == 2048 && strstr($errstr, 'Declaration')!==false && strstr($errstr, 'should be compatible with')!==false){
                return true;
        }
        return false;
//      print_r($errno);exit;
//              print_r(debug_backtrace());exit;
});
//set_error_handler(function($errno, $errstr, $errfile, $errline, $errcontext){
//		print_r($errcontext);
//		print_r(debug_backtrace());exit;
//});

require_once __DIR__.'/ReturnData.php';
require_once __DIR__.'/Utils.php';

if(isset($_SERVER['HTTP_X_REAL_IP'])){
	$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_IP'];
}

function W3CNow(){
	$dt = new \DateTime('now', new DateTimeZone('Europe/Moscow'));
	return $dt->format(\DateTime::W3C);
}


function fix_REQUEST_types(&$r){
	foreach($r as &$v){
		if(is_numeric($v) && intval($v)==$v){
			$v = intval($v);
		}elseif(is_array($v)){
			fix_REQUEST_types($v);
		}elseif(in_array(strtolower($v), ['true', 'false']) && boolval($v=='true')==$v){
			$v = boolval($v);
		}
	}
}
fix_REQUEST_types($_REQUEST);

if(!function_exists('openssl_random_pseudo_bytes')){
	function openssl_random_pseudo_bytes($len){
		$res = '';
		for($i=0;$i<$len;$i++){
			$res .= chr(rand(0, 255));
		}
		return $res;
	}
}


