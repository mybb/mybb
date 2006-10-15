<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id: db_mysql.php 1370 2006-04-16 13:47:01Z chris $
 */

class databaseEngine
{
	/**
	 * The title of this layer.
	 *
	 * @var string
	 */
	var $title = "MySQLi";

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
	 * The current version of MySQL.
	 *
	 * @var string
	 */
	var $version;

	/**
	 * The current table type in use (myisam/innodb)
	 *
	 * @var string
	 */
	var $table_type = "myisam";

	/**
	 * The table prefix used for simple select, update, insert and delete queries
	 *
	 * @var string
	 */
	var $table_prefix;

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
		$this->link = @mysqli_connect($hostname, $username, $password) or $this->dberror();
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
		return @mysqli_select_db($this->link, $database) or $this->dberror();
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
		$query = @mysqli_query($this->link, $string);
		if($this->error() && !$hideerr)
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
		if(preg_match("#^\s*select#i", $string))
		{
			$query = mysqli_query($this->link, "EXPLAIN $string");
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

			while($table = mysqli_fetch_assoc($query))
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
				"<td><span style=\"font-family: Courier; font-size: 14px;\">".htmlspecialchars_uni($string)."</span></td>\n".
				"</tr>\n".
				"<tr>\n".
				"<td bgcolor=\"#ffffff\">Query Time: ".$qtime."</td>\n".
				"</tr>\n".
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
	function fetch_array($query)
	{
		$array = mysqli_fetch_assoc($query);
		return $array;
	}

	/**
	 * Return a specific field from a query.
	 *
	 * @param resource The query ID.
	 * @param string The name of the field to return.
	 * @param int The number of the row to fetch it from.
	 */
	function fetch_field($query, $field, $row=false)
	{
		if($row !== false)
		{
			$this->data_seek($query, $row);
		}
		$array = $this->fetch_array($query);
		return $array[$field];
	}

	/**
	 * Moves internal row pointer to the next row
	 *
	 * @param resource The query ID.
	 * @param int The pointer to move the row to.
	 */
	function data_seek($query, $row)
	{
		return mysqli_data_seek($query, $row);
	}

	/**
	 * Return the number of rows resulting from a query.
	 *
	 * @param resource The query data.
	 * @return int The number of rows in the result.
	 */
	function num_rows($query)
	{
		return mysqli_num_rows($query);
	}

	/**
	 * Return the last id number of inserted data.
	 *
	 * @return int The id number.
	 */
	function insert_id()
	{
		$id = mysqli_insert_id($this->link);
		return $id;
	}

	/**
	 * Close the connection with the DBMS.
	 *
	 */
	function close()
	{
		@mysqli_close($this->link);
	}

	/**
	 * Return an error number.
	 *
	 * @return int The error number of the current error.
	 */
	function errno()
	{
		if(version_compare(phpversion(), "5", ">="))
		{
			return mysqli_errno($this->link);
		}
	}

	/**
	 * Return an error string.
	 *
	 * @return string The explanation for the current error.
	 */
	function error()
	{
		if(!$this->link) 
		{
            return mysqli_connect_error();
        } 
		else 
		{
            return mysqli_error($this->link);
        } 
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
			global $error_handler;
		
			$error_handler->error(MYBB_SQL, $this->errno()."|{$string}"); 		
		}
	}

	/**
	 * Returns the number of affected rows in a query.
	 *
	 * @return int The number of affected rows.
	 */
	function affected_rows()
	{
		return mysqli_affected_rows($this->link);
	}

	/**
	 * Return the number of fields.
	 *
	 * @param resource The query data.
	 * @return int The number of fields.
	 */
	function num_fields($query)
	{
		return mysqli_num_fields($query);
	}

	/**
	 * Lists all functions in the database.
	 *
	 * @param string The database name.
	 * @return array The table list.
	 */
	function list_tables($database)
	{
		$query = $this->query("SHOW TABLES FROM `$database`");
		while(list($table) = mysqli_fetch_array($query))
		{
			$tables[] = $table;
		}
		return $tables;
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
		$query = $this->query("SHOW TABLES LIKE '{$this->table_prefix}$table'");
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
		$err = $this->error_reporting;
		$this->error_reporting = 0;
		$query = $this->query("SHOW COLUMNS FROM {$this->table_prefix}$table LIKE '$field'");
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
	 * Performs a simple select query.
	 *
	 * @param string The table name to be queried.
	 * @param string Comma delimetered list of fields to be selected.
	 * @param string SQL formatted list of conditions to be matched.
	 * @param array List of options, order by, order direction, limit, limit start
	 */
	
	function simple_select($table, $fields="*", $conditions="", $options=array())
	{
		$query = "SELECT ".$fields." FROM ".$this->table_prefix.$table;
		if($conditions != "")
		{
			$query .= " WHERE ".$conditions;
		}
		if(isset($options['order_by']))
		{
			$query .= " ORDER BY ".$options['order_by'];
			if(isset($options['order_dir']))
			{
				$query .= " ".my_strtoupper($options['order_dir']);
			}
		}
		if(isset($options['limit_start']) && isset($options['limit']))
		{
			$query .= " LIMIT ".$options['limit_start'].", ".$options['limit'];
		}
		elseif(isset($options['limit']))
		{
			$query .= " LIMIT ".$options['limit'];
		}
		return $this->query($query);
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
		$comma = $query1 = $query2 = "";
		if(!is_array($array))
		{
			return false;
		}
		$comma = "";
		$query1 = "";
		$query2 = "";
		foreach($array as $field => $value)
		{
			$query1 .= $comma.$field;
			$query2 .= $comma."'".$value."'";
			$comma = ", ";
		}
		return $this->query("INSERT INTO ".$this->table_prefix.$table." (".$query1.") VALUES (".$query2.");");
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
		$comma = "";
		$query = "";
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
		return $this->query("UPDATE {$this->table_prefix}$table SET $query");
	}

	/**
	 * Build a delete query.
	 *
	 * @param string The table name to perform the query on.
	 * @param string An optional where clause for the query.
	 * @param string An optional limit clause for the query.
	 * @return resource The query data.
	 */
	function delete_query($table, $where="", $limit="")
	{
		$query = "";
		if(!empty($where))
		{
			$query .= " WHERE $where";
		}
		if(!empty($limit))
		{
			$query .= " LIMIT $limit";
		}
		return $this->query("DELETE FROM {$this->table_prefix}$table $query");
	}

	/**
	 * Escape a string according to the MySQL escape format.
	 *
	 * @param string The string to be escaped.
	 * @return string The escaped string.
	 */
	function escape_string($string)
	{
		$string = mysqli_real_escape_string($this->link, $string);
		return $string;
	}

	/**
	 * Gets the current version of MySQL.
	 *
	 * @return string Version of MySQL.
	 */
	function get_version()
	{
		if($this->version)
		{
			return $this->version;
		}
		$query = $this->query("SELECT VERSION() as version");
		$ver = $this->fetch_array($query);
		if($ver['version'])
		{
			$version = explode(".", $ver['version'], 3);
			$this->version = intval($version[0]).".".intval($version[1]).".".intval($version[2]);
		}
		return $this->version;
	}

	/**
	 * Optimizes a specific table.
	 *
	 * @param string The name of the table to be optimized.
	 */
	function optimize_table($table)
	{
		$this->query("OPTIMIZE TABLE ".$this->table_prefix.$table."");
	}
	
	/**
	 * Analyzes a specific table.
	 *
	 * @param string The name of the table to be analyzed.
	 */
	function analyze_table($table)
	{
		$this->query("ANALYZE TABLE ".$this->table_prefix.$table."");
	}

	/**
	 * Show the "create table" command for a specific table.
	 *
	 * @param string The name of the table.
	 * @return string The MySQL command to create the specified table.
	 */
	function show_create_table($table)
	{
		$query = $this->query("SHOW CREATE TABLE ".$this->table_prefix.$table."");
		$structure = $this->fetch_array($query);
		return $structure['Create Table'];
	}

	/**
	 * Show the "show fields from" command for a specific table.
	 *
	 * @param string The name of the table.
	 * @return string Field info for that table
	 */
	function show_fields_from($table)
	{
		$query = $this->query("SHOW FIELDS FROM ".$this->table_prefix.$table."");
		while($field = $this->fetch_array($query))
		{
			$field_info[] = $field;
		}
		return $field_info;
	}

	/**
	 * Returns whether or not the table contains a fulltext index.
	 *
	 * @param string The name of the table.
	 * @param string Optionally specify the name of the index.
	 * @return boolean True or false if the table has a fulltext index or not.
	 */
	function is_fulltext($table, $index="")
	{
		$structure = $this->show_create_table($table);
		if($index != "")
		{
			if(preg_match("#FULLTEXT KEY (`?)$index(`?)#i", $structure))
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		if(preg_match('#FULLTEXT KEY#i', $structure))
		{
			return true;
		}
		return false;
	}

	/**
	 * Returns whether or not this database engine supports fulltext indexing.
	 *
	 * @param string The table to be checked.
	 * @return boolean True or false if supported or not.
	 */

	function supports_fulltext($table)
	{
		$version = $this->get_version();
		$query = $this->query("SHOW TABLE STATUS LIKE '{$this->table_prefix}$table'");
		$status = $this->fetch_array($query);
		$table_type = my_strtoupper($status['Engine']);
		if($version >= '3.23.23' && $table_type == 'MYISAM')
		{
			return true;
		}
		return false;
	}

	/**
	 * Returns whether or not this database engine supports boolean fulltext matching.
	 *
	 * @param string The table to be checked.
	 * @return boolean True or false if supported or not.
	 */
	function supports_fulltext_boolean($table)
	{
		$version = $this->get_version();
		$supports_fulltext = $this->supports_fulltext($table);
		if($version >= '4.0.1' && $supports_fulltext == true)
		{
			return true;
		}
		return false;
	}

	/**
	 * Creates a fulltext index on the specified column in the specified table with optional index name.
	 *
	 * @param string The name of the table.
	 * @param string Name of the column to be indexed.
	 * @param string The index name, optional.
	 */
	function create_fulltext_index($table, $column, $name="")
	{
		$this->query("ALTER TABLE {$this->table_prefix}$table ADD FULLTEXT $name ($column)");
	}

	/**
	 * Drop an index with the specified name from the specified table
	 *
	 * @param string The name of the table.
	 * @param string The name of the index.
	 */
	function drop_index($table, $name)
	{
		$this->query("ALTER TABLE {$this->table_prefix}$table DROP INDEX $name");
	}

	/**
	 * Sets the table prefix used by the simple select, insert, update and delete functions
	 *
	 * @param string The new table prefix
	 */
	function set_table_prefix($prefix)
	{
		$this->table_prefix = $prefix;
	}
}
?>
