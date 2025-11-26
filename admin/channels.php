<?php
// Process channel actions before output (to avoid header already sent warnings)
require_once __DIR__ . '/../config.php';
require_admin();

// Handle add/update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name        = trim($_POST['name'] ?? '');
    $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $stream_url  = trim($_POST['stream_url'] ?? '');
    $logo_url    = trim($_POST['logo_url'] ?? '');
    $epg_id      = trim($_POST['epg_id'] ?? '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '' || $stream_url === '') {
        $_SESSION['flash_error'] = 'Name and stream URL are required.';
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE channels SET name=?, category_id=?, stream_url=?, logo_url=?, epg_id=?, is_active=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$name, $category_id, $stream_url, $logo_url, $epg_id, $is_active, $id]);
            $_SESSION['flash_success'] = 'Channel updated.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO channels (name, category_id, stream_url, logo_url, epg_id, is_active, created_at) VALUES (?,?,?,?,?,?,NOW())');
            $stmt->execute([$name, $category_id, $stream_url, $logo_url, $epg_id, $is_active]);
            $_SESSION['flash_success'] = 'Channel added.';
        }
    }
    redirect('channels.php');
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM channels WHERE id=?')->execute([$id]);
    $_SESSION['flash_success'] = 'Channel deleted.';
    redirect('channels.php');
}

// Fetch categories
$categories = $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
$categoriesById = [];
foreach ($categories as $cat) {
    $categoriesById[$cat['id']] = $cat['name'];
}

// Fetch channels
$channels = $pdo->query('SELECT * FROM channels ORDER BY id DESC')->fetchAll();

// Channel for editing
$editChannel = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM channels WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $editChannel = $stmt->fetch();
}

require_once __DIR__ . '/header.php';
?>

<h2 class="page-title">
    <span class="page-title-icon">📺</span>
    <span>Channels</span>
</h2>

<h3><?php echo $editChannel ? 'Edit Channel #' . (int)$editChannel['id'] : 'Add Channel'; ?></h3>

<form method="post">
    <input type="hidden" name="id" value="<?php echo $editChannel ? (int)$editChannel['id'] : 0; ?>" />
    <table>
        <tr>
            <td style="width:150px;">Name</td>
            <td><input type="text" name="name" value="<?php echo h($editChannel['name'] ?? ''); ?>" /></td>
        </tr>
        <tr>
            <td>Category</td>
            <td>
                <select name="category_id">
                    <option value="">-- None --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo (int)$cat['id']; ?>" <?php echo (!empty($editChannel['category_id']) && (int)$editChannel['category_id'] === (int)$cat['id']) ? 'selected' : ''; ?>>
                            <?php echo h($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td>Stream URL</td>
            <td><input type="text" name="stream_url" value="<?php echo h($editChannel['stream_url'] ?? ''); ?>" /></td>
        </tr>
        <tr>
            <td>Logo URL</td>
            <td><input type="text" name="logo_url" value="<?php echo h($editChannel['logo_url'] ?? ''); ?>" /></td>
        </tr>
        <tr>
            <td>EPG ID</td>
            <td><input type="text" name="epg_id" value="<?php echo h($editChannel['epg_id'] ?? ''); ?>" /></td>
        </tr>
        <tr>
            <td>Active</td>
            <td><input type="checkbox" name="is_active" <?php echo (!isset($editChannel) || !empty($editChannel['is_active'])) ? 'checked' : ''; ?> /></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" value="<?php echo $editChannel ? 'Update Channel' : 'Add Channel'; ?>" /></td>
        </tr>
    </table>
</form>

<h3>Categories</h3>
<form method="post" action="channels_categories.php">
    <table>
        <tr>
            <td style="width:150px;">New Category Name</td>
            <td><input type="text" name="name" /></td>
            <td><input type="submit" value="Add Category" /></td>
        </tr>
    </table>
</form>

<table>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Stream URL</th>
        <th>Category</th>
        <th>Logo</th>
        <th>EPG ID</th>
        <th>Status</th>
        <th>Last Check</th>
        <th>Active</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($channels as $ch): ?>
        <tr>
            <td><?php echo (int)$ch['id']; ?></td>
            <td><?php echo h($ch['name']); ?></td>
            <td><?php echo h($ch['stream_url']); ?></td>
            <td><?php echo isset($ch['category_id']) && isset($categoriesById[$ch['category_id']]) ? h($categoriesById[$ch['category_id']]) : ''; ?></td>
            <td><?php echo h($ch['logo_url']); ?></td>
            <td><?php echo h($ch['epg_id']); ?></td>
            <td><?php echo h($ch['status']); ?></td>
            <td><?php echo h($ch['last_check']); ?></td>
            <td><?php echo !empty($ch['is_active']) ? 'Yes' : 'No'; ?></td>
            <td>
                <a href="channels.php?edit=<?php echo (int)$ch['id']; ?>">Edit</a> |
                <a href="channels.php?delete=<?php echo (int)$ch['id']; ?>" onclick="return confirm('Delete this channel?');">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/footer.php'; ?>
