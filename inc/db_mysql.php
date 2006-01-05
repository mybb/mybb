<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

class databaseEngine {

	/**
	 * A count of the number of queries.
	 *
	 * @var int
	 */
	var $query_count = 0;
	
	/**
	 * A list of the performed queries.
	 *
	 * @var array
	 */
	var $querylist = array();
	
	/**
	 * 1 if error reporting enabled, 0 if disabled.
	 *
	 * @var boolean
	 */
	var $error_reporting = 1;
	
	/**
	 * The database connection resource.
	 *
	 * @var resource
	 */
	var $link;
	
	/**
	 * Explanation of a query.
	 *
	 * @var string
	 */
	var $explain;
	
	/**
	 * Queries to perform prior to shutdown of connection.
	 *
	 * @var array
	 */
	var $shutdown_queries;

	/**
	 * Connect to the database server.
	 *
	 * @param string The database hostname.
	 * @param string The database username.
	 * @param string The database user's password.
	 * @param boolean 1 if persistent connection, 0 if not.
	 * @return resource The database connection resource.
	 */
	function connect($hostname="localhost", $username="root", $password="", $pconnect=0)
	{
		if($pconnect)
		{
			$this->link = @mysql_pconnect($hostname, $username, $password) or $this->dberror();
		}
		else
		{
			$this->link = @mysql_connect($hostname, $username, $password) or $this->dberror();
		}
		return $this->link;
	}

	/**
	 * Selects the database to use.
	 *
	 * @param string The database name.
	 * @return boolean True when successfully connected, false if not.
	 */
	function select_db($database)
	{
		return @mysql_select_db($database, $this->link) or $this->dberror();
	}

	/**
	 * Query the database.
	 *
	 * @param string The query SQL.
	 * @param boolean 1 if hide errors, 0 if not.
	 * @return resource The query data.
	 */
	function query($string, $hideerr=0)
	{
		global $pagestarttime, $querytime, $db, $mybb;
		$qtimer = new timer();
		$query = @mysql_query($string, $this->link);
		if($this->errno() && !$hideerr)
		{
			 $this->dberror($string);
			 exit;
		}
		$qtime = $qtimer->stop();
		$querytime += $qtimer->totaltime;
		$qtimer->remove();
		$this->query_count++;
		if($mybb->debug)
		{
			$this->explain_query($string, $qtime);
		}
		return $query;
	}
	
	/**
	 * Explain a query on the database.
	 *
	 * @param string The query SQL.
	 * @param string The time it took to perform the query.
	 */
	function explain_query($string, $qtime)
	{
		if(preg_match("#^select#i", $string))
		{
			$query = mysql_query("EXPLAIN $string", $this->link);
			$this->explain .= "<table style=\"background-color: #666;\" width=\"95%\" cellpadding=\"4\" cellspacing=\"1\" align=\"center\">\n".
				"<tr>\n".
				"<td colspan=\"8\" style=\"background-color: #ccc;\"><strong>#".$this->query_count." - Select Query</strong></td>\n".
				"</tr>\n".
				"<tr>\n".
				"<td colspan=\"8\" style=\"background-color: #fefefe;\"><span style=\"font-family: Courier; font-size: 14px;\">".$string."</span></td>\n".
				"</tr>\n".
				"<tr style=\"background-color: #efefef;\">\n".
				"<td><strong>table</strong></td>\n".
				"<td><strong>type</strong></td>\n".
				"<td><strong>possible_keys</strong></td>\n".
				"<td><strong>key</strong></td>\n".
				"<td><strong>key_len</strong></td>\n".
				"<td><strong>ref</strong></td>\n".
				"<td><strong>rows</strong></td>\n".
				"<td><strong>Extra</strong></td>\n".
				"</tr>\n";

			while($table = mysql_fetch_array($query))
			{
				$this->explain .=
					"<tr bgcolor=\"#ffffff\">\n".
					"<td>".$table['table']."</td>\n".
					"<td>".$table['type']."</td>\n".
					"<td>".$table['possible_keys']."</td>\n".
					"<td>".$table['key']."</td>\n".
					"<td>".$table['key_len']."</td>\n".
					"<td>".$table['ref']."</td>\n".
					"<td>".$table['rows']."</td>\n".
					"<td>".$table['Extra']."</td>\n".
					"</tr>\n";
			}
			$this->explain .=
				"<tr>\n".
				"<td colspan=\"8\" style=\"background-color: #fff;\">Query Time: ".$qtime."</td>\n".
				"</tr>\n".
				"</table>\n".
				"<br />\n";
		}
		else
		{
			$this->explain .= "<table style=\"background-color: #666;\" width=\"95%\" cellpadding=\"4\" cellspacing=\"1\" align=\"center\">\n".
				"<tr>\n".
				"<td style=\"background-color: #ccc;\"><strong>#".$this->query_count." - Write Query</strong></td>\n".
				"</tr>\n".
				"<tr style=\"background-color: #fefefe;\">\n".
				"<td><span style=\"font-family: Courier; font-size: 14px;\">".$string."</span></td>\n".
				"</tr>\n".
				"<tr>\n".
				"<td bgcolor=\"#ffffff\">Query Time: ".$qtime."</td>\n".
				"</tr>\n".
				"</table>\n".
				"</table>\n".
				"<br />\n";
		}

		$this->querylist[$this->query_count]['query'] = $string;
		$this->querylist[$this->query_count]['time'] = $qtime;
	}


	/**
	 * Return a result array for a query.
	 *
	 * @param resource The query data.
	 * @param constant The type of array to return.
	 * @return array The array of results.
	 */
	function fetch_array($query, $type=MYSQL_ASSOC)
	{
		if(!$type)
		{
			$type = MYSQL_BOTH;
		}
		$array = mysql_fetch_array($query, $type);
		return $array;
	}


	/**
	 * Return a specified result for a quer.
	 *
	 * @param resource The query data.
	 * @param string The row to return.
	 * @return string The result of the query.
	 */
	function result($query, $row)
	{
		$result = mysql_result($query, $row);
		return $result;
	}

	/**
	 * Return the number of rows resulting from a query.
	 *
	 * @param resource The query data.
	 * @return int The number of rows in the result.
	 */
	function num_rows($query)
	{
		return mysql_num_rows($query);
	}

	/**
	 * Return the last id number of inserted data.
	 *
	 * @return int The id number.
	 */
	function insert_id()
	{
		$id = mysql_insert_id();
		return $id;
	}

	/**
	 * Close the connection with the DBMS.
	 *
	 */
	function close()
	{
		@mysql_close($this->link);
	}

	/**
	 * Return an error number.
	 *
	 * @return int The error number of the current error.
	 */
	function errno()
	{
		return mysql_errno();
	}

	/**
	 * Return an error string.
	 *
	 * @return string The explanation for the current error.
	 */
	function error()
	{
		return mysql_error();
	}

	/**
	 * Output a database error.
	 *
	 * @param string The string to present as an error.
	 */
	function dberror($string="")
	{
		if($this->error_reporting)
		{
			echo "mySQL error: " . mysql_errno();
			echo "<br />" . mysql_error();
			echo "<br />Query: $string";
			exit;
		}
	}


	/**
	 * Returns the number of affected rows in a query.
	 *
	 * @return int The number of affected rows.
	 */
	function affected_rows()
	{
		return mysql_affected_rows();
	}

	/**
	 * Return the number of fields.
	 *
	 * @param resource The query data.
	 * @return int The number of fields.
	 */
	function num_fields($query)
	{
		return mysql_num_fields($query);
	}

	/**
	 * Return a field name.
	 *
	 * @param resource The query data.
	 * @param int The field offset.
	 * @return string The field name.
	 */
	function field_name($query, $i)
	{
		return mysql_field_name($query, $i);
    }

	/**
	 * Lists all functions in the database.
	 *
	 * @param string The database name.
	 * @return array The table list.
	 */
	function list_tables($database)
	{
		return mysql_list_tables($database);
	}

	/**
	 * Check if a table exists in a database.
	 *
	 * @param string The table name.
	 * @return boolean True when exists, false if not.
	 */
	function table_exists($table)
	{
		$err = $this->error_reporting;
		$this->error_reporting = 0;
		$query = $this->query("SHOW TABLES LIKE '$table'");
		$exists = $this->num_rows($query);
		$this->error_reporting = $err;
		if($exists > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Check if a field exists in a database.
	 *
	 * @param string The field name.
	 * @param string The table name.
	 * @return boolean True when exists, false if not.
	 */
	function field_exists($field, $table)
	{
		global $db;
		$err = $this->error_reporting;
		$this->error_reporting = 0;
		$query = $this->query("SHOW COLUMNS FROM $table LIKE '$field'");
		$exists = $this->num_rows($query);
		$this->error_reporting = $err;
		if($exists > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Add a shutdown query.
	 *
	 * @param resource The query data.
	 * @param string An optional name for the query.
	 */
	function shutdown_query($query, $name=0)
	{
		if($name)
		{
			$this->shutdown_queries[$name] = $query;
		}
		else
		{
			$this->shutdown_queries[] = $query;
		}
	}

	/**
	 * Build an insert query from an array.
	 *
	 * @param string The table name to perform the query on.
	 * @param array An array of fields and their values.
	 * @return resource The query data.
	 */
	function insert_query($table, $array)
	{
		if(!is_array($array))
		{
			return false;
		}
		foreach($array as $field => $value)
		{
			$query1 .= $comma.$field;
			$query2 .= $comma."'".$value."'";
			$comma = ", ";
		}
		return $this->query("INSERT INTO ".$table." (".$query1.") VALUES (".$query2.");");
	}
	
	/**
	 * Build an update query from an array.
	 *
	 * @param string The table name to perform the query on.
	 * @param array An array of fields and their values.
	 * @param string An optional where clause for the query.
	 * @param string An optional limit clause for the query.
	 * @return resource The query data.
	 */
	function update_query($table, $array, $where="", $limit="")
	{
		if(!is_array($array))
		{
			return false;
		}
		foreach($array as $field => $value)
		{
			$query .= $comma.$field."='".$value."'";
			$comma = ", ";
		}
		if(!empty($where))
		{
			$query .= " WHERE $where";
		}
		if(!empty($limit))
		{
			$query .= " LIMIT $limit";
		}
		return $this->query("UPDATE $table SET $query");
	}
	
	/**
	 * Escape a string according to the MySQL escape format.
	 *
	 * @param string The string to be escaped.
	 * @return string The escaped string.
	 */
	function escape_string($string)
	{
		$string = mysql_real_escape_string($string);
		return $string;
	}
}
?>