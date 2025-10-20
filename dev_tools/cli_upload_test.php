<?php
// CLI upload test - creates a tiny PNG and simulates the upload handler by moving it into assets/uploads
if (php_sapi_name() !== 'cli') {
    echo "This script is CLI-only.\n";
    http_response_code(403);
    exit;
}

$uploadsDir = __DIR__ . '/../assets/uploads';
if (!is_dir($uploadsDir)) {
    if (!mkdir($uploadsDir, 0755, true)) {
        echo "Failed to create uploads dir at $uploadsDir\n";
        exit(1);
    }
}

$timestamp = time();
$filename = 'test_upload_' . $timestamp . '.png';
$target = $uploadsDir . '/' . $filename;

// A 1x1 transparent PNG base64
$pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';
$pngData = base64_decode($pngBase64);

if (file_put_contents($target, $pngData) === false) {
    echo "Failed to write file to $target\n";
    exit(1);
}

$filesize = filesize($target);
echo "Created test upload: " . basename($target) . " (" . $filesize . " bytes)\n";

// Optionally validate mimetype
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $target);
finfo_close($finfo);

echo "Detected mime-type: $mime\n";

// Basic validation: ensure file is a png and non-empty
if ($filesize > 0 && $mime === 'image/png') {
    echo "Upload simulation successful. File available at: $target\n";
    exit(0);
} else {
    echo "Upload simulation failed (invalid mime or empty file).\n";
    exit(1);
}
?>