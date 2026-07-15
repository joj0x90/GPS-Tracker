<?php
function calc_time($hours)
{
    $hours = max(0, (float) $hours);

    if ($hours < 24) {
        $value = round($hours, 1);
        return $value . ' ' . ($value == 1 ? 'hour' : 'hours');
    }

    $days = $hours / 24;
    if ($days < 365) {
        $value = round($days, 1);
        return $value . ' ' . ($value == 1 ? 'day' : 'days');
    }

    $years = $days / 365;
    $value = round($years, 1);
    return $value . ' ' . ($value == 1 ? 'year' : 'years');
}

?>