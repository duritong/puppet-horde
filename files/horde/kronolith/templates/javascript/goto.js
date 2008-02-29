var currentDate, currentYear;

function weekOfYear(d)
{
    // Adapted from http://www.merlyn.demon.co.uk/js-date7.htm#WkConv.
    var ms1d = 86400000, ms3d = 3 * ms1d, ms7d = 7 * ms1d;

    var year = d.getYear();
    if (year < 1900) {
        year += 1900;
    }
    var D3 = Date.UTC(year, d.getMonth(), d.getDate()) + ms3d;
    var wk = Math.floor(D3 / ms7d);
    with (new Date(wk * ms7d)) {
        var yy = getUTCFullYear();
    }
    return [yy, 1 + wk - Math.floor((Date.UTC(yy, 0, 4) + ms3d) / ms7d)]
}

function openKGoto(timestamp)
{
    var row, cell, img, link, days;

    var d = new Date(timestamp * 1000);
    currentDate = d;
    var month = d.getMonth();
    var year = d.getYear();
    if (year < 1900) {
        year += 1900;
    }
    currentYear = year;
    var firstOfMonth = new Date(year, month, 1);
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
        if (year % 4 == 0 && (year % 100 != 0 || year % 400 == 0)) {
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

    var table = document.createElement('TABLE');
    var tbody = document.createElement('TBODY');
    table.appendChild(tbody);
    table.className = 'item';
    table.cellSpacing = 0;

    // Title.
    row = document.createElement('TR');
    cell = document.createElement('TD');
    cell.colSpan = 8;
    cell.align = 'right';
    cell.className = 'control';
    link = document.createElement('A');
    link.href = '#';
    link.onclick = function() {
        document.getElementById('kgoto').style.display = 'none';
        if (backing = document.getElementById('kgotoBacking')) {
            backing.style.display = 'none';
        }
        return false;
    }
    img = document.createElement('IMG');
    img.src = '<?php echo $GLOBALS['registry']->getImageDir('horde') ?>/close.png';
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
    link.onclick = function() {
        newDate = new Date(currentYear - 1, currentDate.getMonth(), 1);
        openKGoto(newDate.getTime() / 1000);
        return false;
    }
    cell.appendChild(link);
    img = document.createElement('IMG')
    img.src = '<?php echo $GLOBALS['registry']->getImageDir('horde') ?>/nav/left.png';
    link.appendChild(img);
    row.appendChild(cell);

    cell = document.createElement('TD');
    cell.colSpan = 6;
    cell.align = 'center';
    var y = document.createTextNode(year);
    cell.appendChild(y);
    row.appendChild(cell);

    cell = document.createElement('TD');
    cell.align = 'right';
    link = document.createElement('A');
    link.href = '#';
    link.onclick = function() {
        newDate = new Date(currentYear + 1, currentDate.getMonth(), 1);
        openKGoto(newDate.getTime() / 1000);
        return false;
    }
    cell.appendChild(link);
    img = document.createElement('IMG')
    img.src = '<?php echo $GLOBALS['registry']->getImageDir('horde') ?>/nav/right.png';
    link.appendChild(img);
    row.appendChild(cell);
    tbody.appendChild(row);

    // Month name.
    row = document.createElement('TR');
    cell = document.createElement('TD');
    cell.align = 'left';
    link = document.createElement('A');
    link.href = '#';
    link.onclick = function() {
        var newMonth = currentDate.getMonth() - 1;
        var newYear = currentYear;
        if (newMonth == -1) {
            newMonth = 11;
            newYear -= 1;
        }
        newDate = new Date(newYear, newMonth, currentDate.getDate());
        openKGoto(newDate.getTime() / 1000);
        return false;
    }
    cell.appendChild(link);
    img = document.createElement('IMG')
    img.src = '<?php echo $GLOBALS['registry']->getImageDir('horde') ?>/nav/left.png';
    link.appendChild(img);
    row.appendChild(cell);

    cell = document.createElement('TD');
    cell.colSpan = 6;
    cell.align = 'center';
    link = document.createElement('A');
    link.href = '<?php echo Horde::applicationUrl('month.php') ?>';
    if (link.href.indexOf('?') != -1) {
        link.href += '&';
    } else {
        link.href += '?';
    }
    link.href += 'year=' + year + '&month=' + (month + 1);
    cell.appendChild(link);
    var m = document.createTextNode(months[month]);
    link.appendChild(m);
    row.appendChild(cell);

    cell = document.createElement('TD');
    cell.align = 'right';
    link = document.createElement('A');
    link.href = '#';
    link.onclick = function() {
        newDate = new Date(currentYear, currentDate.getMonth() + 1, 1);
        openKGoto(newDate.getTime() / 1000);
        return false;
    }
    cell.appendChild(link);
    img = document.createElement('IMG')
    img.src = '<?php echo $GLOBALS['registry']->getImageDir('horde') ?>/nav/right.png';
    link.appendChild(img);
    row.appendChild(cell);
    tbody.appendChild(row);

    // weekdays
    row = document.createElement('TR');
    cell = document.createElement('TD');
    row.appendChild(cell);
    for (var i = 0; i < 7; i++) {
        cell = document.createElement('TD');
        weekday = document.createTextNode(wdays[i]);
        cell.appendChild(weekday);
        row.appendChild(cell);
    }
    tbody.appendChild(row);

    // rows
    var weekInfo, italic;
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
            cell = document.createElement('TD');
            weekInfo = weekOfYear(new Date(year, month, i));
            italic = document.createElement('I');
            cell.appendChild(italic);
            link = document.createElement('A');
            link.href = '<?php echo Horde::applicationUrl('week.php') ?>';
            if (link.href.indexOf('?') != -1) {
                link.href += '&';
            } else {
                link.href += '?';
            }
            link.href += 'year=' + weekInfo[0] + '&week=' + weekInfo[1];
            italic.appendChild(link);
            link.appendChild(document.createTextNode(weekInfo[1]));
            row.appendChild(cell);
        }
        if (i == 1) {
            for (var j = 0; j < diff; j++) {
                cell = document.createElement('TD');
                row.appendChild(cell);
                count++;
            }
        }
        cell = document.createElement('TD');
        if (thisYear == year &&
            today.getMonth() == month &&
            today.getDate() == i) {
            cell.style.border = '1px solid red';
        }
        link = document.createElement('A');
        link.href = '<?php echo Horde::applicationUrl('day.php') ?>';
        if (link.href.indexOf('?') != -1) {
            link.href += '&';
        } else {
            link.href += '?';
        }
        link.href += 'year=' + year + '&month=' + (month + 1) + '&mday=' + i;
        cell.appendChild(link);
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

    // Show popup div.
    var div = document.getElementById('kgoto');
    if (div.firstChild) {
        div.removeChild(div.firstChild);
    }
    div.appendChild(table);
    div.style.display = 'block';

    // Back the popup with an iframe if necessary to hide <select>
    // boxes in MSIE.
    if (backing = document.getElementById('kgotoBacking')) {
        backing.style.width = div.offsetWidth;
        backing.style.height = div.offsetHeight;
        backing.style.top = div.style.top;
        backing.style.left = div.style.left;

        if (div.style.zIndex == '') {
            div.style.zIndex = 2;
            backing.style.zIndex = 1;
        } else {
            backing.style.zIndex = div.style.zIndex - 1;
        }

        backing.style.display = 'block';
    }
}
