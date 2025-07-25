<?php
$db = new PDO('sqlite:db/gpx.sqlite');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data)
        die("Invalid data");

function normalizeTimestamp($ts)
{
        // Replace 'T' with space
        $ts = str_replace('T', ' ', $ts);
        // Add seconds if missing
        if (strlen($ts) === 16) {
                $ts .= ':00';
        }
        return $ts;
}

$name = $data['name'];
$color = $data['color'];
$id = $data['id'];
$start = normalizeTimestamp($data['start']);
$end = normalizeTimestamp($data['end']);
echo $start;

$stmt = $db->prepare("UPDATE tracks SET name = :name, color = :color WHERE id = :id");
$stmt->execute([
        ':name' => $name,
        ':color' => $color,
        ':id' => $id
]);

// Remove this track assignment from all previous waypoints
$db->prepare("UPDATE gpx_points SET track_id = NULL WHERE track_id = :id")
        ->execute([':id' => $id]);

// Assign this track_id to all points between start and end (inclusive)
$db->prepare("UPDATE gpx_points SET track_id = :id WHERE timestamp >= :start AND timestamp <= :end")
        ->execute([':id' => $id, ':start' => $start, ':end' => $end]);

echo "✅ Track updated.";
