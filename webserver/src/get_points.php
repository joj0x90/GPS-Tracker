<?php
header('Content-Type: application/json');

$sensor = $_GET['sensor'] ?? '';
$startTime = $_GET['startTime'] ?? null;
$endTime = $_GET['endTime'] ?? null;

if (!$sensor) {
        echo json_encode([]);
        exit;
}

$db = new PDO('sqlite:db/gpx.sqlite');

$query = "SELECT latitude, longitude FROM gpx_points WHERE sensor_nr = :sensor";
$params = [':sensor' => $sensor];

if ($startTime) {
        $query .= " AND timestamp >= :startTime";
        $params[':startTime'] = $startTime;
}
if ($endTime) {
        $query .= " AND timestamp <= :endTime";
        $params[':endTime'] = $endTime;
}

$query .= " ORDER BY timestamp ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);

$points = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($points);
