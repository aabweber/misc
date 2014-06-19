<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 24.01.14
 * Time: 14:25
 */

namespace misc;


trait RESTAPI{

	protected $commands         = [
//		['object'=>'', 'action'=>'', 'arguments'=>[]]
	];

	protected $availableActions = [];
	abstract function getAvailableActions();

	protected $isSingle = true;


	private function checkCommand(&$command){
		if(!isset($this->availableActions[$command['object']])){
			return RetErrorWithMessage('REST_OBJECT_NOT_ALLOWED', 'REST: There is no "'.$command['object'].'" in allowed objects');
		}

		if(!isset($command['action'])){
			return RetErrorWithMessage('REST_NO_ACTION', 'REST: Action for object "'.$command['action'].'" not specified in query');
		}

		if(!isset($this->availableActions[$command['object']][$command['action']])){
			return RetErrorWithMessage('REST_ACTION_NOT_ALLOWED', 'REST: There is no action "'.$command['action'].'" for object "'.$command['object'].'" in allowed');
		}

		$arguments = array_merge($_REQUEST, []);//array_slice($uriParts, 2));
		$commandArguments = $this->availableActions[$command['object']][$command['action']];
		$command['arguments'] = [];

		foreach($commandArguments as $arg_name){
			$needed = $arg_name[0]!='?' && $arg_name[strlen($arg_name)-1]!='?';
			$arg_name = trim($arg_name, '?');
			if($needed && !isset($arguments[$arg_name])){
				return RetErrorWithMessage('REST_ARGUMENT_MISSING', 'REST: Argument "'.$arg_name.'" missing, REQUEST: '.var_export($command, true));
			}
			if(isset($arguments[$arg_name])){
				$command['arguments'][$arg_name] = $arguments[$arg_name];
				unset($arguments[$arg_name]);
			}else{
				$command['arguments'][$arg_name] = null;
			}
		}
		$command['arguments'] = array_merge($command['arguments'], $arguments);
		return true;
	}

	protected static function parseURIAction($uri){
		if(preg_match('/(.+?)\?/', $uri, $ms)){
			$uri = $ms[1];
		}
		$uriParts = explode('/', trim($uri, '/'));

		if(!isset($uriParts[0])){
			return RetErrorWithMessage('REST_NO_OBJECT', 'REST: Object not specified in query');
		}
		return	[
						'object'    => $uriParts[0],
						'action'   => $uriParts[1],
						'arguments' => $_REQUEST
				];
	}

	function prepare($uri = null){
		$this->availableActions = $this->getAvailableActions();

		if(isset($_POST['commands'])){
			$this->isSingle = false;
			$this->commands = $_POST['commands'];
		}else{
			$uri = $uri===null ? $_SERVER['REQUEST_URI'] : $uri;
			$command = $this->parseURIAction($uri);
			if( ($err = $this->checkCommand($command)) instanceof ReturnData) return $err;
			$this->commands = [$command];
		}
		return true;
	}

	function process(){
		foreach($this->commands as &$command){
			if( ($err = $this->checkCommand($command)) instanceof ReturnData){
				$command['result'] = $err;
				if($this->isSingle){
					return $command['result'];
				}
				continue;
			}
			$method = 'cmd'.ucfirst($command['action']).ucfirst($command['object']);
			if(!method_exists($this, $method)){
				$command['result'] = RetErrorWithMessage('INTERNAL_NO_SUCH_METHOD', 'There is no method to process action "'.$command['action'].'" for object "'.$command['object'].'"');
			}else{
				$command['result'] = call_user_func_array([$this, $method], $command['arguments']);
			}
			if($this->isSingle){
				return $command['result'];
			}
		}
		$str = '';
		foreach($this->commands as &$command){
			$str .= $command['result'].',';
		}
		return '['.rtrim($str, ',').']';
	}
}

