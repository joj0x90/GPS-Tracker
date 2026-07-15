<?php
// Check if form is submitted and file uploaded
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['gpx_file'])) {
        $file = $_FILES['gpx_file'];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
                die("Upload failed with error code: " . $file['error']);
        }

        // Check file extension
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'gpx') {
                die("Only GPX files are allowed.");
        }

        // Save the uploaded file to a temporary location
        $uploadPath = 'uploads/';
        if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0777, true);
        }

        $targetFile = $uploadPath . uniqid('track_', true) . '.gpx';
        if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
                die("Failed to move uploaded file.");
        }

        // Call the parser
        include 'parse_gpx.php';
        parseGPX($targetFile);

        echo "File uploaded and parsed successfully. <a href='index.php'>View Map</a>";
} else {
        // Display upload form
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
                <meta charset="UTF-8" />
                <meta name="viewport" content="width=device-width, initial-scale=1" />
                <title>Upload GPX File</title>
                <link rel="icon" type="image/x-icon" href="style/icons/favicon.ico">
                <link rel="stylesheet" href="style/main.css" />
                <link rel="stylesheet" href="style/mobile.css" />
        </head>
        <body class="admin-body">
                <div class="admin-page">
                        <header class="admin-header">
                                <a href="index.php" class="home-btn"><img src="style/icons/home.png" width="20" height="20"> Home</a>
                                <h1 class="admin-title">Upload GPX File</h1>
                                <div class="admin-header-spacer"></div>
                        </header>
                        <div class="admin-content">
                                <form method="post" class="admin-form" enctype="multipart/form-data">
                                        <input type="file" name="gpx_file" accept=".gpx" required>
                                        <button type="submit"><img src="style/icons/upload.png" width="20" height="20"> Upload</button>
                                </form>
                        </div>
                </div>
        </body>

        </html>
        <?php
}
