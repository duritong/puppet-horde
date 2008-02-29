<?php

require_once 'Horde/IMAP/Thread.php';

/**
 * The IMP_Thread class extends the IMAP_Thread class to include a function
 * to generate the thread tree images.  This class is necessary to ensure
 * backwards compatibility with Horde 3.0.
 *
 * For the next (mythical) release of Horde 4.x, this code should be merged
 * into the IMAP_Thread class.
 *
 * $Horde: imp/lib/IMAP/Thread.php,v 1.5.2.3 2007/01/02 13:54:58 jan Exp $
 *
 * Copyright 2005-2007 Michael Slusarz <slusarz@curecanti.org>
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@curecanti.org>
 * @since   IMP 4.1
 * @package IMP
 */
class IMP_Thread extends IMAP_Thread {

    /**
     * Cached value of the Horde graphics directory.
     *
     * @var string
     */
    var $_graphicsdir;

    /**
     * Cached values of the thread image objects.
     *
     * @var string
     */
    var $_imgcache = array();

    /**
     * Generate the thread representation image for the given index list.
     *
     * @param array $indices    The list of indices to create a tree for.
     * @param boolean $sortdir  True for newest first, false for oldest first.
     *
     * @return array  An array with the index as the key and the thread image
     *                representation as the value.
     */
    function getThreadImageTree($indices, $sortdir)
    {
        $container = $last_level = $last_thread = null;
        $tree = array();

        if ($sortdir) {
            $indices = array_reverse($indices);
        }

        foreach ($indices as $val) {
            $tree[$val] = '';

            $indentBase = $this->getThreadBase($val);
            if (empty($indentBase)) {
                continue;
            }

            $lines = '';
            $indentLevel = $this->getThreadIndent($val);
            $lastinlevel = $this->lastInLevel($val);

            if ($lastinlevel && ($indentBase == $val)) {
                continue;
            }

            if ($lastinlevel) {
                $join_img = ($sortdir) ? 'joinbottom-down.png' : 'joinbottom.png';
            } elseif (($indentLevel == 1) && ($indentBase == $val)) {
                $join_img = ($sortdir) ? 'joinbottom.png' : 'joinbottom-down.png';
                $container = $val;
            } else {
                $join_img = 'join.png';
            }
            $threadLevel[$indentLevel] = $lastinlevel;
            for ($i = ($container == $indentBase) ? 1 : 2; $i < $indentLevel; $i++) {
                $lines .= $this->_image(!isset($threadLevel[$i]) || ($threadLevel[$i]) ? 'blank.png' : 'line.png');
            }
            $tree[$val] = $lines . $this->_image($join_img);
        }

        return $tree;
    }

    /**
     * Utility function to return a url for the various thread images.
     *
     * @access private
     *
     * @param string $name  Image filename.
     *
     * @return string  Link to the folder image.
     */
    function _image($name)
    {
        if (!empty($this->_imgcache[$name])) {
            return $this->_imgcache[$name];
        }

        if (empty($this->_graphicsdir)) {
            $this->_graphicsdir = $GLOBALS['registry']->getImageDir('horde');
        }

        $this->_imgcache[$name] = Horde::img('tree/' . $name, '', 'style="vertical-align:middle"', $this->_graphicsdir);

        return $this->_imgcache[$name];
    }

}
