<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 22.01.14
 * Time: 15:00
 */

define('BASE_DIR', dirname(realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['PHP_SELF'])));

spl_autoload_register(function ($class_name) {
	$class_name = str_replace('\\', '/', $class_name);
	$fname = BASE_DIR.'/'.$class_name.'.php';
    if(!is_file($fname)){
	    echo 'FILENAME: '.$fname;
        print_r(debug_backtrace());
        exit;
    }
    include $fname;
});

require_once __DIR__.'/ReturnData.php';
require_once __DIR__.'/Utils.php';

if(isset($_SERVER['HTTP_X_REAL_IP'])){
	$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_IP'];
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


