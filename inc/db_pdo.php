<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

class dbpdoEngine {

	/**
	 * The database class to store PDO objects
	 *
	 * @var PDO
	 */
	public $db;

	/**
	 * The last query resource that ran
	 *
	 * @var PDOStatement
	 */
	public $last_query = "";

	public $seek_array = array();

	public $queries = 0;

	/**
	 * Connect to the database.
	 *
	 * @param string $dsn The database DSN.
	 * @param string $username The database username. (depends on DSN)
	 * @param string $password The database user's password. (depends on DSN)
	 * @param array $driver_options The databases driver options (optional)
	 */
	function __construct($dsn, $username="", $password="", $driver_options=array())
	{
		try
		{
			$this->db = new PDO($dsn, $username, $password, $driver_options);
		}
		catch(PDOException $exception)
		{
    		die('Connection failed: '.$exception->getMessage());
		}

		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	/**
	 * Query the database.
	 *
	 * @param string $string The query SQL.
	 * @return PDOStatement The query data.
	 */
	function query($string)
	{
		++$this->queries;

		$query = $this->db->query($string, PDO::FETCH_BOTH);
		$this->last_query = $query;

		$query->guid = $this->queries;

		return $query;
	}

	/**
	 * Return a result array for a query.
	 *
	 * @param PDOStatement $query The query resource.
	 * @param int $resulttype One of PDO's constants: FETCH_ASSOC, FETCH_BOUND, FETCH_CLASS, FETCH_INTO, FETCH_LAZY, FETCH_NAMED, FETCH_NUM, FETCH_OBJ or FETCH_BOTH
	 * @return array The array of results.
	 */
	function fetch_array($query, $resulttype=PDO::FETCH_BOTH)
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

		if($this->seek_array[$query->guid])
		{
			$array = $query->fetch($resulttype, $this->seek_array[$query->guid]['offset'], $this->seek_array[$query->guid]['row']);
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
	function seek($query, $row)
	{
		$this->seek_array[$query->guid] = array('offset' => PDO::FETCH_ORI_ABS, 'row' => $row);
	}

	/**
	 * Return the number of rows resulting from a query.
	 *
	 * @param PDOStatement $query The query resource.
	 * @return int The number of rows in the result.
	 */
	function num_rows($query)
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
	 * @param string $name The name of the insert id to check. (Optional)
	 * @return int The id number.
	 */
	function insert_id($name="")
	{
		return $this->db->lastInsertId($name);
	}

	/**
	 * Return an error number.
	 *
	 * @param PDOStatement $query The query resource.
	 * @return int The error number of the current error.
	 */
	function error_number($query)
	{
		if(!method_exists($query, "errorCode"))
		{
			return 0;
		}

		$errorcode = $query->errorCode();

		return $errorcode;
	}

	/**
	 * Return an error string.
	 *
	 * @param PDOStatement $query The query resource.
	 * @return array The error string of the current error.
	 */
	function error_string($query)
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
	function affected_rows($query)
	{
		return $query->rowCount();
	}

	/**
	 * Return the number of fields.
	 *
	 * @param PDOStatement $query The query resource.
	 * @return int The number of fields.
	 */
	function num_fields($query)
	{
		return $query->columnCount();
	}

	/**
	 * Escape a string according to the pdo escape format.
	 *
	 * @param string $string The string to be escaped.
	 * @return string The escaped string.
	 */
	function escape_string($string)
	{
		$string = $this->db->quote($string);

		// Remove ' from the begginging of the string and at the end of the string, because we already use it in insert_query
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
	function get_attribute($attribute)
	{
		$attribute = $this->db->getAttribute(constant("PDO::".$attribute.""));

		return $attribute;
	}
}
