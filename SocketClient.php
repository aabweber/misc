<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 24.03.14
 * Time: 16:26
 */

namespace misc;


abstract class SocketClient {

	const ERROR_NONE            = 'NONE';
	const ERROR_TIMED_OUT       = 'TIMED_OUT';
	const ERROR_CONNECT         = 'CONNECT';
	const ERROR_BREAK           = 'BREAK';

	/** @var  Resource $socket */
	private $socket;

	/** @var int $client_id */
	private $client_id;

	/** @var SocketDaemon $server */
	private $server;

	/**
	 * @param int $client_id
	 * @param Resource $socket
	 * @param SocketDaemon $server
	 */
	public function setInternalVariables($client_id, $socket, $server){
		$this->client_id = $client_id;
		$this->socket = $socket;
		$this->server = $server;
	}

	/**
	 * @return int
	 */
	public function getClientId(){
		return $this->client_id;
	}

	/**
	 * @return Resource
	 */
	public function getSocket(){
		return $this->socket;
	}


	public function __construct() {}
	public function onConnect(){}
	public function onDisconnect($error = self::ERROR_NONE){}

	public function onReceive(&$buf){}
	public function onSend(&$buf){}

	protected function send($msg){
		$this->server->sendToClient($this->client_id, $msg);
	}

	protected function disconnect(){
		$this->server->disconnectClient($this->getClientId());
	}

}