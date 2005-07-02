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

class bbDB {

	var $query_count = 0;
	var $querylist = array();
	var $error_reporting = 1;
	var $link;

	// Connects to the database server
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

	// Selects which database we're using for MyBB
	function select_db($database)
	{
		return @mysql_select_db($database, $this->link) or $this->dberror();
	}

	// Query the database for $string
	function query($string, $hideerr=0)
	{
		global $pagestarttime, $querytime, $db;
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
		$this->querylist[$this->query_count]['query'] = $string;
		$this->querylist[$this->query_count]['time'] = $qtime;
		return $query;
	}

	// Return a result array for  query
	function fetch_array($query, $type=MYSQL_ASSOC)
	{
		if(!$type)
		{
			$type = MYSQL_BOTH;
		}
		$array = mysql_fetch_array($query, $type);
		return $array;
	}


	// Return a specified result for a query
	function result($query, $row)
	{
		$result = mysql_result($query, $row);
		return $result;
	}

	// Return the number of rows resulting from a query
	function num_rows($query)
	{
		return mysql_num_rows($query);
	}

	// Get the last id number of previously inserted data
	function insert_id()
	{
		$id = mysql_insert_id();
		return $id;
	}

	// Close the connection to the DBMS
	function close()
	{
		@mysql_close($this->link);
	}

	// Return an error number
	function errno()
	{
		global $db;
		return mysql_errno();
	}

	function error()
	{
		return mysql_error();
	}

	// Output a database error
	function dberror($string="")
	{
		if($this->error_reporting)
		{
			echo "mySQL error: " . mysql_errno();
			echo "<br>" . mysql_error();
			echo "<br>Query: $string";
			exit;
		}
	}


	// Return how many affected rows by a query
	function affected_rows()
	{
		return mysql_affected_rows();
	}

	// Return the number of fields
	function num_fields($query)
	{
		return mysql_num_fields($query);
	}

	// Get a field name
	function field_name($query, $i)
	{
		return mysql_field_name($query, $i);
    }

	// List all tables in the database
	function list_tables($database)
	{
		return mysql_list_tables($database);
	}

	function table_exists($table)
	{
		$err = $this->error_reporting;
		$this->error_reporting = 0;
		$exists = $this->query("SELECT 1 FROM $table LIMIT 0");
		if($exists)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	// Check if a field exists in the database
	function field_exists($field, $table)
	{
		global $db;
		$this->query("SELECT COUNT($field) AS count FROM $table", 1);
		if($this->errno())
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	// Add a shutdown query
	function shutdown_query($query, $name=0)
	{
		global $shutdown_queries;
		if($name)
		{
			$shutdown_queries[$name] = $query;
		}
		else
		{
			$shutdown_queries[] = $query;
		}
	}

	// Build an insert query from an array
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
	// Build an update query from an array
	function update_query($table, $array, $where="")
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
		if($where)
		{
			$query .= " WHERE $where";
		}
		return $this->query("UPDATE $table SET $query");
		//die("UPDATE $table SET $query");
	}
}
?>