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
	private $currentClassConstants = [];
	private $objects = [
//		'Block' => ['multi'=>'blocks', 'single'=>'block', 'varName'=>'block', 'table'=>'blocks']
	];
	private $relations = [
//		'Block' => [
//			'Variable' => ['type'=>'many2many', 'table'=>'block_variables']
//		],
	];

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
//		$line = fgets(STDIN);
		$this->generate();
	}

	private function generate() {
		$tables = $this->getTables();
		foreach($tables as $tableName => $tableInfo){
			if(strpos($tableName, '_')===false){
				$objectName = ucfirst(Inflector::singularize($tableName));
				$this->objects[$objectName] = [
						'multi' => lcfirst(Inflector::pluralize($tableName)),
						'single' => lcfirst(Inflector::singularize($tableName)),
						'table' => $tableName,
						'tableInfo' => $tableInfo,
				];
			}else{
				$info = $this->getTableRelations($tableName);
				switch(count($info)){
					case 1:
						$objectName = ucfirst($this->getVarName(Inflector::singularize($tableName)));
						$this->objects[$objectName] = [
								'multi' => lcfirst(Inflector::pluralize($objectName)),
								'single' => lcfirst($objectName),
								'table' => $tableName,
								'tableInfo' => $tableInfo,
						];
						break;
				}
			}
		}

		foreach($tables as $tableName => $tableInfo){
			// с чем связана текущая таблица
			$info = $this->getTableRelations($tableName);
			if(strpos($tableName, '_')===false){
				$objectName = ucfirst(Inflector::singularize($tableName));
				foreach($info as $rel){
					$obj2 = ucfirst($this->getVarName(Inflector::singularize($rel['referenced_table_name'])));
					$this->relations[$obj2][$objectName] = ['type'=>'one2many', 'table'=>$tableName, 'field'=>$rel['column_name']];
				}
			}else{
				switch(count($info)){
					case 1:
						$objectName = ucfirst($this->getVarName(Inflector::singularize($tableName)));
						break;
					case 2:
						$obj1 = ucfirst($this->getVarName(Inflector::singularize($info[0]['referenced_table_name'])));
						$obj2 = ucfirst($this->getVarName(Inflector::singularize($info[1]['referenced_table_name'])));
						if(!isset($this->relations[$obj1])) $this->relations[$obj1] = [];
						if(!isset($this->relations[$obj2])) $this->relations[$obj2] = [];
						$this->relations[$obj1][$obj2] = ['type'=>'many2many', 'table'=>$tableName];
						$this->relations[$obj2][$obj1] = ['type'=>'many2many', 'table'=>$tableName];
						$this->relations[$obj1][$obj2]['field'] = $info[1]['column_name'];
						$this->relations[$obj2][$obj1]['field'] = $info[0]['column_name'];
						if(!isset($this->objects[$obj1])){
							$tName = $info[0]['referenced_table_name'];
							$this->objects[$obj1] = [
									'multi' => lcfirst(Inflector::pluralize($obj1)),
									'single' => lcfirst($obj1),
									'table' => $tName,
									'tableInfo' => $tables[$tName],
							];
						}
						break;
					default:
				}
			}
		}
		foreach($this->objects as $objectName => $info){
			$class = $this->createObjectInterface($objectName, $info);
			file_put_contents(self::FOLDER.'/'.$objectName.'.php', $class);
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

	private function createObjectInterface($objectName, $info) {
//		$class .= $this->createObjectConstants($objectName, $info)."\n\n";

		$this->currentClassVariables = $this->getObjectVariables($info['tableInfo']);
		$this->currentClassConstants = $this->getObjectConstants($objectName, $info);

		$relationsString = $this->createObjectRelations($objectName, $info)."\n\n";



		$class = implode("\n", [
				'use misc\\DB\\DB;',
				'use misc\\DBDynamicData;',
				'use misc\\ReturnData;',
				'',
				$this->createObjectVariables(),
				'class '.$objectName.'{',
				'	use DBDynamicData{',
				'		DBDynamicData::create       as d_create;',
				'		DBDynamicData::__construct  as d__construct;',
				'		DBDynamicData::init         as d_init;',
				'	}',
				'',
				'	const TABLE_NAME = \''.$info['table'].'\';',
				'',
				'	function __construct(){',
				'       $this->d__construct();',
				'       self::$cached = true;',
				'   }',
				'	static function init($table = null) {',
				'		self::d_init(self::TABLE_NAME);',
				'	}',
				'',
				'',
				'',
		]);

		$class .= $this->createObjectConstants()."\n\n";
//
//		$class .= $this->createObjectVariables()."\n\n";
		$class .= $this->createObjectGSetters($info['tableInfo'])."\n\n";
		$class .= $relationsString;
		$class .= $this->createObjectCreate($objectName, $info['tableInfo']);
		$class .= "}\n";
		return "<?php\n\n".$this->genFieldsClass($objectName).$class;//."\n".$this->getObjectName($table)."::initFields();\n";
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

	private function getObjectConstants($objectName, $info) {
		$consts = [];
		foreach($info['tableInfo'] as $fieldName => $fieldInfo){
			if($enums = $this->parseENUM($fieldInfo['Type'])){
				foreach($enums as $value){
					$consts[strtoupper($fieldName).'_'.$value] = strtoupper($value);
				}
			}
		}
		return $consts;
	}

	private function createObjectConstants(){
		$constString = '';
		foreach ($this->currentClassConstants as $constName => $value) {
			$const = "\t" . 'const ' . sprintf('%1$- 34s', $constName) . "= '" . $value . "';\n";
			$constString .= $const;
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
//			$comment = "\t/** @var " . $type . ' $' . $var . " */\n";
//			$var = "\tprivate \$" . $var . ";\n";
//			$variableString .= $comment.$var;
			$variableString .= " * @property $type $var\n";
		}
		return "/** \n".$variableString.' */';
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

	private function createObjectRelations($objectName, $info) {
//		echo "\n-----\n$objectName\n";
//		print_r($this->relations[$objectName]);
		$relations = '';

		foreach($this->relations as $obj2Name => $relation){
			if(isset($relation[$objectName])){
				switch($relation[$objectName]['type']){
					case 'one2many':
						$func = 'get'. $obj2Name;
						$this->currentClassVariables[$this->objects[$obj2Name]['single']] = $obj2Name;
						$relations .= $this->createFunctionString($func, ['renew=false'=>'bool'], [
								'if(!$this->'.$this->objects[$obj2Name]['single'].' || $renew){',
								'   $this->'.$this->objects[$obj2Name]['single'].' = '.$obj2Name.'::get($this->'.$relation[$objectName]['field'].');',
								'}',
								'return $this->'.$this->objects[$obj2Name]['single'].';'
						], $obj2Name, 'Get '.strtolower($obj2Name).' for current '.strtolower(Inflector::singularize($objectName)));
						break;
				}
			}
		}
		if(isset($this->relations[$objectName])){
			foreach($this->relations[$objectName] as $obj2Name => $relation){
				switch($relation['type']){
					case 'many2many':
						$const = 'REF_'.strtoupper($obj2Name);
						$value = $relation['table'];
						$this->currentClassConstants[$const] = $value;
						$func = 'get'.$obj2Name.'List';
						$relations .= $this->createFunctionString($func, [], [
							'$rows = DB::get()->selectBySQL(\'',
							'   SELECT',
							'       \'.'.$obj2Name.'::getTable().\'.*',
							'   FROM',
							'       \'.'.$obj2Name.'::getTable().\', \'.self::'.$const.'.\'',
							'   WHERE',
							'       \'.'.$obj2Name.'::getTable().\'.id = \'.self::'.$const.'.\'.'.$this->relations[$objectName][$obj2Name]['field'].' AND',
							'       \'.self::'.$const.'.\'.'.$this->relations[$obj2Name][$objectName]['field'].' = \'.intval($this->id));',
							'$list = [];',
							'foreach($rows as $row){',
							'   $list[] = '.$obj2Name.'::genOnData($row);',
							'}',
							'return $list;'
						], $obj2Name.'[]', 'Get list of '.$this->objects[$obj2Name]['multi'].' for current '.$this->objects[$objectName]['single']);
						break;
					case 'one2many':
//						echo "$objectName - $obj2Name\n";
						$func = 'get'.Inflector::pluralize($obj2Name);
						$relations .= $this->createFunctionString($func, [], [
							'$list = '.$obj2Name.'::getList(['.$obj2Name.'Fields::$'.$relation['field'].' => $this->id]);',
							'return $list;',
						], $obj2Name.'[]', 'Get list of '.$this->objects[$obj2Name]['multi'].' for current '.$this->objects[$objectName]['single']);

						$func = 'get'.$obj2Name.'ById';
						$relations .= $this->createFunctionString($func, ['id'=>'int', 'returnError=false'=>'bool'],[
								'$'.$this->objects[$obj2Name]['single'].' = $err = '.$obj2Name.'::get($id, $returnError);',
								'if($returnError && $err instanceof ReturnData) return $err;',
								'if($'.$this->objects[$obj2Name]['single'].'->get'.ucfirst($this->getVarName($relation['field'])).'()!=$this->getId()) {',
								'   if($returnError){',
								'       return RetErrorWithMessage(\''.strtoupper($obj2Name).'_NOT_BELONG_TO_'.strtoupper(Inflector::singularize($objectName)).'\', \'The '.strtolower($obj2Name).' with id="\'.$id.\'" does not belong to '.strtolower(Inflector::singularize($objectName)).' with id="\'.$this->getId().\'"\');',
								'   }else{',
								'       return null;',
								'   }',
								'}',
								'return $'.$this->objects[$obj2Name]['single'].';',
						], $obj2Name, 'Get '.strtolower($obj2Name).' related to the '.strtolower(Inflector::singularize($objectName)));

						break;
				}
			}
		}
		/*
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
		*/

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


	private function createObjectCreate($objectName, $object) {
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
		$code[] = '$'.lcfirst($objectName).' = self::d_create($dataArray);';
		$code[] = 'return $' . lcfirst($objectName) . ';';
		$func = $this->createFunctionString('create', $args, $code, $objectName, 'Create object '.$objectName, 'static');
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

	/**
	 * @param $tableName
	 * @return array|mixed
	 */
	private function getTableRelations($tableName) {
		return DB::get()->selectBySQL($q = '
				SELECT
					column_name, referenced_table_name
				FROM information_schema.key_column_usage
				WHERE
					table_schema = "' . $this->database . '" AND
					table_name = "' . $tableName . '" AND
					referenced_column_name = "id"
				');
	}
}



$cg = new ClassesGenerator();
$cg->run();


