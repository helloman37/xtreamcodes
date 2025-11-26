<?php
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id            = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name          = trim($_POST['name'] ?? '');
    $price         = (float)($_POST['price'] ?? 0);
    $duration_days = (int)($_POST['duration_days'] ?? 30);
    $description   = trim($_POST['description'] ?? '');

    if ($name === '') {
        $_SESSION['flash_error'] = 'Name is required.';
        redirect('plans.php');
    }

    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE plans SET name=?, price=?, duration_days=?, description=? WHERE id=?');
        $stmt->execute([$name, $price, $duration_days, $description, $id]);
        $_SESSION['flash_success'] = 'Plan updated.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO plans (name, price, duration_days, description, created_at) VALUES (?,?,?,?,NOW())');
        $stmt->execute([$name, $price, $duration_days, $description]);
        $_SESSION['flash_success'] = 'Plan created.';
    }

    redirect('plans.php');
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM plans WHERE id=?')->execute([$id]);
    $_SESSION['flash_success'] = 'Plan deleted.';
    redirect('plans.php');
}

$editPlan = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM plans WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $editPlan = $stmt->fetch();
}

$plans = $pdo->query('SELECT * FROM plans ORDER BY id ASC')->fetchAll();

require_once __DIR__ . '/header.php';
?>

<h2 class="page-title">
    <span class="page-title-icon">💳</span>
    <span>Plans / Subscriptions</span>
</h2>

<h3><?php echo $editPlan ? 'Edit Plan #' . (int)$editPlan['id'] : 'Add Plan'; ?></h3>

<form method="post">
    <input type="hidden" name="id" value="<?php echo $editPlan ? (int)$editPlan['id'] : 0; ?>" />
    <table>
        <tr>
            <td style="width:150px;">Name</td>
            <td><input type="text" name="name" value="<?php echo h($editPlan['name'] ?? ''); ?>" /></td>
        </tr>
        <tr>
            <td>Price</td>
            <td><input type="text" name="price" value="<?php echo h($editPlan['price'] ?? '0.00'); ?>" /></td>
        </tr>
        <tr>
            <td>Duration (days)</td>
            <td><input type="number" name="duration_days" value="<?php echo h($editPlan['duration_days'] ?? '30'); ?>" /></td>
        </tr>
        <tr>
            <td>Description</td>
            <td><textarea name="description" rows="3"><?php echo h($editPlan['description'] ?? ''); ?></textarea></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" value="<?php echo $editPlan ? 'Update Plan' : 'Create Plan'; ?>" /></td>
        </tr>
    </table>
</form>

<h3>Existing Plans</h3>

<table>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Price</th>
        <th>Duration (days)</th>
        <th>Description</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($plans as $p): ?>
        <tr>
            <td><?php echo (int)$p['id']; ?></td>
            <td><?php echo h($p['name']); ?></td>
            <td><?php echo h($p['price']); ?></td>
            <td><?php echo h($p['duration_days']); ?></td>
            <td><?php echo h($p['description']); ?></td>
            <td>
                <a href="plans.php?edit=<?php echo (int)$p['id']; ?>">Edit</a> |
                <a href="plans.php?delete=<?php echo (int)$p['id']; ?>" onclick="return confirm('Delete this plan?');">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/footer.php'; ?>
