<?php
// This endpoint is called by your SMS gateway script (NOT from the browser)
// It receives parsed SMS data and stores the location
// Secure it with a secret token

require_once '../config/db.php';
header('Content-Type: application/json');

// ── SECURITY TOKEN ── change this to something random
define('API_SECRET', 'ALERTPLUS_SECRET_2025');

$token = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['key'] ?? '');
if ($token !== API_SECRET) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── PARSE INPUT ──
// Accepts JSON: { "phone": "+63912...", "latitude": 6.9xxx, "longitude": 122.0xxx, "source": "location_ping" }
$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true);

$phone  = trim($data['phone']     ?? '');
$lat    = floatval($data['latitude']  ?? 0);
$lng    = floatval($data['longitude'] ?? 0);
$source = $data['source'] ?? 'location_ping';

if (!$phone || !$lat || !$lng) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

if (!in_array($source, ['location_ping', 'presence_call', 'child_button'])) {
    $source = 'location_ping';
}

$db   = getDB();

// Find child by device phone
$stmt = $db->prepare("SELECT id FROM children WHERE phone = ?");
$stmt->bind_param('s', $phone);
$stmt->execute();
$child = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$child) {
    $db->close();
    http_response_code(404);
    echo json_encode(['error' => 'Device not registered']);
    exit;
}

// Insert location log
$ins = $db->prepare("INSERT INTO location_logs (child_id, latitude, longitude, source) VALUES (?, ?, ?, ?)");
$ins->bind_param('idds', $child['id'], $lat, $lng, $source);
$ins->execute();
$ins->close();
$db->close();

echo json_encode(['success' => true]);