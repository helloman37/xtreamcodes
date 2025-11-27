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
    $pdo->exec("TRUNCATE TABLE channels");
    flash_set("All channels deleted", "success");
    break;

  case 'delete_categories':
    $pdo->exec("UPDATE channels SET group_title=NULL");
    flash_set("All categories cleared (group_title reset)", "success");
    break;

  case 'delete_series':
    try { $pdo->exec("TRUNCATE TABLE series"); flash_set("All series deleted", "success"); }
    catch (Throwable $e) { flash_set("Series table not installed in this build", "error"); }
    break;

  case 'delete_movies':
    try { $pdo->exec("TRUNCATE TABLE movies"); flash_set("All movies deleted", "success"); }
    catch (Throwable $e) { flash_set("Movies table not installed in this build", "error"); }
    break;

  case 'delete_all':
    try { $pdo->exec("TRUNCATE TABLE stream_sessions"); } catch (Throwable $e) {}
    try { $pdo->exec("TRUNCATE TABLE subscriptions"); } catch (Throwable $e) {}
    try { $pdo->exec("TRUNCATE TABLE users"); } catch (Throwable $e) {}
    try { $pdo->exec("TRUNCATE TABLE plans"); } catch (Throwable $e) {}
    try { $pdo->exec("TRUNCATE TABLE epg_sources"); } catch (Throwable $e) {}
    try { $pdo->exec("TRUNCATE TABLE channels"); } catch (Throwable $e) {}
    try { $pdo->exec("TRUNCATE TABLE series"); } catch (Throwable $e) {}
    try { $pdo->exec("TRUNCATE TABLE movies"); } catch (Throwable $e) {}
    flash_set("All data deleted", "success");
    break;

  default:
    flash_set("Unknown action", "error");
}

header("Location: channels_manager.php");
exit;
