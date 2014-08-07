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

	static function after($seconds, $callback){
		self::$events[] = ['time' => time()+$seconds, 'callback' => $callback];
	}

	static function check(){
		$events = self::$events;
		$time = time();
		foreach($events as $i => $event){
			if($event['time']<=$time){
				$event['callback']();
				unset(self::$events[$i]);
			}
		}
	}
} 