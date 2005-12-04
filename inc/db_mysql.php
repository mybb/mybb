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

	var $query_count = 0;
	var $querylist = array();
	var $error_reporting = 1;
	var $link;
	var $explain;

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
	
	// Explains the query $string
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
			echo "<br />" . mysql_error();
			echo "<br />Query: $string";
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
		$query = $this->query("SHOW TABLES LIKE '$table'");
		$exists = $this->num_rows($query);
		if($exists > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
		$this->error_reporting = $err;
	}

	// Check if a field exists in the database
	function field_exists($field, $table)
	{
		global $db;
		$err = $this->error_reporting;
		$this->error_reporting = 0;
		$this->query("SHOW COLUMNS FROM $table LIKE '$field'");
		$exists = $this->num_rows($query);
		$this->error_reporting = $err;
		if($exists > 0)
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