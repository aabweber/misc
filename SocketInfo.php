<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 20.08.14
 * Time: 12:24
 */

namespace misc;


class SocketInfo{
	/** @var resource */
	private $socket;
	/** @var string */
	private $address;
	/** @var int */
	private $port;
	/** @var int: SOCK_DGRAM|SOCK_STREAM */
	private $type;
	/** @var int: SOL_UDP:SOL_TCP */
	private $protocol;

	/**
	 * @param resource $socket
	 * @param string $address
	 * @param int $port
	 * @param int: SOCK_DGRAM|SOCK_STREAM $type
	 * @param int: SOL_UDP:SOL_TCP $protocol
	 */
	function __construct($socket, $address, $port, $type, $protocol) {
		$this->socket = $socket;
		$this->address = $address;
		$this->port = intval($port);
		$this->type = intval($type);
		$this->protocol = intval($protocol);
	}

	/**
	 * @param string $address
	 */
	public function setAddress($address) {
		$this->address = $address;
	}

	/**
	 * @return string
	 */
	public function getAddress() {
		return $this->address;
	}

	/**
	 * @param int $port
	 */
	public function setPort($port) {
		$this->port = $port;
	}

	/**
	 * @return int
	 */
	public function getPort() {
		return $this->port;
	}

	/**
	 * @param int $protocol
	 */
	public function setProtocol($protocol) {
		$this->protocol = $protocol;
	}

	/**
	 * @return int
	 */
	public function getProtocol() {
		return $this->protocol;
	}

	/**
	 * @param resource $socket
	 */
	public function setSocket($socket) {
		$this->socket = $socket;
	}

	/**
	 * @return resource
	 */
	public function getSocket() {
		return $this->socket;
	}

	/**
	 * @param int $type
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}

}
