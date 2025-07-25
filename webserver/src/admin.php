<?php
session_start();

$HASHED_PASSWORD = '$2y$10$B8AaGhnqC.C6rFCoqKppnecgZoEZbD/bouJABBKbm5CUIvdgNmhOa';

function isValidDate($date)
{
        return DateTime::createFromFormat('Y-m-d\TH:i', $date) !== false;
}

if (isset($_POST['logout'])) {
        session_destroy();
        header("Location: admin.php");
        exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (password_verify($_POST['password'], $HASHED_PASSWORD)) {
                $_SESSION['admin'] = true;
        } else {
                $error = "Invalid password.";
        }
}

if (isset($_POST['delete']) && $_SESSION['admin']) {
        try {
                $db = new PDO('sqlite:/var/www/html/db/gpx.sqlite');
                $db->exec("DELETE FROM gpx_points");
                $message = "✅ All GPX points deleted.";
        } catch (PDOException $e) {
                $error = "❌ Failed to delete data: " . $e->getMessage();
        }
}

if (isset($_POST['delete_range']) && $_SESSION['admin']) {
        $startInput = $_POST['start_time'] ?? '';
        $endInput = $_POST['end_time'] ?? '';

        // format timestamps to fit the format inside the db.
        $start = DateTime::createFromFormat('Y-m-d\TH:i', $startInput)?->format('Y-m-d H:i:s');
        $end = DateTime::createFromFormat('Y-m-d\TH:i', $endInput)?->format('Y-m-d H:i:s');

        if (!$start || !$end) {
                $error = "❌ Invalid date format. Please use valid date and time.";
        } else {
                try {
                        $db = new PDO('sqlite:/var/www/html/db/gpx.sqlite');
                        $stmt = $db->prepare("DELETE FROM gpx_points WHERE timestamp > :start AND timestamp < :end");
                        $stmt->execute([
                                ':start' => $start,
                                ':end' => $end
                        ]);
                        $message = "✅ GPX points deleted between $start and $end.";
                } catch (PDOException $e) {
                        $error = "❌ Failed to delete data: " . $e->getMessage();
                }
        }
}

if (isset($_POST['create_track'])) {
        $startInput = $_POST['track_start'] ?? '';
        $endInput = $_POST['track_end'] ?? '';
        $name = trim($_POST['track_name'] ?? '');
        $color = $_POST['track_color'] ?? '#000000';

        $start = DateTime::createFromFormat('Y-m-d\TH:i', $startInput)?->format('Y-m-d H:i:s');
        $end = DateTime::createFromFormat('Y-m-d\TH:i', $endInput)?->format('Y-m-d H:i:s');

        if (!$start || !$end || empty($name)) {
                $error = "❌ Invalid input for track creation.";
        } else {
                try {
                        $db = new PDO('sqlite:/var/www/html/db/gpx.sqlite');
                        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        // Check if track name already exists
                        $check = $db->prepare("SELECT COUNT(*) FROM tracks WHERE name = :name");
                        $check->execute([':name' => $name]);

                        if ($check->fetchColumn() > 0) {
                                $error = "❌ Track name already exists. Choose a unique name.";
                        } else {
                                // Insert track
                                $stmt = $db->prepare("INSERT INTO tracks (name, color) VALUES (:name, :color)");
                                $stmt->execute([
                                        ':name' => $name,
                                        ':color' => $color
                                ]);
                                $trackId = $db->lastInsertId();

                                // Assign GPX points to this track
                                $stmt = $db->prepare("UPDATE gpx_points SET track_id = :track_id WHERE timestamp >= :start AND timestamp <= :end");
                                $stmt->execute([
                                        ':track_id' => $trackId,
                                        ':start' => $start,
                                        ':end' => $end
                                ]);

                                $message = "✅ Track '$name' created and points assigned.";
                        }
                } catch (PDOException $e) {
                        $error = "❌ Database error: " . $e->getMessage();
                }
        }
}


?>

<!DOCTYPE html>
<html>

<head>
        <title>Admin Panel</title>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="style/main.css" />
</head>

<body>
        <h2>Admin Panel</h2>
        <?php
        // uncomment following line to generate a new hash and set it at the top of the file.
        // echo password_hash('passw0rd1', PASSWORD_DEFAULT); 
        ?>

        <?php if (!isset($_SESSION['admin'])): ?>
                <?php if (isset($error))
                        echo "<p class='warning'>$error</p>"; ?>
                <form method="POST">
                        <label for="password">Enter admin password:</label>
                        <input type="password" name="password" id="password" required>
                        <button type="submit">Login</button>
                </form>
        <?php else: ?>
                <?php if (isset($message))
                        echo "<p style='color:green'>$message</p>"; ?>
                <?php if (isset($error))
                        echo "<p class='warning'>$error</p>";

                echo "<h2>📋 Existing Tracks</h2>";

                try {
                        $db = new PDO('sqlite:/var/www/html/db/gpx.sqlite');
                        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        $stmt = $db->query("
                                SELECT t.id, t.name, t.color,
                                        MIN(g.timestamp) AS start_time,
                                        MAX(g.timestamp) AS end_time,
                                        COUNT(g.id) AS point_count
                                FROM tracks t
                                LEFT JOIN gpx_points g ON g.track_id = t.id
                                GROUP BY t.id
                                ORDER BY start_time ASC
                        ");
                        $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if ($tracks) {
                                ?>
                                <h3>🛠 Manage Tracks</h3>

                                <table border="1" cellpadding="5" cellspacing="0">
                                        <thead>
                                                <tr>
                                                        <th>Track Name</th>
                                                        <th>Color</th>
                                                        <th>Start Time</th>
                                                        <th>End Time</th>
                                                        <th>Waypoints</th>
                                                        <th>Actions</th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                                <?php foreach ($tracks as $track): ?>
                                                        <tr data-track-id="<?= $track['id'] ?>">
                                                                <td><input type="text" class="nameInput"
                                                                                value="<?= htmlspecialchars($track['name']) ?>" /></td>
                                                                <td><input type="color" class="colorInput"
                                                                                value="<?= htmlspecialchars($track['color']) ?>" />
                                                                </td>
                                                                <td><input type="datetime-local" class="startInput"
                                                                                value="<?= str_replace(' ', 'T', $track['start_time']) ?>" /></td>
                                                                <td><input type="datetime-local" class="endInput"
                                                                                value="<?= str_replace(' ', 'T', $track['end_time']) ?>" /></td>
                                                                <td><?= $track['point_count'] ?></td>
                                                                <td>
                                                                        <button class="saveBtn">💾 Save</button>
                                                                        <button class="deleteBtn">🗑 Delete</button>
                                                                </td>
                                                        </tr>
                                                <?php endforeach; ?>
                                        </tbody>
                                </table>
                                <?php
                        } else {
                                echo "<p>No tracks found.</p>";
                        }
                } catch (PDOException $e) {
                        echo "<p style='color:red'>Error fetching tracks: " . $e->getMessage() . "</p>";
                }
                ?>

                <h2>➕ Create New Track</h2>
                <form method="POST">
                        <label for="track_start">Start Time:</label>
                        <input type="datetime-local" name="track_start" required><br><br>

                        <label for="track_end">End Time:</label>
                        <input type="datetime-local" name="track_end" required><br><br>

                        <label for="track_name">Track Name:</label>
                        <input type="text" name="track_name" required><br><br>

                        <label for="track_color">Track Color:</label>
                        <input type="color" name="track_color" value="#ff0000" required><br><br>

                        <button type="submit" name="create_track">Create Track</button>
                </form>

                <hr>
                <!-- Delete all -->
                <form method="POST" onsubmit="return confirm('⚠️ Are you sure you want to delete ALL GPX points?');">
                        <input type="hidden" name="delete" value="1">
                        <button type="submit" class="warning-btn">Delete All GPX Points</button>
                </form>

                <!-- Delete range -->
                <form method="POST"
                        onsubmit="return confirm('⚠️ Are you sure you want to delete GPX points in this time range?');">
                        <label for="start_time">Start Time:</label>
                        <input type="datetime-local" name="start_time" id="start_time" required><br />

                        <label for="end_time">End Time: </label>
                        <input type="datetime-local" name="end_time" id="end_time" required>

                        <input type="hidden" name="delete_range" value="1">
                        <button type="submit" class="attention-btn">Delete GPX Points in
                                Range</button>
                </form>

                <h3>Delete a Track</h3>
                <select id="deleteTrackSelect"></select>
                <button id="deleteTrackBtn">Delete Track</button>


                <br />&nbsp;<br />

                <!-- Logout -->
                <form method="POST">
                        <input type="hidden" name="logout" value="1">
                        <button type="submit" style="margin-top: 2em;">Log Out</button>
                </form>
        <?php endif; ?>

        <script>
                document.querySelectorAll('.saveBtn').forEach(btn => {
                        btn.addEventListener('click', async (e) => {
                                const row = e.target.closest('tr');
                                const id = row.dataset.trackId;
                                const name = row.querySelector('.nameInput').value.trim();
                                const color = row.querySelector('.colorInput').value;
                                const start = row.querySelector('.startInput').value;
                                const end = row.querySelector('.endInput').value;

                                if (!name || !start || !end) {
                                        alert("All fields are required.");
                                        return;
                                }

                                const res = await fetch('update_track.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ id, name, color, start, end })
                                });

                                const result = await res.text();
                                alert(result);
                                location.reload();
                        });
                });

                document.querySelectorAll('.deleteBtn').forEach(btn => {
                        btn.addEventListener('click', async (e) => {
                                const row = e.target.closest('tr');
                                const id = row.dataset.trackId;

                                if (!confirm("Are you sure you want to delete this track? This cannot be undone.")) return;

                                const res = await fetch('delete_track_info.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ id })
                                });

                                const result = await res.text();
                                alert(result);
                                location.reload();
                        });
                });

                async function loadTracksForDeletion() {
                        const res = await fetch('get_tracks.php');
                        const tracks = await res.json();
                        console.log(tracks.length + " tracks");
                        const select = document.getElementById('deleteTrackSelect');
                        select.innerHTML = '';
                        tracks.forEach(track => {
                                const option = document.createElement('option');
                                option.value = track.id;
                                option.textContent = `${track.name} (${track.count} points)`;
                                select.appendChild(option);
                        });
                }

                document.getElementById('deleteTrackBtn').addEventListener('click', async () => {
                        const trackId = document.getElementById('deleteTrackSelect').value;
                        if (!trackId) return;

                        const confirmDelete = confirm("⚠️ Are you sure you want to delete this track and ALL associated points?");
                        if (!confirmDelete) return;

                        const res = await fetch('delete_track.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: trackId })
                        });

                        const result = await res.text();
                        alert(result);
                        await loadTracksForDeletion();
                        location.reload(); // optional: refresh page to show updated data
                });

                window.addEventListener('DOMContentLoaded', () => {
                        loadTracksForDeletion();
                });

        </script>
</body>

</html>