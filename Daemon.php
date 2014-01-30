<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 22.01.14
 * Time: 14:39
 */


namespace misc;

use Base\Singleton;

declare(ticks=1);

abstract class Daemon extends Singleton {

	protected static $allowedCommands   = [
		'start'     => ['func' => 'cmdStart',   'description' => 'start service', 'arguments' => []],
		'stop'      => ['func' => 'cmdStop',    'description' => 'stop service', 'arguments' => []],
		'restart'   => ['func' => 'cmdRestart', 'description' => 'restart service', 'arguments' => []],
		'reload'    => ['func' => 'cmdReload',  'description' => 'reload service config', 'arguments' => []],
		'status'    => ['func' => 'cmdStatus',  'description' => 'show service status', 'arguments' => []],
		'debug'     => ['func' => 'cmdDebug',   'description' => 'run daemon as usual script, w/o daemonization', 'arguments' => []]
	];

	protected static $configFile        = 'config.conf';

	protected static $PIDFILE           = '';

	protected static $config_file       = '';
	protected static $config            = [];
	protected static $sleep_time        = 100000;

	private static $exit_flag           = false;



	function __construct(){
		global $argv;
		self::$config_file  = dirname(realpath($_SERVER['PHP_SELF'])).'/config.conf';
		self::$PIDFILE      = '/var/run/'.basename($argv[0]).'.pid';
	}

	function getConfig(){
		return self::$config;
	}

	protected function main(){
		while(!self::$exit_flag){
			if(!$this->loop()){
				usleep(self::$sleep_time);
			}
		}
	}

	protected function stopDaemon(){
		self::$exit_flag = true;
	}


	private function printUsage(){
		global $argv;
		echo "Usage: ".$argv[0]." command\n";
		echo "Commands: \n";
		foreach(self::$allowedCommands as $cmd => $command){
			if(method_exists($this, $command['func'])){
				echo "\t".$cmd;
				$arguments_staring = '';
				foreach($command['arguments'] as $arg){
					$arguments_staring .= "$arg ";
				}
				echo trim($arguments_staring)." - ".$command['description']."\n";
			}
		}
	}

	private function getProcessPID(){
		if (!file_exists(self::$PIDFILE) || !is_file(self::$PIDFILE)){
			return null;
		}
		$pid = file_get_contents(self::$PIDFILE);
		return $pid;
	}

	private function isProcessRunning(){
		$pid = $this->getProcessPID();
		if(!$pid){
			return false;
		}
		return posix_kill($pid, 0);
	}

	protected function cmdStart(){
		if($this->isProcessRunning()){
			echo "Service already started\n";
		}else{
			$pid = pcntl_fork();
			if($pid){
				echo "started, PID: $pid\n";
				file_put_contents(self::$PIDFILE, $pid);
			}else{
				register_shutdown_function(function(){
					$this->beforeShutdown();
				});
				pcntl_signal(SIGTERM, function($signo){
					$this->beforeShutdown();
				});
				pcntl_signal(SIGUSR1, function($signo){
					$this->loadConfig();
				});
				$this->loadConfig();
				$this->main();
			}
		}
	}

	protected function cmdDebug(){
		$this->loadConfig();
		$this->main();
	}

	protected function cmdStatus(){
		$running = $this->isProcessRunning();
		if($running){
			echo "service running\n";
		}else{
			echo "service not running\n";
		}
	}

	protected function cmdStop(){
		if(!$this->isProcessRunning()){
			echo "Service is not started\n";
		}else{
			echo "stopping ...";
			$pid = $this->getProcessPID();
			posix_kill($pid, SIGTERM);
			while(posix_kill($pid, 0)){
				usleep(100);
			}
			echo "done\n";
		}
	}

	protected function cmdRestart(){
		$this->cmdStop();
		$this->cmdStart();
	}

	private function loadConfig(){
		self::$config = parse_ini_file(self::$config_file, true);
	}

	protected function cmdReload(){
		$pid = $this->getProcessPID();
		posix_kill($pid, SIGUSR1);
	}



	private function beforeShutdown(){
		if(is_file(self::$PIDFILE)){
			unlink(self::$PIDFILE);
		}
		$this->stopDaemon();
	}

	/**
	 * Входная функция демона, вызывается внешним приложение после создания экземпляра класса наследника
	 */
	function run(){
		global $argv;

		if(!isset($argv[1])){
			echo "You must specify command\n";
			$this->printUsage();
		}else{
			if(isset(self::$allowedCommands[$argv[1]])){
				if(count(self::$allowedCommands[$argv[1]]['arguments'])==count($argv)-2){
					$arguments = self::$allowedCommands[$argv[1]]['arguments'];
					$args = [];
					foreach($arguments as $i => $arg){
						$args[$arg] = $argv[$i+2];
					}
					if(method_exists($this, self::$allowedCommands[$argv[1]]['func'])){
						call_user_func([$this, self::$allowedCommands[$argv[1]]['func']], $args);
					}else{
						echo "Internal error no method ".self::$allowedCommands[$argv[1]]['func']." for dispatch command: ".$argv[1]."\n";
						$this->printUsage();
					}
				}else{
					echo "Incorrect arguments count of command: ".$argv[1]."\n";
					$this->printUsage();
				}
			}else{
				echo "Unknown command: ".$argv[1]."\n";
				$this->printUsage();
			}
		}
	}

	/**
	 * @return bool | NULL
	 */
	abstract function loop();
}
