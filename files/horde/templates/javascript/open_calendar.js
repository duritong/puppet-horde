var currentDate, currentYear, curImgId, currentTarget;

function openCalendar(imgId, target, callback)
{
    if (document.getElementById(target + '[year]').value && document.getElementById(target + '[month]').value && document.getElementById(target + '[day]').value) {
        var d = new Date(document.getElementById(target + '[year]').value,
                         document.getElementById(target + '[month]').value - 1,
                         document.getElementById(target + '[day]').value);
    } else {
        var d = new Date();
    }

    openGoto(d.getTime(), imgId, target, callback);
}

function openGoto(timestamp, imgId, target, callback)
{
    var row, cell, img, link, days;

    currentTarget = target;

    var d = new Date(timestamp);
    currentDate = d;
    month = d.getMonth();
    currentYear = d.getYear();
    if (currentYear < 1900) {
        currentYear += 1900;
    }
    var firstOfMonth = new Date(currentYear, month, 1);
    var diff = firstOfMonth.getDay() - 1;
    if (diff == -1) {
        diff = 6;
    }
    switch (month) {
    case 3:
    case 5:
    case 8:
    case 10:
        days = 30;
        break;

    case 1:
        if (currentYear % 4 == 0 && (currentYear % 100 != 0 || currentYear % 400 == 0)) {
            days = 29;
        } else {
            days = 28;
        }
        break;

    default:
        days = 31;
        break;
    }

    var wdays = [
        '<?php echo _("Mo") ?>',
        '<?php echo _("Tu") ?>',
        '<?php echo _("We") ?>',
        '<?php echo _("Th") ?>',
        '<?php echo _("Fr") ?>',
        '<?php echo _("Sa") ?>',
        '<?php echo _("Su") ?>'
    ];
    var months = [
        '<?php echo _("January") ?>',
        '<?php echo _("February") ?>',
        '<?php echo _("March") ?>',
        '<?php echo _("April") ?>',
        '<?php echo _("May") ?>',
        '<?php echo _("June") ?>',
        '<?php echo _("July") ?>',
        '<?php echo _("August") ?>',
        '<?php echo _("September") ?>',
        '<?php echo _("October") ?>',
        '<?php echo _("November") ?>',
        '<?php echo _("December") ?>'
    ];

    var layer = document.getElementById('goto');
    if (layer.firstChild) {
        layer.removeChild(layer.firstChild);
    }

    var table = document.createElement('TABLE');
    var tbody = document.createElement('TBODY');
    table.appendChild(tbody);
    table.className = 'item';
    table.cellSpacing = 0;
    table.cellPadding = 2;
    table.border = 0;
    table.style.width = 'auto';

    // Title bar.
    row = document.createElement('TR');
    cell = document.createElement('TD');
    cell.colSpan = 7;
    cell.align = 'right';
    cell.className = 'header';
    link = document.createElement('A');
    link.href = '#';
    link.onclick = function()
    {
        var layer = document.getElementById('goto');
        layer.style.visibility = 'hidden';
        if (layer.firstChild) {
            layer.removeChild(layer.firstChild);
        }
        return false;
    }
    img = document.createElement('IMG')
    img.src = '<?php echo $GLOBALS['registry']->getImageDir('horde') ?>/close.png';
    img.border = 0;
    link.appendChild(img);
    cell.appendChild(link);
    row.appendChild(cell);
    tbody.appendChild(row);

    // Year.
    row = document.createElement('TR');
    cell = document.createElement('TD');
    cell.align = 'left';
    link = document.createElement('A');
    link.href = '#';
    link.onclick = function()
    {
        newDate = new Date(currentYear - 1, currentDate.getMonth(), 1);
        openGoto(newDate.getTime(), imgId, currentTarget, callback);
        return false;
    }
    cell.appendChild(link);
    img = document.createElement('IMG')
    img.src = '<?php echo $GLOBALS['registry']->getImageDir('horde') ?>/nav/left.png';
    img.align = 'middle';
    img.border = 0;
    link.appendChild(img);
    row.appendChild(cell);

    cell = document.createElement('TD');
    cell.colSpan = 5;
    cell.align = 'center';
    var y = document.createTextNode(currentYear);
    cell.appendChild(y);
    row.appendChild(cell);

    cell = document.createElement('TD');
    cell.align = 'right';
    link = document.createElement('A');
    link.href = '#';
    link.onclick = function()
    {
        newDate = new Date(currentYear + 1, currentDate.getMonth(), 1);
        openGoto(newDate.getTime(), imgId, currentTarget, callback);
        return false;
    }
    cell.appendChild(link);
    img = document.createElement('IMG')
    img.src = '<?php echo $GLOBALS['registry']->getImageDir('horde') ?>/nav/right.png';
    img.align = 'middle';
    img.border = 0;
    link.appendChild(img);
    row.appendChild(cell);
    tbody.appendChild(row);

    // Month name.
    row = document.createElement('TR');
    cell = document.createElement('TD');
    cell.align = 'left';
    link = document.createElement('A');
    link.href = '#';
    link.onclick = function()
    {
        var newMonth = currentDate.getMonth() - 1;
        var newYear = currentYear;
        if (newMonth == -1) {
            newMonth = 11;
            newYear -= 1;
        }
        newDate = new Date(newYear, newMonth, 1);
        openGoto(newDate.getTime(), imgId, currentTarget, callback);
        return false;
    }
    cell.appendChild(link);
    img = document.createElement('IMG')
    img.src = '<?php echo $GLOBALS['registry']->getImageDir('horde') ?>/nav/left.png';
    img.align = 'middle';
    img.border = 0;
    link.appendChild(img);
    row.appendChild(cell);

    cell = document.createElement('TD');
    cell.colSpan = 5;
    cell.align = 'center';
    var m = document.createTextNode(months[month]);
    cell.appendChild(m);
    row.appendChild(cell);

    cell = document.createElement('TD');
    cell.align = 'right';
    link = document.createElement('A');
    link.href = '#';
    link.onclick = function()
    {
        newDate = new Date(currentYear, currentDate.getMonth() + 1, 1);
        openGoto(newDate.getTime(), imgId, currentTarget, callback);
        return false;
    }
    cell.appendChild(link);
    img = document.createElement('IMG')
    img.src = '<?php echo $GLOBALS['registry']->getImageDir('horde') ?>/nav/right.png';
    img.align = 'middle';
    img.border = 0;
    link.appendChild(img);
    row.appendChild(cell);
    tbody.appendChild(row);

    // Weekdays.
    row = document.createElement('TR');
    for (var i = 0; i < 7; i++) {
        cell = document.createElement('TD');
        weekday = document.createTextNode(wdays[i]);
        cell.appendChild(weekday);
        row.appendChild(cell);
    }
    tbody.appendChild(row);

    // Rows.
    var week, italic;
    var count = 1;
    var today = new Date();
    var thisYear = today.getYear();
    if (thisYear < 1900) {
        thisYear += 1900;
    }

    var odd = true;
    for (var i = 1; i <= days; i++) {
        if (count == 1) {
            row = document.createElement('TR');
            row.align = 'right';
            if (odd) {
                row.className = 'item0';
            } else {
                row.className = 'item1';
            }
            odd = !odd;
        }
        if (i == 1) {
            for (var j = 0; j < diff; j++) {
                cell = document.createElement('TD');
                row.appendChild(cell);
                count++;
            }
        }
        cell = document.createElement('TD');
        if (thisYear == currentYear &&
            today.getMonth() == month &&
            today.getDate() == i) {
            cell.style.border = '1px solid red';
        }

        link = document.createElement('A');
        cell.appendChild(link);

        link.href = i;
        link.onclick = function()
        {
            var day = this.href;
            while (day.indexOf('/') != -1) {
                day = day.substring(day.indexOf('/') + 1);
            }

            setSelectValue(document.getElementById(currentTarget + '[year]'), currentYear);
            setSelectValue(document.getElementById(currentTarget + '[month]'), currentDate.getMonth() + 1);
            setSelectValue(document.getElementById(currentTarget + '[day]'), day);

            var layer = document.getElementById('goto');
            layer.style.visibility = 'hidden';
            if (layer.firstChild) {
                layer.removeChild(layer.firstChild);
            }

            if (callback) {
                eval(callback);
            }

            return false;
        }

        day = document.createTextNode(i);
        link.appendChild(day);

        row.appendChild(cell);
        if (count == 7) {
            tbody.appendChild(row);
            count = 0;
        }
        count++;
    }
    if (count > 1) {
        for (i = count; i <= 7; i++) {
            cell = document.createElement('TD');
            row.appendChild(cell);
        }
        tbody.appendChild(row);
    }

    if (curImgId != imgId) {
        // We're showing this popup for the first time, so try to
        // position it next to the image anchor.
        var el = document.getElementById(imgId);
        var p = getAbsolutePosition(el);

        layer.style.left = p.x + 'px';
        layer.style.top = p.y + 'px';
    }

    curImgId = imgId;
    layer.appendChild(table);

    layer.style.display = 'block';
    layer.style.visibility = 'visible';
}

function getAbsolutePosition(el)
{
    var r = {x: el.offsetLeft, y: el.offsetTop};
    if (el.offsetParent) {
        var tmp = getAbsolutePosition(el.offsetParent);
        r.x += tmp.x;
        r.y += tmp.y;
    }
    return r;
}

function setSelectValue(select, value)
{
    select.value = value;
    if (select.value != value) {
        for (i = 0; i < select.options.length; i++) {
            if (select.options[i].value == value) {
                select.selectedIndex = i;
                return true;
            }
        }
    }

    return false;
}
