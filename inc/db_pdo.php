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
	 * @var object
	 */
	public $db;

	/**
	 * The last query resource that ran
	 *
	 * @var object
	 */
	public $last_query = "";

	public $seek_array = array();

	public $queries = 0;

	/**
	 * Connect to the database.
	 *
	 * @param string The database DSN.
	 * @param string The database username. (depends on DSN)
	 * @param string The database user's password. (depends on DSN)
	 * @param array The databases driver options (optional)
	 * @return boolean True on success
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

		return true;
	}

	/**
	 * Query the database.
	 *
	 * @param string The query SQL.
	 * @return resource The query data.
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
	 * @param resource The query resource.
	 * @return array The array of results.
	 */
	function fetch_array($query)
	{
		if(!is_object($query))
		{
			return;
		}

		if($this->seek_array[$query->guid])
		{
			$array = $query->fetch(PDO::FETCH_BOTH, $this->seek[$query->guid]['offset'], $this->seek[$query->guid]['row']);
		}
		else
		{
			$array = $query->fetch(PDO::FETCH_BOTH);
		}

		return $array;
	}

	/**
	 * Moves internal row pointer to the next row
	 *
	 * @param resource The query resource.
	 * @param int The pointer to move the row to.
	 */
	function seek($query, $row)
	{
		if(!is_object($query))
		{
			return;
		}

		$this->seek_array[$query->guid] = array('offset' => PDO::FETCH_ORI_ABS, 'row' => $row);
	}

	/**
	 * Return the number of rows resulting from a query.
	 *
	 * @param resource The query resource.
	 * @return int The number of rows in the result.
	 */
	function num_rows($query)
	{
		if(!is_object($query))
		{
			return;
		}

		if(is_numeric(stripos($query->queryString, 'SELECT')))
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
	 * @param string The name of the insert id to check. (Optional)
	 * @return int The id number.
	 */
	function insert_id($name="")
	{
		return $this->db->lastInsertId($name);
	}

	/**
	 * Return an error number.
	 *
	 * @param resource The query resource.
	 * @return int The error number of the current error.
	 */
	function error_number($query)
	{
		if(!is_object($query) || !method_exists($query, "errorCode"))
		{
			return;
		}

		$errorcode = $query->errorCode();

		return $errorcode;
	}

	/**
	 * Return an error string.
	 *
	 * @param resource The query resource.
	 * @return int The error string of the current error.
	 */
	function error_string($query)
	{
		if(!is_object($query) || !method_exists($query, "errorInfo"))
		{
			return $this->db->errorInfo();
		}
		return $query->errorInfo();
	}

	/**
	 * Roll back the last query.
	 *
	 * @return boolean true on success, false otherwise.
	 */
	function roll_back()
	{
		//return $this->db->rollBack();
	}

	/**
	 * Returns the number of affected rows in a query.
	 *
	 * @return int The number of affected rows.
	 */
	function affected_rows($query)
	{
		return $query->rowCount();
	}

	/**
	 * Return the number of fields.
	 *
	 * @param resource The query resource.
	 * @return int The number of fields.
	 */
	function num_fields($query)
	{
		return $query->columnCount();
	}

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
	 * @param constant The attribute to check.
	 * @return string The value of the attribute.
	 */
	function get_attribute($attribute)
	{
		$attribute = $this->db->getAttribute(constant("PDO::".$attribute.""));

		return $attribute;
	}
}

