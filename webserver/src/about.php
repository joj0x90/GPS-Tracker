<?php
$versionFile = __DIR__ . '/version.txt';
$version = '0.2';
if (is_file($versionFile)) {
    $versionFromFile = trim((string) file_get_contents($versionFile));
    if ($versionFromFile !== '') {
        $version = $versionFromFile;
    }
}

$commitHash = getenv('APP_COMMIT_SHA') ?: '';
$commitDisplay = $commitHash !== '' ? substr($commitHash, 0, 8) : 'n/a';
$buildTimestamp = getenv('APP_BUILD_TIMESTAMP') ?: '';
$buildDisplay = $buildTimestamp !== '' ? $buildTimestamp : 'n/a';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>About</title>
    <link rel="icon" type="image/x-icon" href="style/icons/favicon.ico">
    <link rel="stylesheet" href="style/main.css" />
</head>
<body class="admin-body">
    <div class="admin-page">
        <header class="admin-header">
            <a href="index.php" class="home-btn"><img src="style/icons/home.png" width="20" height="20"> Home</a>
            <h1 class="admin-title">About</h1>
        </header>

        <div class="admin-content">
            <div class="admin-form" style="max-width: 40rem; text-align: center;">
                <h2>GPS Tracker Viewer</h2>
                <p>This Website visualizes GPS tracks collected by the ESP32 tracker and makes them easy to explore in a web interface.</p>
                <p>
                    <a href="https://github.com/joj0x90/GPS-Tracker" target="_blank" rel="noopener noreferrer">
                        <img src="style/icons/github.png" alt="GitHub" width="20" height="20" style="vertical-align: middle; margin-right: 0.4rem;">
                        View the GitHub repository
                    </a>
                </p>
                <p style="margin-top: 1rem; font-size: 0.95rem; color: #4b5563;">
                    Version: <strong>v<?= htmlspecialchars($version) ?></strong><br>
                    Build Timestamp: <strong><?= htmlspecialchars($buildDisplay) ?></strong><br>
                    Commit: <strong><?= htmlspecialchars($commitDisplay) ?></strong>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
