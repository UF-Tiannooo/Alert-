<?php
// config/db.php — shared database connection & session helper

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'alertplus');

// Open Weather Map API key — replace with yours (free at openweathermap.org)
define('OWM_API_KEY', 'YOUR_OPENWEATHERMAP_KEY');
// Zamboanga City default coordinates for weather
define('WEATHER_LAT', '6.9214');
define('WEATHER_LON', '122.0790');

// Gateway secret — must match sms_gateway/gateway.php
define('API_SECRET', 'ALERTPLUS_SECRET_KEY_2026');

function getDB(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode(['error' => 'DB connection failed: ' . $conn->connect_error]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: /alertplus/index.php');
        exit;
    }
}

// Convert decimal coords → DMS string  e.g. 6°54'55.6"N 122°03'33.6"E
function decToDMS(float $lat, float $lng): string {
    $latDir = $lat >= 0 ? 'N' : 'S';
    $lngDir = $lng >= 0 ? 'E' : 'W';
    $lat = abs($lat); $lng = abs($lng);

    $latD = (int)$lat;
    $latM = (int)(($lat - $latD) * 60);
    $latS = round((($lat - $latD) * 60 - $latM) * 60, 1);

    $lngD = (int)$lng;
    $lngM = (int)(($lng - $lngD) * 60);
    $lngS = round((($lng - $lngD) * 60 - $lngM) * 60, 1);

    return "{$latD}°{$latM}'{$latS}\"{$latDir} {$lngD}°{$lngM}'{$lngS}\"{$lngDir}";
}