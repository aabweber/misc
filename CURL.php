<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 29.01.14
 * Time: 12:43
 */

namespace misc;

class CURLFile{
	private $handler;
	private $filename;
	private $filesize;
	private $boundary;
	private $pointer = 0;

	function __construct($filename) {
		$this->filename = $filename;
		$this->handler = fopen($filename, 'r');
		if(!$this->handler){
			throw new \Exception('Can not open file "'.$filename.'"');
		}else{
			$this->filesize = filesize($filename);
		}
	}

	private function getHeader(){
//		$str = "$boundary\r\nContent-Disposition: form-data; name='how_do_i_turn_you'\r\n\r\non\r\n$boundary--\r\n";
		return $this->boundary."\r\nContent-Disposition: form-data; name='".pathinfo($this->filename, PATHINFO_BASENAME)."'\r\n\r\n";
	}

	private function getFooter(){
		return "\r\n".$this->boundary."--\r\n";
	}

	function getSize(){
		return strlen($this->getHeader()) + $this->filesize + strlen($this->getFooter());
	}

	function __destruct() {
		if($this->handler){
			fclose($this->handler);
		}
	}

	public function setBoundary($boundary) {
		$this->boundary = $boundary;
	}

	public function read($len) {
		if($this->pointer==0){
			return $this->getHeader();
		}elseif(feof($this->handler)){
			return $this->getFooter();
		}
		$data = fread($this->handler, $len);
		$this->pointer += strlen($data);
		return $data;
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

	private $curlFiles = [];

	function enableCookies(){
		$this->cookiesEnabled = true;
		$this->header = 1;
	}

	function __construct($url) {
		$this->url = $url;
	}

	function addFile($filename){
		$this->curlFiles[] = new CURLFile($filename);
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

	/**
	 * @param $request
	 * @return resource
	 */
	function prepare($request = []) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->url);
		curl_setopt($ch, CURLOPT_POST, $this->post);
		if ($this->post && empty($this->curlFiles)) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request));
		}
		if(!empty($this->curlFiles)){
			$boundary = '-----------------------------'.rand(0, PHP_INT_MAX);
			$contentLength = 0;
			foreach($this->curlFiles as $curlFile){
				/** @var CURLFile $curlFile */
				$curlFile->setBoundary($boundary);
				curl_setopt($ch, CURLOPT_READFUNCTION, function($ch, $fp, $len) use($curlFile){
					return $curlFile->read($len);
				});
				$contentLength += $curlFile->getSize();
			}
			$this->headers[] = 'Content-Type: multipart/form-data; boundary='.$boundary;
			$this->headers[] = 'Content-Length: '.$contentLength;
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