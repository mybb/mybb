<?php
/**
 * MyBB 1.8
 * Copyright 2020 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

class MysqlPdoDbDriver extends AbstractPdoDbDriver
{
	protected function getDsn($hostname, $db, $port, $encoding)
	{
		$dsn = "mysql:host={$hostname};dbname={$db}";

		if ($port !== null) {
			$dsn .= ";port={$port}";
		}

		if (!empty($encoding)) {
			$dsn .= ";charset={$encoding}";
		}

		return $dsn;
	}

	function explain_query($string, $qtime)
	{
		// TODO: Implement explain_query() method.
	}

	function list_tables($database, $prefix = '')
	{
		if ($prefix) {
			if (version_compare($this->get_version(), '5.0.2', '>=')) {
				$query = $this->query("SHOW FULL TABLES FROM `{$database}` WHERE table_type = 'BASE TABLE' AND `Tables_in_{$database}` LIKE '".$this->escape_string($prefix)."%'");
			} else {
				$query = $this->query("SHOW TABLES FROM `{$database}` LIKE '".$this->escape_string($prefix)."%'");
			}
		} else {
			if (version_compare($this->get_version(), '5.0.2', '>=')) {
				$query = $this->query("SHOW FULL TABLES FROM `{$database}` WHERE table_type = 'BASE TABLE'");
			} else {
				$query = $this->query("SHOW TABLES FROM `{$database}`");
			}
		}

		$tables = array();
		while (list($table) = $this->fetch_array($query)) {
			$tables[] = $table;
		}

		return $tables;
	}

	public function table_exists($table)
	{
		// Execute on master server to ensure if we've just created a table that we get the correct result
		if (version_compare($this->get_version(), '5.0.2', '>=')) {
			$query = $this->query("SHOW FULL TABLES FROM `{$this->database}` WHERE table_type = 'BASE TABLE' AND `Tables_in_{$this->database}` = '{$this->table_prefix}{$table}'");
		} else {
			$query = $this->query("SHOW TABLES LIKE '{$this->table_prefix}{$table}'");
		}

		$count = 0;
		while ($row = $this->fetch_array($query)) {
			$count++;
		}

		return $count > 0;
	}

	public function field_exists($field, $table)
	{
		$query = $this->write_query("
			SHOW COLUMNS
			FROM {$this->table_prefix}$table
			LIKE '$field'
		");

		$exists = $this->num_rows($query);

		return $exists > 0;
	}

	public function simple_select($table, $fields = "*", $conditions = "", $options = array())
	{
		$query = "SELECT ".$fields." FROM ".$this->table_prefix.$table;

		if (!empty($conditions)) {
			$query .= " WHERE {$conditions}";
		}

		if (isset($options['group_by'])) {
			$query .= " GROUP BY {$options['group_by']}";
		}

		if (isset($options['order_by'])) {
			$query .= " ORDER BY {$options['order_by']}";

			if (isset($options['order_dir'])) {
				$query .= " ".my_strtoupper($options['order_dir']);
			}
		}

		if (isset($options['limit_start']) && isset($options['limit'])) {
			$query .= " LIMIT {$options['limit_start']}, {$options['limit']}";
		}
		else if (isset($options['limit'])) {
			$query .= " LIMIT {$options['limit']}";
		}

		return $this->query($query);
	}

	public function insert_query($table, $array)
	{
		global $mybb;

		if (!is_array($array)) {
			return false;
		}

		foreach ($array as $field => $value) {
			if(isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field]) {
				if ($value[0] != 'X') { // Not escaped?
					$value = $this->escape_binary($value);
				}

				$array[$field] = $value;
			} else {
				$array[$field] = $this->quote_val($value);
			}
		}

		$fields = "`".implode("`,`", array_keys($array))."`";

		$values = implode(",", $array);

		$this->write_query("
			INSERT
			INTO {$this->table_prefix}{$table} ({$fields})
			VALUES ({$values})
		");

		return $this->insert_id();
	}

	public function insert_query_multiple($table, $array)
	{
		global $mybb;

		if (!is_array($array)) {
			return;
		}

		// Field names
		$fields = array_keys($array[0]);
		$fields = "`".implode("`,`", $fields)."`";

		$insert_rows = array();
		foreach ($array as $values) {
			foreach ($values as $field => $value) {
				if (isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field]) {
					if($value[0] != 'X') { // Not escaped?
						$value = $this->escape_binary($value);
					}

					$values[$field] = $value;
				} else {
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

	public function update_query($table, $array, $where = "", $limit = "", $no_quote = false)
	{
		global $mybb;

		if (!is_array($array)) {
			return false;
		}

		$comma = "";
		$query = "";
		$quote = "'";

		if ($no_quote == true) {
			$quote = "";
		}

		foreach ($array as $field => $value) {
			if (isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field]) {
				if($value[0] != 'X') { // Not escaped?
					$value = $this->escape_binary($value);
				}

				$query .= "{$comma}`{$field}`={$value}";
			} else {
				$quoted_value = $this->quote_val($value, $quote);

				$query .= "{$comma}`{$field}`={$quoted_value}";
			}

			$comma = ', ';
		}

		if (!empty($where)) {
			$query .= " WHERE {$where}";
		}

		if (!empty($limit)) {
			$query .= " LIMIT {$limit}";
		}

		return $this->write_query("
			UPDATE {$this->table_prefix}{$table}
			SET {$query}
		");
	}

	public function delete_query($table, $where = "", $limit = "")
	{
		$query = "";
		if (!empty($where)) {
			$query .= " WHERE {$where}";
		}

		if (!empty($limit)) {
			$query .= " LIMIT {$limit}";
		}

		return $this->write_query("DELETE FROM {$this->table_prefix}{$table} {$query}");
	}

	function optimize_table($table)
	{
		// TODO: Implement optimize_table() method.
	}

	function analyze_table($table)
	{
		// TODO: Implement analyze_table() method.
	}

	function show_create_table($table)
	{
		// TODO: Implement show_create_table() method.
	}

	function show_fields_from($table)
	{
		// TODO: Implement show_fields_from() method.
	}

	function is_fulltext($table, $index = "")
	{
		// TODO: Implement is_fulltext() method.
	}

	function supports_fulltext($table)
	{
		// TODO: Implement supports_fulltext() method.
	}

	function index_exists($table, $index)
	{
		// TODO: Implement index_exists() method.
	}

	function supports_fulltext_boolean($table)
	{
		// TODO: Implement supports_fulltext_boolean() method.
	}

	function create_fulltext_index($table, $column, $name = "")
	{
		// TODO: Implement create_fulltext_index() method.
	}

	function drop_index($table, $name)
	{
		// TODO: Implement drop_index() method.
	}

	function drop_table($table, $hard = false, $table_prefix = true)
	{
		// TODO: Implement drop_table() method.
	}

	function rename_table($old_table, $new_table, $table_prefix = true)
	{
		// TODO: Implement rename_table() method.
	}

	function replace_query($table, $replacements = array(), $default_field = "", $insert_id = true)
	{
		// TODO: Implement replace_query() method.
	}

	function drop_column($table, $column)
	{
		// TODO: Implement drop_column() method.
	}

	function add_column($table, $column, $definition)
	{
		// TODO: Implement add_column() method.
	}

	function modify_column($table, $column, $new_definition, $new_not_null = false, $new_default_value = false)
	{
		// TODO: Implement modify_column() method.
	}

	function rename_column($table, $old_column, $new_column, $new_definition, $new_not_null = false, $new_default_value = false)
	{
		// TODO: Implement rename_column() method.
	}

	function fetch_size($table = '')
	{
		// TODO: Implement fetch_size() method.
	}

	function fetch_db_charsets()
	{
		// TODO: Implement fetch_db_charsets() method.
	}

	function fetch_charset_collation($charset)
	{
		// TODO: Implement fetch_charset_collation() method.
	}

	function build_create_table_collation()
	{
		// TODO: Implement build_create_table_collation() method.
	}

	function escape_binary($string)
	{
		// TODO: Implement escape_binary() method.
	}

	function unescape_binary($string)
	{
		// TODO: Implement unescape_binary() method.
	}

	/**
	 * @param int|string $value
	 * @param string $quote
	 *
	 * @return int|string
	 */
	private function quote_val($value, $quote="'")
	{
		if (is_int($value)) {
			return $value;
		}

		return "{$quote}{$value}{$quote}";
	}
}