<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';
require_admin();

$pdo = db();

/* ---------------------------
   CREATE PLAN
---------------------------- */
if (isset($_POST['plan_create'])) {
  $pdo->prepare("INSERT INTO plans (name, price, duration_days, max_streams)
                 VALUES (?,?,?,?)")
      ->execute([
        trim($_POST['name']),
        (float)$_POST['price'],
        (int)$_POST['duration_days'],
        (int)$_POST['max_streams']
      ]);
  flash_set("Plan created", "success");
  header("Location: plans_subs.php"); exit;
}

/* ---------------------------
   UPDATE PLAN (EDIT)
---------------------------- */
if (isset($_POST['plan_update'])) {
  $plan_id = (int)$_POST['plan_id'];
  $pdo->prepare("UPDATE plans
                 SET name=?, price=?, duration_days=?, max_streams=?
                 WHERE id=?")
      ->execute([
        trim($_POST['name']),
        (float)$_POST['price'],
        (int)$_POST['duration_days'],
        (int)$_POST['max_streams'],
        $plan_id
      ]);

  flash_set("Plan updated", "success");
  header("Location: plans_subs.php"); exit;
}

/* ---------------------------
   DELETE PLAN
---------------------------- */
if (isset($_GET['plan_delete'])) {
  $plan_id = (int)$_GET['plan_delete'];
  $pdo->prepare("DELETE FROM plans WHERE id=?")->execute([$plan_id]);
  flash_set("Plan deleted", "success");
  header("Location: plans_subs.php"); exit;
}

/* ---------------------------
   ASSIGN SUBSCRIPTION
---------------------------- */
if (isset($_POST['sub_create'])) {
  $user_id = (int)$_POST['user_id'];
  $plan_id = (int)$_POST['plan_id'];

  $st = $pdo->prepare("SELECT * FROM plans WHERE id=?");
  $st->execute([$plan_id]);
  $plan = $st->fetch();

  if (!$plan) {
    flash_set("Plan not found", "error");
    header("Location: plans_subs.php"); exit;
  }

  $starts = new DateTime();
  $ends   = (new DateTime())->modify("+" . (int)$plan['duration_days'] . " days");

  $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, starts_at, ends_at, status)
                 VALUES (?,?,?,?, 'active')")
      ->execute([
        $user_id,
        $plan_id,
        $starts->format('Y-m-d H:i:s'),
        $ends->format('Y-m-d H:i:s')
      ]);

  flash_set("Subscription assigned", "success");
  header("Location: plans_subs.php"); exit;
}

/* ---------------------------
   LOAD DATA
---------------------------- */
$plans = $pdo->query("SELECT * FROM plans ORDER BY created_at DESC")->fetchAll();
$users = $pdo->query("SELECT id,username FROM users WHERE status='active'")->fetchAll();
$subs  = $pdo->query("
  SELECT s.*, u.username, p.name AS plan_name, p.max_streams
  FROM subscriptions s
  JOIN users u ON u.id=s.user_id
  JOIN plans p ON p.id=s.plan_id
  ORDER BY s.ends_at DESC
")->fetchAll();

$topbar = file_get_contents(__DIR__ . '/topbar.html');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Plans & Subscriptions</title>
  <link rel="stylesheet" href="panel.css">
</head>
<body>
<?= $topbar ?>

<div class="card">
  <h2>Create Plan</h2>
  <?php flash_show(); ?>

  <form method="post">
    <input type="hidden" name="plan_create" value="1">

    <div class="row">
      <div>
        <label>Name</label>
        <input name="name" required>
      </div>
      <div>
        <label>Price</label>
        <input name="price" type="number" step="0.01" value="0.00">
      </div>
    </div>

    <div class="row">
      <div>
        <label>Duration Days</label>
        <input name="duration_days" type="number" value="30">
      </div>
      <div>
        <label>Device Limit (max streams)</label>
        <input name="max_streams" type="number" value="1" min="1">
      </div>
    </div>

    <div style="margin-top:12px;">
      <button>Create Plan</button>
    </div>
  </form>
</div>

<br>

<div class="card">
  <h2>Existing Plans (edit device limit here)</h2>

  <table>
    <tr>
      <th>Name</th>
      <th>Price</th>
      <th>Days</th>
      <th>Device Limit</th>
      <th>Actions</th>
    </tr>

    <?php foreach($plans as $p): ?>
    <tr>
      <form method="post">
        <input type="hidden" name="plan_update" value="1">
        <input type="hidden" name="plan_id" value="<?=$p['id']?>">

        <td><input name="name" value="<?=e($p['name'])?>" required></td>
        <td><input name="price" type="number" step="0.01" value="<?=e($p['price'])?>"></td>
        <td><input name="duration_days" type="number" value="<?=e($p['duration_days'])?>" min="1"></td>
        <td>
          <input name="max_streams" type="number" value="<?=e($p['max_streams'])?>" min="1">
        </td>

        <td style="white-space:nowrap;">
          <button type="submit">Save</button>
          <a href="plans_subs.php?plan_delete=<?=$p['id']?>"
             onclick="return confirm('Delete this plan? (subs using it will break)')"
             style="margin-left:8px;color:#ff5571;">Delete</a>
        </td>
      </form>
    </tr>
    <?php endforeach; ?>

  </table>
</div>

<hr>

<div class="card">
  <h2>Assign Subscription</h2>
  <form method="post">
    <input type="hidden" name="sub_create" value="1">

    <div class="row">
      <div>
        <label>User</label>
        <select name="user_id">
          <?php foreach($users as $u): ?>
          <option value="<?=$u['id']?>"><?=e($u['username'])?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label>Plan</label>
        <select name="plan_id">
          <?php foreach($plans as $p): ?>
          <option value="<?=$p['id']?>"><?=e($p['name'])?> (<?=e($p['max_streams'])?> devices)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="row" style="margin-top:12px;">
      <div style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" id="unlimited" name="unlimited" value="1">
        <label for="unlimited" style="margin:0;"></label>
      </div>
      <div class="muted" style="font-size:12px;margin-top:4px;">
        If checked, the subscription never expires. Leave unchecked for normal plan duration (e.g., monthly).
      </div>
    </div>

    <div style="margin-top:12px;">
      <button>Assign</button>
    </div>
  </form>
</div>

<br>

<div class="card">
  <h2>Subscriptions</h2>
  <table>
    <tr>
      <th>User</th>
      <th>Plan</th>
      <th>Devices</th>
      <th>Starts</th>
      <th>Ends</th>
      <th>Status</th>
    </tr>
    <?php foreach($subs as $s): ?>
    <tr>
      <td><?=e($s['username'])?></td>
      <td><?=e($s['plan_name'])?></td>
      <td><?=e($s['max_streams'])?></td>
      <td><?=e($s['starts_at'])?></td>
      <td><?= $s['ends_at'] ? e($s['ends_at']) : 'Unlimited' ?></td>
      <td><?=e($s['status'])?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

</div><!-- container -->
</main>
</div><!-- app -->
</body>
</html>
