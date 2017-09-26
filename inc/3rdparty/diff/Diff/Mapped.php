<?php
/**
 * Copyright 2007-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Geoffrey T. Dairiki <dairiki@dairiki.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package  Text_Diff
 */

/**
 * This can be used to compute things like case-insensitve diffs, or diffs
 * which ignore changes in white-space.
 *
 * @author    Geoffrey T. Dairiki <dairiki@dairiki.org>
 * @category  Horde
 * @copyright 2007-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package   Text_Diff
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class Horde_Text_Diff_Mapped extends Horde_Text_Diff
{
    /**
     * Computes a diff between sequences of strings.
     *
     * @param string $engine  Name of the diffing engine to use.  'auto' will
     *                        automatically select the best.
     * @param array $params   Parameters to pass to the diffing engine:
     *                        - Two arrays, each containing the lines from a
     *                          file.
     *                        - Two arrays with the same size as the first
     *                          parameters. The elements are what is actually
     *                          compared when computing the diff.
     */
    public function __construct($engine, $params)
    {
        list($from_lines, $to_lines, $mapped_from_lines, $mapped_to_lines) = $params;
        assert(count($from_lines) == count($mapped_from_lines));
        assert(count($to_lines) == count($mapped_to_lines));

        parent::__construct($engine, array($mapped_from_lines, $mapped_to_lines));

        $xi = $yi = 0;
        for ($i = 0; $i < count($this->_edits); $i++) {
            $orig = &$this->_edits[$i]->orig;
            if (is_array($orig)) {
                $orig = array_slice($from_lines, $xi, count($orig));
                $xi += count($orig);
            }

            $final = &$this->_edits[$i]->final;
            if (is_array($final)) {
                $final = array_slice($to_lines, $yi, count($final));
                $yi += count($final);
            }
        }
    }
}
