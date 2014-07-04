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
		$class = "use misc\\DBDynamicData;\nuse misc\\ReturnData;\n\nclass $name{\n\tuse DBDynamicData{\n\t\tDBDynamicData::create as d_create;\n\t}\n\n\tstatic \$cached                          = true;\n\n";
		$class .= $this->createObjectConstants($object)."\n\n";

		$this->currentClassVariables = $this->getObjectVariables($object);
		$relationsString = $this->createObjectRelations($table, $object)."\n\n";

		$class .= $this->createObjectVariables()."\n\n";
		$class .= $this->createObjectGSetters($object)."\n\n";
		$class .= $relationsString;
		$class .= $this->createObjectCreate($name, $object);
//		$class .= $this->createInitFields($name);
		$class .= "}\n";
		return "<?php\n\n".$this->genFieldsClass($name).$class;//."\n".$this->getObjectName($table)."::initFields();\n";
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
					$const = "\t".'const '.sprintf('%1$- 34s', strtoupper($fieldName).'_'.$value)."= '".$value."';\n";
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

	private function createFunctionString($name, $arguments, $body, $return=null, $description='', $function_prefix=''){
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
		$function .= "\t".($function_prefix?$function_prefix.' ':'')."function $name(".trim($args, ', ')."){\n";
		foreach ($body as $line) {
			$function .= "\t\t$line\n";
		}
		$function .= "\t}\n\n";
		return $function;
	}


	private function createObjectGSetters($object) {
		$gsetters = '';
		foreach($object as $fieldName => $fieldInfo){
			$varName = $this->getVarName($fieldName);
			// Getter
			$gsetters .= $this->createFunctionString('get'.ucfirst($varName), [], ['return $this->'.$fieldName.';'], $this->chooseDocType($fieldInfo['Type']));

			if($fieldName!='id'){
				// Setter
				$gsetters .= $this->createFunctionString('set'.ucfirst($varName), [
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

	private function createObjectRelations($table, $object) {
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
				'$list = '.$objectName.'::getList(['.$objectName.'Fields::$'.$relation['column_name'].' => $this->id]);',
				'return $list;'
			], $objectName.'[]', 'Get list of '.strtolower(Inflector::pluralize($objectName)).' for current '.strtolower(Inflector::singularize($table)));

			$func = 'get'.$objectName;
			$relations .= $this->createFunctionString($func, ['id'=>'int', 'returnError=false'=>'bool'],[
				'$'.strtolower($objectName).' = $err = '.$objectName.'::get($id, $returnError);',
				'if($returnError && $err instanceof ReturnData) return $err;',
				'if($'.strtolower($objectName).'->get'.ucfirst($this->getVarName($relation['column_name'])).'()!=$this->getId()) {',
				'   if($returnError){',
				'       return RetErrorWithMessage(\''.strtoupper($objectName).'_NOT_BELONG_TO_'.strtoupper(Inflector::singularize($table)).'\', \'The '.strtolower($objectName).' with id="\'.$id.\'" does not belong to '.strtolower(Inflector::singularize($table)).' with id="\'.$this->getId().\'"\');',
				'   }else{',
				'       return null;',
				'   }',
				'}',
				'return $'.strtolower($objectName).';',
			], $objectName, 'Get '.strtolower($objectName).' related to the '.strtolower(Inflector::singularize($table)));

		}

		return $relations;
	}

	private function getObjectsName($tableName){
		return ucfirst(strtolower(Inflector::pluralize($tableName)));
	}

	private function getObjectName($tableName){
		return ucfirst(strtolower(Inflector::singularize($tableName)));
	}

	private function genFieldsClass($name) {
		$class = 'class '.$this->getObjectName($name)."Fields{\n";
		$class.= "\t\n";
		foreach($this->currentClassVariables as $var => $_){
			$class .= "\tpublic static \$".sprintf('%1$- 25s', $var)."= '$var';\n";
		}
		$class.= "}\n\n";
		return $class;
	}

	private function createInitFields($name) {
		$code = '';
//		$code .= "\t/** @var {$name}Fields \$fields */\n";
//		$code .= "\tpublic static \$fields;\n";
//		$code .= "\tpublic static function initFields() {self::\$fields = new {$name}Fields();}\n";
		return $code;
	}

	private function createObjectCreate($name, $object) {
//		print_r($object);
		$code = ['$dataArray = [];'];
		$args_required = [];
		$args_option_default = [];
		$args_option_null = [];
		foreach($object as $field => $values){
			if($field=='id') continue;
			if($values['Null']=='NO'){
				if($values['Default']!==NULL){
					$args_option_default[] = ['name'=>$field, 'value'=>$values['Default'], 'type'=>$values['Type']];
				}else{
					$args_required[] = ['name'=>$field, 'type'=>$values['Type']];
				}
			}else{
				if($values['Default']!==NULL){
					$args_option_default[] = ['name'=>$field, 'value'=>$values['Default'], 'type'=>$values['Type']];
				}else{
					$args_option_null[] = ['name'=>$field, 'type'=>$values['Type']];
				}
			}
		}
		$args = array_merge($args_required, $args_option_default, $args_option_null);
		foreach($args as $arg){
			$code[] = '$dataArray[\''.$arg['name'].'\'] = $'.$this->getVarName($arg['name']).';';
		}
		$args = [];
		foreach($args_required as $elem){
			$args[$this->getVarName($elem['name'])] = $this->chooseDocType($elem['type']);
		}
		foreach($args_option_default as $elem){
			$defaultValue = $elem['value'];
			if(!in_array($this->chooseDocType($elem['type']), ['bool', 'null', 'int', 'float', 'double'])){
				$defaultValue = '\''.$defaultValue.'\'';
			}
			$args[$this->getVarName($elem['name']).'='.$defaultValue] = $this->chooseDocType($elem['type']);
		}
		foreach($args_option_null as $elem){
			$args[$this->getVarName($elem['name']).'=null'] = $this->chooseDocType($elem['type']);
		}
		$code[] = '$'.strtolower($name).' = self::d_create($dataArray);';
		$code[] = 'return $' . strtolower($name) . ';';
		$func = $this->createFunctionString('create', $args, $code, $name, 'Create object '.$name, 'static');
		return $func;
	}

	/**
	 * @param $fieldName
	 * @return string
	 */
	private function getVarName($fieldName) {
		$parts = explode('_', $fieldName);
		$varName = '';
		foreach ($parts as $part) {
			$varName .= ucfirst(strtolower($part));
		}
		return lcfirst($varName);
	}
}

$cg = new ClassesGenerator();
$cg->run();


