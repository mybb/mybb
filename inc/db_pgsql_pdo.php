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
	 * The title of this layer.
	 *
	 * @var string
	 */
	public $title = "PostgreSQL (PDO)";

	/**
	 * The short title of this layer.
	 *
	 * @var string
	 */
	public $short_title = "PostgreSQL (PDO)";

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

	public function query($string, $hideErrors = false, $writeQuery = false)
	{
		$string = preg_replace("#LIMIT (\s*)([0-9]+),(\s*)([0-9]+);?$#im", "LIMIT $4 OFFSET $2", trim($string));

		return parent::query($string, $hideErrors, $writeQuery);
	}

	public function explain_query($string, $qtime)
	{
		$duration = format_time_duration($qtime);
		$queryText = htmlspecialchars_uni($string);

		if (preg_match('/^\\s*SELECT\\b/i', $string) === 1) {
			$query = $this->current_link->query("EXPLAIN {$string}");

			$this->explain .= <<<HTML
<table style="background-color: #666;" width="95%" cellpadding="4" cellspacing="1" align="center">
	<tr>
		<td colspan="8" style="background-color: #ccc;">
			<strong>#{$this->query_count} - Select Query</strong>
		</td>
	</tr>
	<tr>
		<td colspan="8" style="background-color: #fefefe;">
			<span style=\"font-family: Courier; font-size: 14px;">{$queryText}</span>
		</td>
	<tr style="background-color: #efefef">
		<td>
			<strong>Info</strong>
		</td>
	</tr>
HTML;

			while ($table = $query->fetch(PDO::FETCH_ASSOC)) {
				$this->explain .= <<<HTML
	<tr style="background-color: #fff">
		<td>{$table['QUERY PLAN']}</td>
	</tr>
HTML;
			}

			$this->explain .= <<<HTML
	<tr>
		<td colspan="8" style="background-color: #fff;">
			Query Time: {$duration}
		</td>
	</tr>
</table>
<br />
HTML;
		} else {
			$this->explain .= <<<HTML
<table style="background-color: #666;" width="95%" cellpadding="4" cellspacing="1" align="center">
	<tr>
		<td style="background-color: #ccc;">
			<strong>#{$this->query_count} - Write Query</strong>
		</td>
	</tr>
	<tr style="background-color: #fefefe;">
		<td>
			<span style="font-family: Courier; font-size: 14px;">{$queryText}</span>
		</td>
	</tr>
	<tr>
		<td style="background-color: #fff">
			Query Time: {$duration}
		</td>
	</tr>
</table>
<br />
HTML;
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

	public function simple_select($table, $fields = "*", $conditions = "", $options = array())
	{
		$query = "SELECT {$fields} FROM {$this->table_prefix}{$table}";
		if ($conditions != "") {
			$query .= " WHERE {$conditions}";
		}

		if (isset($options['group_by'])) {
			$query .= " GROUP BY {$options['group_by']}";
		}

		if (isset($options['order_by'])) {
			$query .= " ORDER BY {$options['order_by']}";
			if (isset($options['order_dir'])) {
				$query .= " {$options['order_dir']}";
			}
		}

		if (isset($options['limit_start']) && isset($options['limit'])) {
			$query .= " LIMIT {$options['limit']} OFFSET {$options['limit_start']}";
		} else if (isset($options['limit'])) {
			$query .= " LIMIT {$options['limit']}";
		}

		return $this->query($query);
	}

	public function insert_query($table, $array)
	{
		if (!is_array($array)) {
			return false;
		}

		$values = $this->build_value_string($table, $array);

		$fields = implode(",", array_keys($array));
		$this->write_query("
			INSERT
			INTO {$this->table_prefix}{$table} ({$fields})
			VALUES ({$values})
		");

		return $this->insert_id();
	}

	private function quote_val($value, $quote = "'")
	{
		if (is_int($value)) {
			return $value;
		}

		return "{$quote}{$value}{$quote}";
	}

	public function insert_query_multiple($table, $array)
	{
		if (!is_array($array)){
			return;
		}

		// Field names
		$fields = array_keys($array[0]);
		$fields = implode(",", $fields);

		$insert_rows = array();
		foreach ($array as $values) {
			$insert_rows[] = "(".$this->build_value_string($table, $values).")";
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

		$query = $this->build_field_value_string($table, $array, $no_quote);

		if(!empty($where)) {
			$query .= " WHERE {$where}";
		}

		return $this->write_query("
			UPDATE {$this->table_prefix}$table
			SET $query
		");
	}

	public function delete_query($table, $where = "", $limit = "")
	{
		$query = "";
		if (!empty($where)) {
			$query .= " WHERE {$where}";
		}

		return $this->write_query("
			DELETE
			FROM {$this->table_prefix}$table
			$query
		");
	}

	public function optimize_table($table)
	{
		$this->write_query("VACUUM {$this->table_prefix}{$table};");
	}

	public function analyze_table($table)
	{
		$this->write_query("ANALYZE {$this->table_prefix}{$table};");
	}

	public function show_create_table($table)
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

		while ($row = $this->fetch_array($query)) {
			// Get the data from the table
			$query2 = $this->write_query("
				SELECT pg_get_expr(d.adbin, d.adrelid) as rowdefault
				FROM pg_attrdef d
				LEFT JOIN pg_class c ON (c.oid = d.adrelid)
				WHERE c.relname = '{$this->table_prefix}{$table}' AND d.adnum = '{$row['attnum']}'
			");

			if (!$query2) {
				unset($row['rowdefault']);
			} else {
				$row['rowdefault'] = $this->fetch_field($query2, 'rowdefault');
			}

			if ($row['type'] == 'bpchar') {
				// Stored in the engine as bpchar, but in the CREATE TABLE statement it's char
				$row['type'] = 'char';
			}

			$line = "  {$row['field']} {$row['type']}";

			if (strpos($row['type'], 'char') !== false) {
				if ($row['lengthvar'] > 0) {
					$line .= '('.($row['lengthvar'] - 4).')';
				}
			}

			if (strpos($row['type'], 'numeric') !== false) {
				$line .= '('.sprintf("%s,%s", (($row['lengthvar'] >> 16) & 0xffff), (($row['lengthvar'] - 4) & 0xffff)).')';
			}

			if (!empty($row['rowdefault'])) {
				$line .= " DEFAULT {$row['rowdefault']}";
			}

			if ($row['notnull'] == 't') {
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

		$unique_keys = array();

		// We do this in two steps. It makes placing the comma easier
		while ($row = $this->fetch_array($query)) {
			if ($row['primary_key'] == 't') {
				$primary_key[] = $row['column_name'];
				$primary_key_name = $row['index_name'];
			}

			if ($row['unique_key'] == 't') {
				$unique_keys[$row['index_name']][] = $row['column_name'];
			}
		}

		if (!empty($primary_key)) {
			$lines[] = "  CONSTRAINT $primary_key_name PRIMARY KEY (".implode(', ', $primary_key).")";
		}

		foreach ($unique_keys as $key_name => $key_columns) {
			$lines[] = "  CONSTRAINT $key_name UNIQUE (".implode(', ', $key_columns).")";
		}

		$table_lines .= implode(", \n", $lines);
		$table_lines .= "\n)\n";

		return $table_lines;
	}

	public function show_fields_from($table)
	{
		$query = $this->write_query("SELECT column_name FROM information_schema.constraint_column_usage WHERE table_name = '{$this->table_prefix}{$table}' and constraint_name = '{$this->table_prefix}{$table}_pkey' LIMIT 1");
		$primary_key = $this->fetch_field($query, 'column_name');

		$query = $this->write_query("
			SELECT column_name, data_type, is_nullable, column_default, character_maximum_length, numeric_precision, numeric_precision_radix, numeric_scale
			FROM information_schema.columns
			WHERE table_name = '{$this->table_prefix}{$table}'
		");

		$field_info = array();
		while ($field = $this->fetch_array($query)) {
			if ($field['column_name'] == $primary_key) {
				$field['_key'] = 'PRI';
			} else {
				$field['_key'] = '';
			}

			if (!is_null($field['column_default']) && stripos($field['column_default'], 'nextval') !== false) {
				$field['_extra'] = 'auto_increment';
			} else {
				$field['_extra'] = '';
			}

			// bit, character, text fields.
			if (!is_null($field['character_maximum_length'])) {
				$field['data_type'] .= '('.(int)$field['character_maximum_length'].')';
			}
			// numeric/decimal fields.
			else if ($field['numeric_precision_radix'] == 10 && !is_null($field['numeric_precision']) && !is_null($field['numeric_scale'])) {
				$field['data_type'] .= '('.(int)$field['numeric_precision'].','.(int)$field['numeric_scale'].')';
			}

			$field_info[] = array(
				'Field' => $field['column_name'],
				'Type' => $field['data_type'],
				'Null' => $field['is_nullable'],
				'Key' => $field['_key'],
				'Default' => $field['column_default'],
				'Extra' => $field['_extra'],
			);
		}

		return $field_info;
	}

	function is_fulltext($table, $index = "")
	{
		return false;
	}

	public function supports_fulltext($table)
	{
		return false;
	}

	public function index_exists($table, $index)
	{
		$err = $this->error_reporting;
		$this->error_reporting = 0;

		$tableName = $this->escape_string("{$this->table_prefix}{$table}");

		$query = $this->write_query("SELECT * FROM pg_indexes WHERE tablename = '{$tableName}'");

		$exists = $this->fetch_field($query, $index);
		$this->error_reporting = $err;

		return (bool)$exists;
	}

	public function supports_fulltext_boolean($table)
	{
		return false;
	}

	public function create_fulltext_index($table, $column, $name = "")
	{
		return false;
	}

	public function drop_index($table, $name)
	{
		$this->write_query("
			ALTER TABLE {$this->table_prefix}{$table}
			DROP INDEX {$name}
		");
	}

	public function drop_table($table, $hard = false, $table_prefix = true)
	{
		if ($table_prefix == false) {
			$table_prefix = "";
		} else {
			$table_prefix = $this->table_prefix;
		}

		$table_prefix_bak = $this->table_prefix;
		$this->table_prefix = '';
		$fields = array_column($this->show_fields_from($table_prefix.$table), 'Field');

		if ($hard == false) {
			if($this->table_exists($table_prefix.$table))
			{
				$this->write_query("DROP TABLE {$table_prefix}{$table}");
			}
		} else {
			$this->write_query("DROP TABLE {$table_prefix}{$table}");
		}

		$this->table_prefix = $table_prefix_bak;

		if(!empty($fields)) {
			foreach ($fields as &$field) {
				$field = "{$table_prefix}{$table}_{$field}_seq";
			}
			unset($field);

			if (version_compare($this->get_version(), '8.2.0', '>=')) {
				$fields = implode(', ', $fields);
				$this->write_query("DROP SEQUENCE IF EXISTS {$fields}");
			} else {
				$fields = "'" . implode("', '", $fields) . "'";
				$query = $this->query("SELECT sequence_name as field FROM information_schema.sequences WHERE sequence_name in ({$fields}) AND sequence_schema = 'public'");
				while ($row = $this->fetch_array($query)) {
					$this->write_query("DROP SEQUENCE {$row['field']}");
				}
			}
		}
	}

	public function rename_table($old_table, $new_table, $table_prefix = true)
	{
		if ($table_prefix == false) {
			$table_prefix = "";
		} else {
			$table_prefix = $this->table_prefix;
		}

		return $this->write_query("ALTER TABLE {$table_prefix}{$old_table} RENAME TO {$table_prefix}{$new_table}");
	}

	public function replace_query($table, $replacements = array(), $default_field = "", $insert_id = true)
	{
		global $mybb;

		if ($default_field == "") {
			$query = $this->write_query("SELECT column_name FROM information_schema.constraint_column_usage WHERE table_name = '{$this->table_prefix}{$table}' and constraint_name = '{$this->table_prefix}{$table}_pkey' LIMIT 1");
			$main_field = $this->fetch_field($query, 'column_name');
		} else {
			$main_field = $default_field;
		}

		if (!is_array($main_field)) {
			$main_field = array($main_field);
		}

		if(version_compare($this->get_version(), '9.5.0', '>='))
		{
			// ON CONFLICT clause supported

			$main_field_csv = implode(',', $main_field);

			// INSERT-like list of fields and values
			$fields = implode(",", array_keys($replacements));
			$values = $this->build_value_string($table, $replacements);

			// UPDATE-like SET list, using special EXCLUDED table to avoid passing values twice
			$reassignment_values = array();
			$true_replacement_keys = array_diff(
				array_keys($replacements),
				array_flip($main_field)
			);
			foreach($true_replacement_keys as $key)
			{
				$reassignment_values[$key] = 'EXCLUDED.' . $key;
			}

			$reassignments = $this->build_field_value_string($table, $reassignment_values, true);

			$this->write_query("
				INSERT
				INTO {$this->table_prefix}{$table} ({$fields})
				VALUES ({$values})
				ON CONFLICT ($main_field_csv) DO UPDATE SET {$reassignments}
			");
		}
		else
		{
			// manual SELECT and UPDATE/INSERT (prone to TOCTOU issues)

			$update = false;
			$search_bit = array();

			if (!is_array($main_field)) {
				$main_field = array($main_field);
			}

			foreach ($main_field as $field) {
				if (isset($mybb->binary_fields[$table][$field]) && $mybb->binary_fields[$table][$field]) {
					$search_bit[] = "{$field} = ".$replacements[$field];
				} else {
					$search_bit[] = "{$field} = ".$this->quote_val($replacements[$field]);
				}
			}

			$search_bit = implode(" AND ", $search_bit);

			$query = $this->write_query("SELECT COUNT(".$main_field[0].") as count FROM {$this->table_prefix}{$table} WHERE {$search_bit} LIMIT 1");

			if ($this->fetch_field($query, "count") == 1) {
				$update = true;
			}

			if ($update === true) {
				return $this->update_query($table, $replacements, $search_bit);
			} else {
				return $this->insert_query($table, $replacements);
			}
		}
	}

	public function drop_column($table, $column)
	{
		return $this->write_query("ALTER TABLE {$this->table_prefix}{$table} DROP {$column}");
	}

	public function add_column($table, $column, $definition)
	{
		return $this->write_query("ALTER TABLE {$this->table_prefix}{$table} ADD {$column} {$definition}");
	}

	public function modify_column($table, $column, $new_definition, $new_not_null = false, $new_default_value = false)
	{
		$result1 = $result2 = $result3 = true;

		if ($new_definition !== false) {
			$result1 = $this->write_query("ALTER TABLE {$this->table_prefix}{$table} ALTER COLUMN {$column} TYPE {$new_definition}");
		}

		if ($new_not_null !== false) {
			$set_drop = "DROP";

			if (strtolower($new_not_null) == "set") {
				$set_drop = "SET";
			}

			$result2 = $this->write_query("ALTER TABLE {$this->table_prefix}{$table} ALTER COLUMN {$column} {$set_drop} NOT NULL");
		}

		if ($new_default_value !== null) {
			if($new_default_value !== false) {
				$result3 = $this->write_query("ALTER TABLE {$this->table_prefix}{$table} ALTER COLUMN {$column} SET DEFAULT {$new_default_value}");
			} else {
				$result3 = $this->write_query("ALTER TABLE {$this->table_prefix}{$table} ALTER COLUMN {$column} DROP DEFAULT");
			}
		}

		return $result1 && $result2 && $result3;
	}

	public function rename_column($table, $old_column, $new_column, $new_definition, $new_not_null = false, $new_default_value = false)
	{
		$result1 = $this->write_query("ALTER TABLE {$this->table_prefix}{$table} RENAME COLUMN {$old_column} TO {$new_column}");
		$result2 = $this->modify_column($table, $new_column, $new_definition, $new_not_null, $new_default_value);

		return $result1 && $result2;
	}

	public function fetch_size($table = '')
	{
		if (!empty($table)) {
			$query = $this->query("SELECT SUM(reltuples), SUM(relpages) FROM pg_class WHERE relname = '{$this->table_prefix}{$table}'");
		} else {
			$query = $this->query("SELECT SUM(reltuples), SUM(relpages) FROM pg_class");
		}

		if (null === $query) {
			return 0;
		}

		$result = $this->fetch_array($query, PDO::FETCH_NUM);

		if (false === $result) {
			return 0;
		}

		return $result[0] + $result[1];
	}

	public function fetch_db_charsets()
	{
		return false;
	}

	public function fetch_charset_collation($charset)
	{
		return false;
	}

	public function build_create_table_collation()
	{
		return '';
	}

	public function insert_id()
	{
		try {
			return $this->write_link->lastInsertId();
		} catch (PDOException $e) {
			// in order to behave the same way as the MySQL driver, we return false if there is no last insert ID
			return false;
		}
	}

	public function escape_binary($string)
	{
		$hex = bin2hex($string);
		return "decode('{$hex}', 'hex')";
	}

	public function unescape_binary($string)
	{
		// binary fields are treated as streams
		/** @var resource $string */
		return fgets($string);
	}

	/**
	 * @param string $table
	 * @param string $append
	 *
	 * @return string
	 */
	public function build_fields_string($table, $append="")
	{
		$fields = $this->show_fields_from($table);
		$comma = $fieldstring = '';

		foreach($fields as $key => $field)
		{
			$fieldstring .= "{$comma}{$append}{$field['Field']}";
			$comma = ',';
		}

		return $fieldstring;
	}

	/**
	 * @param string $table
	 * @param array $array
	 * @param bool $no_quote
	 *
	 * @return string
	 */
	protected function build_field_value_string($table, $array, $no_quote = false)
	{
		global $mybb;

		$strings = array();

		if ($no_quote == true)
		{
			$quote = "";
		}
		else
		{
			$quote = "'";
		}

		foreach($array as $field => $value)
		{
			if(!isset($mybb->binary_fields[$table][$field]) || !$mybb->binary_fields[$table][$field])
			{
				$value = $this->quote_val($value, $quote);
			}

			$strings[] = "{$field}={$value}";
		}

		$string = implode(', ', $strings);

		return $string;
	}

	/**
	 * @param string $table
	 * @param array $array
	 *
	 * @return string
	 */
	protected function build_value_string($table, $array)
	{
		global $mybb;

		$values = array();

		foreach($array as $field => $value)
		{
			if(!isset($mybb->binary_fields[$table][$field]) || !$mybb->binary_fields[$table][$field])
			{
				$value = $this->quote_val($value);
			}

			$values[$field] = $value;
		}

		$string = implode(",", $values);

		return $string;
	}

	public function __set($name, $value)
	{
		if ($name === 'type') {
			// NOTE: This is to prevent the type being set - this type should appear as `pgsql` to ensure compatibility
			return;
		}
	}

	public function __get($name)
	{
		if ($name === 'type') {
			// NOTE: this is to ensure compatibility checks on the DB type will work
			return 'pgsql';
		}

		return null;
	}
}