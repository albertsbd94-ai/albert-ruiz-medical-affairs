<?php
/**
 * api/dmsl-auth.php — real accounts for the DMSL Course campus.
 *
 * The ONLY file that touches the Brevo key and the accounts database.
 * Endpoints (all JSON in/out):
 *   POST ?action=register       {name, email, password}
 *   POST ?action=verify         {email, code}
 *   POST ?action=resend         {email}
 *   POST ?action=login          {email, password}
 *   POST ?action=logout         {}
 *   GET  ?action=me
 *   POST ?action=save-progress  {progress: {...}}
 *
 * Accounts + sessions live in a JSON file OUTSIDE public_html so a site
 * redeploy (which wipes public_html) never erases a customer. Passwords are
 * always bcrypt-hashed. Email confirmation is a real 6-digit code sent
 * through Brevo's transactional email API — never shown in the response.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

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

function dmsl_load_db() {
  $path = dmsl_db_path();
  if (!is_file($path)) return ['usuarios' => [], 'sesiones' => []];
  $fh = @fopen($path, 'r');
  if (!$fh) return ['usuarios' => [], 'sesiones' => []];
  flock($fh, LOCK_SH);
  $raw = stream_get_contents($fh);
  flock($fh, LOCK_UN);
  fclose($fh);
  $data = json_decode($raw, true);
  if (!is_array($data)) $data = [];
  if (!isset($data['usuarios'])) $data['usuarios'] = [];
  if (!isset($data['sesiones'])) $data['sesiones'] = [];
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

const DMSL_SENDER_EMAIL = 'hello@albertruizdelaoliva.com';
const DMSL_SENDER_NAME  = 'Albert Ruiz de la Oliva — DMSL Course';

function dmsl_send_email($to_email, $to_name, $subject, $html) {
  $key = brevo_key();
  if ($key === '') return ['ok' => false, 'error' => 'email_not_configured'];
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
    CURLOPT_POSTFIELDS => json_encode([
      'sender' => ['name' => DMSL_SENDER_NAME, 'email' => DMSL_SENDER_EMAIL],
      'to' => [['email' => $to_email, 'name' => $to_name ?: $to_email]],
      'subject' => $subject,
      'htmlContent' => $html,
    ]),
  ]);
  $resp = curl_exec($ch);
  $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  if ($resp === false) return ['ok' => false, 'error' => 'network: ' . $err];
  if ($code < 200 || $code >= 300) return ['ok' => false, 'error' => 'brevo_http_' . $code, 'body' => $resp];
  return ['ok' => true];
}

function otp_email_html($name, $code) {
  $safeName = htmlspecialchars($name ?: '', ENT_QUOTES, 'UTF-8');
  return '<div style="font-family:Poppins,Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px 24px;">'
    . '<p style="font-size:15px;color:#2b2233;">Hi ' . ($safeName !== '' ? $safeName : 'there') . ',</p>'
    . '<p style="font-size:15px;color:#2b2233;">Your DMSL Course verification code is:</p>'
    . '<div style="font-size:32px;font-weight:800;letter-spacing:6px;color:#7A00E6;text-align:center;padding:18px 0;">' . htmlspecialchars($code) . '</div>'
    . '<p style="font-size:13.5px;color:#6b6275;">This code expires in 15 minutes. If you didn\'t request this, you can ignore this email.</p>'
    . '<p style="font-size:13.5px;color:#6b6275;margin-top:24px;">— Albert Ruiz de la Oliva, DMSL Course</p>'
    . '</div>';
}

// ---------------------------------------------------------------------
// 3. Small helpers
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
function normalize_email($email) { return strtolower(trim((string) $email)); }
function is_valid_email($email) { return (bool) filter_var($email, FILTER_VALIDATE_EMAIL); }
function random_token($bytes = 32) { return bin2hex(random_bytes($bytes)); }
function generate_otp() { return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT); }

// Per-IP rate limiting for register/login/resend — file-based, a few lines.
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

function default_progress() {
  return ['modules' => new stdClass(), 'lastModule' => null, 'studentName' => '', 'xp' => 0];
}

// ---------------------------------------------------------------------
// 4. Sessions — 32 random bytes, hex. Cookie HttpOnly; SameSite=Lax; Secure.
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
// 5. Router
// ---------------------------------------------------------------------
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$db = dmsl_load_db();

if ($action === 'register' && $method === 'POST') {
  if (rate_limited('register', 8, 3600)) respond(429, ['ok' => false, 'error' => 'Too many attempts. Please try again later.']);
  $body = json_body();
  $name = trim((string) ($body['name'] ?? ''));
  $email = normalize_email($body['email'] ?? '');
  $password = (string) ($body['password'] ?? '');
  if ($name === '') respond(400, ['ok' => false, 'error' => 'Please enter your full name.']);
  if (!is_valid_email($email)) respond(400, ['ok' => false, 'error' => 'Please enter a valid email address.']);
  if (strlen($password) < 6) respond(400, ['ok' => false, 'error' => 'Password must be at least 6 characters.']);

  $existing = $db['usuarios'][$email] ?? null;
  if ($existing && !empty($existing['verificado'])) {
    respond(409, ['ok' => false, 'error' => 'An account with this email already exists. Please log in.']);
  }
  $code = generate_otp();
  $db['usuarios'][$email] = [
    'email' => $email,
    'nombre' => $name,
    'hash' => password_hash($password, PASSWORD_BCRYPT),
    'verificado' => false,
    'otp' => $code,
    'otp_expira' => time() + 15 * 60,
    'creado' => $existing['creado'] ?? gmdate('c'),
    'progreso' => $existing['progreso'] ?? default_progress(),
  ];
  dmsl_save_db($db);

  $sent = dmsl_send_email($email, $name, 'Verify your email — DMSL Course', otp_email_html($name, $code));
  if (!$sent['ok']) {
    respond(200, ['ok' => true, 'emailSent' => false, 'warning' => 'Account created, but the confirmation email could not be sent (' . $sent['error'] . '). Contact hello@albertruizdelaoliva.com for help.']);
  }
  respond(200, ['ok' => true, 'emailSent' => true]);
}

if ($action === 'resend' && $method === 'POST') {
  if (rate_limited('resend', 5, 600)) respond(429, ['ok' => false, 'error' => 'Please wait a bit before requesting another code.']);
  $body = json_body();
  $email = normalize_email($body['email'] ?? '');
  $rec = $db['usuarios'][$email] ?? null;
  if (!$rec) respond(200, ['ok' => true]); // don't reveal account existence
  if (!empty($rec['verificado'])) respond(200, ['ok' => true, 'alreadyVerified' => true]);
  $code = generate_otp();
  $db['usuarios'][$email]['otp'] = $code;
  $db['usuarios'][$email]['otp_expira'] = time() + 15 * 60;
  dmsl_save_db($db);
  dmsl_send_email($email, $rec['nombre'] ?? '', 'Your new verification code — DMSL Course', otp_email_html($rec['nombre'] ?? '', $code));
  respond(200, ['ok' => true]);
}

if ($action === 'verify' && $method === 'POST') {
  if (rate_limited('verify', 15, 900)) respond(429, ['ok' => false, 'error' => 'Too many attempts. Please try again later.']);
  $body = json_body();
  $email = normalize_email($body['email'] ?? '');
  $code = trim((string) ($body['code'] ?? ''));
  $rec = $db['usuarios'][$email] ?? null;
  if (!$rec) respond(400, ['ok' => false, 'error' => 'Something went wrong. Please register again.']);
  if (empty($rec['otp']) || !hash_equals((string) $rec['otp'], $code)) respond(400, ['ok' => false, 'error' => 'Incorrect verification code.']);
  if (($rec['otp_expira'] ?? 0) < time()) respond(400, ['ok' => false, 'error' => 'This code has expired. Please request a new one.']);
  $db['usuarios'][$email]['verificado'] = true;
  unset($db['usuarios'][$email]['otp'], $db['usuarios'][$email]['otp_expira']);
  $token = issue_session($db, $email);
  dmsl_save_db($db);
  respond(200, ['ok' => true, 'email' => $email, 'name' => $rec['nombre'] ?? '', 'progress' => $db['usuarios'][$email]['progreso']]);
}

if ($action === 'login' && $method === 'POST') {
  if (rate_limited('login', 8, 900)) respond(429, ['ok' => false, 'error' => 'Too many attempts. Please try again in a few minutes.']);
  $body = json_body();
  $email = normalize_email($body['email'] ?? '');
  $password = (string) ($body['password'] ?? '');
  $rec = $db['usuarios'][$email] ?? null;
  if (!$rec || !password_verify($password, $rec['hash'])) respond(401, ['ok' => false, 'error' => 'Incorrect email or password.']);
  if (empty($rec['verificado'])) {
    $code = generate_otp();
    $db['usuarios'][$email]['otp'] = $code;
    $db['usuarios'][$email]['otp_expira'] = time() + 15 * 60;
    dmsl_save_db($db);
    dmsl_send_email($email, $rec['nombre'] ?? '', 'Verify your email — DMSL Course', otp_email_html($rec['nombre'] ?? '', $code));
    respond(200, ['ok' => false, 'needsVerification' => true, 'error' => 'Please verify your email first — we just sent you a new code.']);
  }
  $token = issue_session($db, $email);
  dmsl_save_db($db);
  respond(200, ['ok' => true, 'email' => $email, 'name' => $rec['nombre'] ?? '', 'progress' => $rec['progreso'] ?? default_progress()]);
}

if ($action === 'logout' && $method === 'POST') {
  $found = current_session_email($db);
  if ($found) { [, $token] = $found; unset($db['sesiones'][$token]); dmsl_save_db($db); }
  clear_session_cookie();
  respond(200, ['ok' => true]);
}

if ($action === 'me' && $method === 'GET') {
  $found = current_session_email($db);
  if (!$found) respond(200, ['ok' => true, 'authenticated' => false]);
  [$email] = $found;
  $rec = $db['usuarios'][$email] ?? null;
  if (!$rec) respond(200, ['ok' => true, 'authenticated' => false]);
  respond(200, ['ok' => true, 'authenticated' => true, 'email' => $email, 'name' => $rec['nombre'] ?? '', 'progress' => $rec['progreso'] ?? default_progress()]);
}

if ($action === 'save-progress' && $method === 'POST') {
  $found = current_session_email($db);
  if (!$found) respond(401, ['ok' => false, 'error' => 'Not logged in.']);
  [$email] = $found;
  if (!isset($db['usuarios'][$email])) respond(401, ['ok' => false, 'error' => 'Not logged in.']);
  $body = json_body();
  $progress = $body['progress'] ?? null;
  if (!is_array($progress)) respond(400, ['ok' => false, 'error' => 'Invalid progress payload.']);
  $db['usuarios'][$email]['progreso'] = $progress;
  dmsl_save_db($db);
  respond(200, ['ok' => true]);
}

respond(404, ['ok' => false, 'error' => 'Unknown action.']);
