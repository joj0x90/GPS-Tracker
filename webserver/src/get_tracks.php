<?php
$db = new PDO('sqlite:db/gpx.sqlite');

// Get all tracks and count their waypoints
$stmt = $db->query("
    SELECT 
        tracks.id, 
        tracks.name, 
        tracks.color, 
        COUNT(gpx_points.id) as count 
    FROM tracks 
    LEFT JOIN gpx_points ON gpx_points.track_id = tracks.id 
    GROUP BY tracks.id 
    ORDER BY tracks.name
");

$tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return as JSON
header('Content-Type: application/json');
echo json_encode($tracks);
