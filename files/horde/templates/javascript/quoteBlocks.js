/**
 * $Horde: horde/templates/javascript/quoteBlocks.js,v 1.5 2004/08/26 00:07:29 slusarz Exp $
 *
 * @package Horde
 */
function toggleQuoteBlock(id, lines)
{
    var block = new Horde_Hideable('qb_' + id);

    block.toggle();
    text = document.createTextNode(block.shown() ?
                                   '<?php echo _("[Hide Quoted Text]") ?>' :
                                   '<?php echo _("[Show Quoted Text -") ?> ' + lines + ' <?php echo _("lines]") ?>');
    link = document.createElement('A');
    link.href = '';
    link.className = 'widget';
    link.style.fontSize = '70%';
    link.onclick = function() {
        toggleQuoteBlock(id, lines);
        return false;
    }
    link.appendChild(text);

    var toggle = document.getElementById('qt_' + id);
    if (toggle.firstChild) {
        toggle.removeChild(toggle.firstChild);
    }
    toggle.appendChild(link);
}
