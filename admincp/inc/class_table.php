<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

class DefaultTable
{
	var $cells = array();
	var $rows = array();
	var $headers = array();

	function construct_cell($data, $extra=array())
	{
		$this->cells[] = array("data" => $data, "extra" => $extra);
	}

	function construct_row($extra = array())
	{
		$i = 1;
		// We construct individual cells here
		foreach($this->cells as $key => $cell)
		{
			$cells .= "\t<td";
			if($key == 0)
			{
				$cell['extra']['class'] .= " first";
			}
			elseif(!$this->cells[$key+1])
			{
				$cell['extra']['class'] .= " last";
			}
			if($i == 2)
			{
				$cell['extra']['class'] .= " alt_col";
				$i = 0;
			}
			$i++;
			if($cell['extra']['class'])
			{
				$cells .= " class=\"".$cell['extra']['class']."\"";
			}
			if($cell['extra']['style'])
			{
				$cells .= " style=\"".$cell['extra']['style']."\"";
			}
			if($cell['extra']['id'])
			{
				$cells .= " id=\"".$cell['extra']['id']."\"";
			}
			if(isset($cell['extra']['colspan']) && $cell['extra']['colspan'] > 1)
			{
				$cells .= " colspan=\"".$cell['extra']['colspan']."\"";
			}
			if(isset($cell['extra']['rowspan']) && $cell['extra']['rowspan'] > 1)
			{
				$cells .= " rowspan=\"".$cell['extra']['rowspan']."\"";
			}
			if($cell['extra']['width'])
			{
				$cells .= " width=\"".$cell['extra']['width']."\"";
			}
			$cells .= ">";
			$cells .= $cell['data'];
			$cells .= "</td>\n";
		}
		$data['cells'] = $cells;
		$data['extra'] = $extra;
		$this->rows[] = $data;
		
		$this->cells = array();
	}

	function construct_header($data, $extra=array())
	{
		$this->headers[] = array("data" => $data, "extra" => $extra);
	}

	function output($heading="", $border=1, $class="general")
	{
		echo $this->construct_html($heading, $border, $class);
	}

	function construct_html($heading="", $border=1, $class="general")
	{
		if($border == 1)
		{
			$table .= "<div class=\"border_wrapper\">\n";
			if($heading != "")
			{
				$table .= "	<div class=\"title\">".$heading."</div>\n";
			}
		}
		$table .= "<table";
		if($class != "")
		{
			$table .= " class=\"".$class."\"";
		}
		$table .= " cellspacing=\"0\">\n";
		if($this->headers)
		{
			$table .= "\t<thead>\n";
			$table .= "\t\t<tr>\n";
			foreach($this->headers as $key => $data)
			{
				$table .= "\t\t\t<th";
				if($key == 0)
				{
					$data['extra']['class'] .= " first";
				}
				elseif(!$this->headers[$key+1])
				{
					$data['extra']['class'] .= " last";
				}
				if($data['extra']['class'])
				{
					$table .= " class=\"".$data['extra']['class']."\"";
				}
				if($data['extra']['width'])
				{
					$table .= " width=\"".$data['extra']['width']."\"";
				}
				if(isset($data['extra']['colspan']) && $data['extra']['colspan'] > 1)
				{
					$table .= " colspan=\"".$data['extra']['colspan']."\"";
				}
				$table .= ">".$data['data']."</th>\n";
			}
			$table .= "\t\t</tr>\n";
			$table .= "\t</thead>\n";
		}
		$table .= "\t<tbody>\n";
		$i = 1;
		foreach($this->rows as $key => $table_row)
		{
			$table .= "\t\t<tr";
			if($table_row['extra']['id'])
			{
				$table .= " id=\"{$table_row['extra']['id']}\"";
			}
			if($key == 0)
			{
				$table_row['extra']['class'] .= " first";
			}
			else if(!$this->rows[$key+1])
			{
				$table_row['extra']['class'] .= " last";
			}
			if($i == 2 && !isset($table_row['extra']['no_alt_row']))
			{
				$table_row['extra']['class'] .= " alt_row";
				$i = 0;
			}
			$i++;
			if($table_row['extra']['class'])
			{
				$table_row['extra']['class'] = trim($table_row['extra']['class']);
				$table .= " class=\"".$table_row['extra']['class']."\"";
			}
			$table .= ">\n";
			$table .= $table_row['cells'];
			$table .= "\t\t</tr>\n";
		}
		$table .= "\t</tbody>\n";
		$table .= "</table>\n";
		// Clean up
		$this->cells = $this->rows = $this->headers = array();
		if($border == 1)
		{
			$table .= "</div>";
		}
		return $table;
	}

}
?>