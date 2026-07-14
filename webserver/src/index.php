<?php
// Connect to DB to get distinct sensors for dropdown
$db = new PDO('sqlite:db/gpx.sqlite');
$sensors = $db->query("SELECT DISTINCT sensor_nr FROM gpx_points ORDER BY sensor_nr")->fetchAll(PDO::FETCH_COLUMN);
$last_fix = $db->query("SELECT timestamp FROM gpx_points ORDER BY timestamp DESC LIMIT 1")->fetchAll(PDO::FETCH_COLUMN);

$selectedYear = isset($_GET['year']) ? trim((string) $_GET['year']) : '';
if ($selectedYear === '') {
    $selectedYear = date('Y');
}

$years = $db->query("SELECT DISTINCT strftime('%Y', timestamp) as year FROM gpx_points ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);

$tracksStmt = $db->prepare("
    SELECT t.id, t.name, t.color, COUNT(g.id) as points
    FROM tracks t
    INNER JOIN gpx_points g ON g.track_id = t.id
    WHERE strftime('%Y', g.timestamp) = :year
    GROUP BY t.id
    ORDER BY t.name
");
$tracksStmt->execute([':year' => $selectedYear]);
$tracks = $tracksStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>GPX Track Viewer</title>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <link rel="stylesheet" href="style/main.css" />

        <!-- thirdparty tools for displaying proper html5 charts -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/luxon"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon"></script>
</head>

<body>
        <div id="container">
                <div class="top-bar">
                        <div class="top-bar-left">
                                <div>Select Year:</div>
                                <form id="filterForm" onsubmit="return false;">
                                        <select id="year" onchange="window.location.href = '?year=' + this.value">
                                                <?php foreach ($years as $year): ?>
                                                        <option value="<?= htmlspecialchars($year) ?>" <?= $year === $selectedYear ? 'selected' : '' ?>><?= htmlspecialchars($year) ?></option>
                                                <?php endforeach; ?>
                                        </select>
                                </form>
                        </div>

                        <div class="top-bar-center">
                                <div class="last_upload">
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
                                </div>

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
                        </div>

                        <div class="top-bar-right">
                                <button id="login-btn" onclick="window.location.href='admin.php'">Admin</button>
                        </div>
                </div>


                <div id="map"></div>

                <!-- Floating icon in bottom right -->
                <div id="infoIcon">🗠</div>

                <!-- Pop-up overlay -->
                <div id="infoPopup">
                        <div id="infoContent">
                                <button id="closePopup">&times;</button>

                                <div class="popup-column">
                                        <h3>Track Information</h3>
                                        <p>Total Distance: <span id="totalDistance">Calculating...</span></p>
                                </div>

                                <div class="popup-column">
                                        <button onclick="showElevationData()">Show Elevation data</button>
                                        <button onclick="showSpeedData()">Show Speed data</button>
                                </div>

                                <div id="chartContainer">
                                        <canvas id="chart"></canvas>
                                </div>
                        </div>
                </div>

        </div>

        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

        <!-- custom JS functions -->
        <script>
                const map = L.map('map').setView([0, 0], 2);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                }).addTo(map);

                let trackLayer = L.layerGroup().addTo(map);
                function getDistanceFromLatLon(lat1, lon1, lat2, lon2) {
                        const R = 6371000; // meters
                        const dLat = deg2rad(lat2 - lat1);
                        const dLon = deg2rad(lon2 - lon1);
                        const a =
                                Math.sin(dLat / 2) ** 2 +
                                Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) *
                                Math.sin(dLon / 2) ** 2;
                        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                        return R * c;
                }

                function deg2rad(deg) {
                        return deg * (Math.PI / 180);
                }

                let chart = null;

                let elevationData = [];
                let speedData = [];

                async function collectData(points = null) {
                        if (points == null) {
                                // get current points
                                const track_id = document.getElementById("trackSelect").value
                                console.log("id: " + track_id);

                                if (track_id) {
                                        points = await fetchTrackData(null, null, null, track_id);
                                } else {
                                        points = await fetchTrackData('1970-01-01 00:00:00', '2099-12-31 23:59:59');
                                }
                        }

                        console.log("len: " + points.length);

                        elevationData = [];
                        speedData = [];

                        // Group points by track_id (or use default)
                        const grouped = {};
                        for (const p of points) {
                                const key = p.track_id || 'no_track';
                                if (!grouped[key]) grouped[key] = { color: p.color || defaultColor, points: [] };
                                grouped[key].points.push(p);
                        }

                        for (const [trackId, group] of Object.entries(grouped)) {
                                group.points.forEach((p, i) => {
                                        let speed = 0;

                                        if (i > 0) {
                                                const prev = group.points[i - 1];
                                                const d = getDistanceFromLatLon(prev.latitude, prev.longitude, p.latitude, p.longitude);
                                                const prev_date = new Date(prev.timestamp);
                                                const this_date = new Date(p.timestamp);
                                                speed = (d / ((this_date.getTime() / 1000) - (prev_date.getTime() / 1000))) * 3.6;      // calculate speed in km/h
                                        }

                                        elevationData.push({ x: new Date(p.timestamp), y: p.elevation });
                                        if (speed != 0) {
                                                speedData.push({ x: new Date(p.timestamp), y: speed });
                                        }
                                });
                        }
                }

                function showElevationData(points = null) {
                        if (elevationData.length === 0 || points == null) {
                                collectData(points);
                        }

                        renderChart(elevationData, 'Elevation (m)', 'green');
                }

                function showSpeedData(points = null) {
                        if (speedData.length === 0 || points == null) {
                                collectData(points);
                        }

                        renderChart(speedData, 'Speed (km/h)', 'blue');
                }

                function renderChart(data, dataLabel, color = 'green') {
                        if (chart) chart.destroy();

                        chart = new Chart(document.getElementById("chart"), {
                                type: 'line',
                                data: {
                                        datasets: [{
                                                label: dataLabel,
                                                data: data,
                                                borderColor: color,
                                                fill: false,
                                                tension: 0
                                        }]
                                },
                                options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                                x: {
                                                        type: 'time',
                                                        time: {
                                                                tooltipFormat: 'dd.MM HH:mm',
                                                                displayFormats: {
                                                                        hour: 'dd.MM HH:mm',
                                                                        minute: 'HH:mm',
                                                                }
                                                        },
                                                        title: { display: true, text: 'Time' }
                                                },
                                                y: {
                                                        title: { display: true, text: dataLabel }
                                                }
                                        }
                                }
                        });
                }

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
                                grouped[key].points.push(p);
                        }

                        let totalDistance = 0;

                        for (const [trackId, group] of Object.entries(grouped)) {
                                const points = group.points;

                                for (let i = 0; i < points.length - 1; i++) {
                                        const p1 = points[i]; const p2 = points[i + 1]; const latlngs = [
                                                [p1.latitude, p1.longitude], [p2.latitude, p2.longitude]]; const t1 = new Date(p1.timestamp); const
                                                        t2 = new Date(p2.timestamp); const diffMinutes = Math.abs((t2 - t1) / 1000 / 60); L.polyline(latlngs, {
                                                                color: group.color, dashArray: diffMinutes > 5 ? '5, 10' : null // dashed if time gap > 5 min
                                                        }).addTo(trackLayer);
                                }

                                // Draw individual waypoints with tooltips
                                group.points.forEach((p, i) => {
                                        let speed = 0;
                                        const marker = L.circleMarker([p.latitude, p.longitude], {
                                                radius: 3,
                                                color: group.color,
                                                weight: 1,
                                                fillOpacity: 0.8
                                        }).bindTooltip(`ID: #${p.id}<br>${p.timestamp}<br>elevation: ${p.elevation}m`, {
                                                permanent: false,
                                                direction: 'top',
                                                offset: [0, -5],
                                                sticky: true
                                        }).addTo(trackLayer);

                                        if (i > 0) {
                                                const prev = group.points[i - 1];
                                                const d = getDistanceFromLatLon(prev.latitude, prev.longitude, p.latitude, p.longitude);
                                                totalDistance += d;
                                        }
                                });
                        }

                        // Show distance
                        document.getElementById("totalDistance").textContent = `${(totalDistance / 1000).toFixed(2)} km`;

                        // Fit map bounds
                        const allLatLngs = points.map(p => [p.latitude, p.longitude]);
                        map.fitBounds(allLatLngs);
                }

                document.getElementById('trackSelect').addEventListener('change', async (e) => {
                        const selected = e.target.selectedOptions[0];
                        const trackId = selected.value;
                        const trackColor = selected.dataset.color || 'blue';

                        if (!trackId) {
                                // Show all tracks
                                const allPoints = await fetchTrackData('1970-01-01 00:00:00', '2099-12-31 23:59:59');
                                drawTrack(allPoints, 'blue', false);
                                showSpeedData(allPoints);
                        } else {
                                const points = await fetchTrackData(null, null, null, trackId);
                                drawTrack(points, trackColor, false);
                                showSpeedData(points);
                        }
                });

                // will open the popup when icon is clicked
                document.getElementById("infoIcon").addEventListener("click", () => {
                        document.getElementById("infoPopup").style.display = "block";
                });

                // will close the popup when clos-icon is clicked.
                document.getElementById("closePopup").addEventListener("click", () => {
                        document.getElementById("infoPopup").style.display = "none";
                });

                window.addEventListener('DOMContentLoaded', async () => {
                        const allPoints = await fetchTrackData('1970-01-01 00:00:00', '2099-12-31 23:59:59');
                        drawTrack(allPoints, 'blue', false);
                });
        </script>
</body>

</html>