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
 * Generate a data grid/table.
 */
class DefaultTable
{
	/**
	 * @var array Array of cells for the current row.
	 */
	private $_cells = array();

	/**
	 * @var array Array of rows for the current table.
	 */
	private $_rows = array();

	/**
	 * @var array Array of headers for the current table.
	 */
	private $_headers = array();

	/**
	 * Construct an individual cell for this table.
	 *
	 * @param string The HTML content for this cell.
	 * @param array Array of extra information about this cell (class, id, colspan, rowspan, width)
	 */
	function construct_cell($data, $extra=array())
	{
		$this->_cells[] = array("data" => $data, "extra" => $extra);
	}

	/**
	 * Construct a row from the earlier defined constructed cells for the table.
	 *
	 * @param array Array of extra information about this row (class, id)
	 */
	function construct_row($extra = array())
	{
		$i = 1;
		$cells = '';

		// We construct individual cells here
		foreach($this->_cells as $key => $cell)
		{
			$cells .= "\t\t\t<td";
			
			if(!isset($cell['extra']['class']))
			{
				$cell['extra']['class'] = '';
			}

			if($key == 0)
			{
				$cell['extra']['class'] .= " first";
			}
			elseif(!isset($this->_cells[$key+1]))
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
				$cells .= " class=\"".trim($cell['extra']['class'])."\"";
			}
			if(isset($cell['extra']['style']))
			{
				$cells .= " style=\"".$cell['extra']['style']."\"";
			}
			if(isset($cell['extra']['id']))
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
			if(isset($cell['extra']['width']))
			{
				$cells .= " width=\"".$cell['extra']['width']."\"";
			}
			$cells .= ">";
			$cells .= $cell['data'];
			$cells .= "</td>\n";
		}
		$data['cells'] = $cells;
		$data['extra'] = $extra;
		$this->_rows[] = $data;

		$this->_cells = array();
	}

	/**
	 * return the cells of a row for the table based row.
	 *
	 * @param string The id of the row you want to give it.
	 * @param boolean Whether or not to return or echo the resultant contents.
	 * @return string The output of the row cells (optional).
	 */
	function output_row_cells($row_id, $return=false)
	{
		$row = $this->_rows[$row_id]['cells'];

		if(!$return)
		{
			echo $row;
		}
		else
		{
			return $row;
		}
	}

	/**
	 * Count the number of rows in the table. Useful for displaying a 'no rows' message.
	 *
	 * @return int The number of rows in the table.
	 */
	function num_rows()
	{
		return count($this->_rows);
	}

	/**
	 * Construct a header cell for this table.
	 *
	 * @param string The HTML content for this header cell.
	 * @param array Array of extra information for this header cell (class, style, colspan, width)
	 */
	function construct_header($data, $extra=array())
	{
		$this->_headers[] = array("data" => $data, "extra" => $extra);
	}

	/**
	 * Output this table to the browser.
	 *
	 * @param string The heading for this table.
	 * @param int The border width for this table.
	 * @param string The class for this table.
	 * @param boolean Whether or not to return or echo the resultant contents.
	 * @return string The output of the row cells (optional).
	 */
	function output($heading="", $border=1, $class="general", $return=false)
	{
		if($return == true)
		{
			return $this->construct_html($heading, $border, $class);
		}
		else
		{
			echo $this->construct_html($heading, $border, $class);
		}
	}

	/**
	 * Fetch the built HTML for this table.
	 *
	 * @param string The heading for this table.
	 * @param int The border width for this table.
	 * @param string The class for this table.
	 * @param string The id for this table.
	 * @return string The built HTML.
	 */
	function construct_html($heading="", $border=1, $class=null, $table_id="")
	{
		$table = '';
		if($border == 1)
		{
			$table .= "<div class=\"border_wrapper\">\n";
			if($heading != "")
			{
				$table .= "	<div class=\"title\">".$heading."</div>\n";
			}
		}
		$table .= "<table";
		if(!is_null($class))
		{
			if(!$class)
			{
				$class = "general";
			}
			$table .= " class=\"".$class."\"";
		}
		if($table_id != "")
		{
			$table .= " id=\"".$table_id."\"";
		}
		$table .= " cellspacing=\"0\">\n";
		if($this->_headers)
		{
			$table .= "\t<thead>\n";
			$table .= "\t\t<tr>\n";
			foreach($this->_headers as $key => $data)
			{
				$table .= "\t\t\t<th";
				if($key == 0)
				{
					$data['extra']['class'] .= " first";
				}
				elseif(!isset($this->_headers[$key+1]))
				{
					$data['extra']['class'] .= " last";
				}
				if(isset($data['extra']['class']))
				{
					$table .= " class=\"".$data['extra']['class']."\"";
				}
				if(isset($data['extra']['style']))
				{
					$table .= " style=\"".$data['extra']['style']."\"";
				}
				if(isset($data['extra']['width']))
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
		foreach($this->_rows as $key => $table_row)
		{
			$table .= "\t\t<tr";
			if(isset($table_row['extra']['id']))
			{
				$table .= " id=\"{$table_row['extra']['id']}\"";
			}

			if(!isset($table_row['extra']['class']))
			{
				$table_row['extra']['class'] = '';
			}

			if($key == 0)
			{
				$table_row['extra']['class'] .= " first";
			}
			else if(!isset($this->_rows[$key+1]))
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
				$table .= " class=\"".trim($table_row['extra']['class'])."\"";
			}
			$table .= ">\n";
			$table .= $table_row['cells'];
			$table .= "\t\t</tr>\n";
		}
		$table .= "\t</tbody>\n";
		$table .= "</table>\n";
		// Clean up
		$this->_cells = $this->_rows = $this->_headers = array();
		if($border == 1)
		{
			$table .= "</div>";
		}
		return $table;
	}
}
