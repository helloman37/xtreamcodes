<?php
/**
 * M3U Playlist String Search & Replace (Web + Download)
 * PHP 8 compatible
 *
 * Upload an M3U file, enter search/replace strings,
 * and download the modified playlist.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputFile  = $_FILES['m3u']['tmp_name'] ?? null;
    $search     = $_POST['search'] ?? '';
    $replace    = $_POST['replace'] ?? '';

    if (!$inputFile || !file_exists($inputFile)) {
        die("Error: No M3U file uploaded.");
    }

    $content = file_get_contents($inputFile);
    if ($content === false) {
        die("Error: Failed to read uploaded file.");
    }

    $updatedContent = str_replace($search, $replace, $content, $count);

    // Debug info (optional)
    // echo "Occurrences replaced: {$count}";

    // --- Send file as download ---
    header('Content-Type: audio/x-mpegurl');
    header('Content-Disposition: attachment; filename="updated.m3u"');
    header('Content-Length: ' . strlen($updatedContent));

    echo $updatedContent;
    exit;
}
?>

<!DOCTYPE html>
<html>
<head><title>M3U Editor</title></head>
<body>
    <h2>M3U Playlist Editor</h2>
    <form method="post" enctype="multipart/form-data">
        <label>Upload M3U file: <input type="file" name="m3u" required></label><br><br>
        <label>Search string: <input type="text" name="search" required></label><br><br>
        <label>Replace with: <input type="text" name="replace" required></label><br><br>
        <button type="submit">Process & Download</button>
    </form>
</body>
</html>
