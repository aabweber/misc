<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 20.08.14
 * Time: 11:50
 */

namespace misc;



abstract class ManagerClient{
	use SocketServer;
	use Observable;

	/**
	 * @return string
	 */
	abstract function getBroadcasterNetwork();

	/**
	 * @return int
	 */
	abstract function getBroadcasterPort();

	/**
	 * @return int
	 */
	abstract function getConnectToPort();

	const EVENT_MANAGER_CONNECTED           = 'manager_connected';
	const EVENT_MANAGER_DISCONNECTED        = 'manager_disconnected';
	const EVENT_MANAGER_MESSAGE             = 'manager_message';

	const MESSAGE_MANAGER_STATUS            = 'manager_status';

	/** @var SocketClient */
	private $manager;
	/** @var string */
	private $address;
	/** @var int */
	private $listenPort;

	function __construct() {
		$this->address = Network::getInterfaces($this->getBroadcasterNetwork())[0];
		$this->listenPort = $this->getBroadcasterPort();
		$this->addServerSocket($this->address, $this->listenPort, SOCK_DGRAM, SOL_UDP);
	}


	/**
	 * @param SocketClient $client
	 * @param string $command
	 * @param Mixed[string] $data
	 */
	function readBroadcastMessage(SocketClient $client, $command, $data){
		switch($command){
			case self::MESSAGE_MANAGER_STATUS:
				if( intval($data['active']) ){
					if( !$this->manager ||
							($managerAddress=$this->manager->getAddress()) && $managerAddress!=$client->getAddress()
					){
						$this->manager = $this->connectClient($client->getAddress(), $this->getConnectToPort());
						if($this->manager){
							$this->manager->on(SocketClient::EVENT_CONNECT, function(){
								$this->event(self::EVENT_MANAGER_CONNECTED);
							});
						}
						$this->manager->on(SocketClient::EVENT_MESSAGE, [$this, 'messageFromManager']);
						$this->manager->on(SocketClient::EVENT_DISCONNECT, [$this, 'managerDisconnected']);
					}
				}
				break;
		}
	}

	/**
	 * @param SocketClient $manager
	 * @param string $massage
	 * @param Mixed[] $args
	 */
	function messageFromManager(SocketClient $manager, $massage, $args){
		$this->event(self::EVENT_MANAGER_MESSAGE, $massage, $args);
	}

	function managerDisconnected(){
		$this->manager = null;
		$this->event(self::EVENT_MANAGER_DISCONNECTED);
	}

	/**
	 * @param SocketInfo $socketInfo
	 * @param string $address
	 * @return SocketClient|null
	 */
	function newClient(SocketInfo $socketInfo, $address) {
		switch($socketInfo->getProtocol()){
			case SOL_TCP:
				if($socketInfo->getPort()==$this->getConnectToPort() && Network::IPInNetwork($address, $this->address.'/24')){
					return new SocketClient();
				}
				break;
			case SOL_UDP:
				if($socketInfo->getAddress()==$this->address && $socketInfo->getPort()==$this->listenPort){
					$client = new SocketClient();
					$client->on(SocketClient::EVENT_MESSAGE, [$this, 'readBroadcastMessage']);
					return $client;
				}
				break;
		}
		return null;
	}

	/**
	 * @param string $message
	 * @param Mixed[] $args
	 * @return bool
	 */
	public function sendMessage($message, $args = []) {
		if($this->isConnected()){
			$this->manager->sendMessage($message, $args);
			return true;
		}
		return false;
	}

	/**
	 * @return bool
	 */
	function isConnected(){
		return boolval($this->manager);
	}
}

