<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 21.05.14
 * Time: 12:13
 */

namespace misc;


abstract class SimpleHTTPServer extends SocketDaemon{
	private $my_ip;
	function initInstance(){
		parent::initInstance();
		$config = $this->getConfig()['HTTP_SERVER'];
		$this->my_ip = Network::getInterfaces($config['network'])[0];
		$this->addServerSocket($this->my_ip, $config['listen_port']);
	}

	function loop(){
		parent::loop();
	}

	abstract function processClient(SimpleHTTPClient $client);

	protected function initDaemon(){

	}

	protected function newClient($socketInfo){
		$client = new SimpleHTTPClient($this);
		return $client;
	}
} 