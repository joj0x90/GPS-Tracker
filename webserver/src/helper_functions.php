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

function formatUtcOffset(string $timezoneId, ?DateTimeInterface $at = null): string
{
    $tz = new DateTimeZone($timezoneId);
    $at = $at
        ? DateTimeImmutable::createFromInterface($at)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);

    $offsetSeconds = $tz->getOffset($at);
    $sign = $offsetSeconds >= 0 ? '+' : '-';
    $offsetSeconds = abs($offsetSeconds);

    $hours = intdiv($offsetSeconds, 3600);
    $minutes = intdiv($offsetSeconds % 3600, 60);

    return $minutes === 0
        ? sprintf('UTC%s%d', $sign, $hours)
        : sprintf('UTC%s%02d:%02d', $sign, $hours, $minutes);
}


?>