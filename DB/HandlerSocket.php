<?php
/**
 * Created by PhpStorm.
 * User: aabweber
 * Date: 17.09.14
 * Time: 11:46
 */

namespace misc\DB;


interface HandlerSocket {
	/* Constants */
	const PRIMARY = "PRIMARY";
	const UPDATE = "U";
	const DELETE = "D";

	/* Methods */
	/**
	 * @param string $host
	 * @param string $port
	 * @param array $options
	 */
	public function __construct( $host, $port, $options=[]);


	/**
	 * @param int $id
	 * @param string $db
	 * @param string $table
	 * @param string $index
	 * @param string $field
	 * @param array $filter
	 * @return bool
	 */
	public function openIndex( $id, $db, $table, $index, $field, $filter = [] );

	/**
	 * @param int $id
	 * @param string $operate
	 * @param array $criteria
	 * @param int $limit = 1
	 * @param int $offset = 0
	 * @param string $update = null
	 * @param array $values = array()
	 * @param array $filters = array()
	 * @param int $in_key = -1
	 * @param array $in_values = array()
	 * @return mixed
	 */
	public function executeSingle( $id, $operate, $criteria, $limit = 1, $offset = 0, $update = null, array $values = array(), array $filters = array(), $in_key = -1, array $in_values = array() );
//	public bool function auth( string $key [, string $type ] )
//  public array function executeMulti( array $args )
//  public mixed function executeUpdate( long $id, string $operate, array $criteria, array $values [, long $limit = 1, long $offset = 0, array $filters = array(), long $in_key  = -1, array $in_values = array() ] )
//  public mixed function executeDelete( long $id, string $operate, array $criteria [, long $limit = 1, long $offset = 0, array $filters = array(), long $in_key = -1, array $in_values = array() ] )

	/**
	 * @param int $id
	 * @param array $field
	 * @return mixed
	 */
	public function executeInsert( $id, array $field );

	/**
	 * @return mixed
	 */
	public function getError();
//  public HandlerSocketIndex function createIndex( long $id, string $db, string $table, string $index, string|array $fields [, array $options = array() ] )
}