<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';
require_reseller();

$pdo = db();
$reseller_id = (int)$_SESSION['reseller_id'];

$resellerStmt = $pdo->prepare("SELECT * FROM resellers WHERE id=? AND status='active' LIMIT 1");
$resellerStmt->execute([$reseller_id]);
$reseller = $resellerStmt->fetch(PDO::FETCH_ASSOC);
if (!$reseller) {
  flash_set("Reseller account not found or suspended.", "error");
  header("Location: reseller_signin.php"); exit;
}

$plans = $pdo->query("SELECT * FROM plans ORDER BY price ASC")->fetchAll(PDO::FETCH_ASSOC);

/* Create user by reseller (cost: 1 credit per user) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
  $username = trim($_POST['username'] ?? '');
  $password = (string)($_POST['password'] ?? '');
  $plan_id  = (int)($_POST['plan_id'] ?? 0);
  $allow_adult = !empty($_POST['allow_adult']) ? 1 : 0;
  $unlimited = !empty($_POST['unlimited']) ? 1 : 0;

  if ($username === '' || $password === '' || $plan_id <= 0) {
    flash_set("Please fill username, password, and plan.", "error");
    header("Location: reseller_dashboard.php"); exit;
  }

  if ((int)$reseller['credits'] <= 0) {
    flash_set("Not enough credits to create a user.", "error");
    header("Location: reseller_dashboard.php"); exit;
  }

  // get plan
  $planStmt = $pdo->prepare("SELECT * FROM plans WHERE id=? LIMIT 1");
  $planStmt->execute([$plan_id]);
  $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
  if (!$plan) {
    flash_set("Invalid plan selected.", "error");
    header("Location: reseller_dashboard.php"); exit;
  }

  // compute ends_at
  $starts_at = date("Y-m-d H:i:s");
  $ends_at = null;
  $duration_days = (int)($plan['duration_days'] ?? 0);
  if (!$unlimited && $duration_days > 0) {
    $ends_at = date("Y-m-d H:i:s", strtotime("+{$duration_days} days"));
  }

  try {
    $pdo->beginTransaction();

    // ensure username unique
    $chk = $pdo->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
    $chk->execute([$username]);
    if ($chk->fetch()) {
      throw new Exception("Username already exists.");
    }

    // insert user tied to reseller
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $uStmt = $pdo->prepare("INSERT INTO users (username, password_hash, status, allow_adult, reseller_id) VALUES (?,?,?,?,?)");
    $uStmt->execute([$username, $hash, 'active', $allow_adult, $reseller_id]);
    $user_id = (int)$pdo->lastInsertId();

    // insert subscription
    $sStmt = $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, starts_at, ends_at, status) VALUES (?,?,?,?,?)");
    $sStmt->execute([$user_id, $plan_id, $starts_at, $ends_at, 'active']);

    // deduct credit
    $cStmt = $pdo->prepare("UPDATE resellers SET credits = credits - 1 WHERE id=? AND credits > 0");
    $cStmt->execute([$reseller_id]);
    if ($cStmt->rowCount() !== 1) {
      throw new Exception("Credit deduction failed.");
    }

    $pdo->commit();
    flash_set("User created. 1 credit used.", "success");
  } catch (Exception $e) {
    $pdo->rollBack();
    flash_set("Create failed: ".$e->getMessage(), "error");
  }

  header("Location: reseller_dashboard.php"); exit;
}

// list reseller's recent users
$my_users = $pdo->prepare(
  "SELECT u.id, u.username, u.status, u.allow_adult, s.ends_at, p.name AS plan_name
   FROM users u
   LEFT JOIN subscriptions s ON s.user_id=u.id AND s.status='active'
   LEFT JOIN plans p ON p.id=s.plan_id
   WHERE u.reseller_id=?
   ORDER BY u.id DESC
   LIMIT 50"
);
$my_users->execute([$reseller_id]);
$my_users = $my_users->fetchAll(PDO::FETCH_ASSOC);

$topbar = file_get_contents(__DIR__ . '/reseller_topbar.html');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Reseller Dashboard</title>
  <link rel="stylesheet" href="panel.css">
</head>
<body>
<?= $topbar ?>
<h1 style="margin-top:10px;">Welcome, <?= e($_SESSION['reseller_username'] ?? 'Reseller') ?></h1>
<?php flash_show(); ?>

<div class="card" style="margin-top:15px;">
  <h3>Credits: <?= (int)$reseller['credits'] ?></h3>
</div>

<div class="card" style="margin-top:15px;">
  <h3>Create New User (cost: 1 credit)</h3>
  <form method="post">
    <input type="hidden" name="create_user" value="1">
    <div class="row">
      <label>Username</label>
      <input name="username" required>
    </div>
    <div class="row">
      <label>Password</label>
      <input name="password" type="text" required>
    </div>
    <div class="row">
      <label>Plan</label>
      <select name="plan_id" required>
        <option value="">--select--</option>
        <?php foreach($plans as $pl): ?>
          <option value="<?= (int)$pl['id'] ?>">
            <?= e($pl['name']) ?> ($<?= e($pl['price']) ?> / <?= (int)$pl['duration_days'] ?>d)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="row">
    </div>
    <div class="row">
      <label><input type="checkbox" name="allow_adult" value="1"> Allow Adult Content</label>
    </div>
    <button class="btn" type="submit">Create User</button>
  </form>
</div>

<div class="card" style="margin-top:15px;">
  <h3>My Recent My Users</h3>
  <table class="table">
    <thead><tr>
      <th>ID</th><th>Username</th><th>Plan</th><th>Expires</th><th>Status</th><th>Adult</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach($my_users as $u): ?>
      <tr>
        <td><?= (int)$u['id'] ?></td>
        <td><?= e($u['username']) ?></td>
        <td><?= e($u['plan_name'] ?? '-') ?></td>
        <td><?= $u['ends_at'] ? e($u['ends_at']) : 'Unlimited' ?></td>
        <td><?= e($u['status']) ?></td>
        <td><?= (int)$u['allow_adult'] ? 'Yes' : 'No' ?></td>
        <td><a class="btn btn-small" href="reseller_users.php?edit=<?= (int)$u['id'] ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

</div><!-- container -->
</main>
</div><!-- app -->
</body></html>