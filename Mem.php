<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 14/11/14
 * Time: 00:29
 */

namespace misc;


use Memcache;

class Mem {
    /** @var Memcache */
    private static $link;

    static function connect($server, $port = 11211){
        self::$link = new Memcache();
        self::$link->pconnect($server, $port);
    }

    static function set($name, $value, $time = 60, $compression = 0){
        self::$link->set($name, $value, $compression, $time);
    }

    static function get($name){
        return self::$link->get($name);
    }
}