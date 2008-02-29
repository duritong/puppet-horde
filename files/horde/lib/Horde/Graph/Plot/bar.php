<?php
/**
 * Bar graph implementation for the Horde_Graph package.
 *
 * $Horde: framework/Graph/Graph/Plot/bar.php,v 1.5.12.5 2007/01/02 13:54:20 jan Exp $
 *
 * Copyright 2002-2007 Chuck Hagenbuch <chuck@horde.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @since   Horde 3.0
 * @package Horde_Graph
 */
class Horde_Graph_Plot_bar {

    var $graph;

    var $_color = 'blue';
    var $_outline = 'black';
    var $_width = 10;
    var $_offset = 0;
    var $_dataset;

    function Horde_Graph_Plot_bar(&$graph, $params)
    {
        $this->graph = &$graph;

        foreach ($params as $param => $value) {
            $key = '_' . $param;
            $this->$key = $value;
        }
    }

    function draw($minY = false)
    {
        $data = $this->graph->_data['y'][$this->_dataset];

        $barWidth = $this->_width;
        $barOffset = $this->_offset * $barWidth;
        $u = 0;
        $v = 0;
        $count = count($data);
        for ($i = 0; $i < $count; $i++) {
            $x = $i;
            if ($minY === false || $data[$i] >= $minY) {
                $y = $data[$i];
                $this->graph->translate($x, $y);
                $x = $x - ($barWidth / 2) + $barOffset;
                $height = $this->graph->_graphBottom - $y;

                $this->graph->img->rectangle($x, $y, $barWidth, $height, $this->_outline, $this->_color);
            }
        }
    }

}
