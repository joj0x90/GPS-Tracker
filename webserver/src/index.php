<?php
// Connect to DB to get distinct sensors for dropdown
$db = new PDO('sqlite:db/gpx.sqlite');
$sensors = $db->query("SELECT DISTINCT sensor_nr FROM gpx_points ORDER BY sensor_nr")->fetchAll(PDO::FETCH_COLUMN);
$last_fix = $db->query("SELECT timestamp FROM gpx_points ORDER BY timestamp DESC LIMIT 1")->fetchAll(PDO::FETCH_COLUMN);
$tracks = $db->query("
    SELECT t.id, t.name, t.color, COUNT(g.id) as points
    FROM tracks t
    LEFT JOIN gpx_points g ON g.track_id = t.id
    GROUP BY t.id
    ORDER BY t.name
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>GPX Track Viewer</title>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <link rel="stylesheet" href="style/main.css" />
</head>

<body>
        <div id="container">
                <div class="last_upload" align="center">
                        <?php
                        if ($last_fix == null) {
                                echo "last GPS-fix: never";
                        } else {
                                $date = date_create($last_fix[0]);
                                $formatted = date_format($date, "d.m.Y H:i");
                                $now = new DateTime('now');
                                $diff = $now->diff($date);
                                $hours = abs(floor(($diff->days * 24) + $diff->h + ($diff->i / 60) + ($diff->s / 3600)));
                                echo "last GPS-fix: " . $formatted . " UTC (" .
                                        $hours .
                                        " hours ago)";
                        }

                        ?>
                </div><br />

                <form id="trackForm" onsubmit="return false;" style="margin-bottom: 1em;">
                        <label for="trackSelect">Select Track:</label>
                        <select id="trackSelect">
                                <option value="">-- Show All Tracks --</option>
                                <?php foreach ($tracks as $track): ?>
                                        <option value="<?= htmlspecialchars($track['id']) ?>"
                                                data-color="<?= htmlspecialchars($track['color']) ?>">
                                                <?= htmlspecialchars($track['name']) ?> (<?= $track['points'] ?> pts)
                                        </option>
                                <?php endforeach; ?>
                        </select>
                </form>

                <form id="filterForm" onsubmit="return false;">
                        <input type="datetime-local" id="startTime" name="startTime" />
                        <input type="datetime-local" id="endTime" name="endTime" />
                        <select id="sensorSelect" name="sensor">
                                <option value="">-- Select Sensor --</option>
                                <?php foreach ($sensors as $sensor): ?>
                                        <option value="<?= htmlspecialchars($sensor) ?>"><?= htmlspecialchars($sensor) ?>
                                        </option>
                                <?php endforeach; ?>
                        </select>
                        <button id="filterBtn" type="submit">Filter</button>
                </form>
                <div id="map"></div>
        </div>

        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
                const map = L.map('map').setView([0, 0], 2);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                }).addTo(map);

                let trackLayer = L.layerGroup().addTo(map);

                async function fetchTrackData(start, end, sensor, track_id = null) {
                        const params = new URLSearchParams();
                        if (start) params.append('startTime', start);
                        if (end) params.append('endTime', end);
                        if (sensor) params.append('sensor', sensor);
                        if (track_id) params.append('track_id', track_id);

                        const res = await fetch('get_points.php?' + params.toString());
                        if (!res.ok) {
                                alert('Failed to fetch track data');
                                return [];
                        }
                        return await res.json();
                }

                function drawTrack(points, defaultColor = 'blue', display_errors = true) {
                        trackLayer.clearLayers();

                        if (points.length === 0) {
                                if (display_errors) {
                                        alert('No points found for this filter');
                                }
                                console.log('No points found for this filter');
                                return;
                        }

                        // Group points by track_id (or use default)
                        const grouped = {};
                        for (const p of points) {
                                const key = p.track_id || 'no_track';
                                if (!grouped[key]) grouped[key] = { color: p.color || defaultColor, points: [] };
                                grouped[key].points.push([p.latitude, p.longitude]);
                        }

                        for (const [trackId, group] of Object.entries(grouped)) {
                                L.polyline(group.points, { color: group.color }).addTo(trackLayer);
                        }

                        // Fit map bounds
                        const allLatLngs = points.map(p => [p.latitude, p.longitude]);
                        map.fitBounds(allLatLngs);
                }

                document.getElementById('filterBtn').addEventListener('click', async () => {
                        const start = document.getElementById('startTime').value;
                        const end = document.getElementById('endTime').value;
                        const sensor = document.getElementById('sensorSelect').value;

                        if (!sensor) {
                                alert('Please select a sensor.');
                                return;
                        }

                        const points = await fetchTrackData(start, end, sensor);
                        drawTrack(points);
                });

                document.getElementById('trackSelect').addEventListener('change', async (e) => {
                        const selected = e.target.selectedOptions[0];
                        const trackId = selected.value;
                        const trackColor = selected.dataset.color || 'blue';

                        if (!trackId) {
                                // Show all tracks
                                const allPoints = await fetchTrackData('1970-01-01 00:00:00', '2099-12-31 23:59:59', 'default');
                                drawTrack(allPoints, 'blue', false);
                        } else {
                                const points = await fetchTrackData(null, null, null, trackId);
                                drawTrack(points, trackColor);
                        }
                });

                window.addEventListener('DOMContentLoaded', async () => {
                        const allPoints = await fetchTrackData('1970-01-01 00:00:00', '2099-12-31 23:59:59', 'default');
                        drawTrack(allPoints, 'blue', false);
                });
        </script>
</body>

</html>