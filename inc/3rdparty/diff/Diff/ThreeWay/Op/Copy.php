<?php
/**
 * Copyright 2007-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class Horde_Text_Diff_ThreeWay_Op_Copy extends Horde_Text_Diff_ThreeWay_Op_Base
{
    public function __construct($lines = false)
    {
        $this->orig = $lines ? $lines : array();
        $this->final1 = &$this->orig;
        $this->final2 = &$this->orig;
    }

    public function merged()
    {
        return $this->orig;
    }

    public function isConflict()
    {
        return false;
    }
}
