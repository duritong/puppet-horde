<?php echo $cManager->getJavaScript('event', 'category') ?>

<script type="text/javascript">
<!--

<?php if (!Util::nonInputVar('issearch')): ?>

function setInterval(field)
{
    elt = eval("document.event." + field);
    if (elt.value == "") {
        elt.value = 1;
    }

    if (field == 'recur_daily_interval') clearFields(1);
    else if (field == 'recur_weekly_interval') clearFields(2);
    else if (field == 'recur_day_of_month_interval') clearFields(3);
    else if (field == 'recur_week_of_month_interval') clearFields(4);
    else if (field == 'recur_yearly_interval') clearFields(5);
}

function setRecur(index)
{
    document.event.recur[index].checked = true;
    clearFields(index);
}

function clearFields(index)
{
    if (index != 1) document.event.recur_daily_interval.value = '';
    if (index != 2) {
        document.event.recur_weekly_interval.value = '';
        for (var i = 0; i < document.event.elements.length; i++) {
            if (document.event.elements[i].name == 'weekly[]') {
                document.event.elements[i].checked = false;
            }
        }
    }
    if (index != 3) document.event.recur_day_of_month_interval.value = '';
    if (index != 4) document.event.recur_week_of_month_interval.value = '';
    if (index != 5) document.event.recur_yearly_interval.value = '';
}

<?php endif; ?>

function setWholeDay(wholeDay)
{
    if (wholeDay == 1) {
<?php if ($prefs->getValue('twentyFour')): ?>
        document.event.start_hour.selectedIndex = 0;
<?php else: ?>
        document.event.start_hour.selectedIndex = 11;
        document.event.am_pm[0].checked = true;
<?php endif; ?>
        document.event.start_min.selectedIndex = 0;
        document.event.dur_day.selectedIndex = 0;
        document.event.dur_hour.selectedIndex = 23;
        document.event.dur_min.selectedIndex = 12;
    }
}

function updateWday(span)
{
<?php if ($GLOBALS['browser']->hasFeature('dom')): ?>
    wdays = new Array(
        '<?php echo addslashes(_("Sunday")) ?>',
        '<?php echo addslashes(_("Monday")) ?>',
        '<?php echo addslashes(_("Tuesday")) ?>',
        '<?php echo addslashes(_("Wednesday")) ?>',
        '<?php echo addslashes(_("Thursday")) ?>',
        '<?php echo addslashes(_("Friday")) ?>',
        '<?php echo addslashes(_("Saturday")) ?>'
    );
    if (span == 'start_wday') {
        spanObj = _get('start_wday');
        day = _get('start[day]').value;
        month = _get('start[month]').value - 1;
        year = _get('start[year]').value;
    } else if (span == 'end_wday') {
        spanObj = _get('end_wday');
        day = _get('end[day]').value;
        month = _get('end[month]').value - 1;
        year = _get('end[year]').value;
<?php if (!Util::nonInputVar('issearch')): ?>
    } else if (span == 'recur_end_wday') {
        spanObj = _get('recur_end_wday');
        day = _get('recur_enddate[day]').value;
        month = _get('recur_enddate[month]').value - 1;
        year = _get('recur_enddate[year]').value;
<?php endif; ?>
    }
    date = new Date(year, month, day)
    spanObj.innerHTML = '(' + wdays[date.getDay()] + ')';
<?php endif; ?>
}
<?php if (!$prefs->getValue('twentyFour')): ?>

// Converts a 12 hour based number to its 24 hour format
function convertTo24Hour(in_hour, in_elementName)
{
    var form = document.event;
    if (form[in_elementName][1].checked) {
        if (in_hour != 12) {
            in_hour += 12;
        }
    } else if (in_hour == 12) {
        in_hour = 0;
    }

    return in_hour;
}
<?php endif; ?>

function updateDuration()
{
    var form = document.event;
<?php if ($prefs->getValue('twentyFour')): ?>
    var startHour = form.start_hour[form.start_hour.selectedIndex].value;
    var endHour = form.end_hour[form.end_hour.selectedIndex].value
<?php else: ?>
    var startHour = parseInt(form.start_hour[form.start_hour.selectedIndex].value);
    startHour = convertTo24Hour(startHour, 'am_pm');
    var endHour = parseInt(form.end_hour[form.end_hour.selectedIndex].value);
    endHour = convertTo24Hour(endHour, 'end_am_pm');
<?php endif; ?>
    var startDate = Date.UTC(_get('start[year]').value,
                             _get('start[month]').value - 1,
                             _get('start[day]').value,
                             startHour,
                             form.start_min[form.start_min.selectedIndex].value);
    var endDate = Date.UTC(_get('end[year]').value,
                           _get('end[month]').value - 1,
                           _get('end[day]').value,
                           endHour,
                           form.end_min[form.end_min.selectedIndex].value);
    var failed = false;
    if (endDate < startDate) {
<?php if (!$prefs->getValue('twentyFour')): ?>
        // If the end hour is marked as the (default) AM, and the
        // start hour is also AM, automatically default the end hour
        // to PM if the date is otherwise the same - assume that the
        // user wants a 9am-2pm event (for example), instead of
        // throwing an error.
        if (_get('start[year]').value == _get('end[year]').value &&
            _get('start[month]').value == _get('end[month]').value &&
            _get('start[day]').value == _get('end[day]').value &&
            !document.event['am_pm'][1].checked &&
            !document.event['end_am_pm'][1].checked) {
            // Toggle the end date to PM.
            form.end_am_pm[1].checked = true;

            // Recalculate end time.
            var endHour = parseInt(form.end_hour[form.end_hour.selectedIndex].value);
            endHour = convertTo24Hour(endHour, 'end_am_pm');
            endDate = Date.UTC(_get('end[year]').value,
                               _get('end[month]').value - 1,
                               _get('end[day]').value,
                               endHour,
                               form.end_min[form.end_min.selectedIndex].value);
        } else {
<?php endif; ?>
        alert('<?php echo addslashes(_("The end date must be later than the start date.")) ?>');
        endDate = startDate;
        failed = true;
<?php if (!$prefs->getValue('twentyFour')): ?>
        }
<?php endif; ?>
    }
    var duration = (endDate - startDate) / 1000;
    var durDay = Math.floor(duration / 86400);
    duration %= 86400;
    var durHour = Math.floor(duration / 3600);
    duration %= 3600;
    var durMin = Math.floor(duration / 60 / 5);
    if (durDay < form.dur_day.options.length) {
        form.dur_day.selectedIndex = durDay;
    }
    else {
        form.dur_day.selectedIndex = form.dur_day.options.length - 1;
    }
    form.dur_hour.selectedIndex = durHour;
    form.dur_min.selectedIndex = durMin;
    form.whole_day.checked = false;
    if (failed) {
        updateEndDate();
    }
}

function updateEndDate()
{
    var form = document.event;
    var yearDiff = <?php echo date('Y') - 1 ?>;
<?php if ($prefs->getValue('twentyFour')): ?>
    var startHour = form.start_hour[form.start_hour.selectedIndex].value;
<?php else: ?>
    var startHour = parseInt(form.start_hour[form.start_hour.selectedIndex].value);
    startHour = convertTo24Hour(startHour, 'am_pm');
<?php endif; ?>
    var startDate = new Date(_get('start[year]').value,
                             _get('start[month]').value - 1,
                             _get('start[day]').value,
                             startHour,
                             form.start_min[form.start_min.selectedIndex].value);
    var endDate = new Date();
    var minutes = form.dur_day[form.dur_day.selectedIndex].value * 1440;
    minutes += form.dur_hour[form.dur_hour.selectedIndex].value * 60;
    minutes += form.dur_min[form.dur_min.selectedIndex].value * 1;
    var msecs = minutes * 60000;
    endDate.setTime(startDate.getTime() + msecs);
    var endYear = endDate.getYear();
    if (endYear < 1900) {
        endYear += 1900;
    }
    _get('end[year]').value = endYear;
    _get('end[month]').selectedIndex = endDate.getMonth();
    _get('end[day]').selectedIndex = endDate.getDate() - 1;
    var endHour = endDate.getHours()
<?php if (!$prefs->getValue('twentyFour')): ?>
    if (endHour < 12) {
        if (endHour == 0) {
            endHour = 12;
        }
        form.end_am_pm[0].checked = true;
    } else {
        if (endHour > 12) {
            endHour -= 12;
        }
        form.end_am_pm[1].checked = true;
    }
    endHour -= 1;
<?php endif; ?>
    form.end_hour.selectedIndex = endHour;
    form.end_min.selectedIndex = endDate.getMinutes() / 5;
    updateWday('end_wday');
}

<?php if (Util::getFormData('allday') == 1): ?>
window.onload = function()
{
    document.event.whole_day.click()
};
<?php endif; ?>

function _get(id)
{
    return document.getElementById ?
        document.getElementById(id) :
        eval('document.all.' + id);
}

// -->
</script>
