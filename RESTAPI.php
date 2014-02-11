<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 24.01.14
 * Time: 14:25
 */

namespace misc;


abstract class RESTAPI{
	protected $object;
	protected $action;
	protected $arguments        = [];

	protected $availableActions = [];

	function __construct(){

	}

	function prepare(){
		$uri = $_SERVER['REQUEST_URI'];
		if(preg_match('/(.+?)\?/', $uri, $ms)){
			$uri = $ms[1];
		}
		$uriParts = explode('/', trim($uri, '/'));

		if(!isset($uriParts[0])){
			return RetErrorWithMessage('REST_NO_OBJECT', 'REST: Object not specified in query');
		}
		$this->object = $uriParts[0];

		if(!isset($this->availableActions[$this->object])){
			return RetErrorWithMessage('REST_OBJECT_NOT_ALLOWED', 'REST: There is no "'.$this->object.'" in allowed objects');
		}

		if(!isset($uriParts[1])){
			return RetErrorWithMessage('REST_NO_ACTION', 'REST: Action for object "'.$this->object.'" not specified in query');
		}
		$this->action = $uriParts[1];

		if(!isset($this->availableActions[$this->object][$this->action])){
			return RetErrorWithMessage('REST_ACTION_NOT_ALLOWED', 'REST: There is no action "'.$this->action.'" for object "'.$this->object.'" in allowed');
		}

		$arguments = array_merge($_REQUEST, array_slice($uriParts, 2));
		$commandArguments = $this->availableActions[$this->object][$this->action];

		foreach($commandArguments as $arg_name){
			$needed = $arg_name[0]!='?' && $arg_name[strlen($arg_name)-1]!='?';
			$arg_name = trim($arg_name, '?');
			if($needed && !isset($arguments[$arg_name])){
				return RetErrorWithMessage('REST_ARGUMENT_MISSING', 'REST: Argument "'.$arg_name.'" missing');
			}
			if(isset($arguments[$arg_name])){
				$this->arguments[$arg_name] = $arguments[$arg_name];
				unset($arguments[$arg_name]);
			}else{
				$this->arguments[$arg_name] = null;
			}
		}
		$this->arguments = array_merge($this->arguments, $arguments);

		return true;
	}

	function process(){
		$method = 'cmd'.ucfirst($this->action).ucfirst($this->object);
		if(!method_exists($this, $method)){
			return RetErrorWithMessage('INTERNAL_NO_SUCH_METHOD', 'There is no method to process action "'.$this->action.'" for object "'.$this->object.'"');
		}else{
			return call_user_func_array([$this, $method], $this->arguments);
		}
	}
}

