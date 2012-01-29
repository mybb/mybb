<?php

/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

interface ModsInterface
{	
	public function getAll($where='',$cache=true);
	public function getCached();
	public function getByID($id,$cache=true);
	
	public function create($array);
	public function deleteByID($id);
	public function updateByID($array, $id);
}