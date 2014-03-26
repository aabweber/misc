<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 24.03.14
 * Time: 16:16
 */

namespace misc;


abstract class SocketDaemon extends Daemon{
	const LOOP_TIMEOUT = 1000;
	const BUFFER_LENGTH = 16384;

	private $socket_type;
	private $socket_protocol;

	private $ai_client_id = 1;

	private $serverSocket = null;

	private $socketClients = [];
	private $sockets = [];
	private $readBuffers = [];
	private $writeBuffers = [];



	function __construct($socket_type = SOCK_STREAM, $socket_protocol = SOL_TCP) {
		$this->socket_type = $socket_type;
		$this->socket_protocol = $socket_protocol;
		parent::__construct();
	}

	protected function main() {
		$this->serverSocket = socket_create(AF_INET, $this->socket_type, $this->socket_protocol);
		socket_set_option($this->serverSocket, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_bind($this->serverSocket, $this->getConfig()['SERVER']['address'], $this->getConfig()['SERVER']['port']);
		socket_listen($this->serverSocket);
		parent::main();
	}


	public function loop(){
		$read = $this->sockets;
		$read[] = $this->serverSocket;
		$write = [];
		$except = [];
		$n = socket_select($read, $write, $except, self::LOOP_TIMEOUT/1000, self::LOOP_TIMEOUT%1000);
		var_dump($n);
		$this->processSockets($read, $write, $except);
		return true;
	}

	/**
	 * @param Resource[] $read
	 * @param Resource[] $write
	 * @param Resource[] $except
	 */
	private function processSockets($read, $write, $except) {
		foreach($read as $socket){
			if($this->serverSocket == $socket){
				$this->acceptSocket();
			}else{
				/** @var SocketClient $client */
				$client = $this->socketClients[(int)$socket];
				$buf = socket_read($socket, self::BUFFER_LENGTH);
				if(!$buf){
					$ind = array_search($socket, $write, true);
					if($ind!==FALSE) unset($write[$ind]);

					$ind = array_search($socket, $except, true);
					if($ind!==FALSE) unset($except[$ind]);

					$this->clearSocket($socket);
					continue;
				}
				$this->readBuffers[$client->getClientId()] .= $buf;
			}
		}
	}

	/**
	 * @return SocketClient
	 */
	abstract protected function onConnect();

	private function acceptSocket(){
		$clientSocket = socket_accept($this->serverSocket);
		$client = $this->onConnect();
		$this->socketClients[(int)$clientSocket] = $client;
		$this->sockets[$this->ai_client_id] = $clientSocket;
		$this->readBuffers[$this->ai_client_id] = '';
		$this->writeBuffers[$this->ai_client_id] = '';
		$client->setInternalVariables($this->ai_client_id, $clientSocket, $this);
		$client->onConnect();
		$this->ai_client_id ++;
	}

	/**
	 * @param int $client_id
	 */
	public function deleteClient($client_id){
		$socket = $this->sockets[$client_id];
		$this->clearSocket($socket);
	}

	/**
	 * @param Resource $socket
	 */
	private function clearSocket($socket){
		/** @var SocketClient $client */
		$client = $this->socketClients[(int)$socket];
		$client_id = $client->getClientId();
		unset($this->socketClients[(int)$socket]);
		unset($this->sockets[$client_id]);
		unset($this->readBuffers[$client_id]);
		unset($this->writeBuffers[$client_id]);
	}

	protected function connectClient($address, $int) {
	}

}


