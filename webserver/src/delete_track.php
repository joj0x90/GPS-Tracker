<?php
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo "Invalid request.";
        exit;
}

$db = new PDO('sqlite:db/gpx.sqlite');
$track_id = (int) $data['id'];

// Delete points first (foreign key constraint)
$stmt1 = $db->prepare("DELETE FROM gpx_points WHERE track_id = :id");
$stmt1->execute([':id' => $track_id]);

// Delete the track
$stmt2 = $db->prepare("DELETE FROM tracks WHERE id = :id");
$stmt2->execute([':id' => $track_id]);

echo "✅ Track and all associated points deleted.";
