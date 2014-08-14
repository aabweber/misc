<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 14.08.14
 * Time: 11:57
 */
namespace misc\CURL;

abstract class FormDataRow{
	const RN = "\r\n";

	const TYPE_FILE     = 'FILE';
	const TYPE_VARIABLE = 'VARIABLE';

	protected $boundary;
	protected $headerSent = false;
	protected $finished = false;
	private   $header;
	private   $footer;
	private   $name;


	abstract protected function getValueSize();
	abstract protected function readValue($len);

	function __construct($name) {
		$this->name = $name;
	}

	protected function getHeader($contentDispositionExtra = []){
		$header = $this->boundary.self::RN;

		$header .= 'Content-Disposition: form-data; name="'.$this->name.'"';
		$extra = '';
		foreach($contentDispositionExtra as $name => $value){
			$extra .= $name.'='.$value.'; ';
		}
		$extra = trim($extra, ' ;');
		$header .= ($extra?'; '.$extra:'').self::RN;

		$header .= 'Content-Type: application/octet-stream'.self::RN;
		$header .= self::RN;
		return $header;
	}


	protected function getFooter(){
		return self::RN;
	}

	public function prepare($boundary) {
		$this->boundary = '--'.$boundary;
		$this->header = $this->getHeader();
		$this->footer = $this->getFooter();
	}


	public function read($len){
		$c = '';
		if(!$this->headerSent){
			$c = \Utils::shiftString($this->header, $len);
			if($this->header==''){
				$this->headerSent = true;
			}
		}
		$c .= $this->readValue($len - strlen($c));
		if(strlen($c)<$len){
			$c .= \Utils::shiftString($this->footer, $len-strlen($c));
			if($this->footer==''){
				$this->finished = true;
			}
		}
		return $c;
	}

	function isFinished(){
		return $this->finished;
	}


	public function getSize(){
		return strlen($this->header) + $this->getValueSize() + strlen($this->footer);
	}

}
