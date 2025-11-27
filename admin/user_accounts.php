<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';
require_admin();

$pdo = db();
$resellers = $pdo->query("SELECT id, username FROM resellers WHERE status='active' ORDER BY username")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = $_POST['id'] ?? null;
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $status = $_POST['status'] ?? 'active';
  $allow_adult = isset($_POST['allow_adult']) ? 1 : 0;
  $reseller_id = (int)($_POST['reseller_id'] ?? 0) ?: null;

  if ($id) {
    if ($password) {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $pdo->prepare("UPDATE users SET username=?, password_hash=?, status=?, allow_adult=?, reseller_id=? WHERE id=?")
          ->execute([$username,$hash,$status,$allow_adult, $reseller_id, $id]);
    } else {
      $pdo->prepare("UPDATE users SET username=?, status=?, allow_adult=?, reseller_id=? WHERE id=?")
          ->execute([$username,$status,$allow_adult, $reseller_id, $id]);
    }
    flash_set("User updated","success");
  } else {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (username,password_hash,status,allow_adult,reseller_id) VALUES (?,?,?,?,?)")
        ->execute([$username,$hash,$status,$allow_adult, $reseller_id]);
    flash_set("User created","success");
  }
  header("Location: user_accounts.php"); exit;
}

if (isset($_GET['delete'])) {
  $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$_GET['delete']]);
  flash_set("User deleted","success");
  header("Location: user_accounts.php"); exit;
}

$edit=null;
if (isset($_GET['edit'])) {
  $st=$pdo->prepare("SELECT * FROM users WHERE id=?");
  $st->execute([$_GET['edit']]);
  $edit=$st->fetch();
}

$users=$pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$topbar = file_get_contents(__DIR__ . '/topbar.html');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Users</title>
  <link rel="stylesheet" href="panel.css">
</head>
<body>
<?= $topbar ?>

<div class="card">
  <h2>Users</h2>
  <?php flash_show(); ?>

  <h3><?= $edit ? "Edit User" : "Add User" ?></h3>
  <form method="post">
    <?php if($edit): ?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif; ?>
    <label>Username</label>
    <input name="username" value="<?=e($edit['username'] ?? '')?>" required>
    <label>Password (leave blank to keep)</label>
    <input type="password" name="password" <?= $edit ? '' : 'required' ?>>
    <label>Status</label>
    <select name="status">
      <option value="active" <?=($edit['status']??'')==='active'?'selected':''?>>active</option>
      <option value="suspended" <?=($edit['status']??'')==='suspended'?'selected':''?>>suspended</option>
    </select>

    <label style="margin-top:10px;">
      <input type="checkbox" name="allow_adult" value="1" <?= !empty($edit["allow_adult"]) ? "checked" : "" ?>>
      Allow adult content for this user
    </label>

    <div style="margin-top:12px;">
      <button type="submit"><?= $edit ? "Update" : "Create" ?></button>
    </div>
  </form>
</div>

<br>

<div class="card">
  <table>
    <tr><th>ID</th><th>User</th><th>Status</th><th>Adult</th><th>Created</th><th>Actions</th></tr>
    <?php foreach($users as $u): ?>
    <tr>
      <td><?=$u['id']?></td>
      <td><?=e($u['username'])?></td>
      <td><?=e($u['status'])?></td><td><?= !empty($u['allow_adult']) ? 'yes' : 'no' ?></td>
      <td><?=e($u['created_at'])?></td>
      <td>
        <a href="user_accounts.php?edit=<?=$u['id']?>">Edit</a> |
        <a href="user_accounts.php?delete=<?=$u['id']?>" onclick="return confirm('Delete user?')">Delete</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

</div><!-- container -->
</main>
</div><!-- app -->
</body>
</html>
