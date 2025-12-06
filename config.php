<?php
// config.php
// EDIT THESE to your real cPanel creds + domain.
// PayPal REST API (storefront)
if (!defined('PAYPAL_CLIENT_ID')) define('PAYPAL_CLIENT_ID', '');
if (!defined('PAYPAL_SECRET')) define('PAYPAL_SECRET', '');
if (!defined('PAYPAL_SANDBOX')) define('PAYPAL_SANDBOX', true);
// CashApp storefront (owner cashtag)
if (!defined('CASHAPP_CASHTAG')) define('CASHAPP_CASHTAG', '$');

return [
  'db' => [
    'host' => 'localhost',
    'name' => '',
    'user' => '',
    'pass' => '',
    'charset' => 'utf8mb4'
  ],

  'session_name' => 'iptv_admin_session',

  // your real domain root (NO trailing slash)
  'base_url' => 'http://',

  // CHANGE THIS to a long random string
  'secret_key' => 'h!}[.;RZP,4|Y(wNfdtb6fVx*.W[I[g2XKek8hu>BRNNr06JTlaNk=@YL,4~#f)I',

  // token expiry in seconds (1 hour default)
  'token_ttl' => 3600,

  // device/connection window in seconds
  'device_window' => 120,

  // anti-restream: max unique IPs in window
  'max_ip_changes' => 3,
  'max_ip_window'  => 600
];
