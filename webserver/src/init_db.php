<?php

$dbDir = __DIR__ . '/db';
$dbFile = $dbDir . '/gpx.sqlite';

if (!is_dir($dbDir)) {
        if (!mkdir($dbDir, 0777, true)) {
                die("Failed to create db directory: $dbDir");
        }
}

try {
        $db = new PDO("sqlite:$dbFile");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $db->exec("
        CREATE TABLE IF NOT EXISTS gpx_points (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sensor_nr TEXT NOT NULL,
            latitude REAL NOT NULL,
            longitude REAL NOT NULL,
            elevation REAL,
            timestamp DATETIME NOT NULL
        );

        CREATE TABLE IF NOT EXISTS tracks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                color TEXT NOT NULL
        );

        ALTER TABLE gpx_points ADD COLUMN track_id INTEGER;
    ");

        echo "✅ Database initialized at $dbFile\n";
} catch (PDOException $e) {
        echo "❌ Database error: " . $e->getMessage() . "\n";
}
