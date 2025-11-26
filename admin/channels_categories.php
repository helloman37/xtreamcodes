<?php
// Category actions endpoint (no HTML output)
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $_SESSION['flash_error'] = 'Category name required.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO categories (name, created_at) VALUES (?, NOW())');
        $stmt->execute([$name]);
        $_SESSION['flash_success'] = 'Category added.';
    }
    redirect('channels.php');
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare('UPDATE channels SET category_id=NULL WHERE category_id=?')->execute([$id]);
    $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
    $_SESSION['flash_success'] = 'Category deleted.';
    redirect('channels.php');
}

redirect('channels.php');
