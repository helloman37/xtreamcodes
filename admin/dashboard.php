<?php
require_once __DIR__ . '/header.php';

// Basic counts
$channelsCount  = (int)$pdo->query('SELECT COUNT(*) AS c FROM channels')->fetch()['c'];
$usersCount     = (int)$pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
$plansCount     = (int)$pdo->query('SELECT COUNT(*) AS c FROM plans')->fetch()['c'];
$resellersCount = (int)$pdo->query('SELECT COUNT(*) AS c FROM resellers')->fetch()['c'];

// Stream status breakdown
$onlineCount = (int)$pdo->query("SELECT COUNT(*) AS c FROM channels WHERE status = 'online'")->fetch()['c'];
$offlineCount = (int)$pdo->query("SELECT COUNT(*) AS c FROM channels WHERE status LIKE 'http %' OR status LIKE 'error:%'")->fetch()['c'];
$uncheckedCount = $channelsCount - $onlineCount - $offlineCount;
if ($uncheckedCount < 0) {
    $uncheckedCount = 0;
}

$onlinePercent = $channelsCount > 0 ? round(($onlineCount / $channelsCount) * 100) : 0;

// Recent checks (last 10 by last_check)
$recentChecksStmt = $pdo->query("SELECT * FROM channels WHERE last_check IS NOT NULL ORDER BY last_check DESC LIMIT 10");
$recentChecks = $recentChecksStmt->fetchAll();
?>
<h1 class="page-title">
    <span class="page-title-icon">📊</span>
    <span>Dashboard</span>
</h1>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-main">
            <div class="stat-label">Total Channels</div>
            <div class="stat-value"><?php echo $channelsCount; ?></div>
        </div>
        <div class="stat-icon">📺</div>
    </div>
    <div class="stat-card">
        <div class="stat-main">
            <div class="stat-label">Online Streams</div>
            <div class="stat-value" style="color:#16a34a;"><?php echo $onlineCount; ?></div>
        </div>
        <div class="stat-icon">✅</div>
    </div>
    <div class="stat-card">
        <div class="stat-main">
            <div class="stat-label">Active Users</div>
            <div class="stat-value"><?php
                $activeUsers = (int)$pdo->query("SELECT COUNT(*) AS c FROM users WHERE status='active'")->fetch()['c'];
                echo $activeUsers;
            ?></div>
        </div>
        <div class="stat-icon">👥</div>
    </div>
    <div class="stat-card">
        <div class="stat-main">
            <div class="stat-label">Active Resellers</div>
            <div class="stat-value"><?php echo $resellersCount; ?></div>
        </div>
        <div class="stat-icon">🧑‍💼</div>
    </div>
</div>

<div class="panel-grid">
    <div class="panel">
        <div class="panel-header">
            <span class="panel-header-icon">📡</span>
            <span>Stream Status Overview</span>
        </div>
        <div class="panel-body">
            <table>
                <tr>
                    <th>Status</th>
                    <th>Count</th>
                    <th>Percent</th>
                </tr>
                <tr>
                    <td>Online</td>
                    <td style="color:#16a34a;"><?php echo $onlineCount; ?></td>
                    <td style="color:#16a34a;"><?php echo $onlinePercent; ?>%</td>
                </tr>
                <tr>
                    <td>Offline</td>
                    <td style="color:#dc2626;"><?php echo $offlineCount; ?></td>
                    <td><?php
                        $offlinePercent = $channelsCount > 0 ? round(($offlineCount / $channelsCount) * 100) : 0;
                        echo $offlinePercent . '%';
                    ?></td>
                </tr>
                <tr>
                    <td>Unchecked</td>
                    <td><?php echo $uncheckedCount; ?></td>
                    <td><?php
                        $uncheckedPercent = $channelsCount > 0 ? 100 - $onlinePercent - ($channelsCount > 0 ? round(($offlineCount / $channelsCount) * 100) : 0) : 0;
                        if ($uncheckedPercent < 0) { $uncheckedPercent = 0; }
                        echo $uncheckedPercent . '%';
                    ?></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <span class="panel-header-icon">ℹ️</span>
            <span>Quick Stats</span>
        </div>
        <div class="panel-body">
            <table>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Total Channels</td>
                    <td><?php echo $channelsCount; ?></td>
                </tr>
                <tr>
                    <td>Total Users</td>
                    <td><?php echo $usersCount; ?></td>
                </tr>
                <tr>
                    <td>Active Users</td>
                    <td><?php echo $activeUsers; ?></td>
                </tr>
                <tr>
                    <td>Total Resellers</td>
                    <td><?php echo $resellersCount; ?></td>
                </tr>
                <tr>
                    <td>Online Streams</td>
                    <td><?php echo $onlineCount; ?></td>
                </tr>
                <tr>
                    <td>Offline Streams</td>
                    <td><?php echo $offlineCount; ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <span class="panel-header-icon">⏱️</span>
        <span>Recent Stream Checks</span>
    </div>
    <div class="panel-body">
        <?php if (!$recentChecks): ?>
            <p>No stream checks performed yet.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Channel</th>
                    <th>Status</th>
                    <th>Last Check</th>
                </tr>
                <?php foreach ($recentChecks as $ch): ?>
                    <tr>
                        <td><?php echo (int)$ch['id']; ?></td>
                        <td><?php echo h($ch['name']); ?></td>
                        <td><?php echo h($ch['status']); ?></td>
                        <td><?php echo h($ch['last_check']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
