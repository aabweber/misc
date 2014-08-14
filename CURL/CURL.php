<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 29.01.14
 * Time: 12:43
 */

namespace misc\CURL;


use Exception;
use misc\Observable;


class CURL {
	const BUFFER_SIZE = 131072;
	use Observable;

	const METHOD_GET                = 'GET';
	const METHOD_POST               = 'POST';
	const METHOD_PUT                = 'PUT';

	const EVENT_FILE_ADDED          = 'FILE_ADDED';
	const EVENT_EXECUTED            = 'EXECUTED';
	const EVENT_REQUEST_FINISHED    = 'REQUEST_FINISHED';
	const EVENT_BEFORE_PREPARE      = 'BEFORE_PREPARE';
	const EVENT_PREPARED            = 'PREPARED';

	private $url;
	private $method                 = self::METHOD_GET;

	private $header                 = 0;
    private $headers                = [];
	private $nobody                 = false;
	private $verbose                = 0;
	private $returnTransfer         = true;
	private $followLocation         = true;
	private $connection_timeout     = 10;
	private $timeout                = 15;
	private $proxy                  = null;
	private $referer                = '';
	private $userAgent              = '';

	private $cookiesEnabled         = false;
	private $cookies                = [];

	private $formData               = [];

	private $useFormData            = false;

	/** @var null|StreamableContent  */
	private $putStream              = null;
	/** @var null|callable  */
	private $dataWriteFunction      = null;
	/** @var null|callable  */
	private $progressFunction       = null;
	/** @var resource */
	private $ch;
	/** @var MultiCURL */
	private $multiCURL;

	private $needInitInMultiCURL         = true;

	public function getHandler(){return $this->ch;}

	/**
	 * @param callable $readFunction
	 */
	public function setDataWriteFunction(callable $readFunction) {
		$this->dataWriteFunction = $readFunction;
	}
/**
	 * @param String $method
	 */
	function setMethod($method){
		$this->method = $method;
	}

	function enableCookies(){
		$this->cookiesEnabled = true;
		$this->header = 1;
	}

	function __construct($url) {
		$this->url = $url;
		$this->ch = curl_init();
	}

	function addFile($filename){
		switch($this->method){
			case self::METHOD_POST:
				$this->useFormData = true;
				$this->formData[] = new FormDataFile($filename);
				break;
			case self::METHOD_PUT:
				if($this->putStream){
					throw new Exception('Only one file can be added while method is PUT');
				}else{
					$this->setPutStream(new StreamableFile($this, $filename));
				}
				break;
			case self::METHOD_GET:
				throw new Exception('Cant add file while method is GET');
				break;
		}
		$this->event(self::EVENT_FILE_ADDED, $filename);
	}

	/**
	 * @param string $url
	 * @return StreamableURL
	 */
	function addStreamableURL($url){
		$sURL = new StreamableURL($this, $url);
		$this->setPutStream($sURL);
		return $sURL;
	}

	/**
	 * @param StreamableContent $sc
	 */
	function setPutStream(StreamableContent $sc){
		$this->setMethod(self::METHOD_PUT);
		$this->putStream = $sc;
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
		$this->event(self::EVENT_EXECUTED, $result);

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
		$this->event(self::EVENT_REQUEST_FINISHED);
		return $reply;
	}

	private function parseCookies($ch, &$data) {
		$header=substr($data, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
		$body=substr($data, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
		preg_match_all('/Set-Cookie: (.*?)=(.*?);/i', $header, $ms);
		foreach ($ms[1] as $i => $value) {
			$this->cookies[$value] = $ms[2][$i];
		};
		$data = $body;
	}

	private function getFormDataRequestFooter($boundary){
		return '--'.$boundary.'--'.FormDataFile::RN.FormDataFile::RN;
	}

	/**
	 * @param $request
	 * @return resource
	 */
	function prepare($request = [], $multiCURL = null) {
		$this->multiCURL = $multiCURL;
		$this->event(self::EVENT_BEFORE_PREPARE);
		curl_setopt($this->ch, CURLOPT_URL, $this->url);
		switch($this->method){
			case self::METHOD_POST:
				curl_setopt($this->ch, CURLOPT_POST, 1);
				if (!$this->useFormData) {
					curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($request));
				}else{
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
					$this->headers[] = 'Content-Length: '.($contentLength + strlen($this->getFormDataRequestFooter($boundary)));

					$curlFiles = $this->formData;
					$footer = $this->getFormDataRequestFooter($boundary);
					curl_setopt($this->ch, CURLOPT_READFUNCTION, function($ch, $fp, $len) use ($curlFiles, &$footer) {
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
				break;
			case self::METHOD_PUT:
				if($this->putStream){
					curl_setopt($this->ch, CURLOPT_PUT, 1);
					$size = $this->putStream->getSize();
					if($size) $this->headers[] = 'Content-Length: '.$size;
					$this->headers[] = 'Content-Type: application/octet-stream';
					$this->headers[] = 'Content-Transfer-Encoding: binary';
					$stream = $this->putStream;
					curl_setopt($this->ch, CURLOPT_READFUNCTION, function($ch, $fh, $length) use ($stream){
						return $stream->read($length);
					});
				}
				break;
			case self::METHOD_GET:
				break;
		}

		curl_setopt($this->ch, CURLOPT_BUFFERSIZE, self::BUFFER_SIZE);
		curl_setopt($this->ch, CURLOPT_HEADER, $this->header);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($this->ch, CURLOPT_NOBODY, $this->nobody);
		curl_setopt($this->ch, CURLOPT_VERBOSE, $this->verbose);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, $this->returnTransfer);
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, $this->followLocation);
		curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->connection_timeout);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($this->ch, CURLOPT_REFERER, $this->referer);
		curl_setopt($this->ch, CURLOPT_USERAGENT, $this->userAgent);

		if ($this->proxy) {
			curl_setopt($this->ch, CURLOPT_PROXY, $this->proxy);
		}

		if($this->dataWriteFunction){
			curl_setopt($this->ch, CURLOPT_WRITEFUNCTION, function($ch, $data){
				$func = $this->dataWriteFunction;
				$func($data);
				return strlen($data);
			});
		}

		if($this->progressFunction){
			curl_setopt($this->ch, CURLOPT_PROGRESSFUNCTION, function($ch, $totalDownload, $downloaded, $totalUpload, $uploaded){
				$func = $this->progressFunction;
				$func($totalDownload, $downloaded, $totalUpload, $uploaded);
			});
			curl_setopt($this->ch, CURLOPT_NOPROGRESS, false);
		}

		if ($this->cookiesEnabled) {
			$cookie = '';
			foreach ($this->cookies as $var => $val) {
				$cookie .= $var . '=' . $val . '; ';
			}
			curl_setopt($this->ch, CURLOPT_COOKIE, $cookie);
			return $this->ch;
		}
		$this->event(self::EVENT_PREPARED);
		$this->event(self::EVENT_PREPARED);
		return $this->ch;
	}

	/**
	 * @return \misc\CURL\MultiCURL
	 */
	public function getMultiCURL() {
		return $this->multiCURL;
	}

	/**
	 * @param callable $progressFunction
	 */
	public function setProgressFunction(callable $progressFunction) {
		$this->progressFunction = $progressFunction;
	}

	/**
	 * @return boolean
	 */
	public function needInitInMultiCURL() {
		return $this->needInitInMultiCURL;
	}

	/**
	 * @param $needInitInMultiCURL
	 */
	public function setNeedInitInMultiCURL($needInitInMultiCURL) {
		$this->needInitInMultiCURL = $needInitInMultiCURL;
	}

}