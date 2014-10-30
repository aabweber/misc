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
	static function setDirectory($directory){
		self::$directory = $directory;
	}

	/**
	 * @param string $template
	 * @param bool $absolutePath
	 * @return string
	 */
	static function getFilename($template, $absolutePath = false){
		return ($absolutePath ? $template : rtrim(self::$directory, '/').'/'.$template).'.php';
	}

    static function has($template, $absolutePath = false){
        return is_file(self::getFilename($template, $absolutePath));
    }

	/**
	 * @param string $template
	 * @param Mixed[string] $args
	 * @param bool $absolutePath
	 */
	static function apply($template, $args=[], $absolutePath = false){
		$file = self::getFilename($template, $absolutePath);
		extract((array)$args);
		ob_start();
		include $file;
		return ob_get_clean();
	}
}
