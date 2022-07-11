<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

class dbpdoEngine
{
	/**
	 * The database class to store PDO objects
	 *
	 * @var PDO
	 */
	private $db;

	/**
	 * The last query resource that ran
	 *
	 * @var PDOStatement
	 */
	public $last_query;

	/**
	 * Array used to seek through result sets. This is used when using the `fetch_field` method with a row specified.
	 *
	 * @var array Array keyed by object hashes for {@see PDOStatement} instances.
	 */
	private $seek_array = array();

	/**
	 * Connect to the database.
	 *
	 * @param string $dsn The database DSN.
	 * @param string $username The database username. (depends on DSN)
	 * @param string $password The database user's password. (depends on DSN)
	 * @param array $driver_options The databases driver options (optional)
	 *
	 * @throws Exception Thrown when failing to connect to the database.
	 */
	function __construct($dsn, $username="", $password="", $driver_options=array())
	{
		try
		{
			$driver_options =
				$driver_options +
				array(
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_EMULATE_PREPARES => false,
				)
			;

			$this->db = new PDO($dsn, $username, $password, $driver_options);
		}
		catch(PDOException $exception)
		{
			throw new Exception('Unable to connect to database server');
		}
	}

	/**
	 * Query the database.
	 *
	 * @param string $string The query SQL.
	 *
	 * @return PDOStatement The query data.
	 */
	public function query($string)
	{
		$query = $this->db->query($string, PDO::FETCH_BOTH);
		$this->last_query = $query;

		return $query;
	}

	/**
	 * Return a result array for a query.
	 *
	 * @param PDOStatement $query The query resource.
	 * @param int $resulttype One of PDO's constants: FETCH_ASSOC, FETCH_BOUND, FETCH_CLASS, FETCH_INTO, FETCH_LAZY, FETCH_NAMED, FETCH_NUM, FETCH_OBJ or FETCH_BOTH
	 * @return array The array of results.
	 */
	public function fetch_array($query, $resulttype=PDO::FETCH_BOTH)
	{
		switch($resulttype)
		{
			case PDO::FETCH_ASSOC:
			case PDO::FETCH_BOUND:
			case PDO::FETCH_CLASS:
			case PDO::FETCH_INTO:
			case PDO::FETCH_LAZY:
			case PDO::FETCH_NAMED:
			case PDO::FETCH_NUM:
			case PDO::FETCH_OBJ:
				break;
			default:
				$resulttype = PDO::FETCH_BOTH;
				break;
		}

		$hash = spl_object_hash($query);

		if(isset($this->seek_array[$hash]))
		{
			$array = $query->fetch($resulttype, $this->seek_array[$hash]['offset'], $this->seek_array[$hash]['row']);
		}
		else
		{
			$array = $query->fetch($resulttype);
		}

		return $array;
	}

	/**
	 * Moves internal row pointer to the next row
	 *
	 * @param PDOStatement $query The query resource.
	 * @param int $row The pointer to move the row to.
	 */
	public function seek($query, $row)
	{
		$hash = spl_object_hash($query);

		$this->seek_array[$hash] = array('offset' => PDO::FETCH_ORI_ABS, 'row' => $row);
	}

	/**
	 * Return the number of rows resulting from a query.
	 *
	 * @param PDOStatement $query The query resource.
	 * @return int The number of rows in the result.
	 */
	public function num_rows($query)
	{
		if(stripos($query->queryString, 'SELECT') !== false)
		{
			$query = $this->db->query($query->queryString);
			$result = $query->fetchAll();
			return count($result);
		}
		else
		{
			return $query->rowCount();
		}
	}

	/**
	 * Return the last id number of inserted data.
	 *
	 * @param string|null $name The name of the insert id to check. (Optional)
	 * @return int The id number.
	 */
	public function insert_id($name=null)
	{
		return $this->db->lastInsertId($name);
	}

	/**
	 * Return an error number.
	 *
	 * @param PDOStatement $query The query resource.
	 * @return int The error number of the current error.
	 */
	public function error_number($query)
	{
		if(!method_exists($query, "errorCode"))
		{
			return 0;
		}

		return $query->errorCode();
	}

	/**
	 * Return an error string.
	 *
	 * @param PDOStatement $query The query resource.
	 * @return array The error string of the current error.
	 */
	public function error_string($query)
	{
		if(!method_exists($query, "errorInfo"))
		{
			return $this->db->errorInfo();
		}

		return $query->errorInfo();
	}

	/**
	 * Returns the number of affected rows in a query.
	 *
	 * @param PDOStatement $query
	 * @return int The number of affected rows.
	 */
	public function affected_rows($query)
	{
		return $query->rowCount();
	}

	/**
	 * Return the number of fields.
	 *
	 * @param PDOStatement $query The query resource.
	 * @return int The number of fields.
	 */
	public function num_fields($query)
	{
		return $query->columnCount();
	}

	/**
	 * Escape a string according to the pdo escape format.
	 *
	 * @param string $string The string to be escaped.
	 * @return string The escaped string.
	 */
	public function escape_string($string)
	{
		$string = $this->db->quote($string);

		// Remove ' from the beginning of the string and at the end of the string, because we already use it in insert_query
		$string = substr($string, 1);
		$string = substr($string, 0, -1);

		return $string;
	}

	/**
	 * Return a selected attribute
	 *
	 * @param string $attribute The attribute to check.
	 * @return string The value of the attribute.
	 */
	public function get_attribute($attribute)
	{
		$attribute = $this->db->getAttribute(constant("PDO::{$attribute}"));

		return $attribute;
	}
}
