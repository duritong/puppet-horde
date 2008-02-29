/**
 * Horde Tree Javascript Class
 *
 * Provides the javascript class to create dynamic trees.
 *
 * Copyright 2003-2007 Marko Djukic <marko@oblo.com>
 *
 * See the enclosed file COPYING for license information (GPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * $Horde: horde/templates/javascript/tree.js,v 1.62.2.13 2007/03/22 21:29:49 slusarz Exp $
 *
 * @author  Marko Djukic <marko@oblo.com>
 * @package Horde_Tree
 * @since   Horde 3.0
 */
function Horde_Tree(instanceName)
{
    /* Set up this class instance for function calls from the page. */
    this._instanceName = instanceName;

    /* Image variables. */
    this.imgDir         = '<?php echo $GLOBALS['registry']->getImageDir('horde') . '/tree'; ?>';
    this.imgBlank       = 'blank.png';
    this.imgFolder      = 'folder.png';
    this.imgFolderOpen  = 'folderopen.png';

    /* Variables that change based on text direction. */
<?php if (empty($GLOBALS['nls']['rtl'][$GLOBALS['language']])): ?>
    this.floatDir       = 'float:left;';
    this.imgLine        = 'line.png';
    this.imgJoin        = 'join.png';
    this.imgJoinBottom  = 'joinbottom.png';
    this.imgPlus        = 'plus.png';
    this.imgPlusBottom  = 'plusbottom.png';
    this.imgPlusOnly    = 'plusonly.png';
    this.imgMinus       = 'minus.png';
    this.imgMinusBottom = 'minusbottom.png';
    this.imgMinusOnly   = 'minusonly.png';
    this.imgNullOnly    = 'nullonly.png';
    this.imgLeaf        = 'leaf.png';
<?php else: ?>
    this.floatDir       = 'float:right;';
    this.imgLine        = 'rev-line.png';
    this.imgJoin        = 'rev-join.png';
    this.imgJoinBottom  = 'rev-joinbottom.png';
    this.imgPlus        = 'rev-plus.png';
    this.imgPlusBottom  = 'rev-plusbottom.png';
    this.imgPlusOnly    = 'rev-plusonly.png';
    this.imgMinus       = 'rev-minus.png';
    this.imgMinusBottom = 'rev-minusbottom.png';
    this.imgMinusOnly   = 'rev-minusonly.png';
    this.imgNullOnly    = 'rev-nullonly.png';
    this.imgLeaf        = 'rev-leaf.png';
<?php endif; ?>

    /* Tree building variables. */
    this.renderStatic   = false;
    this.target         = '';
    this.header         = new Array();
    this.rootNodes      = new Array();
    this.nodes          = new Array();
    this.node_pos       = new Array();
    this.dropline       = new Array();
    this.output         = '';
}

Horde_Tree.prototype.setImgDir = function(imgDir)
{
    this.imgDir = imgDir;
}

Horde_Tree.prototype.renderTree = function(rootNodes, renderStatic)
{
    this.rootNodes = rootNodes;
    this.renderStatic = renderStatic;
    this.nodes = eval('n_' + this._instanceName);
    this.header = eval('h_' + this._instanceName);
    this.options = eval('o_' + this._instanceName);
    this.target = 't_' + this._instanceName;
    this._renderTree();
}

Horde_Tree.prototype._renderTree = function()
{
    this.output = '';
    if (!this.options['hideHeaders']) {
        this.output = this._buildHeader();
    }
    for (var i = 0; i < this.rootNodes.length; i++) {
        this.buildTree(this.rootNodes[i]);
    }
    document.getElementById(this.target).innerHTML = this.output;
    this._correctWidthForScrollbar();
    // If using alternating row shading, work out correct shade.
    if (this.options['alternate']) {
        this.stripe();
    }
}

/**
 * Returns the HTML code for a header row, if necessary.
 *
 * @access private
 *
 * @return string  The HTML code of the header row or an empty string.
 */
Horde_Tree.prototype._buildHeader = function()
{
    if (!this.header.length) {
        return '';
    }

    var html = '<div>';
    for (var i = 0; i < this.header.length; i++) {
        html += '<div';
        if (this.header[i]['class']) {
            html += ' class="' + this.header[i]['class'] + '"';
        }

        var style = this.floatDir;
        if (this.header[i]['width']) {
            style += 'width:' + this.header[i]['width'] + ';';
        }
        if (this.header[i]['align']) {
            style += 'text-align:' + this.header[i]['align'] + ';';
        }

        html += ' style="' + style + '"';
        html += '>';
        html += this.header[i]['html'] ? this.header[i]['html'] : '&nbsp;';
        html += '</div>';
    }

    return html + '</div>';
}

/**
 * Recursive function to walk through the tree array and build
 * the output.
 */
Horde_Tree.prototype.buildTree = function(nodeId)
{
    this.output += this.buildLine(nodeId);

    if (typeof(this.nodes[nodeId]['children']) != 'undefined') {
        var numSubnodes = this.nodes[nodeId]['children'].length;
        if (numSubnodes > 0) {
            if (this.nodes[nodeId]['expanded']) {
                rowStyle = 'display:block;';
            } else {
                rowStyle = 'display:none;';
            }
            this.output += '<div id="nodeChildren_' + nodeId + '" style="' + rowStyle + '">';

            for (var c = 0; c < numSubnodes; c++) {
                var childNodeId = this.nodes[nodeId]['children'][c];
                this.node_pos[childNodeId] = new Array();
                this.node_pos[childNodeId]['pos'] = c + 1;
                this.node_pos[childNodeId]['count'] = numSubnodes;
                this.buildTree(childNodeId);
            }

            this.output += '</div>';
        }
    }

    return true;
}

Horde_Tree.prototype.buildLine = function(nodeId)
{
    var style = '';
    var rowClass = 'treeRow';
    if (this.nodes[nodeId]['class']) {
        rowClass += ' ' + this.nodes[nodeId]['class'];
    }

    var line = '<div class="' + rowClass + '">';

    // If we have headers, track which logical "column" we're in for
    // any given cell of content.
    var column = 0;

    if (typeof(this.nodes[nodeId]['extra']) != 'undefined' &&
        typeof(this.nodes[nodeId]['extra'][0]) != 'undefined') {
        var extra = this.nodes[nodeId]['extra'][0];
        for (var c = 0; c < extra.length; c++) {
            style = this.floatDir;
            if (this.header[column] && this.header[column]['width']) {
                style += 'width:' + this.header[column]['width'] + ';';
            }
            line += '<div style="' + style + '">' + extra[c] + '</div>';
            column++;
        }
        for (var d = c; d < extraColsLeft; d++) {
            style = this.floatDir;
            if (this.header[column] && this.header[column]['width']) {
                style += 'width:' + this.header[column]['width'] + ';';
            }
            line += '<div style="' + style + '">&nbsp;</div>';
            column++;
        }
    } else {
        for (var c = 0; c < extraColsLeft; c++) {
            style = this.floatDir;
            if (this.header[column] && this.header[column]['width']) {
                style += 'width:' + this.header[column]['width'] + ';';
            }
            line += '<div style="' + style + '">&nbsp;</div>';
            column++;
        }
    }

    style = this.floatDir;
    if (this.header[column] && this.header[column]['width']) {
        style += 'width:' + this.header[column]['width'] + ';';
    }
    line += '<div style="' + style + '">';

    if (this.options['multiline']) {
        line += '<table cellspacing="0"><tr><td>';
    }

    for (var i = this.renderStatic ? 1 : 0; i < this.nodes[nodeId]['indent']; i++) {
        if (this.dropline[i] && this.options['lines']) {
            line += '<img src="' + this.imgDir + '/' + this.imgLine + '" alt="|&nbsp;&nbsp;&nbsp;" height="20" width="20" />';
        } else {
            line += '<img src="' + this.imgDir + '/' + this.imgBlank + '" alt="&nbsp;&nbsp;&nbsp;" height="20" width="20" />';
        }
    }
    line += this._setNodeToggle(nodeId);
    if (this.options['multiline']) {
        line += '</td><td>';
    }
    line += this._setLabel(nodeId);

    if (this.options['multiline']) {
        line += '</td></tr></table>';
    }

    line += '</div>';
    column++;

    if (typeof(this.nodes[nodeId]['extra']) != 'undefined' &&
        typeof(this.nodes[nodeId]['extra'][1]) != 'undefined') {
        var extra = this.nodes[nodeId]['extra'][1];
        for (var c = 0; c < extra.length; c++) {
            style = this.floatDir;
            if (this.header[column] && this.header[column]['width']) {
                style += 'width:' + this.header[column]['width'] + ';';
            }
            line += '<div style="' + style + '">' + extra[c] + '</div>';
            column++;
        }
        for (var d = c; d < extraColsRight; d++) {
            style = this.floatDir;
            if (this.header[column] && this.header[column]['width']) {
                style += 'width:' + this.header[column]['width'] + ';';
            }
            line += '<div style="' + style + '">&nbsp;</div>';
            column++;
        }
    } else {
        for (var c = 0; c < extraColsRight; c++) {
            style = this.floatDir;
            if (this.header[column] && this.header[column]['width']) {
                style += 'width:' + this.header[column]['width'] + ';';
            }
            line += '<div style="' + style + '">&nbsp;</div>';
            column++;
        }
    }
    line += '</div>';

    return line;
}

Horde_Tree.prototype._setLabel = function(nodeId)
{
    label = this.nodes[nodeId]['label'];

    if (this.nodes[nodeId]['url']) {
        var urlClass = '';
        if (this.nodes[nodeId]['urlclass']) {
            urlClass = ' class="' + this.nodes[nodeId]['urlclass'] + '"';
        } else if (this.options['urlclass']) {
            urlClass = ' class="' + this.options['urlclass'] + '"';
        }

        var target = '';
        if (this.nodes[nodeId]['target']) {
            target = ' target="' + this.nodes[nodeId]['target'] + '"';
        } else if (this.options['target']) {
            target = ' target="' + this.options['target'] + '"';
        }

        var onclick = '';
        if (this.nodes[nodeId]['onclick']) {
            onclick = ' onclick="' + this.nodes[nodeId]['onclick'] + '"';
        }

        return '<a' + urlClass + ' href="' + this.nodes[nodeId]['url'] + '"' + target + onclick + '>' + this._setNodeIcon(nodeId) + label + '</a>';
    } else {
        return '<span class="toggle" onclick="' + this._instanceName + '.toggle(\'' + nodeId.replace(/'/, "\\'") + '\')">' + this._setNodeIcon(nodeId) + label + '</span>';
    }
}

Horde_Tree.prototype._setNodeToggle = function(nodeId)
{
    var attrib = '';
    if (this.nodes[nodeId]['indent'] == '0' &&
        typeof(this.nodes[nodeId]['children']) != 'undefined') {
        // Top level with children.
        this.dropline[0] = false;
        if (this.renderStatic) {
            return '';
        } else {
            attrib = ' style="cursor:pointer" onclick="' + this._instanceName + '.toggle(\'' + nodeId.replace(/'/, "\\'") + '\')"';
        }
    } else if (this.nodes[nodeId]['indent'] != '0' &&
               typeof(this.nodes[nodeId]['children']) == 'undefined') {
        // Node no children.
        if (this.node_pos[nodeId]['pos'] < this.node_pos[nodeId]['count']) {
            // Not last node.
            this.dropline[this.nodes[nodeId]['indent']] = true;
        } else {
            this.dropline[this.nodes[nodeId]['indent']] = false;
        }
    } else if (typeof(this.nodes[nodeId]['children']) != 'undefined') {
        // Node with children.
        if (this.node_pos[nodeId]['pos'] < this.node_pos[nodeId]['count']) {
            // Not last node.
            this.dropline[this.nodes[nodeId]['indent']] = true;
        } else {
            // Last node.
            this.dropline[this.nodes[nodeId]['indent']] = false;
        }
        if (!this.renderStatic) {
            attrib = ' style="cursor:pointer" onclick="' + this._instanceName + '.toggle(\'' + nodeId.replace(/'/, "\\'") + '\')"';
        }
    } else {
        // Top level node with no children.
        if (this.renderStatic) {
            return '';
        }
        this.dropline[0] = false;
    }

    nodeToggle = this._getNodeToggle(nodeId);
    img = '<img id="nodeToggle_' + nodeId + '" src="' + this.imgDir + '/' + nodeToggle[0] + '" ';
    if (nodeToggle[1]) {
        img += 'alt="' + nodeToggle[1] + '" ';
    }
    img += attrib + ' height="20" width="20" />';
    return img;
}

Horde_Tree.prototype._getNodeToggle = function(nodeId)
{
    var nodeToggle = new Array('', '');
    if (this.nodes[nodeId]['indent'] == '0' &&
        typeof(this.nodes[nodeId]['children']) != 'undefined') {
        // Top level with children.
        if (this.renderStatic) {
            return nodeToggle;
        } else if (!this.options['lines']) {
            nodeToggle[0] = this.imgBlank;
            nodeToggle[1] = '&nbsp;&nbsp;&nbsp;'
        } else if (this.nodes[nodeId]['expanded']) {
            nodeToggle[0] = this.imgMinusOnly;
            nodeToggle[1] = '-';
        } else {
            nodeToggle[0] = this.imgPlusOnly;
            nodeToggle[1] = '+';
        }
    } else if (this.nodes[nodeId]['indent'] != '0' &&
        typeof(this.nodes[nodeId]['children']) == 'undefined') {
        // Node no children.
        if (this.node_pos[nodeId]['pos'] < this.node_pos[nodeId]['count']) {
            // Not last node.
            if (this.options['lines']) {
                nodeToggle[0] = this.imgJoin;
                nodeToggle[1] = '|-';
            } else {
                nodeToggle[0] = this.imgBlank;
                nodeToggle[1] = '&nbsp;&nbsp;&nbsp;';
            }
        } else {
            // Last node.
            if (this.options['lines']) {
                nodeToggle[0] = this.imgJoinBottom;
                nodeToggle[1] = '`-';
            } else {
                nodeToggle[0] = this.imgBlank;
                nodeToggle[1] = '&nbsp;&nbsp;&nbsp;';
            }
        }
    } else if (typeof(this.nodes[nodeId]['children']) != 'undefined') {
        // Node with children.
        if (this.node_pos[nodeId]['pos'] < this.node_pos[nodeId]['count']) {
            // Not last node.
            if (!this.options['lines']) {
                nodeToggle[0] = this.imgBlank;
                nodeToggle[1] = '&nbsp;&nbsp;&nbsp;';
            } else if (this.renderStatic) {
                nodeToggle[0] = this.imgJoin;
                nodeToggle[1] = '|-';
            } else if (this.nodes[nodeId]['expanded']) {
                nodeToggle[0] = this.imgMinus;
                nodeToggle[1] = '-';
            } else {
                nodeToggle[0] = this.imgPlus;
                nodeToggle[1] = '+';
            }
        } else {
            // Last node.
            if (!this.options['lines']) {
                nodeToggle[0] = this.imgBlank;
                nodeToggle[1] = '&nbsp;';
            } else if (this.renderStatic) {
                nodeToggle[0] = this.imgJoinBottom;
                nodeToggle[1] = '`-';
            } else if (this.nodes[nodeId]['expanded']) {
                nodeToggle[0] = this.imgMinusBottom;
                nodeToggle[1] = '-';
            } else {
                nodeToggle[0] = this.imgPlusBottom;
                nodeToggle[1] = '+';
            }
        }
    } else {
        // Top level node with no children.
        if (this.renderStatic) {
            return nodeToggle;
        }
        if (this.options['lines']) {
            nodeToggle[0] = this.imgNullOnly;
            nodeToggle[1] = '&nbsp;&nbsp;';
        } else {
            nodeToggle[0] = this.imgBlank;
            nodeToggle[1] = '&nbsp;&nbsp;&nbsp;';
        }
    }

    return nodeToggle;
}

Horde_Tree.prototype._setNodeIcon = function(nodeId)
{
    var imgDir = (typeof(this.nodes[nodeId]['icondir']) != 'undefined') ?
        this.nodes[nodeId]['icondir'] :
        this.imgDir;
    if (imgDir) {
        imgDir += '/';
    }

    if (typeof(this.nodes[nodeId]['icon']) != 'undefined') {
        // Node has a user defined icon.
        if (!this.nodes[nodeId]['icon']) {
            return '';
        }
        if (typeof(this.nodes[nodeId]['iconopen']) != 'undefined' && this.nodes[nodeId]['expanded']) {
            img = this.nodes[nodeId]['iconopen'];
        } else {
            img = this.nodes[nodeId]['icon'];
        }
    } else {
        // Use standard icon set.
        if (typeof(this.nodes[nodeId]['children']) != 'undefined') {
            // Node with children.
            img = (this.nodes[nodeId]['expanded']) ? this.imgFolderOpen
                                                   : this.imgFolder;
        } else {
            // Node no children.
            img = this.imgLeaf;
        }
    }

    var imgtxt = '<img src="' + imgDir + img + '"';

    if (typeof(this.nodes[nodeId]['iconalt']) != 'undefined') {
        imgtxt += ' alt="' + this.nodes[nodeId]['iconalt'] + '"';
    }

    return imgtxt + ' /> ';
}

Horde_Tree.prototype.toggle = function(nodeId)
{
    this.nodes[nodeId]['expanded'] = !this.nodes[nodeId]['expanded'];
    if (this.nodes[nodeId]['expanded']) {
        if (node = document.getElementById('nodeChildren_' + nodeId)) {
            node.style.display = 'block';
        }
    } else {
        if (node = document.getElementById('nodeChildren_' + nodeId)) {
            node.style.display = 'none';
        }
    }

    // If using alternating row shading, work out correct shade.
    if (this.options['alternate']) {
        this.stripe();
    }

    nodeToggle = this._getNodeToggle(nodeId);
    if (toggle = document.getElementById('nodeToggle_' + nodeId)) {
        toggle.src = this.imgDir + '/' + nodeToggle[0];
        toggle.alt = nodeToggle[1];
    }

    this.saveState(nodeId, this.nodes[nodeId]['expanded'])
}

Horde_Tree.prototype.stripe = function()
{
    // The element to start striping.
    var id = arguments[0] ? arguments[0] : this.target;

    // The flag we'll use to keep track of whether the current row is
    // odd or even.
    var even = arguments[1] ? arguments[1] : false;

    // Obtain a reference to the tree parent element.
    var tree = document.getElementById(id);
    if (!tree) {
        return even;
    }

    // Iterate over each child div.
    for (var i = 0; i < tree.childNodes.length; i++) {
        if (tree.childNodes[i].id.indexOf('nodeChildren') != -1) {
            if (this.nodes[tree.childNodes[i].id.replace('nodeChildren_', '')]['expanded']) {
                even = this.stripe(tree.childNodes[i].id, even);
            }
        } else {
            tree.childNodes[i].className = tree.childNodes[i].className.replace(' rowEven', '').replace(' rowOdd', '');
            tree.childNodes[i].className += even ? ' rowEven' : ' rowOdd';

            // Flip from odd to even, or vice-versa.
            even = !even;
        }
    }

    return even;
}

Horde_Tree.prototype.saveState = function(nodeId, expanded)
{
    var newCookie = '';
    var oldCookie = this._getCookie(this._instanceName + '_expanded');
    if (expanded) {
        // Expand requested so add to cookie.
        newCookie = (oldCookie) ? oldCookie + ',' : '';
        newCookie = newCookie + nodeId;
    } else {
        // Collapse requested so remove from cookie.
        var nodes = oldCookie.split(',');
        var newNodes = new Array();
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i] != nodeId) {
                newNodes[newNodes.length] = nodes[i];
            }
        }
        newCookie = newNodes.join(',');
    }
    this._setCookie(this._instanceName + '_expanded', newCookie);
}

Horde_Tree.prototype._getCookie = function(name)
{
    var dc = document.cookie;
    var prefix = name + '=exp';
    var begin = dc.indexOf('; ' + prefix);
    if (begin == -1) {
        begin = dc.indexOf(prefix);
        if (begin != 0) {
            return '';
        }
    } else {
        begin += 2;
    }
    var end = document.cookie.indexOf(';', begin);
    if (end == -1) {
        end = dc.length;
    }
    return unescape(dc.substring(begin + prefix.length, end));
}

Horde_Tree.prototype._setCookie = function(name, value)
{
    var curCookie = name + '=exp' + escape(value);
    curCookie += ';DOMAIN=<?php echo $GLOBALS['conf']['cookie']['domain']; ?>;PATH=<?php echo $GLOBALS['conf']['cookie']['path']; ?>;';
    document.cookie = curCookie;
}

Horde_Tree.prototype._correctWidthForScrollbar = function()
{
<?php if ($GLOBALS['browser']->hasQuirk('scrollbar_in_way')): ?>
    // Correct for frame scrollbar in IE by determining if a scrollbar is present,
    // and if not readjusting the marginRight property to 0
    // See http://www.xs4all.nl/~ppk/js/doctypes.html for why this works
    if (document.documentElement.clientHeight == document.documentElement.offsetHeight) {
        // no scrollbar present, take away extra margin
        document.body.style.marginRight = '0';
    } else {
        document.body.style.marginRight = '15px';
    }
<?php endif; ?>
}
