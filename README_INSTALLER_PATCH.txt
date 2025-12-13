INSTALLER PATCH
- Copy /install (folder), /scripts/install_cli.php, and /install_guard.php into your panel root.
- In your earliest bootstrap (helpers.php or wherever every request passes), add:

require_once __DIR__ . '/install_guard.php';
if (!iptv_is_installed(__DIR__)) iptv_install_redirect(__DIR__);

- Then browse https://yourdomain.com/install/
