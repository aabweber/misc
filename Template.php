<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 16.05.14
 * Time: 14:02
 */

namespace misc;


class Template {
	use Singleton;

	/** @var string $directory */
	private static $directory;

	/**
	 * @param string $directory
	 */
	function setDirectory($directory){
		self::$directory = $directory;
	}

	/**
	 * @param string $filename
	 * @param Mixed[string] $args
	 * @param bool $absolutePath
	 */
	function apply($filename, $args=[], $absolutePath = false){
		$template = ($absolutePath ? $filename : rtrim(self::$directory, '/').'/'.$filename).'.php';
		extract($args);
		ob_start();
		include $template;
		return ob_get_clean();
	}
}
