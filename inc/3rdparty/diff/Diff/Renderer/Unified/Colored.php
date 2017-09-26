<?php
/**
 * Copyright 2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Text_Diff
 */

/**
 * "Unified" diff renderer with output coloring.
 *
 * @author    Jan Schneider <jan@horde.org>
 * @category  Horde
 * @copyright 2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL
 * @package   Text_Diff
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class Horde_Text_Diff_Renderer_Unified_Colored
extends Horde_Text_Diff_Renderer_Unified
{
    /**
     * CLI handler.
     *
     * Contrary to the name, it supports color highlighting for HTML too.
     *
     * @var Horde_Cli
     */
    protected $_cli;

    /**
     * Constructor.
     */
    public function __construct($params = array())
    {
        if (!isset($params['cli'])) {
            throw new BadMethodCallException('CLI handler is missing');
        }
        parent::__construct($params);
        $this->_cli = $params['cli'];
    }

    protected function _blockHeader($xbeg, $xlen, $ybeg, $ylen)
    {
        return $this->_cli->color(
            'lightmagenta', parent::_blockHeader($xbeg, $xlen, $ybeg, $ylen)
        );
    }

    protected function _added($lines)
    {
        return $this->_cli->color(
            'lightgreen', parent::_added($lines)
        );
    }

    protected function _deleted($lines)
    {
        return $this->_cli->color(
            'lightred', parent::_deleted($lines)
        );
    }
}
