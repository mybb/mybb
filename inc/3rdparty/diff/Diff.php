<?php
/**
 * This library remains copyright of it's original authors.
 * It is licensed under the LGPL license which allows us to
 * include it in MyBB
 */

/**
 * Text_Diff
 *
 * General API for generating and formatting diffs - the differences between
 * two sequences of strings.
 *
 * The PHP diff code used in this package was originally written by Geoffrey
 * T. Dairiki and is used with his permission.
 *
 * $Horde: framework/Text_Diff/Diff.php,v 1.17 2006/02/06 00:16:09 jan Exp $
 *
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 */
class Text_Diff {

    /**
     * Array of changes.
     *
     * @var array
     */
    var $_edits;

    /**
     * Computes diffs between sequences of strings.
     *
     * @param array $from_lines  An array of strings.  Typically these are
     *                           lines from a file.
     * @param array $to_lines    An array of strings.
     */
    function Text_Diff($engine, $params)
    {
        // Backward compatibility workaround.
        if (!is_string($engine)) {
            $params = array($engine, $params);
            $engine = 'auto';
        }

        if ($engine == 'auto') {
            $engine = extension_loaded('xdiff') ? 'xdiff' : 'native';
        }
        $engine = basename($engine);

        require_once MYBB_ROOT.'inc/3rdparty/diff/Diff/Engine/' . $engine . '.php';
        $class = 'Text_Diff_Engine_' . $engine;
        $diff_engine = new $class();

        $this->_edits = call_user_func_array(array($diff_engine, 'diff'), $params);
    }

    /**
     * Returns the array of differences.
     */
    function getDiff()
    {
        return $this->_edits;
    }

    /**
     * Computes a reversed diff.
     *
     * Example:
     * <code>
     * $diff = new Text_Diff($lines1, $lines2);
     * $rev = $diff->reverse();
     * </code>
     *
     * @return Text_Diff  A Diff object representing the inverse of the
     *                    original diff.  Note that we purposely don't return a
     *                    reference here, since this essentially is a clone()
     *                    method.
     */
    function reverse()
    {
        if (version_compare(zend_version(), '2', '>')) {
            $rev = clone($this);
        } else {
            $rev = $this;
        }
        $rev->_edits = array();
        foreach ($this->_edits as $edit) {
            $rev->_edits[] = $edit->reverse();
        }
        return $rev;
    }

    /**
     * Checks for an empty diff.
     *
     * @return boolean  True if two sequences were identical.
     */
    function isEmpty()
    {
        foreach ($this->_edits as $edit) {
            if (!is_a($edit, 'Text_Diff_Op_copy')) {
                return false;
            }
        }
        return true;
    }

    /**
     * Computes the length of the Longest Common Subsequence (LCS).
     *
     * This is mostly for diagnostic purposes.
     *
     * @return integer  The length of the LCS.
     */
    function lcs()
    {
        $lcs = 0;
        foreach ($this->_edits as $edit) {
            if (is_a($edit, 'Text_Diff_Op_copy')) {
                $lcs += count($edit->orig);
            }
        }
        return $lcs;
    }

    /**
     * Gets the original set of lines.
     *
     * This reconstructs the $from_lines parameter passed to the constructor.
     *
     * @return array  The original sequence of strings.
     */
    function getOriginal()
    {
        $lines = array();
        foreach ($this->_edits as $edit) {
            if ($edit->orig) {
                array_splice($lines, count($lines), 0, $edit->orig);
            }
        }
        return $lines;
    }

    /**
     * Gets the final set of lines.
     *
     * This reconstructs the $to_lines parameter passed to the constructor.
     *
     * @return array  The sequence of strings.
     */
    function getFinal()
    {
        $lines = array();
        foreach ($this->_edits as $edit) {
            if ($edit->final) {
                array_splice($lines, count($lines), 0, $edit->final);
            }
        }
        return $lines;
    }

    /**
     * Removes trailing newlines from a line of text. This is meant to be used
     * with array_walk().
     *
     * @param string $line  The line to trim.
     * @param integer $key  The index of the line in the array. Not used.
     */
    function trimNewlines(&$line, $key)
    {
        $line = str_replace(array("\n", "\r"), '', $line);
    }

    /**
     * Checks a diff for validity.
     *
     * This is here only for debugging purposes.
     */
    function _check($from_lines, $to_lines)
    {
        if (serialize($from_lines) != serialize($this->getOriginal())) {
            trigger_error("Reconstructed original doesn't match", E_USER_ERROR);
        }
        if (serialize($to_lines) != serialize($this->getFinal())) {
            trigger_error("Reconstructed final doesn't match", E_USER_ERROR);
        }

        $rev = $this->reverse();
        if (serialize($to_lines) != serialize($rev->getOriginal())) {
            trigger_error("Reversed original doesn't match", E_USER_ERROR);
        }
        if (serialize($from_lines) != serialize($rev->getFinal())) {
            trigger_error("Reversed final doesn't match", E_USER_ERROR);
        }

        $prevtype = null;
        foreach ($this->_edits as $edit) {
            if ($prevtype == get_class($edit)) {
                trigger_error("Edit sequence is non-optimal", E_USER_ERROR);
            }
            $prevtype = get_class($edit);
        }

        return true;
    }

}

/**
 * $Horde: framework/Text_Diff/Diff.php,v 1.17 2006/02/06 00:16:09 jan Exp $
 *
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 */
class Text_MappedDiff extends Text_Diff {

    /**
     * Computes a diff between sequences of strings.
     *
     * This can be used to compute things like case-insensitve diffs, or diffs
     * which ignore changes in white-space.
     *
     * @param array $from_lines         An array of strings.
     * @param array $to_lines           An array of strings.
     * @param array $mapped_from_lines  This array should have the same size
     *                                  number of elements as $from_lines.  The
     *                                  elements in $mapped_from_lines and
     *                                  $mapped_to_lines are what is actually
     *                                  compared when computing the diff.
     * @param array $mapped_to_lines    This array should have the same number
     *                                  of elements as $to_lines.
     */
    function Text_MappedDiff($from_lines, $to_lines,
                             $mapped_from_lines, $mapped_to_lines)
    {
        assert(count($from_lines) == count($mapped_from_lines));
        assert(count($to_lines) == count($mapped_to_lines));

        parent::Text_Diff($mapped_from_lines, $mapped_to_lines);

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

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @access private
 */
class Text_Diff_Op {

    var $orig;
    var $final;

    function reverse()
    {
        trigger_error('Abstract method', E_USER_ERROR);
    }

    function norig()
    {
        return $this->orig ? count($this->orig) : 0;
    }

    function nfinal()
    {
        return $this->final ? count($this->final) : 0;
    }

}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @access private
 */
class Text_Diff_Op_copy extends Text_Diff_Op {

    function Text_Diff_Op_copy($orig, $final = false)
    {
        if (!is_array($final)) {
            $final = $orig;
        }
        $this->orig = $orig;
        $this->final = $final;
    }

    function &reverse()
    {
        $reverse = new Text_Diff_Op_copy($this->final, $this->orig);
        return $reverse;
    }

}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @access private
 */
class Text_Diff_Op_delete extends Text_Diff_Op {

    function Text_Diff_Op_delete($lines)
    {
        $this->orig = $lines;
        $this->final = false;
    }

    function &reverse()
    {
        $reverse = new Text_Diff_Op_add($this->orig);
        return $reverse;
    }

}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @access private
 */
class Text_Diff_Op_add extends Text_Diff_Op {

    function Text_Diff_Op_add($lines)
    {
        $this->final = $lines;
        $this->orig = false;
    }

    function &reverse()
    {
        $reverse = new Text_Diff_Op_delete($this->final);
        return $reverse;
    }

}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @access private
 */
class Text_Diff_Op_change extends Text_Diff_Op {

    function Text_Diff_Op_change($orig, $final)
    {
        $this->orig = $orig;
        $this->final = $final;
    }

    function &reverse()
    {
        $reverse = new Text_Diff_Op_change($this->final, $this->orig);
        return $reverse;
    }

}
?>