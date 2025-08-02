<?php
header('Content-Type: application/json');

$sensor = $_GET['sensor'] ?? '';
$startTime = $_GET['startTime'] ?? null;
$endTime = $_GET['endTime'] ?? null;
$track_id = $_GET['track_id'] ?? null;

$db = new PDO('sqlite:db/gpx.sqlite');

$query = "
    SELECT 
        g.id,
        g.latitude, 
        g.longitude, 
        g.track_id,
        g.timestamp,
        g.elevation,
        t.color
    FROM gpx_points g
    LEFT JOIN tracks t ON g.track_id = t.id
    WHERE 1=1
";

if ($sensor) {
        $query .= " AND g.sensor_nr = :sensor";
        $params = [':sensor' => $sensor];
}

if ($startTime) {
        $query .= " AND g.timestamp >= :startTime";
        $params[':startTime'] = $startTime;
}
if ($endTime) {
        $query .= " AND g.timestamp <= :endTime";
        $params[':endTime'] = $endTime;
}

if (!empty($track_id)) {
        $query .= " AND g.track_id = :track_id";
        $params[':track_id'] = $track_id;
}

$query .= " ORDER BY g.timestamp ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);

$points = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($points);
