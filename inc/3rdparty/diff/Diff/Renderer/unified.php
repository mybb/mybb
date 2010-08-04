<?php
/**
 * "Unified" diff renderer.
 *
 * This class renders the diff in classic "unified diff" format.
 *
 * $Horde: framework/Text_Diff/Diff/Renderer/unified.php,v 1.3.10.6 2008/01/04 10:37:27 jan Exp $
 *
 * Copyright 2004-2008 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://opensource.org/licenses/lgpl-license.php.
 *
 * @author  Ciprian Popovici
 * @package Text_Diff
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}
 
/** Text_Diff_Renderer */
require_once MYBB_ROOT.'inc/3rdparty/diff/Diff/Renderer.php';

/**
 * @package Text_Diff
 */
class Text_Diff_Renderer_unified extends Text_Diff_Renderer {

    /**
     * Number of leading context "lines" to preserve.
     */
    var $_leading_context_lines = 4;

    /**
     * Number of trailing context "lines" to preserve.
     */
    var $_trailing_context_lines = 4;

    function _blockHeader($xbeg, $xlen, $ybeg, $ylen)
    {
        if ($xlen != 1) {
            $xbeg .= ',' . $xlen;
        }
        if ($ylen != 1) {
            $ybeg .= ',' . $ylen;
        }
        return "@@ -$xbeg +$ybeg @@";
    }

    function _context($lines)
    {
        return $this->_lines($lines, ' ');
    }

    function _added($lines)
    {
        return $this->_lines($lines, '+');
    }

    function _deleted($lines)
    {
        return $this->_lines($lines, '-');
    }

    function _changed($orig, $final)
    {
        return $this->_deleted($orig) . $this->_added($final);
    }

}
