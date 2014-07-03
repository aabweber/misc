#!/usr/bin/php
<?php
use misc\DB\DB;
use misc\vendor\Inflector;

/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 03.07.14
 * Time: 10:51
 */
define('BASE_DIR', realpath(__DIR__.'/../..'));
include BASE_DIR.'/misc/inc.php';

class ClassesGenerator {

	const FOLDER = 'generatedClasses';

	public function run() {
		global $argv, $argc;
		$usage = 'Usage: ./'.pathinfo($argv[0], PATHINFO_BASENAME)." db_host db_name user password\n";
		if($argc<5){
			echo $usage;
			exit;
		}
		if(!DB::get($argv[1], 3306, $argv[3], $argv[4], $argv[2])){
			echo 'Can not connect to database!';
			echo $usage;
			exit;
		}
		@mkdir(self::FOLDER);
		$this->generate();
	}

	private function generate() {
		$objects = $this->getTables();
		foreach($objects as $name => $object){
			if(strpos($name, '_')===false){
				$className = ucfirst(strtolower(Inflector::singularize($name)));
				$class = $this->createObjectInterface($className, $object);
				file_put_contents(self::FOLDER.'/'.$className.'.php', $class);
			}
		}
	}

	private function getTables() {
		$tables = [];
		$table_rows = DB::get()->selectBySQL('SHOW TABLES');
		foreach($table_rows as $_ => $table_array){
			$table_name = array_shift($table_array);
			$columns = DB::get()->selectBySQL('SHOW COLUMNS FROM `'.$table_name.'`');
			$tables[$table_name] = [];
			foreach($columns as $column_row){
				$field_name = $column_row['Field'];
				$tables[$table_name][$field_name] = $column_row;
			}
		}
		return $tables;
	}

	private function createObjectInterface($name, $object) {
		$class = "<?php\n\nuse misc\\DBDynamicData;\n\nclass $name{\n\tuse DBDynamicData{\n\t}\n\n";
		$class .= $this->createObjectConstants($object)."\n\n";
		$class .= $this->createObjectVariables($object)."\n\n";
		$class .= $this->createObjectGSetters($object)."\n\n";
		$class .= "}\n";
		return $class;
	}

	private function parseENUM($enum){
		if(preg_match('/enum\((.+?)\)/si', $enum, $ms)){
			$enums = $ms[1];
			preg_match_all('/\'([^\']+)\'/si', $enums, $ms);
			return $ms[1];
		}else{
			return null;
		}
	}

	private function createObjectConstants($object) {
		$constString = '';
		foreach($object as $fieldName => $fieldInfo){
			if($enums = $this->parseENUM($fieldInfo['Type'])){
				foreach($enums as $value){
					$value = strtoupper($value);
					$const = "\t".'const '.strtoupper($fieldName).'_'.$value." =\t\t\t\t'".$value."';\n";
					$constString .= $const;
				}
			}
		}
		return $constString;
	}

	private function createObjectVariables($object) {
		$variableString = '';
		foreach($object as $fieldName => $fieldInfo){
			$comment = "\t/** @var ". $this->chooseDocType($fieldInfo['Type']) .' $'.$fieldName." */\n";
			$var = "\tprivate \$".$fieldName.";\n";
			$variableString .= $comment.$var;
		}
		return $variableString;
	}

	/**
	 * @param $fieldInfo
	 * @return mixed
	 */
	private function chooseDocType($type) {
		if($type=='text' || preg_match('/varchar/si', $type)){
			return 'string';
		}if($enums = $this->parseENUM($type)){
			return 'string("'.implode('", "', $enums).'")';
		}elseif(strpos($type, 'int')!==false){
			return 'int';
		}
		return $type;
	}

	private function createObjectGSetters($object) {
		$gsetters = '';
		foreach($object as $fieldName => $fieldInfo){
			$parts = explode('_', $fieldName);
			$varName = '';
			foreach($parts as $part){
				$varName .= ucfirst(strtolower($part));
			}
			// Getter
			$comment = "\t/**\n";
			$comment.= "\t * @return ".$this->chooseDocType($fieldInfo['Type'])." \$".lcfirst($varName)."\n";
			$comment.= "\t */\n";
			$gsetters .= $comment;
			$gsetters .= "\tfunction get$varName(){\n";
			$gsetters .= "\t\treturn \$this->$fieldName;\n";
			$gsetters .= "\t}\n\n";

			if($fieldName!='id'){
				// Setter
				$comment = "\t/**\n";
				$comment.= "\t * @param ".$this->chooseDocType($fieldInfo['Type'])." \$".lcfirst($varName)."\n";
				$comment.= "\t * @param bool \$saveInDB\n";
				$comment.= "\t */\n";
				$gsetters .= $comment;
				$gsetters .= "\tfunction set$varName(\$".lcfirst($varName).", \$saveInDB = true){\n";
				$gsetters .= "\t\t\$this->$fieldName = \$".lcfirst($varName).";\n";
				$gsetters .= "\t\tif(\$saveInDB) \$this->saveInDB();\n";
				$gsetters .= "\t}\n\n";
			}
		}
		return $gsetters;
	}

}

$cg = new ClassesGenerator();
$cg->run();


