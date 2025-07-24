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
                        echo "<p class='warning'>$error</p>"; ?>

                <!-- Delete all -->
                <form method="POST" onsubmit="return confirm('⚠️ Are you sure you want to delete ALL GPX points?');">
                        <input type="hidden" name="delete" value="1">
                        <button type="submit" style="background-color: red; color: white;">Delete All GPX Points</button>
                </form>

                <!-- Delete range -->
                <form method="POST"
                        onsubmit="return confirm('⚠️ Are you sure you want to delete GPX points in this time range?');">
                        <label for="start_time">Start Time:</label>
                        <input type="datetime-local" name="start_time" id="start_time" required>

                        <label for="end_time">End Time:</label>
                        <input type="datetime-local" name="end_time" id="end_time" required>

                        <input type="hidden" name="delete_range" value="1">
                        <button type="submit" style="background-color: orange;">Delete GPX Points in Range</button>
                </form>

                <!-- Logout -->
                <form method="POST">
                        <input type="hidden" name="logout" value="1">
                        <button type="submit" style="margin-top: 2em;">Log Out</button>
                </form>
        <?php endif; ?>
</body>

</html>