<?php
/**
 * M3U Playlist Bulk Editor with Preview (Simplified)
 * PHP 8 compatible
 *
 * Upload an M3U file, preview in a textarea,
 * enter up to 3 find/replace pairs,
 * see changes live, confirm, then download.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $content = $_POST['content'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($confirm !== 'yes') {
        die("Error: You must confirm changes by checking the box before download.");
    }

    // --- Send file as download ---
    header('Content-Type: audio/x-mpegurl');
    header('Content-Disposition: attachment; filename="updated.m3u"');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['m3u'])) {
    $inputFile = $_FILES['m3u']['tmp_name'] ?? null;
    if (!$inputFile || !file_exists($inputFile)) {
        die("Error: No M3U file uploaded.");
    }

    $content = file_get_contents($inputFile);
    if ($content === false) {
        die("Error: Failed to read uploaded file.");
    }

    // --- Show preview box with fixed bulk replace UI ---
    ?>
    <h2>Preview & Bulk Edit Playlist</h2>
    <form method="post" id="editorForm">
        <textarea id="preview" name="content" rows="20" cols="100"
                  style="width:100%;background:#f4f4f4;padding:10px;border:1px solid #ccc;"><?php
            echo htmlspecialchars($content);
        ?></textarea><br><br>

        <h3>Bulk Find & Replace (up to 3 pairs)</h3>
        <div>
            Find: <input type="text" name="find[]" />
            Replace: <input type="text" name="replace[]" />
        </div>
        <div>
            Find: <input type="text" name="find[]" />
            Replace: <input type="text" name="replace[]" />
        </div>
        <div>
            Find: <input type="text" name="find[]" />
            Replace: <input type="text" name="replace[]" />
        </div>

        <br>
        <label><input type="checkbox" name="confirm" value="yes"> Confirm changes</label><br><br>
        <button type="submit">Apply & Download</button>
    </form>

    <script>
    // Real-time replacement preview
    const form = document.getElementById('editorForm');
    const preview = document.getElementById('preview');
    const original = preview.value;

    form.addEventListener('input', function() {
        let text = original;
        const finds = document.getElementsByName('find[]');
        const replaces = document.getElementsByName('replace[]');
        for (let i = 0; i < finds.length; i++) {
            const f = finds[i].value;
            const r = replaces[i].value;
            if (f !== '') {
                const regex = new RegExp(f, 'g');
                text = text.replace(regex, r);
            }
        }
        preview.value = text;
    });
    </script>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html>
<head><title>M3U Bulk Editor</title></head>
<body>
    <h2>Upload M3U Playlist</h2>
    <form method="post" enctype="multipart/form-data">
        <label>Upload M3U file: <input type="file" name="m3u" required></label><br><br>
        <button type="submit">Preview</button>
    </form>
</body>
</html>
