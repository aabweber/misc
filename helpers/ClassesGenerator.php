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

	private $database;
	private $currentClassVariables = [];

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
		$this->database = $argv[2];
		@mkdir(self::FOLDER);
		$this->generate();
	}

	private function generate() {
		$objects = $this->getTables();
		foreach($objects as $name => $object){
			if(strpos($name, '_')===false){
				$className = ucfirst(strtolower(Inflector::singularize($name)));
				$class = $this->createObjectInterface($name, $className, $object);
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

	private function createObjectInterface($table, $name, $object) {
		$class = "<?php\n\nuse misc\\DBDynamicData;\n\nclass $name{\n\tuse DBDynamicData{\n\t}\n\n";
		$class .= $this->createObjectConstants($object)."\n\n";

		$this->currentClassVariables = $this->getObjectVariables($object);
		$relationsString = $this->createObjectRelations($table)."\n\n";

		$class .= $this->createObjectVariables()."\n\n";
		$class .= $this->createObjectGSetters($object)."\n\n";
		$class .= $relationsString;
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

	private function getObjectVariables($object) {
		$vars = [];
		foreach($object as $fieldName => $fieldInfo){
			$vars[$fieldName] = $this->chooseDocType($fieldInfo['Type']);
		}
		return $vars;
	}

	private function createObjectVariables(){
		$variableString = '';
		foreach ($this->currentClassVariables as $var => $type) {
			$comment = "\t/** @var " . $type . ' $' . $var . " */\n";
			$var = "\tprivate \$" . $var . ";\n";
			$variableString .= $comment.$var;
		}
		return $variableString;
	}

	/**
	 * @param $fieldInfo
	 * @return mixed
	 */
	private function chooseDocType($type) {
		if($type=='longtext' || $type=='text' || preg_match('/varchar/si', $type)){
			return 'string';
		}if($enums = $this->parseENUM($type)){
			return 'string("'.implode('", "', $enums).'")';
		}elseif(strpos($type, 'int')!==false){
			return 'int';
		}elseif($type=='datetime'){
			return 'string';
		}
		return $type;
	}

	private function createFunctionString($name, $arguments, $body, $return=null, $description=''){
		$function = '';
		$comment = "\t/**\n";
		if($description){
			$comment.= "\t * $description \n";
		}
		foreach($arguments as $var => $type){
			if(strpos($var, '=')!==false){
				$var = substr($var, 0, strpos($var, '='));
			}
			$comment.= "\t * @param $type \$$var\n";
		}
		if($return!=null){
			$comment.= "\t * @return $return\n";
		}
		$comment.= "\t */\n";
		$function .= $comment;
		$args = '';
		foreach(array_keys($arguments) as $argName){
			$args .= '$'.$argName.', ';
		}
		$function .= "\tfunction $name(".trim($args, ', ')."){\n";
		foreach ($body as $line) {
			$function .= "\t\t$line\n";
		}
		$function .= "\t}\n\n";
		return $function;
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
			$gsetters .= $this->createFunctionString('get'.$varName, [], ['return $this->'.$fieldName.';'], $this->chooseDocType($fieldInfo['Type']));

			if($fieldName!='id'){
				// Setter
				$gsetters .= $this->createFunctionString('set'.$varName, [
					lcfirst($varName) => $this->chooseDocType($fieldInfo['Type']),
					'saveInDB=true' => 'bool'
				], [
					'$this->'.$fieldName.' = $'.lcfirst($varName).';',
					'if($saveInDB) $this->saveInDB();'
				]);
			}
		}
		return $gsetters;
	}

	private function createObjectRelations($table) {
		$relations = '';
		$info = DB::get()->selectBySQL($q='
		SELECT
			column_name, referenced_table_name
		FROM information_schema.key_column_usage
		WHERE
			table_schema = "'.$this->database.'" AND
			table_name = "'.$table.'" AND
			referenced_column_name = "id"
		');
		foreach($info as $relation){
			$objectName = $this->getObjectName($relation['referenced_table_name']);
			$func = 'get'. $objectName;
			$this->currentClassVariables[strtolower($objectName)] = $objectName;
			$relations .= $this->createFunctionString($func, ['renew=false'=>'bool'], [
				'if(!$this->'.strtolower($objectName).' || $renew){',
				'   $this->'.strtolower($objectName).' = '.$objectName.'::get($this->'.$relation['column_name'].');',
				'}',
				'return $this->'.strtolower($objectName).';'
			], $objectName, 'Get '.strtolower($objectName).' for current '.strtolower(Inflector::singularize($table)));
		}
		$info = DB::get()->selectBySQL($q='
		SELECT
			table_name, column_name
		FROM information_schema.key_column_usage
		WHERE
			referenced_table_schema = "'.$this->database.'" AND
			referenced_table_name = "'.$table.'" AND
			referenced_column_name = "id"
		');
		foreach($info as $relation){
			$objectName = $this->getObjectName($relation['table_name']);
			$func = 'get'.$this->getObjectsName($relation['table_name']).'List';
			$relations .= $this->createFunctionString($func, [], [
				'$list = '.$objectName.'::getList([\''.$relation['column_name'].'\' => $this->id]);',
				'return $list;'
			], $objectName.'[]', 'Get list of '.strtolower(Inflector::pluralize($objectName)).' for current '.strtolower(Inflector::singularize($table)));
		}
		return $relations;
	}

	private function getObjectsName($tableName){
		return ucfirst(strtolower(Inflector::pluralize($tableName)));
	}

	private function getObjectName($tableName){
		return ucfirst(strtolower(Inflector::singularize($tableName)));
	}
}

$cg = new ClassesGenerator();
$cg->run();


