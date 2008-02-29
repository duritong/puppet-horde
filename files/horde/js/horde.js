/**
 * General Horde UI effects javascript.
 *
 * $Horde: horde/js/horde.js,v 1.14.2.5 2006/05/25 18:07:26 slusarz Exp $
 *
 * See the enclosed file COPYING for license information (LGPL). If you did not
 * receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 */

var ToolTips = {
    CURRENT: null,
    TIMEOUT: null,
    LINK: null,

    attachBehavior: function()
    {
        links = document.getElementsByTagName('a');
        for (i = 0; i < links.length; i++) {
            if (links[i].title) {
                links[i].setAttribute('nicetitle', links[i].title);
                links[i].removeAttribute('title');

                addEvent(links[i], 'mouseover', ToolTips.over);
                addEvent(links[i], 'mouseout', ToolTips.out);
                addEvent(links[i], 'focus', ToolTips.over);
                addEvent(links[i], 'blur', ToolTips.out);
            }
        }
    },

    over: function(e)
    {
        if (typeof ToolTips == 'undefined') {
            return;
        }

        if (ToolTips.TIMEOUT) {
            window.clearTimeout(ToolTips.TIMEOUT);
        }

        if (window.event && window.event.srcElement) {
            ToolTips.LINK = window.event.srcElement;
        } else if (e && e.target) {
            ToolTips.LINK = e.target;
        }

        ToolTips.TIMEOUT = window.setTimeout('ToolTips.show()', 300)
    },

    out: function()
    {
        if (typeof ToolTips == 'undefined') {
            return;
        }

        if (ToolTips.TIMEOUT) {
            window.clearTimeout(ToolTips.TIMEOUT);
        }

        if (ToolTips.CURRENT) {
            document.getElementsByTagName('body')[0].removeChild(ToolTips.CURRENT);
            ToolTips.CURRENT = null;

            var iframe = document.getElementById('iframe_tt');
            if (iframe != null) {
                iframe.style.display = 'none';
            }
        }
    },

    show: function()
    {
        if (typeof ToolTips == 'undefined' || !ToolTips.LINK) {
            return;
        }

        if (ToolTips.CURRENT) {
            ToolTips.out();
        }

        link = ToolTips.LINK;
        while (!link.getAttribute('nicetitle') && link.nodeName.toLowerCase() != 'body') {
            link = link.parentNode;
        }
        nicetitle = link.getAttribute('nicetitle');
        if (!nicetitle) {
            return;
        }

        d = document.createElement('div');
        d.className = 'nicetitle';
        d.innerHTML = nicetitle;

        STD_WIDTH = 100;
        MAX_WIDTH = 600;
        if (window.innerWidth) {
            MAX_WIDTH = Math.min(MAX_WIDTH, window.innerWidth - 20);
        }
        if (document.body && document.body.scrollWidth) {
            MAX_WIDTH = Math.min(MAX_WIDTH, document.body.scrollWidth - 20);
        }

        nicetitle_length = 0;
        lines = nicetitle.replace(/<br ?\/>/g, "\n").split("\n");
        for (i = 0; i < lines.length; i++) {
            nicetitle_length = Math.max(nicetitle_length, lines[i].length);
        }

        h_pixels = nicetitle_length * 7;
        t_pixels = nicetitle_length * 10;

        if (h_pixels > STD_WIDTH) {
            w = h_pixels;
        } else if (STD_WIDTH > t_pixels) {
            w = t_pixels;
        } else {
            w = STD_WIDTH;
        }

        mpos = findPos(link);
        mx = mpos[0];
        my = mpos[1];

        left = mx + 20;
        if (window.innerWidth && ((left + w) > window.innerWidth)) {
            left = window.innerWidth - w - 40;
        }
        if (document.body && document.body.scrollWidth && ((left + w) > document.body.scrollWidth)) {
            left = document.body.scrollWidth - w - 25;
        }

        d.id = 'toolTip';
        d.style.left = Math.max(left, 5) + 'px';
        d.style.width = Math.min(w, MAX_WIDTH) + 'px';
        d.style.top = (my + 20) + 'px';
        d.style.display = "block";

        try {
            document.getElementsByTagName('body')[0].appendChild(d);
            ToolTips.CURRENT = d;

            if (typeof ToolTips_Option_Windowed_Controls != 'undefined') {
                var iframe = document.getElementById('iframe_tt');
                if (iframe == null) {
                    iframe = document.createElement("<iframe src='javascript:false;' name='iframe_tt' id='iframe_tt' scrolling='no' frameborder='0' style='position:absolute; top:0px; left:0px; display:none;'></iframe>");
                    document.getElementsByTagName('body')[0].appendChild(iframe);
                }
                iframe.style.width = d.offsetWidth;
                iframe.style.height = d.offsetHeight;
                iframe.style.top = d.style.top;
                iframe.style.left = d.style.left;
                iframe.style.position = "absolute";
                iframe.style.display = "block";
                d.style.zIndex = 100;
                iframe.style.zIndex = 99;
            }
        } catch (e) {
        }
    }

};

/**
 * Return the [x,y] position of an object.
 */
function findPos(obj)
{
    if (obj.offsetParent) {
        for (posX = 0, posY = 0; obj.offsetParent; obj = obj.offsetParent) {
            posX += obj.offsetLeft;
            posY += obj.offsetTop;
        }
        return [posX, posY];
    } else {
        return [obj.x, obj.y];
    }
}

/**
 * Add an event listener as long as the browser supports it. Different
 * browsers still handle these events slightly differently; in
 * particular avoid using "this" in event functions.
 *
 * @author Scott Andrew
 * @author Chuck Hagenbuch <chuck@horde.org>
 */
function addEvent(obj, evType, fn)
{
    if (obj.addEventListener) {
        obj.addEventListener(evType, fn, true);
        return true;
    } else if (obj.attachEvent) {
        var r = obj.attachEvent('on' + evType, fn);
        EventCache.add(obj, evType, fn);
        return r;
    } else {
        return false;
    }
}

var EventCache = function()
{
    var listEvents = [];

    return {
        listEvents: listEvents,

        add: function(node, sEventName, fHandler, bCapture)
        {
            listEvents.push(arguments);
        },

        flush: function()
        {
            var i, item;
            for (i = listEvents.length - 1; i >= 0; i = i - 1) {
                item = listEvents[i];

                if (item[0].removeEventListener) {
                    item[0].removeEventListener(item[1], item[2], item[3]);
                };

                /* From this point on we need the event names to be
                 * prefixed with 'on'. */
                if (item[1].substring(0, 2) != 'on') {
                    item[1] = 'on' + item[1];
                }

                if (item[0].detachEvent) {
                    item[0].detachEvent(item[1], item[2]);
                }

                item[0][item[1]] = null;
            }
        }
    };
}();

if (document.createElement && document.getElementsByTagName) {
    addEvent(window, 'load', ToolTips.attachBehavior);
    addEvent(window, 'unload', ToolTips.out);
    addEvent(window, 'unload', EventCache.flush);
}
