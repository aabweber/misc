<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 22.01.14
 * Time: 15:00
 */

define('__DEBUG__', true);

//if(isset($_SERVER['PHP_SELF']) && $_SERVER['PHP_SELF']){
//	$path = realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['PHP_SELF']);
//	echo $_SERVER['PHP_SELF'];
//	$base_dir = $path;
//	echo 1;
//}else{
//	$path = realpath($_SERVER['DOCUMENT_ROOT']);
//	$base_dir = dirname($path ? $path : $_SERVER['SCRIPT_FILENAME']);
//	echo 2;
//}
//
//echo $path;exit;
define('BASE_DIR', $_SERVER['DOCUMENT_ROOT']);

spl_autoload_register(function ($class_name) {
	$class_name = str_replace('\\', '/', $class_name);
	if(method_exists('Phar', 'running') && Phar::running()){
		$fname = Phar::running().'/'.$class_name.'.php';
	}else{
		$file = isset($GLOBALS['path']) ? $GLOBALS['path'] : $_SERVER['SCRIPT_FILENAME'];
		if(!method_exists('Phar', 'running') && strpos($file, '.phar')!==FALSE){
			$fname = 'phar://'.$file.'/'.$class_name.'.php';
		}else{
			$fname = BASE_DIR.'/'.$class_name.'.php';
		}
	}
    if(!is_file($fname)){
	    echo "Can't include file $fname\n";
        print_r(debug_backtrace());
        exit;
    }
    include $fname;
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
	return (new \DateTime())->format(\DateTime::W3C);
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


