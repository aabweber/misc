<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 28.03.14
 * Time: 15:27
 */

namespace misc;


class Looper{
	private $prev_loop_time = 0;
	private $period;
	private $ai = 0;
	private $functions = [];

	function __construct($period){
		$this->period = $period;
	}

	function add($func){
		$this->functions[$this->ai] = $func;
		return $this->ai++;
	}

	/**
	 * @param int $id
	 */
	function remove($id){
		unset($this->functions[$id]);
	}

	function loop(){
		if(microtime(true)*1000 - $this->prev_loop_time > $this->period){
			foreach($this->functions as $func){
				call_user_func($func);
			}
			$this->prev_loop_time = microtime(true)*1000;
			return true;
		}
		return false;
	}
}