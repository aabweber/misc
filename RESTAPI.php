<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 21.08.14
 * Time: 15:58
 */

namespace misc;



trait RESTAPI {
	/**
	 * @return mixed[]
	 */
	abstract function getAvailableActions();
	/**
	 * @param RESTCommand $command
	 * @return bool
	 */
	abstract function checkActionAccess(RESTCommand $command);

	protected $availableActions = [];

	/** @var RESTCommand[] */
	private $commands           = [];
	/** @var RESTCommand */
	private $currentCommand     = null;

	/**
	 * @return ReturnData|bool
	 */
	function prepare($uri = null){
		$this->availableActions = $this->getAvailableActions();
		if(!$uri) $uri = $_SERVER['REQUEST_URI'];
		if(isset($_REQUEST['commands'])){
			if( ($error = $this->commands = $this->createCommands($_REQUEST['commands'])) instanceof ReturnData) return $error;
		}else{
			if( ($error = $command = $this->parseURICommand($uri)) instanceof ReturnData ) return $error;
			$this->commands[] = $command;
		}
        if( ($error = $this->checkCommandsArguments()) instanceof ReturnData ) return $error;
        if( ($error = $this->checkCommandsAccess()) instanceof ReturnData ) return $error;
		return true;
	}


	/**
	 * @return string
	 */
	function process(){
		foreach($this->commands as $command){
			$this->currentCommand = $command;
			$command->execute();
		}
		return ReturnData::implodeResults(array_map(function(RESTCommand $command){return $command->getResult();}, $this->commands));
	}

	/**
	 * @param $commandRows
	 * @return RESTCommand[]|ReturnData
	 */
	private function createCommands($commandRows) {
		$commands = [];
		foreach($commandRows as $commandRow){
			if( ($command = $error = $this->createCommand($commandRow)) instanceof ReturnData){
				return $error;
			}
			$commands[] = $command;
		}
		return $commands;
	}

	/**
	 * @param string $uri
	 * @return RESTCommand|ReturnData
	 */
	private function parseURICommand($uri) {
		if(($p=strpos($uri, '?'))!==false) $uri = substr($uri, 0, $p);
		$uriParts = explode('/', trim($uri, '/'));
		@$object = $uriParts[0];
		@$action = $uriParts[1];
		@$commandArguments = $this->availableActions[$object][$action];
		$uriArguments = array_slice($uriParts, 2);
		$commandRow = ['object'=>$object, 'action'=>$action];
		if($commandArguments){
			foreach($commandArguments as $commandArgument){
				$commandArgumentName = trim($commandArgument, '?');
				$commandRow[$commandArgumentName] = isset($_REQUEST[$commandArgumentName]) ? $_REQUEST[$commandArgumentName] : array_shift($uriArguments) ;
			}
		}
		return $this->createCommand($commandRow);
	}

	/**
	 * @param Mixed[] $commandRow
	 * @return RESTCommand|ReturnData
	 */
	private function createCommand($commandRow) {
		if(!isset($commandRow['object'])){
			return RetErrorWithMessage('REST_NO_OBJECT', 'Object not specified');
		}
		$object = $commandRow['object'];
		unset($commandRow['object']);
		if(!isset($commandRow['action'])){
			return RetErrorWithMessage('REST_NO_ACTION', 'Action for object "'. $object .'" not specified');
		}
		$action = $commandRow['action'];
		unset($commandRow['action']);
		$arguments = $commandRow;
		$command = new RESTCommand($object, $action, $arguments);
		$method = 'cmd'.ucfirst($action).ucfirst($object);
		if(!method_exists($this, $method)){
			return RetErrorWithMessage('REST_NO_SUCH_METHOD', 'There is no method to process action "'.$action.'" for object "'.$object.'"');
		}
		$command->setMethod([$this, $method]);
		return $command;
	}

	/**
	 * @return bool|ReturnData
	 */
	private function checkCommandsArguments(){
		foreach($this->commands as $command){
			if(!isset($this->availableActions[$command->getObject()])){
				return RetErrorWithMessage('REST_OBJECT_UNAVAILABLE', 'Object "'. $command->getObject() .'" is unavailable');
			}
			if(!isset($this->availableActions[$command->getObject()][$command->getAction()])){
				return RetErrorWithMessage('REST_ACTION_UNAVAILABLE', 'Action "'.$command->getAction().'" for object "'. $command->getObject() .'" is unavailable');
			}
			$commandArguments = $this->availableActions[$command->getObject()][$command->getAction()];
			foreach($commandArguments as $cmdName){
				if($cmdName[0]=='?' || $cmdName[strlen($cmdName)-1]=='?'){

				}else{
					if(!isset($command->getArguments()[$cmdName])){
						return RetErrorWithMessage('REST_ARGUMENT_MISSING', 'Argument "'.$cmdName.'" missing');
					}
				}
			}
		}
		return true;
	}

	/**
	 * @return ReturnData|bool
	 */
	private function checkCommandsAccess(){
		foreach($this->commands as $command){
			if(!$this->checkActionAccess($command)){
				return RetErrorWithMessage('REST_ACCESS_DENIED', 'You have not privileges to access action "'.$command->getAction().'" on object "'.$command->getObject().'"');
			}
		}
		return true;
	}

	/**
	 * @return RESTCommand
	 */
	public function getCurrentCommand(){
		return $this->currentCommand;
	}

}






