<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';
require_admin();

$pdo = db();
$action = $_POST['action'] ?? '';

if ($action === '') {
  flash_set("No action selected", "error");
  header("Location: channels_manager.php");
  exit;
}

switch ($action) {
  case 'delete_channels':
    // Cannot TRUNCATE a parent table referenced by foreign keys (e.g. package_channels, stream_health).
    // Use DELETE which will honor ON DELETE CASCADE, then reset AUTO_INCREMENT.
    try {
      $pdo->beginTransaction();
      $pdo->exec("DELETE FROM channels");
      $pdo->exec("ALTER TABLE channels AUTO_INCREMENT = 1");
      $pdo->commit();
      flash_set("All channels deleted", "success");
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      flash_set("Delete failed: " . $e->getMessage(), "error");
    }
    break;

  case 'delete_categories':
    $pdo->exec("UPDATE channels SET group_title=NULL");
    flash_set("All categories cleared (group_title reset)", "success");
    break;

  case 'delete_series':
    try { $pdo->exec("TRUNCATE TABLE series_episodes"); } catch (Throwable $e) {}
    try { $pdo->exec("TRUNCATE TABLE series"); flash_set("All series deleted", "success"); }
    catch (Throwable $e) { flash_set("Series table not installed in this build", "error"); }
    break;

  case 'delete_movies':
    try {
      $pdo->exec("TRUNCATE TABLE movies");
      flash_set("All movies deleted", "success");
    } catch (Throwable $e) {
      flash_set("Movies table not installed in this build", "error");
    }
    try { $pdo->exec("TRUNCATE TABLE vod_categories"); } catch (Throwable $e) {}
    try { $pdo->exec("TRUNCATE TABLE series_categories"); } catch (Throwable $e) {}
    break;

  case 'delete_all':
    // For a full wipe, disable FK checks so TRUNCATE works regardless of constraint order.
    // Re-enable in a finally block so the session doesn't remain with FK checks off.
    try {
      $pdo->beginTransaction();
      $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
      foreach ([
        'package_channels',
        'package_movies',
        'package_series',
        'user_packages',
        'user_devices',
        'audit_logs',
        'stream_health',
        'stream_sessions',
        'epg_programs',
        'epg_sources',
        'series_episodes',
        'series',
        'series_categories',
        'movies',
        'vod_categories',
        'subscriptions',
        'users',
        'plans',
        'packages',
        'categories',
        'channels'
      ] as $t) {
        try { $pdo->exec("TRUNCATE TABLE `$t`"); } catch (Throwable $e) {}
      }
      $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
      $pdo->commit();
      flash_set("All data deleted", "success");
    } catch (Throwable $e) {
      try { $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); } catch (Throwable $ignore) {}
      if ($pdo->inTransaction()) $pdo->rollBack();
      flash_set("Delete failed: " . $e->getMessage(), "error");
    }
    break;

  default:
    flash_set("Unknown action", "error");
}

header("Location: channels_manager.php");
exit;
