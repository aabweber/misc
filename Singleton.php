<?php


namespace misc;


trait Singleton {

	/** @var Singleton[] $instances */
	private static $instances = [];

	/**
	 * Returns instance of class
	 * @return static
	 */
	public static function get(){
		$className = get_called_class();
		$arguments = func_get_args();
		return self::getInstance($className, $arguments);
	}

	/**
	 * Get instance of the class by name, save it in cache
	 * @param string $className
	 * @param array $arguments
	 * @return Singleton
	 */
	final public static function getInstance($className, $arguments = []){
		if (!isset(self::$instances[$className])) {
			self::$instances[$className] = new $className();

			if(method_exists(self::$instances[$className], 'initInstance')){
				$initInstanceResult = call_user_func_array([self::$instances[$className], 'initInstance'], $arguments);
				if($initInstanceResult===false){
					self::dropInstance($className);
					return null;
				}
			}
		}
		return self::$instances[$className];
	}

	/**
	 * Drop the instance
	 */
	public static function drop(){
		$className = get_called_class();
		self::dropInstance($className);
	}

	/**
	 * Drop instance cache by class name
	 * @param string $className
	 */
	protected static function dropInstance($className){
		if (isset(self::$instances[$className])) {
			unset(self::$instances[$className]);
		}
	}

}



