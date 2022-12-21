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
	/**
	 * The title of this layer.
	 *
	 * @var string
	 */
	public $title = "MySQL (PDO)";

	/**
	 * The short title of this layer.
	 *
	 * @var string
	 */
	public $short_title = "MySQL (PDO)";

	/**
	 * Explanation of a query.
	 *
	 * @var string
	 */
	public $explain = '';

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
		global $plugins;

		$duration = format_time_duration($qtime);
		$queryText = htmlspecialchars_uni($string);

		$debug_extra = '';
		if ($plugins->current_hook) {
			$debug_extra = <<<HTML
<div style="float_right">(Plugin Hook: {$plugins->current_hook})</div>
HTML;
		}

		if (preg_match('/^\\s*SELECT\\b/i', $string) === 1) {
			$query = $this->current_link->query("EXPLAIN {$string}");

			$this->explain .= <<<HTML
<table style="background-color: #666;" width="95%" cellpadding="4" cellspacing="1" align="center">
	<tr>
		<td colspan="8" style="background-color: #ccc;">
			{$debug_extra}<div><strong>#{$this->query_count} - Select Query</strong></div>
		</td>
	</tr>
	<tr>
		<td colspan="8" style="background-color: #fefefe;">
			<span style="font-family: Courier; font-size: 14px;">{$queryText}</span>
		</td>
	</tr>
	<tr style="background-color: #efefef">
		<td><strong>Table</strong></td>
		<td><strong>Type</strong></td>
		<td><strong>Possible Keys</strong></td>
		<td><strong>Key</strong></td>
		<td><strong>Key Length</strong></td>
		<td><strong>Ref</strong></td>
		<td><strong>Rows</strong></td>
		<td><strong>Extra</strong></td>
	</tr>
HTML;

			while ($table = $query->fetch(PDO::FETCH_ASSOC)) {
				$this->explain .= <<<HTML
<tr bgcolor="#ffffff">
	<td>{$table['table']}</td>
	<td>{$table['type']}</td>
	<td>{$table['possible_keys']}</td>
	<td>{$table['key']}</td>
	<td>{$table['key_len']}</td>
	<td>{$table['ref']}</td>
	<td>{$table['rows']}</td>
	<td>{$table['Extra']}</td>
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
			{$debug_extra}<div><strong>#{$this->query_count} - Write Query</strong></div>
		</td>
	</tr>
	<tr style="background-color: #fefefe;">
		<td><span style="font-family: Courier; font-size: 14px;">{$queryText}</span></td>
	</tr>
	<tr>
		<td bgcolor="#ffffff">Query Time: {$duration}</td>
	</tr>
</table>
<br/>
HTML;
		}

		$this->querylist[$this->query_count]['query'] = $string;
		$this->querylist[$this->query_count]['time'] = $qtime;
	}

	public function list_tables($database, $prefix = '')
	{
		if ($prefix) {
			if (version_compare($this->get_version(), '5.0.2', '>=')) {
				$query = $this->query("SHOW FULL TABLES FROM `{$database}` WHERE table_type = 'BASE TABLE' AND `Tables_in_{$database}` LIKE '".$this->escape_string($prefix)."%'");
			} else {
				$query = $this->query("SHOW TABLES FROM `{$database}` LIKE '{$this->escape_string($prefix)}%'");
			}
		} else {
			if (version_compare($this->get_version(), '5.0.2', '>=')) {
				$query = $this->query("SHOW FULL TABLES FROM `{$database}` WHERE table_type = 'BASE TABLE'");
			} else {
				$query = $this->query("SHOW TABLES FROM `{$database}`");
			}
		}

		return $query->fetchAll(PDO::FETCH_COLUMN, 0);
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
			FROM {$this->table_prefix}{$table}
			LIKE '{$field}'
		");

		$exists = $this->num_rows($query);

		return $exists > 0;
	}

	public function simple_select($table, $fields = "*", $conditions = "", $options = array())
	{
		$query = "SELECT {$fields} FROM {$this->table_prefix}{$table}";

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

	public function optimize_table($table)
	{
		$this->write_query("OPTIMIZE TABLE {$this->table_prefix}{$table}");
	}

	public function analyze_table($table)
	{
		$this->write_query("ANALYZE TABLE {$this->table_prefix}{$table}");
	}

	public function show_create_table($table)
	{
		$query = $this->write_query("SHOW CREATE TABLE {$this->table_prefix}{$table}");
		$structure = $this->fetch_array($query);

		return $structure['Create Table'];
	}

	public function show_fields_from($table)
	{
		$query = $this->write_query("SHOW FIELDS FROM {$this->table_prefix}{$table}");

		$field_info = array();
		while ($field = $this->fetch_array($query)) {
			$field_info[] = $field;
		}

		return $field_info;
	}

	public function is_fulltext($table, $index = "")
	{
		$structure = $this->show_create_table($table);
		if ($index != "") {
			if(preg_match("#FULLTEXT KEY (`?){$index}(`?)#i", $structure)) {
				return true;
			}

			return false;
		}

		if (preg_match('#FULLTEXT KEY#i', $structure)) {
			return true;
		}

		return false;
	}

	public function supports_fulltext($table)
	{
		$version = $this->get_version();
		$query = $this->write_query("SHOW TABLE STATUS LIKE '{$this->table_prefix}$table'");
		$status = $this->fetch_array($query);
		$table_type = my_strtoupper($status['Engine']);

		if (version_compare($version, '3.23.23', '>=') && ($table_type == 'MYISAM' || $table_type == 'ARIA')) {
			return true;
		} else if (version_compare($version, '5.6', '>=') && $table_type == 'INNODB') {
			return true;
		}

		return false;
	}

	public function index_exists($table, $index)
	{
		$index_exists = false;
		$query = $this->write_query("SHOW INDEX FROM {$this->table_prefix}{$table}");
		while ($ukey = $this->fetch_array($query)) {
			if ($ukey['Key_name'] == $index) {
				$index_exists = true;
				break;
			}
		}

		return $index_exists;
	}

	public function supports_fulltext_boolean($table)
	{
		$version = $this->get_version();
		$supports_fulltext = $this->supports_fulltext($table);
		if (version_compare($version, '4.0.1', '>=') && $supports_fulltext == true) {
			return true;
		}

		return false;
	}

	public function create_fulltext_index($table, $column, $name = "")
	{
		$this->write_query("ALTER TABLE {$this->table_prefix}{$table} ADD FULLTEXT {$name} ({$column})");
	}

	public function drop_index($table, $name)
	{
		$this->write_query("ALTER TABLE {$this->table_prefix}{$table} DROP INDEX {$name}");
	}

	public function drop_table($table, $hard = false, $table_prefix = true)
	{
		if ($table_prefix == false) {
			$table_prefix = "";
		} else {
			$table_prefix = $this->table_prefix;
		}

		if ($hard == false) {
			$this->write_query("DROP TABLE IF EXISTS {$table_prefix}{$table}");
		} else {
			$this->write_query("DROP TABLE {$table_prefix}{$table}");
		}
	}

	public function rename_table($old_table, $new_table, $table_prefix = true)
	{
		if ($table_prefix == false) {
			$table_prefix = "";
		} else {
			$table_prefix = $this->table_prefix;
		}

		return $this->write_query("RENAME TABLE {$table_prefix}{$old_table} TO {$table_prefix}{$new_table}");
	}

	public function replace_query($table, $replacements = array(), $default_field = "", $insert_id = true)
	{
		global $mybb;

		$values = '';
		$comma = '';

		foreach ($replacements as $column => $value) {
			if (isset($mybb->binary_fields[$table][$column]) && $mybb->binary_fields[$table][$column]) {
				if ($value[0] != 'X') { // Not escaped?
					$value = $this->escape_binary($value);
				}

				$values .= $comma."`".$column."`=".$value;
			} else {
				$values .= $comma."`".$column."`=".$this->quote_val($value);
			}

			$comma = ',';
		}

		if (empty($replacements)) {
			return false;
		}

		return $this->write_query("REPLACE INTO {$this->table_prefix}{$table} SET {$values}");
	}

	public function drop_column($table, $column)
	{
		$column = trim($column, '`');

		return $this->write_query("ALTER TABLE {$this->table_prefix}{$table} DROP `{$column}`");
	}

	public function add_column($table, $column, $definition)
	{
		$column = trim($column, '`');

		return $this->write_query("ALTER TABLE {$this->table_prefix}{$table} ADD `{$column}` {$definition}");
	}

	public function modify_column($table, $column, $new_definition, $new_not_null = false, $new_default_value = false)
	{
		$column = trim($column, '`');

		if ($new_not_null !== false) {
			if (strtolower($new_not_null) == "set") {
				$not_null = "NOT NULL";
			} else {
				$not_null = "NULL";
			}
		} else {
			$not_null = '';
		}

		if ($new_default_value !== false) {
			$default = "DEFAULT ".$new_default_value;
		}
		else
		{
			$default = '';
		}

		return (bool)$this->write_query("ALTER TABLE {$this->table_prefix}{$table} MODIFY `{$column}` {$new_definition} {$not_null} {$default}");
	}

	public function rename_column($table, $old_column, $new_column, $new_definition, $new_not_null = false, $new_default_value = false)
	{
		$old_column = trim($old_column, '`');
		$new_column = trim($new_column, '`');

		if ($new_not_null !== false) {
			if(strtolower($new_not_null) == "set") {
				$not_null = "NOT NULL";
			} else {
				$not_null = "NULL";
			}
		} else {
			$not_null = '';
		}

		if ($new_default_value !== false) {
			$default = "DEFAULT ".$new_default_value;
		} else {
			$default = '';
		}

		return (bool)$this->write_query("ALTER TABLE {$this->table_prefix}{$table} CHANGE `{$old_column}` `{$new_column}` {$new_definition} {$not_null} {$default}");
	}

	public function fetch_size($table = '')
	{
		if ($table != '') {
			$query = $this->query("SHOW TABLE STATUS LIKE '{$this->table_prefix}{$table}'");
		} else {
			$query = $this->query("SHOW TABLE STATUS");
		}

		$total = 0;
		while ($table = $this->fetch_array($query)) {
			$total += $table['Data_length'] + $table['Index_length'];
		}

		return $total;
	}

	public function fetch_db_charsets()
	{
		if ($this->write_link && version_compare($this->get_version(), "4.1", "<")) {
			return false;
		}

		return array(
			'big5' => 'Big5 Traditional Chinese',
			'dec8' => 'DEC West European',
			'cp850' => 'DOS West European',
			'hp8' => 'HP West European',
			'koi8r' => 'KOI8-R Relcom Russian',
			'latin1' => 'ISO 8859-1 Latin 1',
			'latin2' => 'ISO 8859-2 Central European',
			'swe7' => '7bit Swedish',
			'ascii' => 'US ASCII',
			'ujis' => 'EUC-JP Japanese',
			'sjis' => 'Shift-JIS Japanese',
			'hebrew' => 'ISO 8859-8 Hebrew',
			'tis620' => 'TIS620 Thai',
			'euckr' => 'EUC-KR Korean',
			'koi8u' => 'KOI8-U Ukrainian',
			'gb2312' => 'GB2312 Simplified Chinese',
			'greek' => 'ISO 8859-7 Greek',
			'cp1250' => 'Windows Central European',
			'gbk' => 'GBK Simplified Chinese',
			'latin5' => 'ISO 8859-9 Turkish',
			'armscii8' => 'ARMSCII-8 Armenian',
			'utf8' => 'UTF-8 Unicode',
			'utf8mb4' => '4-Byte UTF-8 Unicode (requires MySQL 5.5.3 or above)',
			'ucs2' => 'UCS-2 Unicode',
			'cp866' => 'DOS Russian',
			'keybcs2' => 'DOS Kamenicky Czech-Slovak',
			'macce' => 'Mac Central European',
			'macroman' => 'Mac West European',
			'cp852' => 'DOS Central European',
			'latin7' => 'ISO 8859-13 Baltic',
			'cp1251' => 'Windows Cyrillic',
			'cp1256' => 'Windows Arabic',
			'cp1257' => 'Windows Baltic',
			'geostd8' => 'GEOSTD8 Georgian',
			'cp932' => 'SJIS for Windows Japanese',
			'eucjpms' => 'UJIS for Windows Japanese',
		);
	}

	public function fetch_charset_collation($charset)
	{
		$collations = array(
			'big5' => 'big5_chinese_ci',
			'dec8' => 'dec8_swedish_ci',
			'cp850' => 'cp850_general_ci',
			'hp8' => 'hp8_english_ci',
			'koi8r' => 'koi8r_general_ci',
			'latin1' => 'latin1_swedish_ci',
			'latin2' => 'latin2_general_ci',
			'swe7' => 'swe7_swedish_ci',
			'ascii' => 'ascii_general_ci',
			'ujis' => 'ujis_japanese_ci',
			'sjis' => 'sjis_japanese_ci',
			'hebrew' => 'hebrew_general_ci',
			'tis620' => 'tis620_thai_ci',
			'euckr' => 'euckr_korean_ci',
			'koi8u' => 'koi8u_general_ci',
			'gb2312' => 'gb2312_chinese_ci',
			'greek' => 'greek_general_ci',
			'cp1250' => 'cp1250_general_ci',
			'gbk' => 'gbk_chinese_ci',
			'latin5' => 'latin5_turkish_ci',
			'armscii8' => 'armscii8_general_ci',
			'utf8' => 'utf8_general_ci',
			'utf8mb4' => 'utf8mb4_general_ci',
			'ucs2' => 'ucs2_general_ci',
			'cp866' => 'cp866_general_ci',
			'keybcs2' => 'keybcs2_general_ci',
			'macce' => 'macce_general_ci',
			'macroman' => 'macroman_general_ci',
			'cp852' => 'cp852_general_ci',
			'latin7' => 'latin7_general_ci',
			'cp1251' => 'cp1251_general_ci',
			'cp1256' => 'cp1256_general_ci',
			'cp1257' => 'cp1257_general_ci',
			'geostd8' => 'geostd8_general_ci',
			'cp932' => 'cp932_japanese_ci',
			'eucjpms' => 'eucjpms_japanese_ci',
		);

		if (isset($collations[$charset])) {
			return $collations[$charset];
		}

		return false;
	}

	public function build_create_table_collation()
	{
		if (!$this->db_encoding) {
			return '';
		}

		$collation = $this->fetch_charset_collation($this->db_encoding);
		if (!$collation) {
			return '';
		}

		return " CHARACTER SET {$this->db_encoding} COLLATE {$collation}";
	}

	public function escape_binary($string)
	{
		return "X'{$this->escape_string(bin2hex($string))}'";
	}

	public function unescape_binary($string)
	{
		return $string;
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

	public function __set($name, $value)
	{
		if ($name === 'type') {
			// NOTE: This is to prevent the type being set - this type should appear as `mysqli` to ensure compatibility
			return;
		}
	}

	public function __get($name)
	{
		if ($name === 'type') {
			// NOTE: this is to ensure compatibility checks on the DB type will work
			return 'mysqli';
		}

		return null;
	}
}