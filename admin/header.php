<?php
require_once __DIR__ . '/../config.php';
require_admin();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>IPTV Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: #f4f6fb;
            color: #333;
            margin: 0;
        }
        a {
            color: inherit;
        }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 24px;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
        }
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .menu-toggle {
            width: 32px;
            height: 32px;
            border-radius: 999px;
            border: none;
            background: #eef1ff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: default;
        }
        .menu-toggle span {
            width: 16px;
            height: 2px;
            background: #4f46e5;
            display: block;
            position: relative;
        }
        .menu-toggle span::before,
        .menu-toggle span::after {
            content: "";
            position: absolute;
            left: 0;
            width: 16px;
            height: 2px;
            background: #4f46e5;
        }
        .menu-toggle span::before {
            top: -5px;
        }
        .menu-toggle span::after {
            top: 5px;
        }
        .topbar-title {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .topbar-title-icon {
            font-size: 22px;
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }
        .topbar-user {
            color: #6b7280;
        }
        .btn-logout {
            background: #f97373;
            border: none;
            color: #fff;
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-logout:hover {
            background: #ef4444;
        }
        .btn-logout-icon {
            font-size: 14px;
        }

        .layout {
            display: flex;
            min-height: calc(100vh - 56px);
        }

        .sidebar {
            width: 220px;
            background: #ffffff;
            padding: 16px 12px;
            box-shadow: 1px 0 4px rgba(15, 23, 42, 0.06);
        }
        .sidebar-nav-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #9ca3af;
            margin: 4px 8px 8px;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 14px;
            color: #374151;
            text-decoration: none;
            margin-bottom: 4px;
        }
        .sidebar-nav a:hover {
            background: #eef1ff;
            color: #4f46e5;
        }
        .sidebar-nav-icon {
            font-size: 16px;
        }

        .container {
            flex: 1;
            padding: 20px 24px 32px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 16px;
        }
        .page-title-icon {
            font-size: 26px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 14px 16px;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .stat-main {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .stat-label {
            font-size: 13px;
            color: #6b7280;
        }
        .stat-value {
            font-size: 22px;
            font-weight: 600;
        }
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: #eef1ff;
            color: #4f46e5;
        }

        .panel-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        @media (max-width: 900px) {
            .layout {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                display: flex;
                overflow-x: auto;
            }
            .sidebar-nav {
                display: flex;
                gap: 4px;
            }
            .sidebar-nav a {
                white-space: nowrap;
            }
            .panel-grid {
                grid-template-columns: minmax(0,1fr);
            }
        }

        .panel {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }
        .panel-header {
            padding: 10px 16px;
            background: linear-gradient(90deg, #4f46e5, #6366f1);
            color: #ffffff;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .panel-header-icon {
            font-size: 16px;
        }
        .panel-body {
            padding: 12px 16px 16px;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th, td {
            padding: 8px 6px;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6b7280;
            background: #f9fafb;
        }

        .flash {
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13px;
        }
        .flash-success {
            background: #dcfce7;
            color: #166534;
        }
        .flash-error {
            background: #fee2e2;
            color: #b91c1c;
        }

        input[type="text"],
        input[type="number"],
        input[type="email"],
        input[type="password"],
        textarea,
        select {
            width: 100%;
            padding: 6px 8px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: #ffffff;
            font-size: 13px;
        }
        input[type="submit"],
        button,
        .btn {
            background: #4f46e5;
            color: #ffffff;
            border: none;
            padding: 6px 12px;
            border-radius: 999px;
            cursor: pointer;
            font-size: 13px;
        }
        input[type="submit"]:hover,
        button:hover,
        .btn:hover {
            background: #4338ca;
        }
        .btn-danger {
            background: #ef4444;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
<div class="topbar">
    <div class="topbar-left">
        <button class="menu-toggle" type="button" aria-label="Menu">
            <span></span>
        </button>
        <div class="topbar-title">
            <span class="topbar-title-icon">⏱️</span>
            <span>Dashboard</span>
        </div>
    </div>
    <div class="topbar-right">
        <span class="topbar-user">👤 <?php echo h($_SESSION[$config['admin_username_key']] ?? 'admin'); ?></span>
        <a class="btn-logout" href="logout.php">
            <span class="btn-logout-icon">⏻</span>
            <span>Logout</span>
        </a>
    </div>
</div>

<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-nav-title">Navigation</div>
        <div class="sidebar-nav">
            <a href="dashboard.php"><span class="sidebar-nav-icon">📊</span><span>Dashboard</span></a>
            <a href="channels.php"><span class="sidebar-nav-icon">📺</span><span>Channels</span></a>
            <a href="import_m3u.php"><span class="sidebar-nav-icon">📥</span><span>Import M3U</span></a>
            <a href="stream_checker.php"><span class="sidebar-nav-icon">✅</span><span>Stream Checker</span></a>
            <a href="epg.php"><span class="sidebar-nav-icon">🕒</span><span>EPG</span></a>
            <a href="users.php"><span class="sidebar-nav-icon">👥</span><span>Users</span></a>
            <a href="plans.php"><span class="sidebar-nav-icon">💳</span><span>Plans/Subs</span></a>
            <a href="resellers.php"><span class="sidebar-nav-icon">🤝</span><span>Resellers</span></a>
        </div>
    </aside>
    <main class="container">
        <?php
        if (!empty($_SESSION['flash_success'])) {
            echo '<div class="flash flash-success">' . h($_SESSION['flash_success']) . '</div>';
            unset($_SESSION['flash_success']);
        }
        if (!empty($_SESSION['flash_error'])) {
            echo '<div class="flash flash-error">' . h($_SESSION['flash_error']) . '</div>';
            unset($_SESSION['flash_error']);
        }
        ?>
