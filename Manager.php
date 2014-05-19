<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 02.04.14
 * Time: 9:26
 */

namespace misc;


class Manager extends SocketDaemon{
	// INTERNAL MESSAGES
	const MESSAGE_MANAGER_STATUS    = 'manager_status';

	// EXTERNAL MESSAGES
	const MESSAGE_HAVE_CLIENT       = 'i_already_have_client';

	// EXTERNAL BROADCAST MESSAGES
	const MESSAGE_BECAME_ACTIVE     = 'manager_became_active';
	const MESSAGE_BECAME_INACTIVE   = 'manager_became_inactive';
	const MESSAGE_WORKER_STATUS     = 'worker_status';
	const MESSAGE_WORKER_STATE      = 'worker_state';

	/** @var bool $active */
	private $active = false;
	/** @var Looper $looper */
	private $looper = null;
	/** @var float $start_time */
	private $start_time;
	/** @var int $alone_time */
	private $alone_time = 0;

	// INTERNAL NETWORK
	/** @var int */
	private $internal_broadcast_port;
	/** @var string */
	private $my_internal_ip;
	/** @var string */
	private $internal_broadcast_address;
	
	// EXTERNAL NETWORK
	/** @var int */
	private $external_broadcast_port;
	/** @var string */
	private $my_external_ip;
	/** @var string */
	private $external_broadcast_address;

	/** @var  SocketClient */
	protected $client;

	protected function getInternalAddress(){ return $this->my_internal_ip; }
	protected function getExternalAddress(){ return $this->my_external_ip; }
	protected function isActive() { return $this->active; }


	function initInstance(){
		parent::initInstance();
		$config = $this->getConfig()['SERVER'];

		$this->looper = new Looper($config['heartbeat_time']);
		$this->looper->add([$this, 'heartbeat']);
		$this->looper->add([$this, 'tick']);
		$this->start_time = microtime(true)*1000;

		$this->my_internal_ip = Network::getInterfaces($config['internal_network'])[0];
		$this->internal_broadcast_address = long2ip(ip2long($this->my_internal_ip)|255);
		$this->internal_broadcast_port = $config['internal_broadcast_port'];

		$this->my_external_ip = Network::getInterfaces($config['external_network'])[0];
		$this->external_broadcast_address = long2ip(ip2long($this->my_external_ip)|255);
		$this->external_broadcast_port = $config['external_broadcast_port'];

		// listen TCP
		$this->addServerSocket($this->my_internal_ip, $config['internal_listen_port']);
		$this->addServerSocket($this->my_external_ip, $config['external_listen_port']);

		// listen broadcast
		$this->addServerSocket($this->my_internal_ip, $this->internal_broadcast_port, SOCK_DGRAM, SOL_UDP);
	}

	/**
	 * @param string $message
	 * @param array[string]mixed $data
	 * @param bool $internal
	 */
	protected function broadcastMessage($message, $data = [], $internal = true) {
		parent::broadcast(
				$internal ? $this->internal_broadcast_port : $this->external_broadcast_port,
				json_encode(['message' => $message, 'data' => $data])."\n",
				$internal ? $this->internal_broadcast_address : $this->external_broadcast_address
		);
	}

	/**
	 * @param string $message
	 * @param array[string]mixed $data
	 */
	protected function broadcastMessageOutside($message, $data = []) {
		$this->broadcastMessage($message, $data, false);
	}

	function heartbeat(){
		$this->broadcastMessage(static::MESSAGE_MANAGER_STATUS, ['active'=>$this->active, 'start_time'=>$this->start_time]);
		$this->broadcastMessageOutside(static::MESSAGE_MANAGER_STATUS, ['active'=>$this->active, 'start_time'=>$this->start_time]);
	}

	private function becomeActive(){
		$this->active = true;
		$this->broadcastMessageOutside(static::MESSAGE_BECAME_ACTIVE);
		echo "i am active\n";
	}

	private function becomeInactive(){
		$this->active = false;
		$this->broadcastMessageOutside(static::MESSAGE_BECAME_INACTIVE);
		echo "triggered to inactive\n";
	}


	/**
	 * @param SocketClient $client
	 */
	public function clientDisconnected(SocketClient $client){
		$this->client = null;
	}


	/**
	 * Called every "heartbeat_time" period
	 */
	function tick(){
		if(!$this->active){
			if($this->alone_time++ == 2){
				// i am alone and long time, become active
				$this->becomeActive();
			}
		}
	}

	/**
	 * @param SocketClient $udp_client
	 * @param String $message
	 * @param Array $data
	 */
	public function receiveMessageFromAnotherManager($udp_client, $message, $data){
		switch($message){
			case static::MESSAGE_MANAGER_STATUS:
				$this->alone_time = 0; // i am not alone
				if($this->active){
					if($data['active']){
						// i am active and he is active too
						if($this->start_time > $data['start_time']){
							// i am younger, become inactive
							$this->becomeInactive();
						}
					}
				}else{
					if($data['active']){
						// another is active, i am do nothing
					}else{
						// hi is inactive too
						if($this->start_time < $data['start_time']){
							// i am older, become active
							$this->becomeActive();
						}
					}
				}
				break;
		}
	}

	/**
	 * @return SocketClient
	 */
	protected function createClient(){
		return new SocketClient();
	}

	/**
	 * @param Array|null $socketInfo
	 * @return SocketClient
	 */
	protected function newClient($socketInfo) {
		if($socketInfo['protocol']==SOL_UDP){
			$udp_client = new SocketClient();
			$udp_client->on(SocketClient::EVENT_MESSAGE, [$this, 'receiveMessageFromAnotherManager']);
			return $udp_client;
		}else{
			if($socketInfo['address']==$this->my_external_ip){
				if($this->client){
					$client = new SocketClient();
					// already have a client
					$client->on(SocketClient::EVENT_CONNECT, function(SocketClient $client){
						$client->sendMessage(static::MESSAGE_HAVE_CLIENT);
					});
					$client->on(SocketClient::EVENT_SEND, function(SocketClient $client, &$buf){
						if($buf==''){
							$client->disconnect();
						}
					});
					return $client;
				}
				$this->client = $this->createClient();
				return $this->client;
			}
		}
		return null;
	}

	public function loop(){
		$this->looper->loop();
		return parent::loop();
	}

}