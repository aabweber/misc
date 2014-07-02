<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 29.01.14
 * Time: 12:43
 */

namespace misc;


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
//			$header_part = substr($this->header, 0, $len);
//			$this->header = substr($this->header, strlen($header_part));
//			$c .= $header_part;
			$c = \Utils::shiftString($this->header, $len);
			if($this->header==''){
				$this->headerSent = true;
			}
		}
		$c .= $this->readValue($len - strlen($c));
		if(strlen($c)<$len){
//			$delta = $len-strlen($c);
//			$footer_part = substr($this->footer, 0, $delta);
//			$this->footer = substr($this->footer, strlen($footer_part));
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


class FormDataVariable extends FormDataRow{
	private $value;
	function __construct($name, $value) {
		$this->value = $value;
		parent::__construct($name);
	}

	protected function getValueSize(){
		return strlen($this->value);
	}
	protected function readValue($len) {
		return \Utils::shiftString($this->value, $len);
	}
}

class CURL {
	private $url;
	private $post = 1;

	private $header = 0;
    private $headers = [];
	private $nobody = false;
	private $verbose = 0;
	private $returnTransfer = true;
	private $followLocation = true;
	private $connection_timeout = 10;
	private $timeout = 15;
	private $proxy = null;
	private $referer = '';
	private $userAgent = '';

	private $cookiesEnabled = false;
	private $cookies = [];

	private $formData = [];

	private $useFormData = false;

	function enableCookies(){
		$this->cookiesEnabled = true;
		$this->header = 1;
	}

	function __construct($url) {
		$this->url = $url;
	}

	function addFile($filename){
		$this->useFormData = true;
		$this->formData[] = new FormDataFile($filename);
	}

	function addVar($name, $value){
		$this->formData[] = new FormDataVariable($name, $value);
	}

	/**
	 * @param array[string]mixed $args
	 */
	function modify($args){
		foreach($args as $name => $value){
			$this->{$name} = $value;
		}
	}

	/**
	 * @param arra[string] $request
	 * @param bool $reply_is_json
	 * @return mixed|null
	 */
	function request($request, $reply_is_json = true){
		$ch = $this->prepare($request);

		$result = curl_exec($ch);

		if($this->cookiesEnabled){
			$this->parseCookies($ch, $result);
		}
		curl_close ($ch);
		if(!$reply_is_json){
			return $result;
		}
		$reply = @json_decode($result, true);
		if(!$result || !$reply){
			error_log('CURL: Cant get content('.$this->url.') or cant json_decode: '.$result.', POST:'.print_r($request, true));
			return null;
		}
		return $reply;
	}

	private function parseCookies($ch, &$data) {
		$header=substr($data, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
		$body=substr($data, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
		preg_match_all('/Set-Cookie: (.*?)=(.*?);/i', $header, $ms);
		foreach ($ms[1] as $i => $value) {
			$this->cookies[$value] = $ms[2][$i];
		};
		print_r($this->cookies);
		$data = $body;
	}

	private function getRequestFooter($boundary){
		return '--'.$boundary.'--'.FormDataFile::RN.FormDataFile::RN;
	}
	/**
	 * @param $request
	 * @return resource
	 */
	function prepare($request = []) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->url);
		curl_setopt($ch, CURLOPT_POST, $this->post);
		if ($this->post && !$this->useFormData) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request));
		}
		if($this->useFormData){
			foreach($request as $var => $val){
				$this->addVar($var, $val);
			}
			$boundary = '---------------------'.rand(0, PHP_INT_MAX);
			$contentLength = 0;
			foreach($this->formData as $formDataRow){
				/** @var FormDataFile $formDataRow */
				$formDataRow->prepare($boundary);
				$contentLength += $formDataRow->getSize();
			}
			$this->headers[] = 'Content-Type: multipart/form-data; boundary='.$boundary;
			$this->headers[] = 'Content-Length: '.($contentLength + strlen($this->getRequestFooter($boundary)));

			$curlFiles = $this->formData;
			$footer = $this->getRequestFooter($boundary);
			curl_setopt($ch, CURLOPT_READFUNCTION, function($ch, $fp, $len) use ($curlFiles, &$footer) {
				$c = '';
				do{
					$row = null;
					foreach($curlFiles as $formDataRow){
						/** @var FormDataRow $formDataRow */
						if(!$formDataRow->isFinished()){
							$row = $formDataRow;
							break;
						}
					}
					if($row){
						$c .= $row->read($len-strlen($c));
					}
					$l = strlen($c);
				}while ($row && $l<$len);
				if($l<$len){
					$c .= \Utils::shiftString($footer, $len-$l);
				}
				return $c;
			});
		}
		curl_setopt($ch, CURLOPT_HEADER, $this->header);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($ch, CURLOPT_NOBODY, $this->nobody);
		curl_setopt($ch, CURLOPT_VERBOSE, $this->verbose);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, $this->returnTransfer);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->followLocation);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connection_timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($ch, CURLOPT_REFERER, $this->referer);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);

		if ($this->proxy) {
			curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
		}

		if ($this->cookiesEnabled) {
			$cookie = '';
			foreach ($this->cookies as $var => $val) {
				$cookie .= $var . '=' . $val . '; ';
			}
			curl_setopt($ch, CURLOPT_COOKIE, $cookie);
			return $ch;
		}
		return $ch;
	}
}