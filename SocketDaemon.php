<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 24.03.14
 * Time: 16:16
 */

namespace misc;


abstract class SocketDaemon extends Daemon{
	protected $LOOP_TIMEOUT = 1000;
	const BUFFER_LENGTH = 16384;


	const CONNECT_TIMEOUT = 3000;

	private $ai_client_id = 1;

	private $serverSocketsInfo = []; // key - socket, value = [type, protocol][]

	/** @var SocketClient[Resource]  */
	private $socketClients = []; // key - socket
	private $sockets = []; // key - client_id
	private $readBuffers = []; // key - client_id
	private $writeBuffers = []; // key - client_id

	private $connectingSockets = []; // key - client_id, value - socket
	private $connectingSocketsStart = []; // key - socket, value - start time

	private $socketsToWrite = []; // key client_id, value - socket


	/**
	 * @param Resource $socket|null
	 * @return SocketClient
	 */
	abstract protected function newClient($socket);

	public function loop(){
		$read = $this->sockets;
		foreach($this->serverSocketsInfo as $socketInfo) $read[] = $socketInfo['socket'];
		$write = $this->socketsToWrite;
		foreach($this->connectingSockets as $socket) $write[] = $socket;
		$except = $this->sockets;
		socket_select($read, $write, $except, $this->LOOP_TIMEOUT/1000, $this->LOOP_TIMEOUT%1000);
		$this->processSockets($read, $write, $except);
		$this->checkConnectionTimeouts();
		return true;
	}

	/**
	 * @param Resource[] $read
	 * @param Resource[] $write
	 * @param Resource[] $except
	 */
	private function processSockets($read, $write, $except) {
		foreach($read as $socket){
			if(isset($this->serverSocketsInfo[(int)$socket])){
				if($this->serverSocketsInfo[(int)$socket]['protocol']==SOL_TCP){
					$this->acceptSocket($socket);
				}else{
					$this->receiveFromUDPSocket($socket);
				}
			}else{
				/** @var SocketClient $client */
				$client = $this->socketClients[(int)$socket];
				$client_id = $client->getClientId();
				$buf = socket_read($socket, self::BUFFER_LENGTH);
				if(!$buf){
					$ind = array_search($socket, $write, true);
					if($ind!==FALSE) unset($write[$ind]);

					$ind = array_search($socket, $except, true);
					if($ind!==FALSE) unset($except[$ind]);

					$client->onDisconnect(SocketClient::ERROR_BREAK);
					$this->removeSocket($socket);
					continue;
				}
				$this->readBuffers[$client_id] .= $buf;
				$client->onReceive($this->readBuffers[$client_id]);
			}
		}

		foreach($write as $socket){
			$client = $this->socketClients[(int)$socket];
			$client_id = $client->getClientId();
			if(isset($this->connectingSockets[$client_id])){
				unset($this->connectingSockets[$client_id]);
				unset($this->connectingSocketsStart[(int)$socket]);
				$client->onConnect();
			}else{
				/** @var SocketClient $client */
				$n = socket_write($socket, $this->writeBuffers[$client_id]);
				$this->writeBuffers[$client_id] = substr($this->writeBuffers[$client_id], $n);
				$client->onSend($this->writeBuffers[$client_id]);
				if(!$this->writeBuffers[$client_id]){
					unset($this->socketsToWrite[$client_id]);
				}
			}
		}
	}


	/**
	 * @param Resource $socket
	 */
	private function removeSocket($socket){
		/** @var SocketClient $client */
		$client = $this->socketClients[(int)$socket];
		$client_id = $client->getClientId();
		unset($this->socketClients[(int)$socket]);
		unset($this->sockets[$client_id]);
		unset($this->readBuffers[$client_id]);
		unset($this->writeBuffers[$client_id]);
		unset($this->socketsToWrite[$client_id]);
	}


	/**
	 * @param int $client_id
	 */
	private function removeTimedOutSocket($client_id) {
		$socket = $this->connectingSockets[$client_id];
		/** @var SocketClient $client */
		$client = $this->socketClients[(int)$socket];
		$client->onDisconnect(SocketClient::ERROR_TIMED_OUT);
		$this->removeSocket($socket);
		unset($this->connectingSockets[$client_id]);
		unset($this->connectingSocketsStart[$socket]);
	}

	private function checkConnectionTimeouts() {
		foreach ($this->connectingSockets as $client_id => $socket) {
			if (microtime(true) - $this->connectingSocketsStart[(int)$socket] >= self::CONNECT_TIMEOUT / 1000) {
				$this->removeTimedOutSocket($client_id);
			}
		}
	}

	/**
	 * @param Resource $socket
	 */
	private function acceptSocket($socket){
		$clientSocket = socket_accept($socket);
		$client = $this->newClient($this->serverSocketsInfo[(int)$socket]);
		$this->socketClients[(int)$clientSocket] = $client;
		$this->sockets[$this->ai_client_id] = $clientSocket;
		$this->readBuffers[$this->ai_client_id] = '';
		$this->writeBuffers[$this->ai_client_id] = '';
		$client->setInternalVariables($this->ai_client_id, $clientSocket, $this);
		$client->onConnect();
		$this->ai_client_id ++;
	}

	/**
	 * @param Resource $socket
	 */
	private function receiveFromUDPSocket($socket) {
		$buf = $address = $port = '';
		socket_recvfrom($socket, $buf, self::BUFFER_LENGTH, /*MSG_DONTWAIT*/0, $address, $port);
		if(1||$address != self::my_ip()){
			$client = $this->newClient($this->serverSocketsInfo[(int)$socket]);
			$client->setInternalVariables($address, null, $this);
			$client->onConnect();
			$client->onReceive($buf);
			$client->onDisconnect(SocketClient::ERROR_NONE);
		}
	}

	/**
	 * Connect to some address
	 * @param string $address
	 * @param int $port
	 * @param int $socket_type
	 * @param int $socket_protocol
	 */
	protected function connectClient($address, $port, $socket_type = SOCK_STREAM, $socket_protocol = SOL_TCP) {
		$socket = socket_create(AF_INET, $socket_type, $socket_protocol);
		socket_set_nonblock($socket);
		$client = $this->newClient(null);
		@socket_connect($socket, $address, $port);
		if(SOCKET_EINPROGRESS != socket_last_error($socket)){
			$client->onDisconnect(SocketClient::ERROR_CONNECT);
			return;
		}
		$this->socketClients[(int)$socket] = $client;
		$this->sockets[$this->ai_client_id] = $socket;
		$this->readBuffers[$this->ai_client_id] = '';
		$this->writeBuffers[$this->ai_client_id] = '';
		$this->connectingSockets[$this->ai_client_id] = $socket;
		$this->connectingSocketsStart[(int)$socket] = microtime(true);
		$client->setInternalVariables($this->ai_client_id, $socket, $this);
		$this->ai_client_id ++;
	}

	/**
	 * @param int $port
	 * @param string $message
	 */
	public static function broadcast($port, $message){
		$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
		socket_sendto($sock, $message, strlen($message), 0, '255.255.255.255', $port);
	}

	/**
	 * @param string $destination
	 * @param int $port
	 * @return string
	 */
	public static function my_ip($destination='64.0.0.0', $port=80){
		$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_connect($socket, $destination, $port);
		socket_getsockname($socket, $address, $port);
		socket_close($socket);
		return $address;
	}

	/**
	 * @param int $client_id
	 * @param string $buf
	 */
	public function sendToClient($client_id, $buf) {
		$this->writeBuffers[$client_id] .= $buf;
		if($buf){
			$this->socketsToWrite[$client_id] = $this->sockets[$client_id];
		}
	}

	/**
	 * @param string $address
	 * @param int $port
	 * @param int $socket_type
	 * @param int $socket_protocol
	 */
	public function addServerSocket($address, $port, $socket_type = SOCK_STREAM, $socket_protocol = SOL_TCP){
		$socket = socket_create(AF_INET, $socket_type, $socket_protocol);
		socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_bind($socket, $address, $port);
		if($socket_protocol==SOL_TCP){
			socket_listen($socket);
		}
		$this->serverSocketsInfo[(int)$socket] = [
				'socket'    => $socket,
				'address'   => $address,
				'port'      => $port,
				'type'      => $socket_type,
				'protocol'  => $socket_protocol
		];
	}

	/**
	 * @param int $client_id
	 */
	public function disconnectClient($client_id){
		$socket = $this->sockets[$client_id];
		/** @var SocketClient $client */
		$client = $this->socketClients[(int)$socket];
		$client->onDisconnect(SocketClient::ERROR_NONE);
		$this->removeSocket($socket);
	}

}









