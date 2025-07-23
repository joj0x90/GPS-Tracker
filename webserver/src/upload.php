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
        <html>

        <head>
                <title>Upload GPX File</title>
        </head>

        <body>
                <h1>Upload GPX File</h1>
                <form method="post" enctype="multipart/form-data">
                        <input type="file" name="gpx_file" accept=".gpx" required>
                        <button type="submit">Upload</button>
                </form>
        </body>

        </html>
        <?php
}
