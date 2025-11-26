<?php
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'save') {
        $id      = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $notes   = trim($_POST['notes'] ?? '');

        if ($name === '') {
            $_SESSION['flash_error'] = 'Name is required.';
            redirect('resellers.php');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE resellers SET name=?, email=?, notes=? WHERE id=?');
            $stmt->execute([$name, $email, $notes, $id]);
            $_SESSION['flash_success'] = 'Reseller updated.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO resellers (name, email, notes, created_at) VALUES (?,?,?,NOW())');
            $stmt->execute([$name, $email, $notes]);
            $_SESSION['flash_success'] = 'Reseller created.';
        }
    } elseif ($action === 'credits') {
        $id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $delta  = (int)($_POST['delta'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        if ($id > 0 && $delta !== 0) {
            $stmt = $pdo->prepare('UPDATE resellers SET credits = credits + ? WHERE id=?');
            $stmt->execute([$delta, $id]);
            $stmt2 = $pdo->prepare('INSERT INTO reseller_credits (reseller_id, delta, reason, created_at) VALUES (?,?,?,NOW())');
            $stmt2->execute([$id, $delta, $reason]);
            $_SESSION['flash_success'] = 'Credits updated.';
        } else {
            $_SESSION['flash_error'] = 'Reseller and non-zero credit delta required.';
        }
    }
    redirect('resellers.php');
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM resellers WHERE id=?')->execute([$id]);
    $_SESSION['flash_success'] = 'Reseller deleted.';
    redirect('resellers.php');
}

$editReseller = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM resellers WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $editReseller = $stmt->fetch();
}

$resellers = $pdo->query('SELECT * FROM resellers ORDER BY id ASC')->fetchAll();

require_once __DIR__ . '/header.php';
?>

<h2 class="page-title">
    <span class="page-title-icon">🤝</span>
    <span>Resellers</span>
</h2>

<h3><?php echo $editReseller ? 'Edit Reseller #' . (int)$editReseller['id'] : 'Add Reseller'; ?></h3>

<form method="post">
    <input type="hidden" name="action" value="save" />
    <input type="hidden" name="id" value="<?php echo $editReseller ? (int)$editReseller['id'] : 0; ?>" />
    <table>
        <tr>
            <td style="width:150px;">Name</td>
            <td><input type="text" name="name" value="<?php echo h($editReseller['name'] ?? ''); ?>" /></td>
        </tr>
        <tr>
            <td>Email</td>
            <td><input type="email" name="email" value="<?php echo h($editReseller['email'] ?? ''); ?>" /></td>
        </tr>
        <tr>
            <td>Notes</td>
            <td><textarea name="notes" rows="3"><?php echo h($editReseller['notes'] ?? ''); ?></textarea></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" value="<?php echo $editReseller ? 'Update Reseller' : 'Create Reseller'; ?>" /></td>
        </tr>
    </table>
</form>

<h3>Adjust Credits</h3>
<form method="post">
    <input type="hidden" name="action" value="credits" />
    <table>
        <tr>
            <td style="width:150px;">Reseller</td>
            <td>
                <select name="id">
                    <option value="">-- Select --</option>
                    <?php foreach ($resellers as $r): ?>
                        <option value="<?php echo (int)$r['id']; ?>"><?php echo h($r['name']); ?> (Credits: <?php echo (int)$r['credits']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td>Delta (+/-)</td>
            <td><input type="number" name="delta" value="0" /></td>
        </tr>
        <tr>
            <td>Reason</td>
            <td><input type="text" name="reason" /></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" value="Update Credits" /></td>
        </tr>
    </table>
</form>

<h3>Existing Resellers</h3>

<table>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Credits</th>
        <th>Notes</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($resellers as $r): ?>
        <tr>
            <td><?php echo (int)$r['id']; ?></td>
            <td><?php echo h($r['name']); ?></td>
            <td><?php echo h($r['email']); ?></td>
            <td><?php echo (int)$r['credits']; ?></td>
            <td><?php echo nl2br(h($r['notes'])); ?></td>
            <td>
                <a href="resellers.php?edit=<?php echo (int)$r['id']; ?>">Edit</a> |
                <a href="resellers.php?delete=<?php echo (int)$r['id']; ?>" onclick="return confirm('Delete this reseller?');">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/footer.php'; ?>
