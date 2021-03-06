<?php
declare(ticks = 1);
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 24.03.14
 * Time: 16:16
 */

namespace misc;



trait SocketServer{
	/**
	 * @param SocketInfo $socketInfo
	 * @param string $address
	 * @return SocketClient
	 */
	abstract function newClient(SocketInfo $socketInfo, $address);


	/** @var int $LOOP_TIMEOUT in milliseconds */
	protected $LOOP_TIMEOUT = 0;
	protected $BUFFER_LENGTH = 16384;
	/** @var int $CONNECT_TIMEOUT in milliseconds */
	protected $CONNECT_TIMEOUT = 3000;

	private $ai_client_id = 1;

	/** @var SocketInfo[] */
	private $serverSocketsInfo = [];

	/** @var SocketClient[]  */
	private $socketClients = []; // key - socket
	/** @var resource[] */
	private $sockets = []; // key - client_id
	/** @var string[] */
	private $readBuffers = []; // key - client_id
	/** @var string[] */
	private $writeBuffers = []; // key - client_id

	/** @var resource[] */
	private $connectingSockets = []; // key - client_id, value - socket
	/** @var string[] */
	private $connectingAddress = []; // key client id, value client address connecting to
	/** @var int[] */
	private $connectingSocketsStart = []; // key - socket, value - start time

	/** @var resource[] */
	private $socketsToWrite = []; // key client_id, value - socket


	/** @var string[] */
	private $localInterfaces;



	function loop(){
		if(!$this->localInterfaces){
			$this->localInterfaces = Network::getInterfaces();
		}
		$read = $this->sockets;
		foreach($this->serverSocketsInfo as $socketInfo) $read[] = $socketInfo->getSocket();
		$write = $this->socketsToWrite;
		foreach($this->connectingSockets as $socket) $write[] = $socket;
		$except = $this->sockets;
		echo '-';
		@socket_select($read, $write, $except, 0);//intval($this->LOOP_TIMEOUT/1000), ($this->LOOP_TIMEOUT%1000)*1000);
		echo '+';
		$this->processSockets($read, $write, $except);
		$this->checkConnectionTimeouts();
		return !empty($read) || !empty($write);
	}

	/**
	 * @param Resource[] $read
	 * @param Resource[] $write
	 * @param Resource[] $except
	 */
	private function processSockets($read, $write, $except) {
		foreach($read as $socket){
			if(isset($this->serverSocketsInfo[(int)$socket])){
				switch($this->serverSocketsInfo[(int)$socket]->getProtocol()){
					case SOL_TCP:
						$this->acceptSocket($socket);
						break;
					case SOL_UDP:
						$this->receiveFromUDPSocket($socket);
						break;
				}
			}else{
				/** @var SocketClient $client */
				$client = $this->socketClients[(int)$socket];
				$client_id = $client->getClientId();
				$buf = @socket_read($socket, $this->BUFFER_LENGTH);
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
				$this->callClientReceive($client);
			}
		}

		foreach($write as $socket){
			if(!isset($this->socketClients[(int)$socket])) continue;
			$client = $this->socketClients[(int)$socket];
			$client_id = $client->getClientId();
			if(isset($this->connectingSockets[$client_id])){
				$address = $this->connectingAddress[$client_id];
				unset($this->connectingSockets[$client_id]);
				unset($this->connectingSocketsStart[(int)$socket]);
				unset($this->connectingAddress[$client_id]);
				$client->onConnect($address);
			}else{
				/** @var SocketClient $client */
				$n = socket_write($socket, $this->writeBuffers[$client_id]);
				$this->writeBuffers[$client_id] = substr($this->writeBuffers[$client_id], $n);
				$client->onSend($this->writeBuffers[$client_id]);
				if(!isset($this->writeBuffers[$client_id]) || !$this->writeBuffers[$client_id]){
					unset($this->socketsToWrite[$client_id]);
				}
			}
		}
	}

	/**
	 * @param SocketClient $client
	 */
	public function callClientReceive(SocketClient $client){
//		if($client->getClientId()>1){
//			echo "buf for a now (".$client->getClientId()."): ".$this->readBuffers[$client->getClientId()]."\n";
//		}
		$client->onReceive($this->readBuffers[$client->getClientId()]);
	}

	/**
	 * @param Resource $socket
	 */
	private function removeSocket($socket){
		/** @var SocketClient $client */
		$client = $this->socketClients[(int)$socket];
		$client_id = $client->getClientId();
		unset($this->socketClients[(int)$socket]);
//		echo "unsetting socket with client id: $client_id\n";
        unset($this->sockets[$client_id]);
		unset($this->readBuffers[$client_id]);
		unset($this->writeBuffers[$client_id]);
		unset($this->socketsToWrite[$client_id]);

		unset($this->connectingSockets[$client_id]);
		unset($this->connectingSocketsStart[(int)$socket]);
		unset($this->connectingAddress[$client_id]);
		@socket_shutdown($socket);
		@socket_close($socket);
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
	}

	private function checkConnectionTimeouts() {
		foreach ($this->connectingSockets as $client_id => $socket) {
			if (microtime(true) - $this->connectingSocketsStart[(int)$socket] >= $this->CONNECT_TIMEOUT / 1000) {
				$this->removeTimedOutSocket($client_id);
			}
		}
	}

	/**
	 * @param Resource $socket
	 */
	private function acceptSocket($socket){
		$clientSocket = socket_accept($socket);
		$client_address = '';
		socket_getpeername($clientSocket, $client_address);
		$client = $this->newClient($this->serverSocketsInfo[(int)$socket], $client_address);
		if($client){
			$this->socketClients[(int)$clientSocket] = $client;
			$this->sockets[$this->ai_client_id] = $clientSocket;
			$this->readBuffers[$this->ai_client_id] = '';
			$this->writeBuffers[$this->ai_client_id] = '';
			$client->setInternalVariables($this->ai_client_id, $clientSocket, $this);
			$client->onConnect($client_address);
			$this->ai_client_id ++;
		}
	}

	/**
	 * @param Resource $socket
	 */
	private function receiveFromUDPSocket($socket) {
		$buf = $address = $port = '';
		socket_recvfrom($socket, $buf, $this->BUFFER_LENGTH, /*MSG_DONTWAIT*/0, $address, $port);
//		$local = $this->isAddressLocal($address);
		$socketInfo = $this->serverSocketsInfo[(int)$socket];
		if(/*!$local && */Network::IPInNetwork($address, $socketInfo->getAddress().'/24')){
			$client = $this->newClient($socketInfo, $address);
			if($client){
				$client->setInternalVariables($address, null, $this);
				$client->onConnect($address);
				$client->onReceive($buf);
				$client->onDisconnect(SocketClient::ERROR_NONE);
			}
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
		$client = $this->newClient(new SocketInfo($socket, $address, $port, $socket_type, $socket_protocol), $address);
		if($client){
			$this->socketClients[(int)$socket] = $client;
			$this->sockets[$this->ai_client_id] = $socket;
			$this->readBuffers[$this->ai_client_id] = '';
			$this->writeBuffers[$this->ai_client_id] = '';
			$this->connectingSockets[$this->ai_client_id] = $socket;
			$this->connectingSocketsStart[(int)$socket] = microtime(true);
			$this->connectingAddress[$this->ai_client_id] = $address;
			$client->setInternalVariables($this->ai_client_id, $socket, $this);
			$this->ai_client_id ++;
			@socket_connect($socket, $address, $port);
			if(SOCKET_EINPROGRESS != socket_last_error($socket)){
				echo "!SOCKET_EINPROGRESS \n";
				$client->onDisconnect(SocketClient::ERROR_CONNECT);
				return null;
			}
		}
		return $client;
	}

	/**
	 * @param int $port
	 * @param string $message
	 */
	public static function broadcast($port, $message, $address = '255.255.255.255'){
		$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
		socket_sendto($sock, $message, strlen($message), 0, $address, $port);
		socket_close($sock);
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
		if(!socket_bind($socket, $socket_protocol == SOL_TCP ? $address : '0.0.0.0', $port)){
			echo "Critical error, cant bind socket ($address, $port): ".socket_strerror(socket_last_error($socket))."\n";
			exit;
		}
		if($socket_protocol==SOL_TCP){
			socket_listen($socket);
		}
		$this->serverSocketsInfo[(int)$socket] = new SocketInfo($socket, $address, $port, $socket_type, $socket_protocol);
	}

	/**
	 * @param int $client_id
	 */
	public function disconnectClient($client_id){
        if(isset($this->sockets[$client_id])){
	        $socket = $this->sockets[$client_id];
	        /** @var SocketClient $client */
	        $client = $this->socketClients[(int)$socket];
	        /*if($client->isConnected()) */$client->onDisconnect(SocketClient::ERROR_NONE);
	        $this->removeSocket($socket);
        }
	}

	/**
	 * @param $address
	 * @return bool
	 */
	protected function isAddressLocal($address) {
		$local = false;
		foreach ($this->localInterfaces as $interface) {
			if ($address == $interface) {
				$local = true;
				break;
			}
		}
		return $local;
	}

	/**
	 * @param SocketClient $old
	 * @param SocketClient $new
	 */
	public function replaceClient(SocketClient $old, SocketClient $new){
		$new->setInternalVariables($old->getClientId(), $old->getSocket(), $this);
		$new->setConnected($old->isConnected());
		$new->setAddress($old->getAddress());
		$this->socketClients[(int)$old->getSocket()] = $new;
	}
}









