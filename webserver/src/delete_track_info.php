<?php
$db = new PDO('sqlite:db/gpx.sqlite');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data)
        die("Invalid data");

// Clear track_id in gpx_points
$db->prepare("UPDATE gpx_points SET track_id = NULL WHERE track_id = :id")
        ->execute([':id' => $data['id']]);

// Delete the track
$db->prepare("DELETE FROM tracks WHERE id = :id")
        ->execute([':id' => $data['id']]);

echo "🗑 Track deleted.";
