<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 02.04.14
 * Time: 17:47
 */

namespace misc;


trait Observable {
	private $event_callbacks = []; // key event name, value array of callbacks

	/**
	 * @param string $event_name
	 */
	protected function event($event_name){
		$args = func_get_args();
		array_shift($args);
		$this->event_vars_array($event_name, $args);
	}

	protected function event_vars_array($event_name, $args){
		array_unshift($args, $this);
		if(isset($this->event_callbacks[$event_name])){
			foreach($this->event_callbacks[$event_name] as $callback_info){
				call_user_func_array($callback_info['callback'], $args);
			}
		}
	}
	/**
	 * @param string $event_name
	 * @param $callback
	 */
	public function on($event_name, $callback){
		if(!isset($this->event_callbacks[$event_name])) $this->event_callbacks[$event_name] = [];
		$this->event_callbacks[$event_name][] = ['callback'=>$callback];
	}

	/**
	 * @param string $event_name
	 * @param $callback
	 */
	public function remove_listener($event_name, $callback){
		if(isset($this->event_callbacks[$event_name])){
			foreach($this->event_callbacks[$event_name] as $key => $callback_info){
				if($callback_info['callback']==$callback){
					unset($this->event_callbacks[$event_name][$key]);
				}
			}
		}
	}
}



