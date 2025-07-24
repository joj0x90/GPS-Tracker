<?php

function parseGPX($filename, $sensor = 'default')
{
        // Open SQLite database
        $db = new PDO('sqlite:db/gpx.sqlite');

        // Create table if it doesn't exist
        $db->exec("
        CREATE TABLE IF NOT EXISTS gpx_points (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sensor_nr TEXT NOT NULL,
            latitude REAL NOT NULL,
            longitude REAL NOT NULL,
            elevation REAL,
            timestamp DATETIME NOT NULL
        )
    ");

        // Load GPX file as XML
        $xml = simplexml_load_file($filename);

        if (!$xml) {
                die("Failed to load GPX file.");
        }

        // Register GPX namespace (sometimes needed)
        $xml->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');

        // Prepare insert statement
        $stmt = $db->prepare("
        INSERT INTO gpx_points (sensor_nr, latitude, longitude, elevation, timestamp)
        VALUES (:sensor_nr, :lat, :lon, :ele, :time)
    ");

        // Find all trackpoints
        foreach ($xml->xpath('//gpx:trkpt') as $trkpt) {
                $lat = (float) $trkpt['lat'];
                $lon = (float) $trkpt['lon'];
                $ele = isset($trkpt->ele) ? (float) $trkpt->ele : null;
                $time = isset($trkpt->time) ? date('Y-m-d H:i:s', strtotime((string) $trkpt->time)) : null;
                $sensor_nr = isset($trkpt->sensor_nr) ? strtoupper((string) $trkpt->sensor_nr) : 'default';

                if ($lat && $lon && $time) {
                        $stmt->execute([
                                ':sensor_nr' => $sensor_nr,
                                ':lat' => $lat,
                                ':lon' => $lon,
                                ':ele' => $ele,
                                ':time' => $time
                        ]);
                }
        }
}
