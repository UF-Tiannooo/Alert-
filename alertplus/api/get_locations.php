<?php
require_once '../config/db.php';
requireLogin();
header('Content-Type: application/json');

$db = getDB();

$sql = "
SELECT ll.child_id,
       c.name AS child_name,
       ll.latitude,
       ll.longitude,
       ll.source,
       ll.received_at
FROM location_logs ll
JOIN children c ON c.id = ll.child_id
JOIN (
    SELECT child_id, MAX(id) AS max_id
    FROM location_logs
    GROUP BY child_id
) latest ON latest.child_id = ll.child_id AND latest.max_id = ll.id
ORDER BY ll.received_at DESC
";

$res = $db->query($sql);
$data = $res->fetch_all(MYSQLI_ASSOC);

$db->close();

echo json_encode($data);