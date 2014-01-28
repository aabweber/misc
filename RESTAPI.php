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

		$this->arguments = array_merge($_REQUEST, array_slice($uriParts, 2));
		if( count($this->arguments) < count($this->availableActions[$this->object][$this->action]) ){
			return RetErrorWithMessage('REST_ACTION_WRONG_ARGUMENTS_NUMBER', 'REST: Action "'.$this->action.'" for object "'.$this->object.'" assumed to have '.count($this->availableActions[$this->object][$this->action]).' argument(s)');
		}

		foreach($this->availableActions[$this->object][$this->action] as $arg_name){
			if(!isset($this->arguments[$arg_name])){
				return RetErrorWithMessage('REST_ARGUMENT_MISSING', 'REST: Argument "'.$arg_name.'" missing');
			}
		}


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

