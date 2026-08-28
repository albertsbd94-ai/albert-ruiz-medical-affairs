<?php
/**
 * api/dmsl-common.php — shared storage, email and purchase-code helpers
 * used by both dmsl-auth.php (accounts) and stripe-webhook.php (payments).
 * Kept in one place so the two files can never disagree about where the
 * database lives or how a purchase code is generated/normalized.
 */

// ---------------------------------------------------------------------
// 1. Storage — outside public_html, with an in-folder fallback (denied
//    from the web by datos/.htaccess) if the outside path isn't writable.
// ---------------------------------------------------------------------
function dmsl_db_path() {
  $fuera = dirname(__DIR__, 2) . '/dmsl_data'; // api/ -> public_html -> domain root
  if (is_dir($fuera) || @mkdir($fuera, 0750, true)) {
    if (is_writable($fuera)) return $fuera . '/usuarios.json';
  }
  $dentro = __DIR__ . '/../datos';
  @mkdir($dentro, 0750, true);
  return $dentro . '/usuarios.json';
}

function dmsl_empty_db() {
  return [
    'usuarios' => [],
    'sesiones' => [],
    'codigos' => [],
    'codigos_por_sesion' => [],
    'soporte' => [],
    'sugerencias' => [],
    'admin_sesiones' => [],
  ];
}

function dmsl_load_db() {
  $path = dmsl_db_path();
  if (!is_file($path)) return dmsl_empty_db();
  $fh = @fopen($path, 'r');
  if (!$fh) return dmsl_empty_db();
  flock($fh, LOCK_SH);
  $raw = stream_get_contents($fh);
  flock($fh, LOCK_UN);
  fclose($fh);
  $data = json_decode($raw, true);
  if (!is_array($data)) $data = [];
  foreach (dmsl_empty_db() as $key => $default) {
    if (!isset($data[$key])) $data[$key] = $default;
  }
  return $data;
}

function dmsl_save_db($data) {
  $path = dmsl_db_path();
  $fh = @fopen($path, 'c+');
  if (!$fh) return false;
  flock($fh, LOCK_EX);
  ftruncate($fh, 0);
  rewind($fh);
  fwrite($fh, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  fflush($fh);
  flock($fh, LOCK_UN);
  fclose($fh);
  return true;
}

// ---------------------------------------------------------------------
// 2. Brevo key — pasted once via setup-email.php, never in chat, never
//    shipped to the browser.
// ---------------------------------------------------------------------
function brevo_key() {
  $k = getenv('BREVO_API_KEY');
  if (!$k && is_file(dirname(__DIR__, 2) . '/brevo_api_key.php')) $k = @include dirname(__DIR__, 2) . '/brevo_api_key.php';
  if (!$k && is_file(__DIR__ . '/../datos/brevo_config.php')) $k = @include __DIR__ . '/../datos/brevo_config.php';
  $k = is_string($k) ? trim($k) : '';
  return $k;
}

// Must match a sender verified in Brevo (Senders, Domains & Dedicated IPs) —
// otherwise Brevo rejects the send. Confirmed verified on Albert's account.
const DMSL_SENDER_EMAIL = 'contacto.dmsl@albertruiz.com';
const DMSL_SENDER_NAME  = 'Digital Medical Science Liaison (DMSL)';

function dmsl_send_email($to_email, $to_name, $subject, $html, $replyTo = null) {
  $key = brevo_key();
  if ($key === '') return ['ok' => false, 'error' => 'email_not_configured'];
  $payload = [
    'sender' => ['name' => DMSL_SENDER_NAME, 'email' => DMSL_SENDER_EMAIL],
    'to' => [['email' => $to_email, 'name' => $to_name ?: $to_email]],
    'subject' => $subject,
    'htmlContent' => $html,
  ];
  if ($replyTo && is_valid_email($replyTo)) {
    $payload['replyTo'] = ['email' => $replyTo];
  }
  $ch = curl_init('https://api.brevo.com/v3/smtp/email');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
      'accept: application/json',
      'content-type: application/json',
      'api-key: ' . $key,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
  ]);
  $resp = curl_exec($ch);
  $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  if ($resp === false) return ['ok' => false, 'error' => 'network: ' . $err];
  if ($code < 200 || $code >= 300) return ['ok' => false, 'error' => 'brevo_http_' . $code, 'body' => $resp];
  return ['ok' => true];
}

// ---------------------------------------------------------------------
// 3. Small generic helpers
// ---------------------------------------------------------------------
function random_token($bytes = 32) { return bin2hex(random_bytes($bytes)); }
function normalize_email($email) { return strtolower(trim((string) $email)); }
function is_valid_email($email) { return (bool) filter_var($email, FILTER_VALIDATE_EMAIL); }

// Per-IP (or per-bucket-key) rate limiting — file-based, a few lines.
function rate_limited($bucket, $max, $window_seconds) {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  $file = sys_get_temp_dir() . '/dmsl_rl_' . $bucket . '_' . md5($ip) . '.json';
  $now = time();
  $stamps = is_file($file) ? json_decode((string) @file_get_contents($file), true) : [];
  if (!is_array($stamps)) $stamps = [];
  $stamps = array_values(array_filter($stamps, function ($t) use ($now, $window_seconds) { return $t > $now - $window_seconds; }));
  if (count($stamps) >= $max) return true;
  $stamps[] = $now;
  @file_put_contents($file, json_encode($stamps), LOCK_EX);
  return false;
}

// ---------------------------------------------------------------------
// 4. Purchase codes — generated by stripe-webhook.php after a successful
//    payment, required at registration time by dmsl-auth.php.
// ---------------------------------------------------------------------
// Unambiguous charset: no 0/O, 1/I/L, so a buyer typing the code by hand
// from an email can't confuse similar-looking characters.
const DMSL_CODE_CHARSET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

function generate_purchase_code() {
  $chars = DMSL_CODE_CHARSET;
  $len = strlen($chars);
  $raw = '';
  for ($i = 0; $i < 12; $i++) { $raw .= $chars[random_int(0, $len - 1)]; }
  return substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
}

// Accepts whatever a buyer pastes (with or without dashes, any case) and
// normalizes it back to the canonical XXXX-XXXX-XXXX storage key format.
function normalize_purchase_code($raw) {
  $s = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $raw));
  if ($s === '') return '';
  return implode('-', str_split($s, 4));
}

// ---------------------------------------------------------------------
// 5. JSON request/response helpers — shared by every endpoint file.
// ---------------------------------------------------------------------
function json_body() {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}
function respond($status, $payload) {
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

// ---------------------------------------------------------------------
// 6. Student sessions — 32 random bytes, hex. Cookie HttpOnly; SameSite=Lax;
//    Secure. Shared by dmsl-auth.php and dmsl-feedback.php (Soporte /
//    Sugerencias, which need to know which logged-in student is writing).
// ---------------------------------------------------------------------
const SESSION_COOKIE = 'dmsl_session';
const SESSION_DAYS = 30;

function issue_session(&$db, $email) {
  $token = random_token();
  $db['sesiones'][$token] = ['email' => $email, 'expira' => time() + SESSION_DAYS * 86400];
  setcookie(SESSION_COOKIE, $token, [
    'expires' => time() + SESSION_DAYS * 86400,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  return $token;
}
function current_session_email(&$db) {
  $token = $_COOKIE[SESSION_COOKIE] ?? '';
  if ($token === '' || !isset($db['sesiones'][$token])) return null;
  $s = $db['sesiones'][$token];
  if (($s['expira'] ?? 0) < time()) { unset($db['sesiones'][$token]); return null; }
  return [$s['email'], $token];
}
function clear_session_cookie() {
  setcookie(SESSION_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
}

// ---------------------------------------------------------------------
// 7. Admin session — completely separate cookie/store from student
//    sessions, used only by admin-auth.php / admin-data.php (the private
//    Dashboard). Never overlaps with a student's dmsl_session cookie.
// ---------------------------------------------------------------------
const ADMIN_SESSION_COOKIE = 'dmsl_admin_session';
const ADMIN_SESSION_DAYS = 7;

function issue_admin_session(&$db) {
  $token = random_token();
  $db['admin_sesiones'][$token] = ['expira' => time() + ADMIN_SESSION_DAYS * 86400];
  setcookie(ADMIN_SESSION_COOKIE, $token, [
    'expires' => time() + ADMIN_SESSION_DAYS * 86400,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  return $token;
}
function current_admin_session(&$db) {
  $token = $_COOKIE[ADMIN_SESSION_COOKIE] ?? '';
  if ($token === '' || !isset($db['admin_sesiones'][$token])) return null;
  $s = $db['admin_sesiones'][$token];
  if (($s['expira'] ?? 0) < time()) { unset($db['admin_sesiones'][$token]); return null; }
  return $token;
}
function clear_admin_session_cookie() {
  setcookie(ADMIN_SESSION_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
}
