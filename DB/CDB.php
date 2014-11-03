<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 03/11/14
 * Time: 11:40
 */

namespace misc\DB;


class CDB extends DB{
    public function initInstance($host, $port, $user, $pass, $base){
        $this->engine = new CassandraEngine();
        if(!$this->connect($host, $port, $user, $pass, $base)){
            return false;
        }
        return true;
    }

} 