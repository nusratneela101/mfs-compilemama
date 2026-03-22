<?php
/**
 * Bangladesh Location Spoof
 * Forces timezone, language and geolocation headers for Bangladesh
 */

// Force Bangladesh timezone
date_default_timezone_set('Asia/Dhaka');

// Spoof geolocation headers for Bangladesh (Dhaka)
if (!headers_sent()) {
    header('X-Country: BD');
    header('X-Country-Name: Bangladesh');
    header('X-City: Dhaka');
    header('X-Timezone: Asia/Dhaka');
    header('X-Latitude: 23.8103');
    header('X-Longitude: 90.4125');
    header('Content-Language: bn-BD, en-BD');
}

// Override server locale for Bangladesh
putenv('TZ=Asia/Dhaka');
putenv('LANG=bn_BD.UTF-8');

// Dhaka coordinates
define('GEO_LAT', 23.8103);
define('GEO_LNG', 90.4125);
define('GEO_CITY', 'Dhaka');
define('GEO_COUNTRY', 'Bangladesh');
define('GEO_COUNTRY_CODE', 'BD');
define('GEO_TIMEZONE', 'Asia/Dhaka');
define('GEO_CURRENCY', 'BDT');
define('GEO_PHONE_PREFIX', '+880');
define('GEO_LOCALE', 'bn-BD');
