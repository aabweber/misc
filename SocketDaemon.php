<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 20.08.14
 * Time: 15:02
 */

namespace misc;


abstract class SocketDaemon extends Daemon{
	use SocketServer;
}