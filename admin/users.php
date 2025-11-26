<?php
require_once __DIR__ . '/../config.php';
require_admin();

// Fetch plans and resellers for use in forms
$plans = $pdo->query('SELECT * FROM plans ORDER BY id ASC')->fetchAll();
$plansById = [];
foreach ($plans as $p) {
    $plansById[$p['id']] = $p['name'];
}

$resellers = $pdo->query('SELECT * FROM resellers ORDER BY id ASC')->fetchAll();
$resById = [];
foreach ($resellers as $r) {
    $resById[$r['id']] = $r['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $username   = trim($_POST['username'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $plan_id    = isset($_POST['plan_id']) && $_POST['plan_id'] !== '' ? (int)$_POST['plan_id'] : null;
    $reseller_id= isset($_POST['reseller_id']) && $_POST['reseller_id'] !== '' ? (int)$_POST['reseller_id'] : null;
    $status     = trim($_POST['status'] ?? 'active');
    $is_admin   = isset($_POST['is_admin']) ? 1 : 0;

    if ($username === '') {
        $_SESSION['flash_error'] = 'Username is required.';
        redirect('users.php');
    }

    if ($id > 0) {
        if ($password !== '') {
            $stmt = $pdo->prepare('UPDATE users SET username=?, password=?, email=?, plan_id=?, reseller_id=?, status=?, is_admin=? WHERE id=?');
            $stmt->execute([$username, $password, $email, $plan_id, $reseller_id, $status, $is_admin, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET username=?, email=?, plan_id=?, reseller_id=?, status=?, is_admin=? WHERE id=?');
            $stmt->execute([$username, $email, $plan_id, $reseller_id, $status, $is_admin, $id]);
        }
        $_SESSION['flash_success'] = 'User updated.';
    } else {
        if ($password === '') {
            $_SESSION['flash_error'] = 'Password is required for new users.';
            redirect('users.php');
        }
        $stmt = $pdo->prepare('INSERT INTO users (username, password, email, plan_id, reseller_id, status, is_admin, created_at) VALUES (?,?,?,?,?,?,?,NOW())');
        $stmt->execute([$username, $password, $email, $plan_id, $reseller_id, $status, $is_admin]);
        $_SESSION['flash_success'] = 'User created.';
    }

    redirect('users.php');
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 1) {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        $_SESSION['flash_success'] = 'User deleted.';
    } else {
        $_SESSION['flash_error'] = 'Refusing to delete default admin.';
    }
    redirect('users.php');
}

$editUser = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $editUser = $stmt->fetch();
}

$users = $pdo->query('SELECT * FROM users ORDER BY id DESC LIMIT 200')->fetchAll();

require_once __DIR__ . '/header.php';
?>

<h2 class="page-title">
    <span class="page-title-icon">👥</span>
    <span>Users</span>
</h2>

<h3><?php echo $editUser ? 'Edit User #' . (int)$editUser['id'] : 'Add User'; ?></h3>

<form method="post">
    <input type="hidden" name="id" value="<?php echo $editUser ? (int)$editUser['id'] : 0; ?>" />
    <table>
        <tr>
            <td style="width:150px;">Username</td>
            <td><input type="text" name="username" value="<?php echo h($editUser['username'] ?? ''); ?>" /></td>
        </tr>
        <tr>
            <td>Password</td>
            <td>
                <input type="text" name="password" value="" />
                <?php if ($editUser): ?>
                    <small>Leave blank to keep existing password.</small>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td>Email</td>
            <td><input type="email" name="email" value="<?php echo h($editUser['email'] ?? ''); ?>" /></td>
        </tr>
        <tr>
            <td>Plan</td>
            <td>
                <select name="plan_id">
                    <option value="">-- None --</option>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>" <?php echo (!empty($editUser['plan_id']) && (int)$editUser['plan_id'] === (int)$p['id']) ? 'selected' : ''; ?>>
                            <?php echo h($p['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td>Reseller</td>
            <td>
                <select name="reseller_id">
                    <option value="">-- None --</option>
                    <?php foreach ($resellers as $r): ?>
                        <option value="<?php echo (int)$r['id']; ?>" <?php echo (!empty($editUser['reseller_id']) && (int)$editUser['reseller_id'] === (int)$r['id']) ? 'selected' : ''; ?>>
                            <?php echo h($r['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td>Status</td>
            <td>
                <select name="status">
                    <?php
                    $statuses = ['active', 'disabled', 'expired'];
                    $currentStatus = $editUser['status'] ?? 'active';
                    foreach ($statuses as $st):
                    ?>
                        <option value="<?php echo h($st); ?>" <?php echo $currentStatus === $st ? 'selected' : ''; ?>>
                            <?php echo h(ucfirst($st)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td>Admin</td>
            <td><input type="checkbox" name="is_admin" <?php echo (!empty($editUser['is_admin'])) ? 'checked' : ''; ?> /></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" value="<?php echo $editUser ? 'Update User' : 'Create User'; ?>" /></td>
        </tr>
    </table>
</form>

<h3>Existing Users</h3>

<table>
    <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Email</th>
        <th>Admin</th>
        <th>Status</th>
        <th>Plan</th>
        <th>Reseller</th>
        <th>Created</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?php echo (int)$u['id']; ?></td>
            <td><?php echo h($u['username']); ?></td>
            <td><?php echo h($u['email']); ?></td>
            <td><?php echo !empty($u['is_admin']) ? 'Yes' : 'No'; ?></td>
            <td><?php echo h($u['status']); ?></td>
            <td><?php echo isset($u['plan_id']) && isset($plansById[$u['plan_id']]) ? h($plansById[$u['plan_id']]) : ''; ?></td>
            <td><?php echo isset($u['reseller_id']) && isset($resById[$u['reseller_id']]) ? h($resById[$u['reseller_id']]) : ''; ?></td>
            <td><?php echo h($u['created_at']); ?></td>
            <td>
                <a href="users.php?edit=<?php echo (int)$u['id']; ?>">Edit</a>
                <?php if ((int)$u['id'] !== 1): ?>
                    | <a href="users.php?delete=<?php echo (int)$u['id']; ?>" onclick="return confirm('Delete this user?');">Delete</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php require_once __DIR__ . '/footer.php'; ?>
