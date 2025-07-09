<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * @property string title The title of the database access layer.
 * @property string short_title The short title of the database access layer.
 * @property string type The type of db software being used.
 * @property int query_count A count of the number of queries.
 * @property array querylist A list of the performed queries.
 * @property float query_time The time spent performing queries.
 * @property string engine The engine used to run the SQL database.
 * @property bool can_search Whether or not this engine can use the search functionality.
 */
interface DB_Base
{
	/**
	 * Connect to the database server.
	 *
	 * @param array $config Array of DBMS connection details.
	 * @return resource|PDOStatement|mysqli_result The DB connection resource. Returns false on fail or -1 on a db connect failure.
	 */
	function connect($config);

	/**
	 * Query the database.
	 *
	 * @param string $string The query SQL.
	 * @param integer|bool $hide_errors 1 if hide errors, 0 if not.
	 * @param integer 1 $write_query if executes on master database, 0 if not.
	 * @return resource|PDOStatement|mysqli_result The query data.
	 */
	function query($string, $hide_errors=0, $write_query=0);

	/**
	 * Execute a write query on the master database
	 *
	 * @param string $query The query SQL.
	 * @param boolean|int $hide_errors 1 if hide errors, 0 if not.
	 * @return resource|PDOStatement|mysqli_result The query data.
	 */
	function write_query($query, $hide_errors=0);

	/**
	 * Explain a query on the database.
	 *
	 * @param string $string The query SQL.
	 * @param string $qtime The time it took to perform the query.
	 */
	function explain_query($string, $qtime);

	/**
	 * Return a result array for a query.
	 *
	 * @param resource|PDOStatement|mysqli_result $query The query ID.
	 * @param int $resulttype The type of array to return. Specified with the different constants for the type using
	 *
	 * @return array The array of results.
	 */
	function fetch_array($query, $resulttype=1);

	/**
	 * Return a specific field from a query.
	 *
	 * @param resource|PDOStatement|mysqli_result $query The query ID.
	 * @param string $field The name of the field to return.
	 * @param int|boolean $row The number of the row to fetch it from.
	 */
	function fetch_field($query, $field, $row=false);

	/**
	 * Moves internal row pointer to the next row
	 *
	 * @param resource|PDOStatement|mysqli_result $query The query ID.
	 * @param int $row The pointer to move the row to.
	 */
	function data_seek($query, $row);

	/**
	 * Return the number of rows resulting from a query.
	 *
	 * @param resource|PDOStatement|mysqli_result $query The query ID.
	 * @return int The number of rows in the result.
	 */
	function num_rows($query);

	/**
	 * Return the last id number of inserted data.
	 *
	 * @return int The id number.
	 */
	function insert_id();

	/**
	 * Close the connection with the DBMS.
	 *
	 */
	function close();

	/**
	 * Return an error number.
	 *
	 * @return int The error number of the current error.
	 */
	function error_number();

	/**
	 * Return an error string.
	 *
	 * @return string The explanation for the current error.
	 */
	function error_string();

	/**
	 * Output a database error.
	 *
	 * @param string $string The string to present as an error.
	 */
	function error($string="");

	/**
	 * Returns the number of affected rows in a query.
	 *
	 * @return int The number of affected rows.
	 */
	function affected_rows();

	/**
	 * Return the number of fields.
	 *
	 * @param resource|PDOStatement|mysqli_result $query The query ID.
	 * @return int The number of fields.
	 */
	function num_fields($query);

	/**
	 * Lists all functions in the database.
	 *
	 * @param string $database The database name.
	 * @param string $prefix Prefix of the table (optional)
	 * @return array The table list.
	 */
	function list_tables($database, $prefix='');

	/**
	 * Check if a table exists in a database.
	 *
	 * @param string $table The table name.
	 * @return boolean True when exists, false if not.
	 */
	function table_exists($table);

	/**
	 * Check if a field exists in a database.
	 *
	 * @param string $field The field name.
	 * @param string $table The table name.
	 * @return boolean True when exists, false if not.
	 */
	function field_exists($field, $table);

	/**
	 * Add a shutdown query.
	 *
	 * @param resource|PDOStatement|mysqli_result $query The query data.
	 * @param string $name An optional name for the query.
	 */
	function shutdown_query($query, $name='');

	/**
	 * Performs a simple select query.
	 *
	 * @param string $table The table name to be queried.
	 * @param string $fields Comma delimited list of fields to be selected.
	 * @param string $conditions SQL formatted list of conditions to be matched.
	 * @param array $options List of options: group by, order by, order direction, limit, limit start.
	 * @return resource|PDOStatement|mysqli_result The query data.
	 */
	function simple_select($table, $fields="*", $conditions="", $options=array());

	/**
	 * Build an insert query from an array.
	 *
	 * @param string $table The table name to perform the query on.
	 * @param array $array An array of fields and their values.
	 * @return int The insert ID if available
	 */
	function insert_query($table, $array);

	/**
	 * Build one query for multiple inserts from a multidimensional array.
	 *
	 * @param string $table The table name to perform the query on.
	 * @param array $array An array of inserts.
	 * @return void
	 */
	function insert_query_multiple($table, $array);

	/**
	 * Build an update query from an array.
	 *
	 * @param string $table The table name to perform the query on.
	 * @param array $array An array of fields and their values.
	 * @param string $where An optional where clause for the query.
	 * @param string $limit An optional limit clause for the query.
	 * @param boolean $no_quote An option to quote incoming values of the array.
	 * @return resource|PDOStatement|mysqli_result The query data.
	 */
	function update_query($table, $array, $where="", $limit="", $no_quote=false);

	/**
	 * Build a delete query.
	 *
	 * @param string $table The table name to perform the query on.
	 * @param string $where An optional where clause for the query.
	 * @param string $limit An optional limit clause for the query.
	 * @return resource|PDOStatement|mysqli_result The query data.
	 */
	function delete_query($table, $where="", $limit="");

	/**
	 * Escape a string according to the MySQL escape format.
	 *
	 * @param string $string The string to be escaped.
	 * @return string The escaped string.
	 */
	function escape_string($string);

	/**
	 * Frees the resources of a query.
	 *
	 * @param resource|PDOStatement|mysqli_result $query The query to destroy.
	 * @return boolean Returns true on success, false on faliure
	 */
	function free_result($query);

	/**
	 * Escape a string used within a like command.
	 *
	 * @param string $string The string to be escaped.
	 * @return string The escaped string.
	 */
	function escape_string_like($string);

	/**
	 * Gets the current version of MySQL.
	 *
	 * @return string Version of MySQL.
	 */
	function get_version();

	/**
	 * Optimizes a specific table.
	 *
	 * @param string $table The name of the table to be optimized.
	 */
	function optimize_table($table);

	/**
	 * Analyzes a specific table.
	 *
	 * @param string $table The name of the table to be analyzed.
	 */
	function analyze_table($table);

	/**
	 * Show the "create table" command for a specific table.
	 *
	 * @param string $table The name of the table.
	 * @return string The SQL command to create the specified table.
	 */
	function show_create_table($table);

	/**
	 * Show the "show fields from" command for a specific table.
	 *
	 * @param string $table The name of the table.
	 * @return array Field info for that table
	 */
	function show_fields_from($table);

	/**
	 * Returns whether or not the table contains a fulltext index.
	 *
	 * @param string $table The name of the table.
	 * @param string $index Optionally specify the name of the index.
	 * @return boolean True or false if the table has a fulltext index or not.
	 */
	function is_fulltext($table, $index="");

	/**
	 * Returns whether or not this database engine supports fulltext indexing.
	 *
	 * @param string $table The table to be checked.
	 * @return boolean True or false if supported or not.
	 */
	function supports_fulltext($table);

	/**
	 * Checks to see if an index exists on a specified table
	 *
	 * @param string $table The name of the table.
	 * @param string $index The name of the index.
	 */
	function index_exists($table, $index);

	/**
	 * Returns whether or not this database engine supports boolean fulltext matching.
	 *
	 * @param string $table The table to be checked.
	 * @return boolean True or false if supported or not.
	 */
	function supports_fulltext_boolean($table);

	/**
	 * Creates a fulltext index on the specified column in the specified table with optional index name.
	 *
	 * @param string $table The name of the table.
	 * @param string $column Name of the column to be indexed.
	 * @param string $name The index name, optional.
	 */
	function create_fulltext_index($table, $column, $name="");

	/**
	 * Drop an index with the specified name from the specified table
	 *
	 * @param string $table The name of the table.
	 * @param string $name The name of the index.
	 */
	function drop_index($table, $name);

	/**
	 * Drop an table with the specified table
	 *
	 * @param string $table The name of the table.
	 * @param boolean $hard Hard drop - no checking
	 * @param boolean $table_prefix Use table prefix?
	 */
	function drop_table($table, $hard=false, $table_prefix=true);

	/**
	 * Renames a table
	 *
	 * @param string $old_table The old table name
	 * @param string $new_table the new table name
	 * @param boolean $table_prefix Use table prefix?
	 */
	function rename_table($old_table, $new_table, $table_prefix=true);

	/**
	 * Replace contents of table with values
	 *
	 * @param string $table The table
	 * @param array $replacements The replacements
	 * @param string|array $default_field The default field(s)
	 * @param boolean $insert_id Whether or not to return an insert id. True by default
	 */
	function replace_query($table, $replacements=array(), $default_field="", $insert_id=true);

	/**
	 * Drops a column
	 *
	 * @param string $table The table
	 * @param string $column The column name
	 */
	function drop_column($table, $column);

	/**
	 * Adds a column
	 *
	 * @param string $table The table
	 * @param string $column The column name
	 * @param string $definition The new column definition
	 */
	function add_column($table, $column, $definition);

	/**
	 * Modifies a column
	 *
	 * @param string $table The table
	 * @param string $column The column name
	 * @param string $new_definition the new column definition
	 * @param boolean|string $new_not_null Whether to "drop" or "set" the NOT NULL attribute (no change if false)
	 * @param boolean|string $new_default_value The new default value, or false to drop the attribute
	 * @return bool Returns true if all queries are executed successfully or false if one of them failed
	 */
	function modify_column($table, $column, $new_definition, $new_not_null=false, $new_default_value=false);

	/**
	 * Renames a column
	 *
	 * @param string $table The table
	 * @param string $old_column The old column name
	 * @param string $new_column the new column name
	 * @param string $new_definition the new column definition
	 * @param boolean|string $new_not_null Whether to "drop" or "set" the NOT NULL attribute (no change if false)
	 * @param boolean|string $new_default_value The new default value, or false to drop the attribute
	 * @return bool Returns true if all queries are executed successfully
	 */
	function rename_column($table, $old_column, $new_column, $new_definition, $new_not_null=false, $new_default_value=false);

	/**
	 * Sets the table prefix used by the simple select, insert, update and delete functions
	 *
	 * @param string $prefix The new table prefix
	 */
	function set_table_prefix($prefix);

	/**
	 * Fetched the total size of all mysql tables or a specific table
	 *
	 * @param string $table The table (optional)
	 * @return integer the total size of all mysql tables or a specific table
	 */
	function fetch_size($table='');

	/**
	 * Fetch a list of database character sets this DBMS supports
	 *
	 * @return array|bool Array of supported character sets with array key being the name, array value being display name. False if unsupported
	 */
	function fetch_db_charsets();

	/**
	 * Fetch a database collation for a particular database character set
	 *
	 * @param string $charset The database character set
	 * @return string|bool The matching database collation, false if unsupported
	 */
	function fetch_charset_collation($charset);

	/**
	 * Fetch a character set/collation string for use with CREATE TABLE statements. Uses current DB encoding
	 *
	 * @return string The built string, empty if unsupported
	 */
	function build_create_table_collation();

	/**
	 * Time how long it takes for a particular piece of code to run. Place calls above & below the block of code.
	 *
	 * @deprecated
	 */
	function get_execution_time();

	/**
	 * Binary database fields require special attention.
	 *
	 * @param string $string Binary value
	 * @return string Encoded binary value
	 */
	function escape_binary($string);

	/**
	 * Unescape binary data.
	 *
	 * @param string $string Binary value
	 * @return string Encoded binary value
	 */
	function unescape_binary($string);
}
