<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 14.08.14
 * Time: 11:59
 */
namespace misc\CURL;

class StreamableFile extends StreamableContent{
	private $filename;
	private $fp;

	function __construct(CURL $curl, $filename) {
		parent::__construct($curl);
		$this->filename = $filename;
		$this->fp = fopen($this->filename, 'rb');
		$curl->modify(['timeout'=>0]);
	}

	function getSize() {
		return filesize($this->filename);
	}

	function read($length) {
		if(!$this->fp) return '';
		$c = fread($this->fp, $length);
		if(feof($this->fp)){
			fclose($this->fp);
			$this->fp = null;
		}
		return $c;
	}
}
