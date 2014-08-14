<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 14.08.14
 * Time: 11:57
 */
namespace misc\CURL;

class FormDataFile extends FormDataRow{
	static $ai_id = 0;

	private $id;
	private $handler;
	private $filename;
	private $filesize;

	function __construct($filename) {
		self::$ai_id++;
		$this->id = self::$ai_id;
		$this->filename = $filename;
		$this->handler = fopen($filename, 'r');
		if(!$this->handler){
			throw new \Exception('Can not open file "'.$filename.'"');
		}else{
			$this->filesize = filesize($filename);
		}
		parent::__construct('file'.$this->id);
	}

	protected function getHeader($contentDispositionExtra = []) {
		return parent::getHeader(['filename'=>'"'.pathinfo($this->filename, PATHINFO_BASENAME).'"']);
	}


	protected function getValueSize(){
		return $this->filesize;
	}

	function __destruct() {
		if($this->handler){
			fclose($this->handler);
		}
	}

	protected function readValue($len) {
		return fread($this->handler, $len);
	}

}
