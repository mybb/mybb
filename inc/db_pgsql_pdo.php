<?php
/**
 * MyBB 1.8
 * Copyright 2020 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

class PostgresPdoDbDriver extends AbstractPdoDbDriver
{
	/**
	 * Explanation of a query.
	 *
	 * @var string
	 */
	public $explain = '';

	protected function getDsn($hostname, $db, $port, $encoding)
	{
		$dsn = "pgsql:host={$hostname};dbname={$db}";

		if ($port !== null) {
			$dsn .= ";port={$port}";
		}

		if (!empty($encoding)) {
			$dsn .= ";options='--client_encoding={$encoding}'";
		}

		return $dsn;
	}

	public function explain_query($string, $qtime)
	{
		if (preg_match("#^\s*select#i", $string))
		{
			$query = $this->read_link->query("EXPLAIN {$string}");
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

			while ($table = $query->fetch(PDO::FETCH_ASSOC)) {
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

	public function list_tables($database, $prefix = '')
	{
		if ($prefix) {
			$query = $this->write_query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE '{$this->escape_string($prefix)}%'");
		} else {
			$query = $this->write_query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
		}

		return $query->fetchAll(PDO::FETCH_COLUMN);
	}

	public function table_exists($table)
	{
		$query = $this->write_query("SELECT COUNT(table_name) as table_names FROM information_schema.tables WHERE table_schema = 'public' AND table_name='{$this->table_prefix}{$table}'");

		$exists = $this->fetch_field($query, 'table_names');

		return $exists > 0;
	}

	public function field_exists($field, $table)
	{
		$query = $this->write_query("SELECT COUNT(column_name) as column_names FROM information_schema.columns WHERE table_name='{$this->table_prefix}{$table}' AND column_name='{$field}'");

		$exists = $this->fetch_field($query, 'column_names');

		return $exists > 0;
	}

	function simple_select($table, $fields = "*", $conditions = "", $options = array())
	{
		// TODO: Implement simple_select() method.
	}

	function insert_query($table, $array)
	{
		// TODO: Implement insert_query() method.
	}

	function insert_query_multiple($table, $array)
	{
		// TODO: Implement insert_query_multiple() method.
	}

	function update_query($table, $array, $where = "", $limit = "", $no_quote = false)
	{
		// TODO: Implement update_query() method.
	}

	function delete_query($table, $where = "", $limit = "")
	{
		// TODO: Implement delete_query() method.
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
}