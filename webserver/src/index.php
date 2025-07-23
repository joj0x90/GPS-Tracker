<?php
// Connect to DB to get distinct sensors for dropdown
$db = new PDO('sqlite:db/gpx.sqlite');
$sensors = $db->query("SELECT DISTINCT sensor_nr FROM gpx_points ORDER BY sensor_nr")->fetchAll(PDO::FETCH_COLUMN);
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

                async function fetchTrackData(start, end, sensor) {
                        const params = new URLSearchParams();
                        if (start) params.append('startTime', start);
                        if (end) params.append('endTime', end);
                        if (sensor) params.append('sensor', sensor);

                        const res = await fetch('get_points.php?' + params.toString());
                        if (!res.ok) {
                                alert('Failed to fetch track data');
                                return [];
                        }
                        return await res.json();
                }

                function drawTrack(points) {
                        trackLayer.clearLayers();

                        if (points.length === 0) {
                                alert('No points found for this filter');
                                return;
                        }

                        const latlngs = points.map(p => [p.latitude, p.longitude]);

                        L.polyline(latlngs, { color: 'blue' }).addTo(trackLayer);

                        // Fit map bounds to track
                        map.fitBounds(latlngs);
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
                        console.log("found " + points.length + " points");
                        drawTrack(points);
                });
        </script>
</body>

</html>