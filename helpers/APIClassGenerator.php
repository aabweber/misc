#!/usr/bin/php
<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 07.03.14
 * Time: 16:12
 */

$usage = 'Usage '.pathinfo($argv[0], PATHINFO_FILENAME)." filename\n";
if(count($argv)<2){
	echo $usage;
	echo "Or enter code below:\n";
	$c = '';
	while (!feof(STDIN)){
		$line = fgets(STDIN);
		if(!rtrim($line,"\n\r")) break;
		$c .= $line;
	}
	eval('$actionList = ['.$c.'];');
	generatePHPCode($actionList);
	exit;
}

function getFilePath($file){
	$pwd = trim(`pwd`);
	$parts = explode('/', $pwd.'/'.$file);
	$parts2concat = [];
	foreach($parts as $part){
		if($part=='..'){
			array_pop($parts2concat);
		}elseif($part!='.'){
			$parts2concat[] = $part;
		}
	}
	return implode('/', $parts2concat);
}

$filename = getFilePath($argv[1]);
//echo $filename;exit;
if(!is_file($filename)){
	echo "Can't find file \"$filename\"\n";
	echo $usage;
	exit;
}

$classname = preg_replace('/^([^.]+).*/si', '\\1', pathinfo($filename, PATHINFO_FILENAME));

$c = file_get_contents($filename);
if(!preg_match('/class\s+'.$classname.'.+?\$availableActions.+?(\[.+?);/si', $c, $ms)){
	echo "Cant parse file\n";
	echo $usage;
}

eval('$actionList = '.$ms[1].';');
generatePHPCode($actionList);


function generatePHPCode($actionList){
	foreach($actionList as $object => $actions){
		echo "\n\n// ******************************* ".strtoupper($object)." SECTION *******************************\n";
		foreach($actions as $action => $args){
			$args_str = '';
			$phpdoc = "\t/**\n";
			foreach($args as $arg){
				$args_str .= '$'.trim($arg, '?').', ';
				$phpdoc .= "\t * @var \$$arg\n";
			}
			$phpdoc .= "\t * @return ReturnData\n\t */";
			echo $phpdoc."\n";
			echo "\tfunction cmd".ucfirst($action).ucfirst($object)."(".trim($args_str, ' ,')."){\n\t\treturn RetOK([]);\n\t}\n\n";
		}
		echo "\n// ******************************* /".strtoupper($object)." SECTION *******************************\n\n";
	}
}

