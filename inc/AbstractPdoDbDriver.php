<?php
/**
 * MyBB 1.8
 * Copyright 2020 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

abstract class AbstractPdoDbDriver implements DB_Base
{
	/**
	 * Whether error reporting is enabled.
	 *
	 * @var boolean
	 */
	public $error_reporting = 1;

	/**
	 * The read database connection resource.
	 *
	 * @var PDO|null
	 */
	public $read_link = null;

	/**
	 * The write database connection resource.
	 *
	 * @var PDO|null
	 */
	public $write_link = null;

	/**
	 * Reference to the last database connection resource used.
	 *
	 * @var PDO|null
	 */
	public $current_link = null;

	/**
	 * The database name.
	 *
	 * @var string
	 */
	public $database;

	/**
	 * The database encoding currently in use (if supported).
	 *
	 * @var string
	 */
	public $db_encoding = "utf8";

	/**
	 * The time spent performing queries.
	 *
	 * @var float
	 */
	public $query_time = 0;

	/**
	 * A count of the number of queries.
	 *
	 * @var int
	 */
	public $query_count = 0;

	/**
	 * @var array
	 */
	public $connections = array();

	/**
	 * @var PDOException|null
	 */
	private $lastPdoException;

	/**
	 * The type of the previous query.
	 *
	 * 1 => write; 0 => read
	 *
	 * @var int
	 */
	protected $last_query_type = 0;

	/**
	 * Used to store row offsets for queries when seeking.
	 *
	 * @var array
	 */
	private $resultSeekPositions = array();

	/**
	 * The last result, used to get the number of affected rows in {@see AbstractPdoDbDriver::affected_rows()}.
	 *
	 * @var PDOStatement|null
	 */
	private $lastResult = null;

	/**
	 * The table prefix used for simple select, update, insert and delete queries
	 *
	 * @var string
	 */
	public $table_prefix;

	/**
	 * The current version of the DBMS.
	 *
	 * Note that this is the version used by the {@see AbstractPdoDbDriver::$read_link}.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * A list of the performed queries.
	 *
	 * @var array
	 */
	public $querylist = array();

	/**
	 * The engine used to run the SQL database.
	 *
	 * @var string
	 */
	public $engine = "pdo";

	/**
	 * Whether or not this engine can use the search functionality.
	 *
	 * @var boolean
	 */
	public $can_search = true;

	/**
	 * Build a DSN string using the given configuration.
	 *
	 * @param string $hostname The hostname of the database serer to connect to.
	 * @param string $db The name of the database to connect to.
	 * @param int|null The optional port to use to connect to the database server.
	 * @param string|null The character encoding to use for the connection.
	 *
	 * @return string The DSN string, including the driver prefix.
	 */
	protected abstract function getDsn($hostname, $db, $port, $encoding);

	/**
	 * Connect to the database server.
	 *
	 * @param array $config Array of DBMS connection details.
	 *
	 * @return bool Whether opening the connection was successful.
	 */
	public function connect($config)
	{
		$connections = array(
			'read' => array(),
			'write' => array(),
		);

		if (isset($config['hostname'])) {
			// simple connection, with single DB server
			$connections['read'][] = $config;
		} else {
			if (!isset($config['read'])) {
				// multiple servers, but no specific read/write servers
				foreach ($config as $key => $settings) {
					if (is_int($key)) {
						$connections['read'][] = $settings;
					}
				}
			} else {
				// both read and write servers
				$connections = $config;
			}
		}

		if (isset($config['encoding'])) {
			$this->db_encoding = $config['encoding'];
		}

		// Actually connect to the specified servers
		foreach (array('read', 'write') as $type) {
			if (!isset($connections[$type]) || !is_array($connections[$type])){
				break;
			}

			if (isset($connections[$type]['hostname'])) {
				$details = $connections[$type];
				unset($connections[$type]);
				$connections[$type][] = $details;
			}

			// shuffle the connections
			shuffle($connections[$type]);

			// loop through the connections
			foreach($connections[$type] as $singleConnection)
			{
				$flags = array(
					PDO::ATTR_PERSISTENT => false,
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_EMULATE_PREPARES => false,
				);

				if (!empty($singleConnection['pconnect'])) {
					$flags[PDO::ATTR_PERSISTENT] = true;
				}

				$link = "{$type}_link";

				get_execution_time();

				list($hostname, $port) = self::parseHostname($singleConnection['hostname']);

				$dsn = $this->getDsn(
					$hostname,
					$config['database'],
					$port,
					$this->db_encoding
				);

				try {
					$this->$link = new PDO(
						$dsn,
						$singleConnection['username'],
						$singleConnection['password'],
						$flags
					);

					$this->lastPdoException = null;
				} catch (PDOException $e) {
					$this->$link = null;
					$this->lastPdoException = $e;
				}

				$time_spent = get_execution_time();
				$this->query_time += $time_spent;

				// Successful connection? break down brother!
				if ($this->$link !== null) {
					$this->connections[] = "[".strtoupper($type)."] {$singleConnection['username']}@{$singleConnection['hostname']} (Connected in ".format_time_duration($time_spent).")";
					break;
				} else {
					$this->connections[] = "<span style=\"color: red\">[FAILED] [".strtoupper($type)."] {$singleConnection['username']}@{$singleConnection['hostname']}</span>";
				}
			}
		}

		// No write server was specified (simple connection or just multiple servers) - mirror write link
		if (empty($connections['write'])) {
			$this->write_link = $this->read_link;
		}

		// Have no read connection?
		if ($this->read_link === null) {
			$this->error("[READ] Unable to connect to database server");
			return false;
		} else if($this->write_link === null) {
			$this->error("[WRITE] Unable to connect to database server");
			return false;
		}

		$this->database = $config['database'];

		if (version_compare(PHP_VERSION, '5.3.6', '<') === true) {
			// character set in DSN was ignored before PHP 5.3.6, so we must SET NAMES
			$this->setCharacterSet($this->db_encoding);
		}

		$this->current_link = $this->read_link;
		return true;
	}

	/**
	 * Parse a hostname and possible port combination.
	 *
	 * @param string $hostname The hostname string. Can be any of the following formats:
	 * - `127.0.0.1` - IPv4 address.
	 * - `[::1]` - IPv6 address.
	 * - `localhost` - hostname.
	 * - `127.0.0.1:3306` - IPv4 address and port combination.
	 *  `[::1]:3306` - IPv6 address and port combination.
	 * - `localhost:3306` - hostname and port combination.
	 *
	 * @return array Array of host and port.
	 *
	 * @throws InvalidArgumentException Thrown if {@see $hostname} is an IPv6 address which lacks a closing square bracket.
	 */
	private static function parseHostname($hostname)
	{
		// first, check for an IPv6 address - IPv6 addresses always start with `[`
		$openingSquareBracket = strpos($hostname, '[');
		if ($openingSquareBracket === 0) {
			// find ending `]`
			$closingSquareBracket = strpos($hostname, ']', $openingSquareBracket);

			if ($closingSquareBracket !== false) {
				$portSeparator = strpos($hostname, ':', $closingSquareBracket);

				// there is no port specified
				if ($portSeparator === false) {
					return array($hostname, null);
				} else {
					$host = substr($hostname, $openingSquareBracket, $closingSquareBracket + 1);
					$port = (int) substr($hostname, $portSeparator + 1);

					return array($host, $port);
				}
			} else {
				throw new InvalidArgumentException("Hostname is missing a closing square bracket for IPv6 address: {$hostname}");
			}
		}

		// either an IPv4 address or a hostname
		$portSeparator = strpos($hostname, ':', 0);
		if ($portSeparator === false) {
			return array($hostname, null);
		} else {
			$host = substr($hostname, 0, $portSeparator);
			$port = (int) substr($hostname, $portSeparator + 1);

			return array($host, $port);
		}
	}

	/**
	 * Set the character set to use. This issues a `SET NAMES` query to both the read and write links.
	 *
	 * @param string $characterSet The character set to use.
	 *
	 * @return void
	 */
	public function setCharacterSet($characterSet)
	{
		$query = "SET NAMES '{$characterSet}'";

		self::execIgnoreError($this->read_link, $query);

		if ($this->write_link !== $this->read_link) {
			self::execIgnoreError($this->write_link, $query);
		}
	}

	/**
	 * Execute a query, ignoring any errors.
	 *
	 * @param PDO $connection The connection to execute the query on.
	 * @param string $query The query to execute.
	 */
	private static function execIgnoreError($connection, $query)
	{
		try {
			$connection->exec($query);
		} catch (PDOException $e) {
			// ignored on purpose
		}
	}

	/**
	 * Output a database error.
	 *
	 * @param string $string The string to present as an error.
	 *
	 * @return bool Whether error reporting is enabled or not
	 */
	public function error($string = '')
	{
		if ($this->error_reporting) {
			if (class_exists("errorHandler")) {
				global $error_handler;

				if(!is_object($error_handler))
				{
					require_once MYBB_ROOT."inc/class_error.php";
					$error_handler = new errorHandler();
				}

				$error = array(
					"error_no" => $this->error_number(),
					"error" => $this->error_string(),
					"query" => $string
				);

				$error_handler->error(MYBB_SQL, $error);
			} else {
				trigger_error("<strong>[SQL] [". $this->error_number() ."]" . $this->error_string() . " </strong><br />{$string}", E_USER_ERROR);
			}

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Return the error code for the last error that occurred.
	 *
	 * @return string|null The error code for the last error that occurred, or null if no error occurred.
	 */
	public function error_number()
	{
		if ($this->lastPdoException !== null) {
			return $this->lastPdoException->getCode();
		}

		return null;
	}

	/**
	 * Return athe error message for the last error that occurred.
	 *
	 * @return string|null The error message for the last error that occurred, or null if no error occurred.
	 */
	public function error_string()
	{
		if ($this->lastPdoException !== null && isset($this->lastPdoException->errorInfo[2])) {
			return $this->lastPdoException->errorInfo[2];
		}

		return null;
	}

	/**
	 * Query the database.
	 *
	 * @param string $string The query SQL.
	 * @param boolean|int $hideErrors Whether to hide any errors that occur.
	 * @param boolean|int $writeQuery Whether to run the query on the write connection rather than the read connection.
	 *
	 * @return PDOStatement|null The result of the query, or null if an error occurred and {@see $hideErrors} was set.
	 */
	public function query($string, $hideErrors = false, $writeQuery = false)
	{
		global $mybb;

		get_execution_time();

		// Only execute write queries on master server
		if (($writeQuery || $this->last_query_type) && $this->write_link) {
			$this->current_link = &$this->write_link;
		} else {
			$this->current_link = &$this->read_link;
		}

		/** @var PDOStatement|null $query */
		$query = null;

		try {
			if (preg_match('/^\\s*SELECT\\b/i', $string) === 1) {
				// NOTE: we use prepare + execute here rather than just query so that we may request a scrollable cursor...
				$query = $this->current_link->prepare($string, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
				$query->execute();
				$this->lastPdoException = null;
			} else {
				$query = $this->current_link->query($string);
				$this->lastPdoException = null;
			}
		} catch (PDOException $e) {
			$this->lastPdoException = $e;
			$query = null;

			if (!$hideErrors) {
				$this->error($string);
				exit;
			}
		}

		if ($writeQuery) {
			$this->last_query_type = 1;
		} else {
			$this->last_query_type = 0;
		}

		$query_time = get_execution_time();
		$this->query_time += $query_time;
		$this->query_count++;
		$this->lastResult = $query;

		if ($mybb->debug_mode) {
			$this->explain_query($string, $query_time);
		}

		return $query;
	}

	/**
	 * Execute a write query on the master database
	 *
	 * @param string $query The query SQL.
	 * @param boolean|int $hideErrors Whether to hide any errors that occur.
	 *
	 * @return PDOStatement|null The result of the query, or null if an error occurred and {@see $hideErrors} was set.
	 */
	public function write_query($query, $hideErrors = false)
	{
		return $this->query($query, $hideErrors, true);
	}

	/**
	 * Return a result array for a query.
	 *
	 * @param PDOStatement $query The query to retrieve a result for.
	 * @param int $resultType The type of array to return. Can be any of the following values:
	 *  - {@see PDO::FETCH_ASSOC} Fetch an array of results keyed by column name. This is the default.
	 *  - {@see PDO::FETCH_NUM} Fetch an array of results keyed by column number, starting at 0.
	 *  - {@see PDO::FETCH_BOTH} Fetch an array of results keyed by both column name and number.
	 *
	 * @return array|bool The array of results, or false if there are no more results.
	 */
	public function fetch_array($query, $resultType = PDO::FETCH_ASSOC)
	{
		if (is_null($query) || !($query instanceof PDOStatement)) {
			return false;
		}

		switch($resultType)
		{
			case PDO::FETCH_NUM:
			case PDO::FETCH_BOTH:
				break;
			default:
				$resultType = PDO::FETCH_ASSOC;
				break;
		}

		$hash = spl_object_hash($query);

		if (isset($this->resultSeekPositions[$hash])) {
			return $query->fetch($resultType, PDO::FETCH_ORI_ABS, $this->resultSeekPositions[$hash]);
		}

		return $query->fetch($resultType);
	}

	/**
	 * Return a specific field from a query.
	 *
	 * @param PDOStatement $query The query to retrieve a result for.
	 * @param string $field The name of the field to return.
	 * @param int|bool $row The number of the row to fetch it from, or false to fetch from the next row in the result set.
	 *
	 * @return mixed The resulting field, of false if no more rows are in th result set.
	 *  Note that when querying fields that have a boolean value, this method should not be used.
	 */
	public function fetch_field($query, $field, $row = false)
	{
		if (is_null($query) || !($query instanceof PDOStatement)) {
			return false;
		}

		if ($row !== false) {
			$this->data_seek($query, (int) $row);
		}

		// NOTE: PDOStatement::fetchColumn only operates on numbered columns, so we must fetch the array result
		$array = $this->fetch_array($query, PDO::FETCH_ASSOC);

		if ($array === false) {
			return false;
		}

		return $array[$field];
	}

	/**
	 * Move the internal row pointer to the specified row.
	 *
	 * @param PDOStatement $query The query to move the row pointer for.
	 * @param int $row The row to move to. Rows are numbered from 0.
	 *
	 * @return bool Whether seeking was successful.
	 */
	public function data_seek($query, $row)
	{
		if (is_null($query) || !($query instanceof PDOStatement)) {
			return false;
		}

		$hash = spl_object_hash($query);

		// NOTE: PDO numbers rows from 1, but all other drivers are 0 based. We add 1 to the row number for compatibility
		$this->resultSeekPositions[$hash] = ((int) $row) + 1;

		return true;
	}

	/**
	 * Return the number of rows resulting from a query.
	 *
	 * @param PDOStatement $query The query data.
	 * @return int|bool The number of rows in the result, or false on failure.
	 */
	public function num_rows($query)
	{
		if (is_null($query) || !($query instanceof PDOStatement)) {
			return false;
		}

		if (preg_match('/^\\s*SELECT\\b/i', $query->queryString) === 1) {
			// rowCount does not return the number of rows in a select query on most DBMS, so we instead fetch all results then count them
			// TODO: how do we handle the case where we issued a prepared statement with parameters..?
			$countQuery = $this->read_link->query($query->queryString);
			$result = $countQuery->fetchAll(PDO::FETCH_COLUMN, 0);

			return count($result);
		} else {
			return $query->rowCount();
		}
	}

	/**
	 * Return the last id number of inserted data.
	 *
	 * @return string The id number.
	 */
	public function insert_id()
	{
		return $this->current_link->lastInsertId();
	}

	/**
	 * Close the connection with the DBMS.
	 */
	public function close()
	{
		$this->read_link = $this->write_link = $this->current_link = null;
	}

	/**
	 * Returns the number of affected rows in a query.
	 *
	 * @return int The number of affected rows.
	 */
	public function affected_rows()
	{
		if ($this->lastResult === null) {
			return 0;
		}

		return $this->lastResult->rowCount();
	}

	/**
	 * Return the number of fields.
	 *
	 * @param PDOStatement $query The query result to get the number of fields for.
	 *
	 * @return int|bool The number of fields, or false if the number of fields could not be retrieved.
	 */
	public function num_fields($query)
	{
		if (is_null($query) || !($query instanceof PDOStatement)) {
			return false;
		}

		return $query->columnCount();
	}

	 public function shutdown_query($query, $name = '')
	 {
		 global $shutdown_queries;

		 if($name) {
			 $shutdown_queries[$name] = $query;
		 } else {
			 $shutdown_queries[] = $query;
		 }
	 }

	 public function escape_string($string)
	 {
		 $string = $this->read_link->quote($string);

		 // Remove ' from the beginning of the string and at the end of the string, because we already quote parameters
		 $string = substr($string, 1);
		 $string = substr($string, 0, -1);

		 return $string;
	 }

	 public function free_result($query)
	 {
	 	 if (is_object($query) && $query instanceof PDOStatement) {
		     return $query->closeCursor();
	     }

	 	 return false;
	 }

	 public function escape_string_like($string)
	 {
		 return $this->escape_string(str_replace(array('\\', '%', '_') , array('\\\\', '\\%' , '\\_') , $string));
	 }

	 public function get_version()
	 {
		 if ($this->version) {
			 return $this->version;
		 }

		 $this->version = $this->read_link->getAttribute(PDO::ATTR_SERVER_VERSION);

		 return $this->version;
	 }

	 public function set_table_prefix($prefix)
	 {
		 $this->table_prefix = $prefix;
	 }

	 public function get_execution_time()
	 {
		 return get_execution_time();
	 }
 }
