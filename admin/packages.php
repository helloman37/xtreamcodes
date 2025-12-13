<?php
require_once __DIR__ . '/../api_common.php';
require_once __DIR__ . '/../auth.php';
require_admin();

$pdo = db();
ensure_categories($pdo);

$package_id = (int)($_GET['package_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['create_package'])) {
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
      $pdo->prepare("INSERT IGNORE INTO packages (name) VALUES (?)")->execute([$name]);
      flash_set("Package created", "success");
    }
    header("Location: packages.php");
    exit;
  }

  if (isset($_POST['save_package_channels'])) {
    $pid = (int)($_POST['package_id'] ?? 0);
    $ids = $_POST['channel_ids'] ?? [];
    if (!is_array($ids)) $ids = [];
    $ids = array_values(array_unique(array_map('intval', $ids)));

    $pdo->prepare("DELETE FROM package_channels WHERE package_id=?")->execute([$pid]);
    if ($ids) {
      $ins = $pdo->prepare("INSERT INTO package_channels (package_id, channel_id) VALUES (?,?)");
      foreach ($ids as $cid) $ins->execute([$pid, $cid]);
    }
    flash_set("Package channels saved", "success");
    header("Location: packages.php?package_id=".$pid);
    exit;
  }

  if (isset($_POST['assign_user_packages'])) {
    $uid = (int)($_POST['user_id'] ?? 0);
    $pids = $_POST['package_ids'] ?? [];
    if (!is_array($pids)) $pids = [];
    $pids = array_values(array_unique(array_map('intval', $pids)));

    $pdo->prepare("DELETE FROM user_packages WHERE user_id=?")->execute([$uid]);
    if ($pids) {
      $ins = $pdo->prepare("INSERT INTO user_packages (user_id, package_id) VALUES (?,?)");
      foreach ($pids as $pid) $ins->execute([$uid, $pid]);
    }
    flash_set("User packages saved", "success");
    header("Location: packages.php?package_id=".$package_id);
    exit;
  }
}

$packages = $pdo->query("
  SELECT p.*, 
    (SELECT COUNT(*) FROM package_channels pc WHERE pc.package_id=p.id) AS chan_count,
    (SELECT COUNT(*) FROM user_packages up WHERE up.package_id=p.id) AS user_count
  FROM packages p
  ORDER BY p.name
")->fetchAll();

$selected = null;
$selected_ids = [];
if ($package_id > 0) {
  $st = $pdo->prepare("SELECT * FROM packages WHERE id=?");
  $st->execute([$package_id]);
  $selected = $st->fetch();

  if ($selected) {
    $st = $pdo->prepare("SELECT channel_id FROM package_channels WHERE package_id=?");
    $st->execute([$package_id]);
    $selected_ids = array_map(fn($r)=>(int)$r['channel_id'], $st->fetchAll());
  }
}

$channels = $pdo->query("SELECT id,name,IFNULL(group_title,'Uncategorized') AS grp, IFNULL(is_adult,0) AS is_adult FROM channels ORDER BY grp,name")->fetchAll();
$users = $pdo->query("SELECT id,username,status FROM users ORDER BY username")->fetchAll();

$topbar = file_get_contents(__DIR__ . '/topbar.html');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Packages</title>
  <link rel="stylesheet" href="panel.css">
</head>
<body>
<?= $topbar ?>

<div class="card">
  <h2>Packages (Bouquets)</h2>
  <?php flash_show(); ?>

  <form method="post" style="display:flex;gap:10px;align-items:flex-end;">
    <input type="hidden" name="create_package" value="1">
    <div style="flex:1;">
      <label>New package name</label>
      <input name="name" placeholder="USA / Sports / Adult..." required>
    </div>
    <button>Create</button>
  </form>
</div>

<br>

<div class="card">
  <table>
    <tr><th>Package</th><th>Channels</th><th>Users</th><th></th></tr>
    <?php foreach($packages as $p): ?>
      <tr>
        <td><?=e($p['name'])?></td>
        <td><?= (int)$p['chan_count'] ?></td>
        <td><?= (int)$p['user_count'] ?></td>
        <td><a class="btn gray" href="packages.php?package_id=<?=$p['id']?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if($selected): ?>
<br>
<div class="card">
  <h2>Edit Package: <?=e($selected['name'])?></h2>
  <p class="muted">Check channels that belong to this package. Users with NO packages assigned can see ALL channels (default open). As soon as you assign packages to a user, they only see channels in those packages.</p>

  <form method="post">
    <input type="hidden" name="save_package_channels" value="1">
    <input type="hidden" name="package_id" value="<?=$selected['id']?>">

    <div style="max-height:520px;overflow:auto;border:1px solid #1f2937;border-radius:12px;padding:10px;">
      <?php foreach($channels as $c): ?>
        <label style="display:flex;gap:10px;align-items:center;padding:6px;border-bottom:1px solid #0b1220;">
          <input type="checkbox" name="channel_ids[]" value="<?=$c['id']?>" <?= in_array((int)$c['id'],$selected_ids,true) ? 'checked' : '' ?>>
          <span class="code" style="min-width:50px;opacity:.8;">#<?=$c['id']?></span>
          <span style="flex:1;"><?=e($c['name'])?></span>
          <span class="pill <?= $c['is_adult'] ? 'bad':'good' ?>"><?= $c['is_adult'] ? 'ADULT':'OK' ?></span>
          <span class="pill"><?=e($c['grp'])?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <div style="margin-top:12px;">
      <button>Save Channels</button>
    </div>
  </form>
</div>

<br>
<div class="card">
  <h2>Assign Packages to Users</h2>
  <form method="post">
    <input type="hidden" name="assign_user_packages" value="1">
    <div class="row">
      <div>
        <label>User</label>
        <select name="user_id" required>
          <?php foreach($users as $u): ?>
            <option value="<?=$u['id']?>"><?=e($u['username'])?> (<?=$u['status']?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:2;">
        <label>Packages (multi-select)</label>
        <select name="package_ids[]" multiple size="6" style="width:100%;">
          <?php foreach($packages as $p): ?>
            <option value="<?=$p['id']?>"><?=e($p['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:0;">
        <label style="visibility:hidden">Save</label>
        <button>Save</button>
      </div>
    </div>
    <p class="muted" style="margin-top:10px;">Tip: to give a user full access, leave them with NO packages assigned (default). To restrict, assign 1+ packages.</p>
  </form>
</div>
<?php endif; ?>

</div><!-- container -->
</main>
</div><!-- app -->
</body>
</html>
