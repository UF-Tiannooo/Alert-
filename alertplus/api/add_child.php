<?php
require_once '../config/db.php';
requireLogin();
header('Content-Type: application/json');

$name  = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$color = trim($_POST['color'] ?? '#e74c3c');

if (!$name || !$phone) {
    echo json_encode(['error' => 'Name and phone are required']);
    exit;
}

if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
    $color = '#e74c3c';
}

$db = getDB();

$stmt = $db->prepare("INSERT INTO children (name, phone, color) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $name, $phone, $color);
$stmt->execute();

$stmt->close();
$db->close();

echo json_encode(['success' => true]);