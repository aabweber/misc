<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 22.01.14
 * Time: 15:00
 */


spl_autoload_register(function ($class_name) {
	$class_name = str_replace('\\', '/', $class_name);
	include dirname(realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['PHP_SELF'])).'/'.$class_name.'.php';
});

require_once __DIR__.'/ReturnData.php';


