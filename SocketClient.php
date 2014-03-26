<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 24.03.14
 * Time: 16:26
 */

namespace misc;


abstract class SocketClient {
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
	public function onDisconnect(){}

	public function onReceive(){}
	public function onSend(){}

	protected function send($buf){

	}

	private function disconnect(){
		$this->server->deleteClient($this->getClientId());
	}

}