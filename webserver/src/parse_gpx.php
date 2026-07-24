<?php

function sanitizeGPXFile($filename, $minIntervalSeconds = 30)
{
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (!$dom->load($filename)) {
                throw new RuntimeException('Failed to load GPX file for sanitizing.');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('gpx', 'http://www.topografix.com/GPX/1/1');

        $nodesToRemove = [];

        foreach ($xpath->query('//gpx:wpt') as $waypoint) {
                $nodesToRemove[] = $waypoint;
        }

        $trackpoints = $xpath->query('//gpx:trkpt');
        $lastTimestamp = null;
        $pointsToRemove = [];

        foreach ($trackpoints as $trackpoint) {
                $timeNode = $xpath->query('./gpx:time', $trackpoint)->item(0);
                $timestamp = null;

                if ($timeNode && $timeNode->nodeValue !== '') {
                        $timestamp = strtotime($timeNode->nodeValue);
                }

                if ($timestamp === false || $timestamp === null) {
                        $lastTimestamp = null;
                        continue;
                }

                if ($lastTimestamp !== null && ($timestamp - $lastTimestamp) < $minIntervalSeconds) {
                        $pointsToRemove[] = $trackpoint;
                        continue;
                }

                $lastTimestamp = $timestamp;
        }

        foreach (array_merge($nodesToRemove, $pointsToRemove) as $node) {
                if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                }
        }

        if (!$dom->save($filename)) {
                throw new RuntimeException('Failed to write sanitized GPX file.');
        }

        return $filename;
}

function parseGPX($filename, $sensor = 'default')
{
        set_time_limit(300);

        $sanitizedFilename = sanitizeGPXFile($filename);

        // Open SQLite database
        $db = new PDO('sqlite:db/gpx.sqlite');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create table if it doesn't exist
        $db->exec("
        CREATE TABLE IF NOT EXISTS gpx_points (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sensor_nr TEXT NOT NULL,
            latitude REAL NOT NULL,
            longitude REAL NOT NULL,
            elevation REAL,
            timestamp DATETIME NOT NULL,
            track_id INTEGER
        )
    ");

        $db->exec('PRAGMA synchronous = NORMAL');
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA temp_store = MEMORY');

        // Load GPX file as XML
        $xml = simplexml_load_file($sanitizedFilename);

        if (!$xml) {
                throw new RuntimeException('Failed to load GPX file.');
        }

        // Register GPX namespace (sometimes needed)
        $xml->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');

        // Prepare insert statement
        $stmt = $db->prepare("
        INSERT INTO gpx_points (sensor_nr, latitude, longitude, elevation, timestamp, track_id)
        VALUES (:sensor_nr, :lat, :lon, :ele, :time, :track_id)
    ");

        try {
                $db->beginTransaction();

                $insertedRows = 0;

                // Find all trackpoints
                foreach ($xml->xpath('//gpx:trkpt') as $trkpt) {
                        $lat = (float) $trkpt['lat'];
                        $lon = (float) $trkpt['lon'];
                        $ele = isset($trkpt->ele) ? (float) $trkpt->ele : null;
                        $time = isset($trkpt->time) ? date('Y-m-d H:i:s', strtotime((string) $trkpt->time)) : null;
                        $sensor_nr = isset($trkpt->sensor_nr) ? strtoupper((string) $trkpt->sensor_nr) : $sensor;

                        if ($lat !== 0.0 && $lon !== 0.0 && $time !== null) {
                                $stmt->execute([
                                        ':sensor_nr' => $sensor_nr,
                                        ':lat' => $lat,
                                        ':lon' => $lon,
                                        ':ele' => $ele,
                                        ':time' => $time,
                                        ':track_id' => null
                                ]);
                                $insertedRows++;
                        }
                }

                $db->commit();
                return $insertedRows;
        } catch (Exception $exception) {
                if ($db->inTransaction()) {
                        $db->rollBack();
                }

                throw $exception;
        }
}
