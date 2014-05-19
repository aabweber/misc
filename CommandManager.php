<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 27.03.14
 * Time: 11:38
 */

namespace misc;


class CommandManager {
	const PIPE_TYPE_STDIN   = 'STDIN';
	const PIPE_TYPE_STDOUT  = 'STDOUT';
	const PIPE_TYPE_STDERR  = 'STDERR';

	private $ai_stream_id = 0;

	private $processes          = []; // key - process

	private $streams            = []; // key - stream
	private $readStreams        = []; // key - id, value - stream
	private $streamReadBuffer   = []; // key - stream


	/**
	 * @param string $cmd
	 * @param callable $onRead
	 * @param callable $onFinish
	 * @param null|string $cwd
	 * @param null|array $env
	 */
	function add($cmd, $onRead, $onFinish, $cwd=null, $env=null){
		$descriptors = [
				0 => ['pipe', 'r'], // STDIN of child process
				1 => ['pipe', 'w'], // STDOUT
				2 => ['pipe', 'w'], // STDERR
		];
		$process = proc_open($cmd, $descriptors, $pipes, $cwd, $env);
		$this->addStream($process, $pipes[1], self::PIPE_TYPE_STDOUT);
		$this->addStream($process, $pipes[2], self::PIPE_TYPE_STDERR);
		$this->processes[(int)$process] = [
			'command'               => $cmd,
			self::PIPE_TYPE_STDIN   => $pipes[0],
			self::PIPE_TYPE_STDOUT  => $pipes[1],
			self::PIPE_TYPE_STDERR  => $pipes[2],
			'process'               => $process,
			'onRead'                => $onRead,
			'onFinish'              => $onFinish,
		];
		return (int)$process;
	}

	function loop(){
		$read = $this->readStreams;
		$write = $except = [];
		if($n = @stream_select($read, $write, $except, 0)){
//			echo 'selected'.$n."\n";
			$this->ReadFromStreams($read);
		}
	}

	/**
	 * @param Resource[] $read
	 */
	private function ReadFromStreams($read) {
		$processes = [];
		foreach($read as $stream){
			$buf = fread($stream, 8094);
			$this->streamReadBuffer[(int)$stream] .= $buf;
			$process = $this->streams[(int)$stream]['process'];
			$f = $this->processes[(int)$process]['onRead'];
			call_user_func_array(
				$f,
				[
					$this->processes[(int)$process],
					&$this->streamReadBuffer[(int)$stream],
					$this->streams[(int)$stream]['type']
				]
			);
			$processes[(int)$this->streams[(int)$stream]['process']] = $this->streams[(int)$stream]['process'];
		}
		foreach($processes as $process){
			if(feof($this->processes[(int)$process][self::PIPE_TYPE_STDOUT])){
				$f = $this->processes[(int)$process]['onFinish'];
				call_user_func($f, $this->processes[(int)$process]);
				$this->removeProcess($process);
			}
		}
	}

	/**
	 * @param Resource $process
	 * @param Resource $stream
	 * @param string $type
	 */
	private function addStream($process, $stream, $type) {
		$this->streams[(int)$stream] = [
				'id' => $this->ai_stream_id,
				'stream' => $stream,
				'type' => $type,
				'process' => $process,
		];
		$this->readStreams[$this->ai_stream_id++] = $stream;
		$this->streamReadBuffer[(int)$stream] = '';
		stream_set_read_buffer($stream, 0);
		stream_set_write_buffer($stream, 0);
		stream_set_blocking($stream, 0);
	}

	/**
	 * @param Resource $process
	 */
	private function removeProcess($process){
		$stdin_stream = $this->processes[(int)$process][self::PIPE_TYPE_STDIN];
		$stdout_stream = $this->processes[(int)$process][self::PIPE_TYPE_STDOUT];
		$stderr_stream = $this->processes[(int)$process][self::PIPE_TYPE_STDERR];


		unset($this->streamReadBuffer[(int)$stdout_stream]);
		unset($this->streamReadBuffer[(int)$stderr_stream]);

		unset($this->readStreams[(int)$stdout_stream]);
		unset($this->readStreams[(int)$stderr_stream]);

		unset($this->streams[(int)$stdout_stream]);
		unset($this->streams[(int)$stderr_stream]);

		fclose($stdin_stream);
		fclose($stdout_stream);
		fclose($stderr_stream);

		proc_close($process);
		unset($this->processes[(int)$process]);
	}


	public function kill($process_id) {
		proc_terminate($this->processes[$process_id]['process']);
		$this->removeProcess($this->processes[$process_id]['process']);
	}

	public function killAll(){
		$processes = $this->processes;
		foreach($processes as $process_id => $_){
			$this->kill($process_id);
		}
	}
}