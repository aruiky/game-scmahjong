<?php
declare(strict_types=1);

function configureMahjongTimezone(): string
{
    $timezone = getenv('MAHJONG_TIMEZONE');
    if ($timezone === false || trim($timezone) === '') {
        $timezone = 'Asia/Shanghai';
    }

    try {
        $timezone = (new DateTimeZone($timezone))->getName();
    } catch (Exception $error) {
        throw new RuntimeException('MAHJONG_TIMEZONE must be a valid PHP timezone identifier.', 0, $error);
    }

    if (!date_default_timezone_set($timezone)) {
        throw new RuntimeException('Unable to configure the MAHJONG_TIMEZONE setting.');
    }

    return $timezone;
}
