<?php
declare(ticks = 1);
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 22.01.14
 * Time: 14:39
 */

namespace misc;

use misc\Singleton;




abstract class Daemon{
	use Singleton;

	abstract protected function initDaemon();

	/**
	 * @return bool | NULL
	 */
	abstract protected function loop();

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
	private $commandArguments           = [];

	protected $exit_flag                = false;


	function initInstance(){
		$this->loadConfig();
		$this->registerShutdown();
	}

	function __construct(){
		global $argv;
		static::$config_file  = dirname(realpath($_SERVER['PHP_SELF'])).'/config.conf';
		static::$PIDFILE      = '/var/run/'.basename($argv[0]).'.pid';
		$currentVar = null;
		for($i=2, $l=count($argv); $i<$l; $i++){
			$elem = $argv[$i];
			if($elem[0]=='-' && $elem[1]=='-'){
				if(strpos('=', $elem)===false){
					$elem = trim($elem, '-').'=true';
				}
			}
			if(preg_match('/([\w\d-]+)=([\w\d-]+)/si', $elem, $ms)){
				$currentVar = $ms[1];
				$this->commandArguments[$currentVar] = Utils::decideType($ms[2]);
			}elseif($currentVar){
				$this->commandArguments[$currentVar] .= ' '.$elem;
			}
		}
	}

	function getConfig(){
		return static::$config;
	}

	protected function main(){
		$this->registerShutdown();
		while(!$this->exit_flag){
			$r = $this->loop();
			Timer::check();
			if(!$r){
				usleep(static::$sleep_time);
			}
		}
	}

	protected function stopDaemon(){
		$this->exit_flag = true;
	}


	private function printUsage(){
		global $argv;
		echo "Usage: ".$argv[0]." command\n";
		echo "Commands: \n";
		foreach(static::$allowedCommands as $cmd => $command){
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

	protected function getArgument($argName){
		return isset($this->commandArguments[$argName])?$this->commandArguments[$argName]:null;
	}

	private function getProcessPID(){
		if (!file_exists(static::$PIDFILE) || !is_file(static::$PIDFILE)){
			return null;
		}
		$pid = file_get_contents(static::$PIDFILE);
		return $pid;
	}

	private function isProcessRunning(){
		$pid = $this->getProcessPID();
		if(!$pid){
			return false;
		}
		return posix_kill($pid, 0);
	}


	private function registerShutdown(){
		register_shutdown_function([$this, 'beforeShutdown']);
		$exit = function(){
			exit;
		};
		pcntl_signal(SIGINT, $exit);
		pcntl_signal(SIGTERM, $exit);
		pcntl_signal(SIGUSR1, [$this, 'loadConfig']);
	}

	protected function cmdStart(){
		if($this->isProcessRunning()){
			echo "Service already started\n";
		}else{
			$pid = pcntl_fork();
			if($pid){
				if(@file_put_contents(static::$PIDFILE, $pid) === FALSE){
					posix_kill($pid, SIGTERM);
					pcntl_waitpid($pid, $st);
					die("Permission denied: You must be a root to start service\n");
				}
				echo "started, PID: $pid\n";
			}else{
				$this->loadConfig();
				$this->initDaemon();
				$this->main();
			}
		}
	}

	protected function cmdDebug(){
		$this->loadConfig();
		$this->initDaemon();
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
		if(is_file(static::$config_file)){
			static::$config = parse_ini_file(static::$config_file, true);
		}else{
			static::$config = [];
		}
	}

	protected function cmdReload(){
		$pid = $this->getProcessPID();
		posix_kill($pid, SIGUSR1);
	}



	public function beforeShutdown($signo=null){
		if(is_file(static::$PIDFILE)){
			unlink(static::$PIDFILE);
		}
		$this->stopDaemon();
//		exit;
//		if(!$this->exit_flag){
//			exit;
//		}
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
			if(isset(static::$allowedCommands[$argv[1]])){
				if(count(static::$allowedCommands[$argv[1]]['arguments'])<=count($argv)-2){
					$arguments = static::$allowedCommands[$argv[1]]['arguments'];
					$args = [];
					foreach($arguments as $i => $arg){
						$args[$arg] = $argv[$i+2];
					}
					if(method_exists($this, static::$allowedCommands[$argv[1]]['func'])){
						call_user_func([$this, static::$allowedCommands[$argv[1]]['func']], $args);
					}else{
						echo "Internal error no method ".static::$allowedCommands[$argv[1]]['func']." for dispatch command: ".$argv[1]."\n";
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


}
