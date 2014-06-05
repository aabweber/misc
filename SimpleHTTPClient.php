<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 21.05.14
 * Time: 12:52
 */

namespace misc;

class HTTPHeaders{
	/** @var string */
	private $method;
	/** @var string[string] */
	private $headers = [];
	/** @var String */
	private $url;

	static $DEFAULT_HEADERS = [
			'Server'    => 'SimpleHTTPClient.php',
			'Connection'=> 'close'
	];

	function __construct() {
		$this->headers = [];
		foreach(self::$DEFAULT_HEADERS as $var => $val){
			$this->set($var, $val);
		}
	}

	function get($name){return $this->headers[$name];}
	function getAll(){return $this->headers;}
	function getURL(){return $this->url;}
	function getMethod(){return $this->method;}

	/**
	 * @param string $name
	 * @param string $value
	 */
	function set($name, $value){
		$this->headers[$name] = $value;
	}

	function __toString() {
		$str = '';
		foreach($this->headers as $var => $val){
			$str .= "$var: $val\r\n";
		}
		return $str;
	}


	static $ALLOWED_METHODS = [SimpleHTTPClient::METHOD_GET, SimpleHTTPClient::METHOD_POST];

	/**
	 * @param string $content
	 * @return string[]
	 */
	private function getHeaderLines($content){
		$lines_ = $lines = explode("\n", trim($content));
		foreach($lines_ as $i => $line){
			if($line[0]==' ' && $i>0){
				$lines[$i-1] .= $line;
			}
		}
		return $lines;
	}

	/**
	 * @param string $content
	 * @return bool
	 */
	private function parseHeaders($content){
		$lines = $this->getHeaderLines($content);
		if(!preg_match('/(\w+)\s+(\S+)/si', $lines[0], $ms)){
			return false;
		}
		$this->method = strtoupper($ms[1]);
		$this->url = $ms[2];
		if(!in_array($this->method, self::$ALLOWED_METHODS)){
			return false;
		}
		array_splice($lines, 0, 1);
		foreach($lines as $line){
			list($var, $val) = explode(':', $line, 2);
			$this->headers[trim($var)] = trim($val);
		}
		return true;
	}

	/**
	 * @param string $content
	 * @return bool|HTTPHeaders
	 */
	static function parse($content){
		$header = new HTTPHeaders();
		if($header->parseHeaders($content)){
			return $header;
		}
		return false;
	}
}

class SimpleHTTPReply{
	static $CODE_MESSAGES = [
			100 => 'Continue',
			101 => 'Switching Protocols',
			102 => 'Processing',
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			207 => 'Multi-Status',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			307 => 'Temporary Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			422 => 'Unprocessable Entity',
			423 => 'Locked',
			424 => 'Failed Dependency',
			426 => 'Upgrade Required',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
			506 => 'Variant Also Negotiates',
			507 => 'Insufficient Storage',
			509 => 'Bandwidth Limit Exceeded',
			510 => 'Not Extended'
	];
	/** @var HTTPHeaders */
	private $headers;
	/** @var int */
	private $code;
	/** @var String */
	private $body;

	const TYPE_HTML = 'html';
	const TYPE_JSON = 'json';
	private $type = self::TYPE_HTML;

	function setType($type){$this->type = $type;}

	/**
	 * @param int $code
	 * @param string[string] $headers
	 * @param string $body
	 * @return SimpleHTTPReply
	 */
	static function build($code, $headers=[], $body=''){
		$reply = new SimpleHTTPReply();
		$reply->code = $code;
		$reply->headers = new HTTPHeaders();
		$reply->body = $body;
		foreach($headers as $var => $val){
			$reply->headers->set($var, $val);
		}
		return $reply;
	}

	function __toString() {
		$str = 'HTTP/1.1 '.$this->code.' '.self::$CODE_MESSAGES[$this->code]."\r\n";
		$str .= $this->headers;
		$str .= "Content-Length: ".strlen($this->body)."\r\n";
		$str .= 'Date: '.W3CNow()."\r\n";
		switch($this->type){
			case self::TYPE_HTML:
				$str .= "Content-Type: text/html; charset=utf-8\r\n";
				break;
			case self::TYPE_JSON:
				$str .= "Content-Type: application/json\r\n";
				break;
		}
		$str .= "\r\n";
		$str .= $this->body;
		return $str;
	}


}

class SimpleHTTPClient extends SocketClient{
	/** @var HTTPHeaders */
	private $headers;
	/** @var SimpleHTTPServer */
	private $server;
	/** @var string */
	private $body;

	const METHOD_GET        = 'GET';
	const METHOD_POST       = 'POST';

	private $reply_type     = SimpleHTTPReply::TYPE_HTML;

	/**
	 * @param SimpleHTTPServer $server
	 */
	public function __construct(SimpleHTTPServer $server) {
		$this->server = $server;
		parent::__construct();
		$this->on(SocketClient::EVENT_RECEIVE, [$this, 'awaitReceiveEnd']);
		$this->on(SocketClient::EVENT_SEND, [$this, 'awaitSendEnd']);
	}

	/**
	 * @param SocketClient $client
	 */
	private function setGlobalVariables(SocketClient $client) {
		$_SERVER['REQUEST_URI'] = $this->headers->getURL();
		$_SERVER['REMOTE_ADDR'] = $client->getAddress();
		parse_str(parse_url($this->headers->getURL(), PHP_URL_QUERY), $_GET);
		$_POST = [];
		if($this->headers->getMethod()==self::METHOD_POST){
			parse_str($this->body, $_POST);
		}
		$_REQUEST = array_merge($_GET, $_POST);
	}

	private function replyError(){
		$reply = SimpleHTTPReply::build(500);
		$this->send($reply);
	}

	function setReplyType($type){
		$this->reply_type = $type;
	}

	/**
	 * @param string $content
	 */
	private function reply($content){
		$reply = SimpleHTTPReply::build(200, [], $content);
		$reply->setType($this->reply_type);
		$this->send($reply);
	}

	function awaitReceiveEnd(SocketClient $client, &$buf){
		if(!$this->headers){
			if( ($p1=strpos($buf, "\n\r\n")) !== false || ($p2=strpos($buf, "\n\n")) !== false){
				$p = $p1 ? $p1+3 : $p2+2;
				$header_content = substr($buf, 0, $p);
				$this->headers = HTTPHeaders::parse($header_content);
				$buf = substr($buf, $p);
				if(!$this->headers){
					$this->replyError();
				}
			}
		}
		if($this->headers){
			if($this->headers->getMethod()==self::METHOD_POST){
				$length = intval($this->headers->get('Content-Length'));
				if($length!=strlen($buf)){
					return;
				}
				$this->body = $buf;
			}
			ob_start();
			$this->setGlobalVariables($client);
			$this->server->processClient($this);
			$content = ob_get_clean();
			$this->reply($content);
			$buf = '';
		}
	}

	function awaitSendEnd(SocketClient $client, &$buf){
		if($buf==''){
			$this->disconnect();
		}
	}

}