<?php
require_once '../config/db.php';
requireLogin();

header('Content-Type: application/json');

$db = getDB();
$uid = $_SESSION['user_id'];

/*
We combine:
1. location_logs (GPS)
2. commands (ping/ring history)
*/

$sql = "
SELECT 
    'location' AS type,
    ll.child_id,
    c.name AS child_name,
    ll.latitude,
    ll.longitude,
    ll.source,
    ll.received_at AS time
FROM location_logs ll
JOIN children c ON c.id = ll.child_id
WHERE c.parent_id = ?

UNION ALL

SELECT 
    'command' AS type,
    co.child_id,
    c.name AS child_name,
    NULL AS latitude,
    NULL AS longitude,
    co.command_type AS source,
    co.created_at AS time
FROM commands co
JOIN children c ON c.id = co.child_id
WHERE c.parent_id = ?

ORDER BY time DESC
";

$stmt = $db->prepare($sql);
$stmt->bind_param("ii", $uid, $uid);
$stmt->execute();

$result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$db->close();

echo json_encode($result);