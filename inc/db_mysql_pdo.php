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
}