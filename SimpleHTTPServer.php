<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 21.05.14
 * Time: 12:13
 */

namespace misc;


class SimpleHTTPServer extends SocketDaemon{
	private $my_external_ip;
	function initInstance(){
		parent::initInstance();
		$config = $this->getConfig()['SERVER'];
		$this->my_external_ip = Network::getInterfaces($config['external_network'])[0];
		$this->addServerSocket($this->my_external_ip, $config['external_listen_port']);
	}

	function loop(){
		parent::loop();
	}

	public function processClient(SimpleHTTPClient $client) {
		$client->setReplyType(SimpleHTTPReply::TYPE_JSON);
		echo json_encode(['abc'=>@$_REQUEST['abc']]);
	}

	protected function initDaemon(){

	}

	protected function newClient($socketInfo){
		$client = new SimpleHTTPClient($this);
		return $client;
	}
} 