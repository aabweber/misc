<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 06.08.14
 * Time: 15:27
 */

namespace misc;


class Timer {
	private static $events  = [];

	static function after($seconds, callable $callback){
		self::$events[] = ['time' => time()+$seconds, 'callback' => $callback];
	}

	static function each($seconds, callable $callback){
		self::$events[] = ['time' => time()+$seconds, 'callback' => $callback, 'period' => $seconds];
	}

	static function check(){
		$events = self::$events;
		$time = time();
		foreach($events as $i => &$event){
			if($event['time']<=$time){
				$event['callback']();
				if(isset($event['period']) && $event['period']){
					$event['time'] = time() + $event['period'];
				}else{
					unset(self::$events[$i]);
				}
			}
		}
	}
} 