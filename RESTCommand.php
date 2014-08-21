<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 21.08.14
 * Time: 16:08
 */

namespace misc;

class RESTCommand{
	/** @var string */
	private $object;
	/** @var string */
	private $action;
	/** @var Mixed[] */
	private $arguments;
	/** @var callable */
	private $method;
	/** @var null|ReturnData */
	private $result     = null;

	function __construct($object, $action, $arguments) {
		$this->object = $object;
		$this->action = $action;
		$this->arguments = $arguments;
	}

	/**
	 * @param mixed $action
	 */
	public function setAction($action) {
		$this->action = $action;
	}

	/**
	 * @return mixed
	 */
	public function getAction() {
		return $this->action;
	}

	/**
	 * @param mixed $arguments
	 */
	public function setArguments($arguments) {
		$this->arguments = $arguments;
	}

	/**
	 * @return mixed
	 */
	public function getArguments() {
		return $this->arguments;
	}

	/**
	 * @param mixed $method
	 */
	public function setMethod($method) {
		$this->method = $method;
	}

	/**
	 * @return mixed
	 */
	public function getMethod() {
		return $this->method;
	}

	/**
	 * @param mixed $object
	 */
	public function setObject($object) {
		$this->object = $object;
	}

	/**
	 * @return mixed
	 */
	public function getObject() {
		return $this->object;
	}

	/**
	 * @param null $result
	 */
	public function setResult($result) {
		$this->result = $result;
	}

	/**
	 * @return null
	 */
	public function getResult() {
		return $this->result;
	}

	/**
	 * @return ReturnData
	 */
	public function execute(){
		return $this->result = call_user_func_array($this->method, $this->arguments);
	}
}
