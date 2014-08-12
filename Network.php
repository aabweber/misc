<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 28.03.14
 * Time: 13:49
 */

namespace misc;


class Network {
	/**
	 * Get IP addresses of interfaces on this computer
	 * @param string $networkMask
	 * @return array[]string
	 */
	static function getInterfaces($networkMask = '0.0.0.0/0'){
		$c = `ip addr show | grep inet | grep eth`;
		$lines = explode("\n", trim($c));
		$interfaces = [];
		list($network, $mask) = explode('/', $networkMask);
		$network = ip2long($network);
		$_ = ($network >> (32-$mask)) << (32-$mask);
		foreach($lines as $line){
			preg_match('/inet\s+(\d+\.\d+\.\d+\.\d+)/si', $line, $ms);
			$ip = ip2long($ms[1]);
			$maskedIP = ($ip >> (32-$mask)) << (32-$mask);
			if($maskedIP == $_){
				$interfaces[] = long2ip($ip);
			}
		}
		return $interfaces;
	}

	/**
	 * @param string $ip
	 * @param string $networkMask
	 * @return bool
	 */
	public static function IPInNetwork($ip, $networkMask) {
//		echo "$ip, $networkMask\n";
		list($network, $mask) = explode('/', $networkMask);
		$network = ip2long($network);
		$ip = ip2long($ip);
		return (($ip >> (32-$mask)) << (32-$mask)) == (($network >> (32-$mask)) << (32-$mask));
	}

	public static function getDomainLevel($domain){
		if(!preg_match_all('/(\.)/si', $domain, $ms)){
			return false;
		}
		return count($ms[1])+1;
	}

	public static function ping($host){
		$c = `ping -c 1 $host`;
		return strpos($c, '1 packets transmitted, 1 received')!==false;
	}
}