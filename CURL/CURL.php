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
	use Observable;

	const BUFFER_SIZE               = 131072; // 128Kb

	const METHOD_GET                = 'GET';
	const METHOD_POST               = 'POST';
	const METHOD_PUT                = 'PUT';

	const EVENT_FILE_ADDED          = 'FILE_ADDED';
	const EVENT_EXECUTED            = 'EXECUTED';
	const EVENT_BEFORE_PREPARE      = 'BEFORE_PREPARE';
	const EVENT_PREPARED            = 'PREPARED';
	const EVENT_REQUEST_FINISHED    = 'REQUEST_FINISHED';

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

    private $headersString;
    private $headersArray;

	private $cookiesEnabled         = false;
	private $cookies                = [];

	private $formData               = [];
	private $formDataFooter;

	private $useFormData            = false;

	/** @var null|StreamableContent  */
	private $putStream              = null;
	/** @var null|callable  */
	private $dataReadFunction       = null;
	/** @var null|callable  */
	private $dataWriteFunction      = null;
	/** @var null|callable  */
	private $progressFunction       = null;
	/** @var resource */
	private $ch;
	/** @var MultiCURL */
	private $multiCURL;
	/** @var ReadersManager */
	private $readersManager;
	/** @var CURL[] */
	private $putters                = [];
	/** @var bool */
	private $finished               = false;
	/** @var string */
	private $reply                  = '';
	private $actionInLoop           = false;
    /** @var string[string] */
    private $postFields             = [];

    function __construct($url) {
		$this->url = $url;
	}

	/**
	 * @return resource
	 */
	public function getHandler(){
		return $this->ch;
	}

	/**
	 * @return string
	 */
	public function getURL() {
		return $this->url;
	}

    /**
     * @param mixed $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

	/**
	 * @return \misc\CURL\MultiCURL
	 */
	public function getMultiCURL() {
		return $this->multiCURL;
	}


	/**
	 * @param callable $writeFunction
	 */
	public function setDataWriteFunction(callable $writeFunction) {
		$this->dataWriteFunction = $writeFunction;
	}

	/**
	 * @param callable|null $dataReadFunction
	 */
	public function setDataReadFunction($dataReadFunction) {
		$this->dataReadFunction = $dataReadFunction;
	}

	/**
	 * @param callable $progressFunction
	 */
	public function setProgressFunction(callable $progressFunction) {
		$this->progressFunction = $progressFunction;
	}

	/**
	 * @param String $method
	 */
	function setMethod($method){
		$this->method = $method;
	}

	function enableCookies(){
		$this->cookiesEnabled = true;
        $this->enableHeaders();
    }

	/**
	 * @param string $filename
	 * @throws \Exception
	 */
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
	 * @param StreamableContent $stream
	 */
	function setPutStream(StreamableContent $stream){
		$this->setMethod(self::METHOD_PUT);
		$this->putStream = $stream;
	}

	/**
	 * @param string $name
	 * @param string $value
	 */
	function addVar($name, $value){
		$this->formData[] = new FormDataVariable($name, $value);
	}


	/**
	 * @param string $url
	 * @param callable $callback
	 */
	public function addPutTo($url, callable $callback) {
		if(!$this->readersManager){
			$this->readersManager = new ReadersManager($this);
		}
		$put = new CURL($url);
		$put->setMethod(self::METHOD_PUT);
		$this->readersManager->addReader($put, $callback);
		$this->putters[] = $put;
	}


	/**
	 * @return Reader[]
	 */
	public function getReaders(){
		if(!$this->readersManager){
			return [];
		}
		return $this->readersManager->getReaders();
	}

	public function clearReaders(){
		$this->readersManager->clearReaders();
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

		curl_exec($ch);
        if($this->header){
            $this->parseHeaders();
        }
        $result = $this->getReply();
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
		$this->onFinish();
		return $reply;
	}

	/**
	 * @param resource $ch
	 * @param string $data
	 */
	private function parseCookies($ch, &$data) {
		preg_match_all('/Set-Cookie: (.*?)=(.*?);/i', $header = $this->headersString, $ms);
		foreach ($ms[1] as $i => $value) {
			$this->cookies[$value] = $ms[2][$i];
		};
	}

	/**
	 * @param string $boundary
	 * @return string
	 */
	private function getFormDataRequestFooter($boundary){
		return '--'.$boundary.'--'.FormDataFile::RN.FormDataFile::RN;
	}

	function postFormDataBodyReader($length){
		$c = '';
		do{
			$row = null;
			foreach($this->formData as $formDataRow){
				/** @var FormDataRow $formDataRow */
				if(!$formDataRow->isFinished()){
					$row = $formDataRow;
					break;
				}
			}
			if($row){
				$c .= $row->read($length-strlen($c));
			}
			$l = strlen($c);
		}while ($row && $l<$length);
		if($l<$length){
			$c .= \Utils::shiftString($this->formDataFooter, $length-$l);
		}
		return $c;
	}

	/**
	 * @param array $request
	 * @param null|MultiCURL $multiCURL
	 * @return resource
	 */
	function prepare($request = [], $multiCURL = null) {
        $request = array_merge($this->postFields, $request);
        $this->ch = curl_init();
        $this->reply = '';
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

					$this->formDataFooter = $this->getFormDataRequestFooter($boundary);
					$this->setDataReadFunction([$this, 'postFormDataBodyReader']);
				}
				break;
			case self::METHOD_PUT:
				if($this->putStream){
					curl_setopt($this->ch, CURLOPT_PUT, 1);
					$size = $this->putStream->getSize();
					if($size) $this->headers[] = 'Content-Length: '.$size;
					$this->headers[] = 'Content-Type: application/octet-stream';
					$this->headers[] = 'Content-Transfer-Encoding: binary';
					$this->setDataReadFunction([$this->putStream, 'read']);
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

		curl_setopt($this->ch, CURLOPT_WRITEFUNCTION, function($ch, $data){
			$this->setActionInLoop(true);
			if(!$this->dataWriteFunction){
				$this->reply .= $data;
			}else{
				call_user_func($this->dataWriteFunction, $data);
			}
			return strlen($data);
		});

		curl_setopt($this->ch, CURLOPT_READFUNCTION, function($ch, $fh, $length){
			$this->setActionInLoop(true);
			if(!$this->dataReadFunction){
				if(feof($fh)){
					fclose($fh);
					return '';
				}
				return fread($fh, $length);
			}else{
				return call_user_func($this->dataReadFunction, $length);
			}
		});

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
		return $this->ch;
	}

	public function onFinish() {
        if($this->reply){
            if($this->header){
                $this->parseHeaders();
            }
        }
		$this->finished = true;
		$this->event(self::EVENT_REQUEST_FINISHED);
	}

	public function isFinished(){
		return $this->finished;
	}

	/**
	 * @return string
	 */
	public function getReply() {
		return $this->reply;
	}

	/**
	 * @return boolean
	 */
	public function getActionInLoop() {
		return $this->actionInLoop;
	}

	/**
	 * @param boolean $actionInLoop
	 */
	public function setActionInLoop($actionInLoop) {
		$this->actionInLoop = $actionInLoop;
	}



    public function setHeader($var, $val){
        $this->headers[] = $var.': '.$val;
    }

    public function setPostFields($postFields){
        $this->postFields = $postFields;
    }

    public function enableHeaders()
    {
        $this->header = 1;
    }

    private function parseHeaders(){
        $this->headersString = substr($this->reply, 0, curl_getinfo($this->ch, CURLINFO_HEADER_SIZE));
        $this->reply = substr($this->reply, curl_getinfo($this->ch, CURLINFO_HEADER_SIZE));
        $this->headersArray = [];
        preg_match_all('/([^\n:]+):\s*([^\n]+)/si', $this->headersString, $ms);
        foreach($ms[1] as $i => $name){
            $value = $ms[2][$i];
            $this->headersArray[$name] = $value;
        }
    }

    /**
     * @return mixed
     */
    public function getHeadersArray(){
        return $this->headersArray;
    }
}