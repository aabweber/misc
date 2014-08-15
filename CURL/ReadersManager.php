<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 15.08.14
 * Time: 11:46
 */

namespace misc\CURL;
class Reader{
	/** @var CURL  */
	public $curl;
	/** @var int */
	public $position;
	/** @var callable */
	public $callback;
	/** @var bool */
	public $finished        = false;
	/** @var bool */
	public $paused          = false;

	function __construct(CURL $curl, $position, callable $callback) {
		$this->curl = $curl;
		$this->position = $position;
		$this->callback = $callback;
		$this->curl->modify(['timeout'=>0]);
	}

	function onFinish(){
		$this->finished = true;
		if($this->paused){
			echo "resume on FINISH\n";
			$this->resumeSending();
		}
		call_user_func($this->callback);
	}

	function pauseSending(){
//		echo "PAUSED\n";
		curl_pause($this->curl->getHandler(), CURLPAUSE_SEND);
		$this->paused = true;
	}

	function resumeSending(){
//		echo "RESUMED\n";
		curl_pause($this->curl->getHandler(), CURLPAUSE_SEND_CONT);
		$this->paused = false;
	}
}

class ReadersManager extends StreamableContent{
	const MAX_GARBAGE_SIZE              = 1048576;
	/** @var  CURL */
	private $read_curl                  = null;
	/** @var Reader[] */
	private $readers                    = [];
	/** @var string */
	private $buffer                     = '';
	private $bufferLength               = 0;

	function __construct(CURL $curl) {
		$this->read_curl = $curl;
		$this->read_curl->modify(['timeout' => 0]);
		$this->read_curl->on(CURL::EVENT_REQUEST_FINISHED, function(){
			$this->resumeReaders();
		});
		$readersInitialized = false;
		$this->read_curl->setDataWriteFunction(function($data) use (&$readersInitialized){
			$this->buffer .= $data;
			$this->bufferLength += strlen($data);
			if(!$readersInitialized){
				$readersInitialized = true;
				foreach($this->readers as $reader){
					$this->initializeReader($reader);
				}
			}
			$this->resumeReaders();
		});
	}

	function resumeReaders() {
		foreach ($this->readers as $reader) {
			if ($reader->paused) {
				$reader->resumeSending();
			}
		}
	}

	/**
	 * @param Reader $reader
	 */
	private function initializeReader(Reader $reader) {
		$reader->curl->setPutStream($this);
		$reader->curl->on(CURL::EVENT_PREPARED, function() use($reader){
			$reader->curl->setDataReadFunction(function($length) use ($reader){
				return $this->readFunction($reader, $length);
			});
		});
		$this->read_curl->getMultiCURL()->add($reader->curl, [$reader, 'onFinish']);
	}

	private function checkBufferGarbage(){
		$minBufferPosition = $this->readers[0]->position;
		foreach($this->readers as $reader){
			if($reader->position < $minBufferPosition){
				$minBufferPosition = $reader->position;
			}
		}
		if($minBufferPosition >= self::MAX_GARBAGE_SIZE){
			$this->buffer = substr($this->buffer, $minBufferPosition);
			$this->bufferLength -= $minBufferPosition;
			foreach($this->readers as $reader){
				$reader->position -= $minBufferPosition;
			}
		}
	}

	/**
	 * @param Reader $reader
	 * @param int $length
	 */
	private $timing = 0;
	private function readFunction(Reader $reader, $length) {
		$t1 = microtime(true);
		if(!isset($this->buffer[$reader->position + $length]) && !$this->read_curl->isFinished()){
			$getLength = $this->bufferLength - $reader->position - 1;
			$reader->pauseSending();
		}else{
			$getLength = $length;
		}
		$str = substr($this->buffer, $reader->position, $getLength);
		$reader->position += $getLength;

		if($reader->position > self::MAX_GARBAGE_SIZE/2){
			$this->checkBufferGarbage();
		}
//		echo "sending $getLength/$length\n";
		$this->timing += microtime(true) - $t1;
//		echo $this->timing."\n";
		return $str;
	}

	/**
	 * @param CURL $curl
	 * @param callable $callback
	 */
	public function addReader(CURL $curl, callable $callback) {
		$reader = new Reader($curl, 0, $callback);
		$this->readers[] = $reader;
	}


	/**
	 * @return int
	 */
	function getSize() {
	}

	/**
	 * @param int $length
	 * @return string
	 */
	function read($length) {
	}

	/**
	 * @return mixed
	 */
	public function getTiming() {
		return $this->timing;
	}
}




