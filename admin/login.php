<?php
require_once __DIR__ . '/../config.php';

if (is_admin_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_admin = 1 LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && $user['password'] === $password) {
            $_SESSION[$config['admin_session_key']] = true;
            $_SESSION[$config['admin_username_key']] = $user['username'];
            redirect('dashboard.php');
        } else {
            $error = 'Invalid credentials.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #111;
            color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-box {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 4px;
            width: 320px;
            box-shadow: 0 0 10px rgba(0,0,0,0.7);
        }
        h1 {
            margin-top: 0;
            font-size: 20px;
            text-align: center;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 6px;
            margin-bottom: 10px;
            border-radius: 3px;
            border: 1px solid #555;
            background: #000;
            color: #f5f5f5;
        }
        input[type="submit"] {
            width: 100%;
            padding: 8px;
            border-radius: 3px;
            border: none;
            background: #0b84ff;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
        }
        input[type="submit"]:hover {
            background: #0a6ad2;
        }
        .error {
            background: #922b21;
            padding: 8px;
            border-radius: 3px;
            margin-bottom: 10px;
            font-size: 13px;
        }
        .hint {
            margin-top: 10px;
            font-size: 12px;
            color: #aaa;
        }
    </style>
</head>
<body>
<div class="login-box">
    <h1>Admin Login</h1>
    <?php if ($error): ?>
        <div class="error"><?php echo h($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <input type="text" name="username" placeholder="Username" autofocus />
        <input type="password" name="password" placeholder="Password" />
        <input type="submit" value="Login" />
    </form>
    <div class="hint">
        Default: admin / admin123 (change in DB).
    </div>
</div>
</body>
</html>
