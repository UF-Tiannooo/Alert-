<?php
require_once '../config/db.php';
requireLogin();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$childId     = (int)($input['child_id'] ?? 0);
$commandType = $input['command_type'] ?? '';

if (!$childId || !in_array($commandType, ['ping', 'ring'])) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$db = getDB();

// Verify child exists
$check = $db->prepare("SELECT id FROM children WHERE id = ?");
$check->bind_param('i', $childId);
$check->execute();
$child = $check->get_result()->fetch_assoc();
$check->close();

if (!$child) {
    echo json_encode(['error' => 'Child not found']);
    exit;
}

// Queue command
$ins = $db->prepare("INSERT INTO commands (child_id, command_type, status) VALUES (?, ?, 'pending')");
$ins->bind_param('is', $childId, $commandType);
$ins->execute();

$ins->close();
$db->close();

$log = $db->prepare("
    INSERT INTO commands_log (child_id, command_type, created_at)
    VALUES (?, ?, NOW())
");
$log->bind_param('is', $childId, $commandType);
$log->execute();
$log->close();

echo json_encode(['success' => true]);