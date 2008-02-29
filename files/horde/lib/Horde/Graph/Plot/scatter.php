<?php
/**
 * Scatter plot implementation for the Horde_Graph package.
 *
 * $Horde: framework/Graph/Graph/Plot/scatter.php,v 1.3.12.5 2007/01/02 13:54:20 jan Exp $
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
class Horde_Graph_Plot_scatter {

    var $_graph;
    var $_color = 'blue';
    var $_shape = 'square';
    var $_dataset;

    function Horde_Graph_Plot_scatter(&$graph, $params)
    {
        $this->_graph = &$graph;

        foreach ($params as $param => $value) {
            $key = '_' . $param;
            $this->$key = $value;
        }
    }

    function draw($minY = false)
    {
        $data = $this->_graph->_data['y'][$this->_dataset];

        $count = count($data);
        $verts = array();
        for ($i = 0; $i < $count; $i++) {
            $x = $i;
            if ($minY === false || $data[$i] >= $minY) {
                $y = $data[$i];
                $this->_graph->translate($x, $y);
                $this->_graph->img->brush($x, $y, $this->_color, $this->_shape);
            }
        }
    }

}
