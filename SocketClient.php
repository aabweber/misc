<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 24.03.14
 * Time: 16:26
 */

namespace misc;


class SocketClient {
	use Observable;

	protected $usualProtocol    = true;

	const ERROR_NONE            = 'NONE';
	const ERROR_CONNECT         = 'CONNECT';
	const ERROR_TIMED_OUT       = 'TIMED_OUT';
	const ERROR_BREAK           = 'BREAK';

	const EVENT_CONNECT         = 'connect';
	const EVENT_DISCONNECT      = 'disconnect';
	const EVENT_RECEIVE         = 'receive';
	const EVENT_MESSAGE         = 'message';
	const EVENT_SEND            = 'send';

	/** @var  Resource $socket */
	private $socket;

	/** @var int $client_id */
	private $client_id;

	/** @var SocketDaemon $server */
	private $server;

	/** @var String $address */
	protected $address;

	private $connected = false;

	/**
	 * @return bool
	 */
	public function isConnected(){
		return $this->connected;
	}

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

	function getAddress(){
		return $this->address;
	}

	/**
	 * @return Resource
	 */
	public function getSocket(){
		return $this->socket;
	}


	public function __construct() {

	}

	public function onConnect($address){
		$this->address = $address;
		$this->connected = true;
		$this->event(self::EVENT_CONNECT, $address);
	}

	public function onDisconnect($error = self::ERROR_NONE){
		$this->event(self::EVENT_DISCONNECT, $error);
	}

	public function onReceive(&$buf){
		$this->event_vars_array(self::EVENT_RECEIVE, [&$buf]);
		if($this->usualProtocol){
			while( ($pos = strpos($buf, "\n"))!==false ){
				$packet = substr($buf, 0, $pos);
				$buf = substr($buf, $pos+1);
				$msg = json_decode($packet, true);
				$this->onMessage($msg['message'], $msg['data']);
			}
		}
	}

	public function onMessage($message, $data){
		$this->event_vars_array(self::EVENT_MESSAGE, [$message, $data]);
	}

	public function onSend(&$buf){
		$this->event_vars_array(self::EVENT_SEND, [&$buf]);
	}

	public function send($msg){
		if($this->connected){
			$this->server->sendToClient($this->client_id, $msg);
		}
	}

	public function sendMessage($message, $data = []){
		$arr = ['message'=>$message, 'data'=>$data];
		$this->send(json_encode($arr)."\n");
	}

	function disconnect(){
//		if($this->connected){
		$this->server->disconnectClient($this->getClientId());
		$this->connected = false;
//		}
	}

}







