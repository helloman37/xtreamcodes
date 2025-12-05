<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
session_start();
$pdo=db();

if($_SERVER['REQUEST_METHOD']==='POST'){
  $username=trim($_POST['username'] ?? '');
  $password=$_POST['password'] ?? '';
  $st=$pdo->prepare("SELECT * FROM users WHERE username=?");
  $st->execute([$username]);
  $u=$st->fetch();
  if($u && password_verify($password,$u['password_hash'])){
    $_SESSION['store_user']=$u['id'];
    header("Location: dashboard.php"); exit;
  }
  $err="Invalid login";
}
?>
<!doctype html><html><head>
<meta charset="utf-8"><title>Store Login</title>
<link rel="stylesheet" href="store.css"></head><body>
<div class="wrap">
  <?php include __DIR__."/topbar.php"; ?>
<div class="card" style="max-width:420px;margin:0 auto;">
    <h3>Customer Login</h3>
    <?php if(!empty($err)): ?><p style="color:#fca5a5; font-weight:800;"><?=$err?></p><?php endif; ?>
    <form method="post">
      <label>Username</label>
      <input class="input" name="username" required>
      <label style="margin-top:10px;">Password</label>
      <input class="input" type="password" name="password" required>
      <button class="btn green" style="margin-top:14px;" type="submit">Login</button>
    </form>
  </div>
</div>
</body></html>
