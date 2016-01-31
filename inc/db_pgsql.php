<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

class DB_PgSQL implements DB_Base
{
	/**
	 * The title of this layer.
	 *
	 * @var string
	 */
	public $title = "PostgreSQL";

	/**
	 * The short title of this layer.
	 *
	 * @var string
	 */
	public $short_title = "PostgreSQL";

	/**
	 * A count of the number of queries.
	 *
	 * @var int
	 */
	public $query_count = 0;

	/**
	 * A list of the performed queries.
	 *
	 * @var array
	 */
	public $querylist = array();

	/**
	 * 1 if error reporting enabled, 0 if disabled.
	 *
	 * @var boolean
	 */
	public $error_reporting = 1;

	/**
	 * The read database connection resource.
	 *
	 * @var resource
	 */
	public $read_link;

	/**
	 * The write database connection resource
	 *
	 * @var resource
	 */
	public $write_link;

	/**
	 * Reference to the last database connection resource used.
	 *
	 * @var resource
	 */
	public $current_link;

	/**
	 * Explanation of a query.
	 *
	 * @var string
	 */
	public $explain;

	/**
	 * The current version of PgSQL.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * The current table type in use (myisam/innodb)
	 *
	 * @var string
	 */
	public $table_type = "myisam";

	/**
	 * The table prefix used for simple select, update, insert and delete queries
	 *
	 * @var string
	 */
	public $table_prefix;

	/**
	 * The temperary connection string used to store connect details
	 *
	 * @var string
	 */
	public $connect_string;

	/**
	 * The last query run on the database
	 *
	 * @var string
	 */
	public $last_query;

	/**
	 * The current value of pconnect (0/1).
	 *
	 * @var string
	 */
	public $pconnect;

	/**
	 * The engine used to run the SQL database
	 *
	 * @var string
	 */
	public $engine = "pgsql";

	/**
	 * Weather or not this engine can use the search functionality
	 *
	 * @var boolean
	 */
	public $can_search = true;

	/**
	 * The database encoding currently in use (if supported)
	 *
	 * @var string
	 */
	public $db_encoding = "utf8";

	/**
	 * The time spent performing queries
	 *
	 * @var float
	 */
	public $query_time = 0;

	/**
	 * The last result run on the database (needed for affected_rows)
	 *
	 * @var resource
	 */
	public $last_result;

	/**
	 * Connect to the database server.
	 *
	 * @param array $config Array of DBMS connection details.
	 * @return resource The DB connection resource. Returns false on failure
	 */
	function connect($config)
	{
		// Simple connection to one server
		if(array_key_exists('hostname', $config))
		{
			$connections['read'][] = $config;
		}
		else
		// Connecting to more than one server
		{
			// Specified multiple servers, but no specific read/write servers
			if(!array_key_exists('read', $config))
			{
				foreach($config as $key => $settings)
				{
					if(is_int($key)) $connections['read'][] = $settings;
				}
			}
			// Specified both read & write servers
			else
			{
				$connections = $config;
			}
		}

		$this->db_encoding = $config['encoding'];

		// Actually connect to the specified servers
		foreach(array('read', 'write') as $type)
		{
			if(!isset($connections[$type]) || !is_array($connections[$type]))
			{
				break;
			}

			if(array_key_exists('hostname', $connections[$type]))
			{
				$details = $connections[$type];
				unset($connections);
				$connections[$type][] = $details;
			}

			// Shuffle the connections
			shuffle($connections[$type]);

			// Loop-de-loop
			foreach($connections[$type] as $single_connection)
			{
				$connect_function = "pg_connect";
				if(isset($single_connection['pconnect']))
				{
					$connect_function = "pg_pconnect";
				}

				$link = $type."_link";

				get_execution_time();

				$this->connect_string = "dbname={$single_connection['database']} user={$single_connection['username']}";

				if(strpos($single_connection['hostname'], ':') !== false)
				{
					list($single_connection['hostname'], $single_connection['port']) = explode(':', $single_connection['hostname']);
				}

				if($single_connection['port'])
				{
					$this->connect_string .= " port={$single_connection['port']}";
				}

				if($single_connection['hostname'] != "")
				{
					$this->connect_string .= " host={$single_connection['hostname']}";
				}

				if($single_connection['password'])
				{
					$this->connect_string .= " password={$single_connection['password']}";
				}
				$this->$link = @$connect_function($this->connect_string);

				$time_spent = get_execution_time();
				$this->query_time += $time_spent;

				// Successful connection? break down brother!
				if($this->$link)
				{
					$this->connections[] = "[".strtoupper($type)."] {$single_connection['username']}@{$single_connection['hostname']} (Connected in ".format_time_duration($time_spent).")";
					break;
				}
				else
				{
					$this->connections[] = "<span style=\"color: red\">[FAILED] [".strtoupper($type)."] {$single_connection['username']}@{$single_connection['hostname']}</span>";
				}
			}
		}

		// No write server was specified (simple connection or just multiple servers) - mirror write link
		if(!array_key_exists('write', $connections))
		{
			$this->write_link = &$this->read_link;
		}

		// Have no read connection?
		if(!$this->read_link)
		{
			$this->error("[READ] Unable to connect to PgSQL server");
			return false;
		}
		// No write?
		else if(!$this->write_link)
		{
			$this->error("[WRITE] Unable to connect to PgSQL server");
			return false;
		}

		$this->current_link = &$this->read_link;
		return $this->read_link;
	}

	/**
	 * Query the database.
	 *
	 * @param string $string The query SQL.
	 * @param boolean|int $hide_errors 1 if hide errors, 0 if not.
	 * @param integer $write_query 1 if executes on slave database, 0 if not.
	 * @return resource The query data.
	 */
	function query($string, $hide_errors=0, $write_query=0)
	{
		global $mybb;

		$string = preg_replace("#LIMIT (\s*)([0-9]+),(\s*)([0-9]+)$#im", "LIMIT $4 OFFSET $2", trim($string));

		$this->last_query = $string;

		get_execution_time();

		if(strtolower(substr(ltrim($string), 0, 5)) == 'alter')
		{
			$string = preg_replace("#\sAFTER\s([a-z_]+?)(;*?)$#i", "", $string);
			if(strstr($string, 'CHANGE') !== false)
			{
				$string = str_replace(' CHANGE ', ' ALTER ', $string);
			}
		}

		if($write_query && $this->write_link)
		{
			while(pg_connection_busy($this->write_link));
			$this->current_link = &$this->write_link;
			pg_send_query($this->current_link, $string);
			$query = pg_get_result($this->current_link);
		}
		else
		{
			while(pg_connection_busy($this->read_link));
			$this->current_link = &$this->read_link;
			pg_send_query($this->current_link, $string);
			$query = pg_get_result($this->current_link);
		}

		if((pg_result_error($query) && !$hide_errors))
		{
			$this->error($string, $query);
			exit;
		}

		$query_time = get_execution_time();
		$this->query_time += $query_time;
		$this->query_count++;
		$this->last_result = $query;

		if($mybb->debug_mode)
		{
			$this->explain_query($string, $query_time);
		}
		return $query;
	}

	/**
	 * Execute a write query on the slave database
	 *
	 * @param string $query The query SQL.
	 * @param boolean|int $hide_errors 1 if hide errors, 0 if not.
	 * @return resource The query data.
	 */
	function write_query($query, $hide_errors=0)
	{
		return $this->query($query, $hide_errors, 1);
	}

	/**
	 * Explain a query on the database.
	 *
	 * @param string $string The query SQL.
	 * @param string $qtime The time it took to perform the query.
	 */
	function explain_query($string, $qtime)
	{
		if(preg_match("#^\s*select#i", $string))
		{
			$query = pg_query($this->current_link, "EXPLAIN $string");
			$this->explain .= "<table style=\"background-color: #666;\" width=\"95%\" cellpadding=\"4\" cellspacing=\"1\" align=\"center\">\n".
				"<tr>\n".
				"<td colspan=\"8\" style=\"background-color: #ccc;\"><strong>#".$this->query_count." - Select Query</strong></td>\n".
				"</tr>\n".
				"<tr>\n".
				"<td colspan=\"8\" style=\"background-color: #fefefe;\"><span style=\"font-family: Courier; font-size: 14px;\">".htmlspecialchars_uni($string)."</span></td>\n".
				"</tr>\n".
				"<tr style=\"background-color: #efefef;\">\n".
				"<td><strong>Info</strong></td>\n".
				"</tr>\n";

			while($table = pg_fetch_assoc($query))
			{
				$this->explain .=
					"<tr bgcolor=\"#ffffff\">\n".
					"<td>".$table['QUERY PLAN']."</td>\n".
					"</tr>\n";
			}
			$this->explain .=
				"<tr>\n".
				"<td colspan=\"8\" style=\"background-color: #fff;\">Query Time: ".format_time_duration($qtime)."</td>\n".
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
				"<td bgcolor=\"#ffffff\">Query Time: ".format_time_duration($qtime)."</td>\n".
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
	 * @param resource $query The query ID.
	 * @param int $resulttype The type of array to return. Either PGSQL_NUM, PGSQL_BOTH or PGSQL_ASSOC
	 * @return array The array of results. Note that all fields are returned as string: http://php.net/manual/en/function.pg-fetch-array.php
	 */
	function fetch_array($query, $resulttype=PGSQL_ASSOC)
	{
		switch($resulttype)
		{
			case PGSQL_NUM:
			case PGSQL_BOTH:
				break;
			default:
				$resulttype = PGSQL_ASSOC;
				break;
		}

		$array = pg_fetch_array($query, NULL, $resulttype);

		return $array;
	}

	/**
	 * Return a specific field from a query.
	 *
	 * @param resource $query The query ID.
	 * @param string $field The name of the field to return.
	 * @param int|bool The number of the row to fetch it from.
	 * @return string|bool|null As per http://php.net/manual/en/function.pg-fetch-result.php
	 */
	function fetch_field($query, $field, $row=false)
	{
		if($row === false)
		{
			$array = $this->fetch_array($query);
			return $array[$field];
		}
		else
		{
			return pg_fetch_result($query, $row, $field);
		}
	}

	/**
	 * Moves internal row pointer to the next row
	 *
	 * @param resource $query The query ID.
	 * @param int $row The pointer to move the row to.
	 * @return bool
	 */
	function data_seek($query, $row)
	{
		return pg_result_seek($query, $row);
	}

	/**
	 * Return the number of rows resulting from a query.
	 *
	 * @param resource $query The query ID.
	 * @return int The number of rows in the result.
	 */
	function num_rows($query)
	{
		return pg_num_rows($query);
	}

	/**
	 * Return the last id number of inserted data.
	 *
	 * @return int The id number.
	 */
	function insert_id()
	{
		$this->last_query = str_replace(array("\r", "\t"), '', $this->last_query);
		$this->last_query = str_replace("\n", ' ', $this->last_query);
		preg_match('#INSERT INTO ([a-zA-Z0-9_\-]+)#i', $this->last_query, $matches);

		$table = $matches[1];

		$query = $this->query("SELECT column_name FROM information_schema.constraint_column_usage WHERE table_name = '{$table}' and constraint_name = '{$table}_pkey' LIMIT 1");
		$field = $this->fetch_field($query, 'column_name');

		// Do we not have a primary field?
		if(!$field)
		{
			return 0;
		}

		$id = $this->write_query("SELECT currval(pg_get_serial_sequence('{$table}', '{$field}')) AS last_value");
		return $this->fetch_field($id, 'last_value');
	}

	/**
	 * Close the connection with the DBMS.
	 *
	 */
	function close()
	{
		@pg_close($this->read_link);
		if($this->write_link)
		{
			@pg_close($this->write_link);
		}
	}

	/**
	 * Return an error number.
	 *
	 * @param resource $query
	 * @return int The error number of the current error.
	 */
	function error_number($query=null)
	{
		if($query != null || !function_exists("pg_result_error_field"))
		{
			return 0;
		}

		return pg_result_error_field($query, PGSQL_DIAG_SQLSTATE);
	}

	/**
	 * Return an error string.
	 *
	 * @param resource $query
	 * @return string The explanation for the current error.
	 */
	function error_string($query=null)
	{
		if($query != null)
		{
			return pg_result_error($query);
		}

		if($this->current_link)
		{
			return pg_last_error($this->current_link);
		}
		else
		{
			return pg_last_error();
		}
	}

	/**
	 * Output a database error.
	 *
	 * @param string $string The string to present as an error.
	 * @param resource $query
	 */
	function error($string="", $query=null)
	{
		if($this->error_reporting)
		{
			if(class_exists("errorHandler"))
			{
				global $error_handler;

				if(!is_object($error_handler))
				{
					require_once MYBB_ROOT."inc/class_error.php";
					$error_handler = new errorHandler();
				}

				$error = array(
					"error_no" => $this->error_number($query),
					"error" => $this->error_string($query),
					"query" => $string
				);
				$error_handler->error(MYBB_SQL, $error);
			}
			else
			{
				trigger_error("<strong>[SQL] [".$this->error_number()."] ".$this->error_string()."</strong><br />{$string}", E_USER_ERROR);
			}
		}
	}

	/**
	 * Returns the number of affected rows in a query.
	 *
	 * @return int The number of affected rows.
	 */
	function affected_rows()
	{
		return pg_affected_rows($this->last_result);
	}

	/**
	 * Return the number of fields.
	 *
	 * @param resource $query The query ID.
	 * @return int The number of fields.
	 */
	function num_fields($query)
	{
		return pg_num_fields($query);
	}

	/**
	 * Lists all tables in the database.
	 *
	 * @param string $database The database name.
	 * @param string $prefix Prefix of the table (optional)
	 * @return array The table list.
	 */
	function list_tables($database, $prefix='')
	{
		if($prefix)
		{
			$query = $this->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE '".$this->escape_string($prefix)."%'");
		}
		else
		{
			$query = $this->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
		}

		$tables = array();
		while($table = $this->fetch_array($query))
		{
			$tables[] = $table['table_name'];
		}

		return $tables;
	}

	/**
	 * Check if a table exists in a database.
	 *
	 * @param string $table The table name.
	 * @return boolean True when exists, false if not.
	 */
	function table_exists($table)
	{
		// Execute on master server to ensure if we've just created a table that we get the correct result
		$query = $this->write_query("SELECT COUNT(table_name) as table_names FROM information_schema.tables WHERE table_schema = 'public' AND table_name='{$this->table_prefix}{$table}'");

		$exists = $this->fetch_field($query, 'table_names');

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
	 * @param string $field The field name.
	 * @param string $table The table name.
	 * @return boolean True when exists, false if not.
	 */
	function field_exists($field, $table)
	{
		$query = $this->write_query("SELECT COUNT(column_name) as column_names FROM information_schema.columns WHERE table_name='{$this->table_prefix}{$table}' AND column_name='{$field}'");

		$exists = $this->fetch_field($query, "column_names");

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
	 * @param resource $query The query data.
	 * @param string $name An optional name for the query.
	 */
	function shutdown_query($query, $name="")
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

	/**
	 * Performs a simple select query.
	 *
	 * @param string $table The table name to be queried.
	 * @param string $fields Comma delimetered list of fields to be selected.
	 * @param string $conditions SQL formatted list of conditions to be matched.
	 * @param array $options List of options: group by, order by, order direction, limit, limit start.
	 * @return resource The query data.
	 */
	function simple_select($table, $fields="*", $conditions="", $options=array())
	{
		$query = "SELECT ".$fields." FROM ".$this->table_prefix.$table;
		if($conditions != "")
		{
			$query .= " WHERE ".$conditions;
		}

		if(isset($options['group_by']))
		{
			$query .= " GROUP BY ".$options['group_by'];
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
		else if(isset($options['limit']))
		{
			$query .= " LIMIT ".$options['limit'];
		}

		return $this->query($query);
	}

	/**
	 * Build an insert query from an array.
	 *
	 * @param string $table The table name to perform the query on.
	 * @param array $array An array of fields and their values.
	 * @param boolean $insert_id Whether or not to return an insert id. True by default
	 * @return int|bool The insert ID if available. False on failure and true if $insert_id is false
	 */
	function insert_query($table, $array, $insert_id=true)
	{
		global $mybb;

		if(!is_array($array))
		{
			return false;
		}

		foreach($array as $field => $value)
		{
			if(isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field])
			{
				$array[$field] = $value;
			}
			else
			{
				$array[$field] = $this->quote_val($value);
			}
		}

		$fields = implode(",", array_keys($array));
		$values = implode(",", $array);
		$this->write_query("
			INSERT
			INTO {$this->table_prefix}{$table} (".$fields.")
			VALUES (".$values.")
		");

		if($insert_id != false)
		{
			return $this->insert_id();
		}
		else
		{
			return true;
		}
	}

	/**
	 * Build one query for multiple inserts from a multidimensional array.
	 *
	 * @param string $table The table name to perform the query on.
	 * @param array $array An array of inserts.
	 * @return void
	 */
	function insert_query_multiple($table, $array)
	{
		global $mybb;

		if(!is_array($array))
		{
			return;
		}
		// Field names
		$fields = array_keys($array[0]);
		$fields = implode(",", $fields);

		$insert_rows = array();
		foreach($array as $values)
		{
			foreach($values as $field => $value)
			{
				if(isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field])
				{
					$values[$field] = $value;
				}
				else
				{
					$values[$field] = $this->quote_val($value);
				}
			}
			$insert_rows[] = "(".implode(",", $values).")";
		}
		$insert_rows = implode(", ", $insert_rows);

		$this->write_query("
			INSERT
			INTO {$this->table_prefix}{$table} ({$fields})
			VALUES {$insert_rows}
		");
	}

	/**
	 * Build an update query from an array.
	 *
	 * @param string $table The table name to perform the query on.
	 * @param array $array An array of fields and their values.
	 * @param string $where An optional where clause for the query.
	 * @param string $limit An optional limit clause for the query.
	 * @param boolean $no_quote An option to quote incoming values of the array.
	 * @return resource The query data.
	 */
	function update_query($table, $array, $where="", $limit="", $no_quote=false)
	{
		global $mybb;

		if(!is_array($array))
		{
			return false;
		}

		$comma = "";
		$query = "";
		$quote = "'";

		if($no_quote == true)
		{
			$quote = "";
		}

		foreach($array as $field => $value)
		{
			if(isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field])
			{
				$query .= $comma.$field."={$value}";
			}
			else
			{
				$quoted_value = $this->quote_val($value, $quote);

				$query .= $comma.$field."={$quoted_value}";
			}
			$comma = ', ';
		}
		if(!empty($where))
		{
			$query .= " WHERE $where";
		}
		return $this->write_query("
			UPDATE {$this->table_prefix}$table
			SET $query
		");
	}

	/**
	 * @param int|string $value
	 * @param string $quote
	 *
	 * @return int|string
	 */
	private function quote_val($value, $quote="'")
	{
		if(is_int($value))
		{
			$quoted = $value;
		}
		else
		{
			$quoted = $quote . $value . $quote;
		}

		return $quoted;
	}

	/**
	 * Build a delete query.
	 *
	 * @param string $table The table name to perform the query on.
	 * @param string $where An optional where clause for the query.
	 * @param string $limit An optional limit clause for the query.
	 * @return resource The query data.
	 */
	function delete_query($table, $where="", $limit="")
	{
		$query = "";
		if(!empty($where))
		{
			$query .= " WHERE $where";
		}

		return $this->write_query("
			DELETE
			FROM {$this->table_prefix}$table
			$query
		");
	}

	/**
	 * Escape a string according to the pg escape format.
	 *
	 * @param string $string The string to be escaped.
	 * @return string The escaped string.
	 */
	function escape_string($string)
	{
		if(function_exists("pg_escape_string"))
		{
			$string = pg_escape_string($string);
		}
		else
		{
			$string = addslashes($string);
		}
		return $string;
	}

	/**
	 * Frees the resources of a PgSQL query.
	 *
	 * @param resource $query The query to destroy.
	 * @return boolean Returns true on success, false on failure
	 */
	function free_result($query)
	{
		return pg_free_result($query);
	}

	/**
	 * Escape a string used within a like command.
	 *
	 * @param string $string The string to be escaped.
	 * @return string The escaped string.
	 */
	function escape_string_like($string)
	{
		return $this->escape_string(str_replace(array('%', '_') , array('\\%' , '\\_') , $string));
	}

	/**
	 * Gets the current version of PgSQL.
	 *
	 * @return string Version of PgSQL.
	 */
	function get_version()
	{
		if($this->version)
		{
			return $this->version;
		}

		$version = pg_version($this->current_link);

  		$this->version = $version['server'];

		return $this->version;
	}

	/**
	 * Optimizes a specific table.
	 *
	 * @param string $table The name of the table to be optimized.
	 */
	function optimize_table($table)
	{
		$this->write_query("VACUUM ".$this->table_prefix.$table."");
	}

	/**
	 * Analyzes a specific table.
	 *
	 * @param string $table The name of the table to be analyzed.
	 */
	function analyze_table($table)
	{
		$this->write_query("ANALYZE ".$this->table_prefix.$table."");
	}

	/**
	 * Show the "create table" command for a specific table.
	 *
	 * @param string $table The name of the table.
	 * @return string The pg command to create the specified table.
	 */
	function show_create_table($table)
	{
		$query = $this->write_query("
			SELECT a.attnum, a.attname as field, t.typname as type, a.attlen as length, a.atttypmod as lengthvar, a.attnotnull as notnull
			FROM pg_class c
			LEFT JOIN pg_attribute a ON (a.attrelid = c.oid)
			LEFT JOIN pg_type t ON (a.atttypid = t.oid)
			WHERE c.relname = '{$this->table_prefix}{$table}' AND a.attnum > 0
			ORDER BY a.attnum
		");

		$lines = array();
		$table_lines = "CREATE TABLE {$this->table_prefix}{$table} (\n";

		while($row = $this->fetch_array($query))
		{
			// Get the data from the table
			$query2 = $this->write_query("
				SELECT pg_get_expr(d.adbin, d.adrelid) as rowdefault
				FROM pg_attrdef d
				LEFT JOIN pg_class c ON (c.oid = d.adrelid)
				WHERE c.relname = '{$this->table_prefix}{$table}' AND d.adnum = '{$row['attnum']}'
			");

			if(!$query2)
			{
				unset($row['rowdefault']);
			}
			else
			{
				$row['rowdefault'] = $this->fetch_field($query2, 'rowdefault');
			}

			if($row['type'] == 'bpchar')
			{
				// Stored in the engine as bpchar, but in the CREATE TABLE statement it's char
				$row['type'] = 'char';
			}

			$line = "  {$row['field']} {$row['type']}";

			if(strpos($row['type'], 'char') !== false)
			{
				if($row['lengthvar'] > 0)
				{
					$line .= '('.($row['lengthvar'] - 4).')';
				}
			}

			if(strpos($row['type'], 'numeric') !== false)
			{
				$line .= '('.sprintf("%s,%s", (($row['lengthvar'] >> 16) & 0xffff), (($row['lengthvar'] - 4) & 0xffff)).')';
			}

			if(!empty($row['rowdefault']))
			{
				$line .= " DEFAULT {$row['rowdefault']}";
			}

			if($row['notnull'] == 't')
			{
				$line .= ' NOT NULL';
			}

			$lines[] = $line;
		}

		// Get the listing of primary keys.
		$query = $this->write_query("
			SELECT ic.relname as index_name, bc.relname as tab_name, ta.attname as column_name, i.indisunique as unique_key, i.indisprimary as primary_key
			FROM pg_class bc
			LEFT JOIN pg_index i ON (bc.oid = i.indrelid)
			LEFT JOIN pg_class ic ON (ic.oid = i.indexrelid)
			LEFT JOIN pg_attribute ia ON (ia.attrelid = i.indexrelid)
			LEFT JOIN pg_attribute ta ON (ta.attrelid = bc.oid AND ta.attrelid = i.indrelid AND ta.attnum = i.indkey[ia.attnum-1])
			WHERE bc.relname = '{$this->table_prefix}{$table}'
			ORDER BY index_name, tab_name, column_name
		");

		$primary_key = array();
		$primary_key_name = '';

		// We do this in two steps. It makes placing the comma easier
		while($row = $this->fetch_array($query))
		{
			if($row['primary_key'] == 't')
			{
				$primary_key[] = $row['column_name'];
				$primary_key_name = $row['index_name'];
			}
		}

		if(!empty($primary_key))
		{
			$lines[] = "  CONSTRAINT $primary_key_name PRIMARY KEY (".implode(', ', $primary_key).")";
		}

		$table_lines .= implode(", \n", $lines);
		$table_lines .= "\n)\n";

		return $table_lines;
	}

	/**
	 * Show the "show fields from" command for a specific table.
	 *
	 * @param string $table The name of the table.
	 * @return array Field info for that table
	 */
	function show_fields_from($table)
	{
		$query = $this->write_query("SELECT column_name FROM information_schema.constraint_column_usage WHERE table_name = '{$this->table_prefix}{$table}' and constraint_name = '{$this->table_prefix}{$table}_pkey' LIMIT 1");
		$primary_key = $this->fetch_field($query, 'column_name');

		$query = $this->write_query("
			SELECT column_name as Field, data_type as Extra
			FROM information_schema.columns
			WHERE table_name = '{$this->table_prefix}{$table}'
		");
		$field_info = array();
		while($field = $this->fetch_array($query))
		{
			if($field['field'] == $primary_key)
			{
				$field['extra'] = 'auto_increment';
			}

			$field_info[] = array('Extra' => $field['extra'], 'Field' => $field['field']);
		}

		return $field_info;
	}

	/**
	 * Returns whether or not the table contains a fulltext index.
	 *
	 * @param string $table The name of the table.
	 * @param string $index Optionally specify the name of the index.
	 * @return boolean True or false if the table has a fulltext index or not.
	 */
	function is_fulltext($table, $index="")
	{
		return false;
	}

	/**
	 * Returns whether or not this database engine supports fulltext indexing.
	 *
	 * @param string $table The table to be checked.
	 * @return boolean True or false if supported or not.
	 */

	function supports_fulltext($table)
	{
		return false;
	}

	/**
	 * Returns whether or not this database engine supports boolean fulltext matching.
	 *
	 * @param string $table The table to be checked.
	 * @return boolean True or false if supported or not.
	 */
	function supports_fulltext_boolean($table)
	{
		return false;
	}

	/**
	 * Creates a fulltext index on the specified column in the specified table with optional index name.
	 *
	 * @param string $table The name of the table.
	 * @param string $column Name of the column to be indexed.
	 * @param string $name The index name, optional.
	 * @return bool
	 */
	function create_fulltext_index($table, $column, $name="")
	{
		return false;
	}

	/**
	 * Drop an index with the specified name from the specified table
	 *
	 * @param string $table The name of the table.
	 * @param string $name The name of the index.
	 */
	function drop_index($table, $name)
	{
		$this->write_query("
			ALTER TABLE {$this->table_prefix}$table
			DROP INDEX $name
		");
	}

	/**
	 * Checks to see if an index exists on a specified table
	 *
	 * @param string $table The name of the table.
	 * @param string $index The name of the index.
	 * @return bool Returns whether index exists
	 */
	function index_exists($table, $index)
	{
		$err = $this->error_reporting;
		$this->error_reporting = 0;

		$query = $this->write_query("SELECT * FROM pg_indexes WHERE tablename='".$this->escape_string($this->table_prefix.$table)."'");

		$exists = $this->fetch_field($query, $index);
		$this->error_reporting = $err;

		if($exists)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Drop an table with the specified table
	 *
	 * @param string $table The name of the table.
	 * @param boolean $hard hard drop - no checking
	 * @param boolean $table_prefix use table prefix
	 */
	function drop_table($table, $hard=false, $table_prefix=true)
	{
		if($table_prefix == false)
		{
			$table_prefix = "";
		}
		else
		{
			$table_prefix = $this->table_prefix;
		}

		if($hard == false)
		{
			if($this->table_exists($table))
			{
				$this->write_query('DROP TABLE '.$table_prefix.$table);
			}
		}
		else
		{
			$this->write_query('DROP TABLE '.$table_prefix.$table);
		}

		$query = $this->query("SELECT column_name FROM information_schema.constraint_column_usage WHERE table_name = '{$table}' and constraint_name = '{$table}_pkey' LIMIT 1");
		$field = $this->fetch_field($query, 'column_name');

		// Do we not have a primary field?
		if($field)
		{
			$this->write_query('DROP SEQUENCE {$table}_{$field}_id_seq');
		}
	}

	/**
	 * Renames a table
	 *
	 * @param string $old_table The old table name
	 * @param string $new_table the new table name
	 * @param boolean $table_prefix use table prefix
	 * @return resource
	 */
	function rename_table($old_table, $new_table, $table_prefix=true)
	{
		if($table_prefix == false)
		{
			$table_prefix = "";
		}
		else
		{
			$table_prefix = $this->table_prefix;
		}

		return $this->write_query("ALTER TABLE {$table_prefix}{$old_table} RENAME TO {$table_prefix}{$new_table}");
	}

	/**
	 * Replace contents of table with values
	 *
	 * @param string $table The table
	 * @param array $replacements The replacements
	 * @param string|array $default_field The default field(s)
	 * @param boolean $insert_id Whether or not to return an insert id. True by default
	 * @return int|resource|bool Returns either the insert id (if a new row is inserted and $insert_id is true), a boolean (if $insert_id is wrong) or the query resource (if a row is updated)
	 */
	function replace_query($table, $replacements=array(), $default_field="", $insert_id=true)
	{
		global $mybb;

		if($default_field == "")
		{
			$query = $this->write_query("SELECT column_name FROM information_schema.constraint_column_usage WHERE table_name = '{$this->table_prefix}{$table}' and constraint_name = '{$this->table_prefix}{$table}_pkey' LIMIT 1");
			$main_field = $this->fetch_field($query, 'column_name');
		}
		else
		{
			$main_field = $default_field;
		}

		$update = false;
		$search_bit = array();
		if(is_array($main_field) && !empty($main_field))
		{
			foreach($main_field as $field)
			{
				if(isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field])
				{
					$search_bit[] = "{$field} = ".$replacements[$field];
				}
				else
				{
					$search_bit[] = "{$field} = ".$this->quote_val($replacements[$field]);
				}
			}

			$search_bit = implode(" AND ", $search_bit);
			$query = $this->write_query("SELECT COUNT(".$main_field[0].") as count FROM {$this->table_prefix}{$table} WHERE {$search_bit} LIMIT 1");
			if($this->fetch_field($query, "count") == 1)
			{
				$update = true;
			}
		}
		else
		{
			$query = $this->write_query("SELECT {$main_field} FROM {$this->table_prefix}{$table}");

			while($column = $this->fetch_array($query))
			{
				if($column[$main_field] == $replacements[$main_field])
				{
					$update = true;
					break;
				}
			}
		}

		if($update === true)
		{
			if(is_array($main_field))
			{
				return $this->update_query($table, $replacements, $search_bit);
			}
			else
			{
				return $this->update_query($table, $replacements, "{$main_field}=".$this->quote_val($replacements[$main_field]));
			}
		}
		else
		{
			return $this->insert_query($table, $replacements, $insert_id);
		}
	}

	/**
	 * @param string $table
	 * @param string $append
	 *
	 * @return string
	 */
	function build_fields_string($table, $append="")
	{
		$fields = $this->show_fields_from($table);
		$comma = $fieldstring = '';

		foreach($fields as $key => $field)
		{
			$fieldstring .= $comma.$append.$field['Field'];
			$comma = ',';
		}

		return $fieldstring;
	}

	/**
	 * Drops a column
	 *
	 * @param string $table The table
	 * @param string $column The column name
	 * @return resource
	 */
	function drop_column($table, $column)
	{
		return $this->write_query("ALTER TABLE {$this->table_prefix}{$table} DROP {$column}");
	}

	/**
	 * Adds a column
	 *
	 * @param string $table The table
	 * @param string $column The column name
	 * @param string $definition the new column definition
	 * @return resource
	 */
	function add_column($table, $column, $definition)
	{
		return $this->write_query("ALTER TABLE {$this->table_prefix}{$table} ADD {$column} {$definition}");
	}

	/**
	 * Modifies a column
	 *
	 * @param string $table The table
	 * @param string $column The column name
	 * @param string $new_definition the new column definition
	 * @param boolean $new_not_null Whether to drop or set a column
	 * @param boolean $new_default_value The new default value (if one is to be set)
	 * @return bool Returns true if all queries are executed successfully or false if one of them failed
	 */
	function modify_column($table, $column, $new_definition, $new_not_null=false, $new_default_value=false)
	{
		$result1 = $result2 = $result3 = true;

		if($new_definition !== false)
		{
			$result1 = $this->write_query("ALTER TABLE {$this->table_prefix}{$table} ALTER COLUMN {$column} TYPE {$new_definition}");
		}

		if($new_not_null !== false)
		{
			$set_drop = "DROP";

			if(strtolower($new_not_null) == "set")
			{
				$set_drop = "SET";
			}

			$result2 = $this->write_query("ALTER TABLE {$this->table_prefix}{$table} ALTER COLUMN {$column} {$set_drop} NOT NULL");
		}

		if($new_default_value !== false)
		{
			$result3 = $this->write_query("ALTER TABLE {$this->table_prefix}{$table} ALTER COLUMN {$column} SET DEFAULT {$new_default_value}");
		}
		else
		{
			$result3 = $this->write_query("ALTER TABLE {$this->table_prefix}{$table} ALTER COLUMN {$column} DROP DEFAULT");
		}

		return $result1 && $result2 && $result3;
	}

	/**
	 * Renames a column
	 *
	 * @param string $table The table
	 * @param string $old_column The old column name
	 * @param string $new_column the new column name
	 * @param string $new_definition the new column definition
	 * @param boolean $new_not_null Whether to drop or set a column
	 * @param boolean $new_default_value The new default value (if one is to be set)
	 * @return bool Returns true if all queries are executed successfully
	 */
	function rename_column($table, $old_column, $new_column, $new_definition, $new_not_null=false, $new_default_value=false)
	{
		$result1 = $this->write_query("ALTER TABLE {$this->table_prefix}{$table} RENAME COLUMN {$old_column} TO {$new_column}");
		$result2 = $this->modify_column($table, $new_column, $new_definition, $new_not_null, $new_default_value);
		return ($result1 && $result2);
	}

	/**
	 * Sets the table prefix used by the simple select, insert, update and delete functions
	 *
	 * @param string $prefix The new table prefix
	 */
	function set_table_prefix($prefix)
	{
		$this->table_prefix = $prefix;
	}

	/**
	 * Fetched the total size of all mysql tables or a specific table
	 *
	 * @param string $table The table (optional)
	 * @return integer the total size of all mysql tables or a specific table
	 */
	function fetch_size($table='')
	{
		if($table != '')
		{
			$query = $this->query("SELECT reltuples, relpages FROM pg_class WHERE relname = '".$this->table_prefix.$table."'");
		}
		else
		{
			$query = $this->query("SELECT reltuples, relpages FROM pg_class");
		}
		$total = 0;
		while($table = $this->fetch_array($query))
		{
			$total += $table['relpages']+$table['reltuples'];
		}
		return $total;
	}

	/**
	 * Fetch a list of database character sets this DBMS supports
	 *
	 * @return array|bool Array of supported character sets with array key being the name, array value being display name. False if unsupported
	 */
	function fetch_db_charsets()
	{
		return false;
	}

	/**
	 * Fetch a database collation for a particular database character set
	 *
	 * @param string $charset The database character set
	 * @return string|bool The matching database collation, false if unsupported
	 */
	function fetch_charset_collation($charset)
	{
		return false;
	}

	/**
	 * Fetch a character set/collation string for use with CREATE TABLE statements. Uses current DB encoding
	 *
	 * @return string The built string, empty if unsupported
	 */
	function build_create_table_collation()
	{
		return '';
	}

	/**
	 * Time how long it takes for a particular piece of code to run. Place calls above & below the block of code.
	 *
	 * @deprecated
	 */
	function get_execution_time()
	{
		return get_execution_time();
	}

	/**
	 * Binary database fields require special attention.
	 *
	 * @param string $string Binary value
	 * @return string Encoded binary value
	 */
	function escape_binary($string)
	{
		return "'".pg_escape_bytea($string)."'";
	}

	/**
	 * Unescape binary data.
	 *
	 * @param string $string Binary value
	 * @return string Encoded binary value
	 */
	function unescape_binary($string)
	{
		// hex format
		if(substr($string, 0, 2) == '\x')
		{
			return pack('H*', substr($string, 2));
		}
		// escape format
		else
		{
			return pg_unescape_bytea($string);
		}
	}
}

