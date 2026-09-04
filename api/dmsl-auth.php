<?php
/**
 * api/dmsl-auth.php — real accounts for the DMSL Course campus.
 *
 * Endpoints (all JSON in/out):
 *   POST ?action=register        {name, email, password, purchase_code}
 *   POST ?action=verify          {email, code}
 *   POST ?action=resend          {email}
 *   POST ?action=login           {email, password}
 *   POST ?action=forgot-password {email, lang}
 *   POST ?action=reset-password  {email, token, password}
 *   POST ?action=logout          {}
 *   GET  ?action=me
 *   POST ?action=save-progress   {progress: {...}}
 *
 * Accounts + sessions live in a JSON file OUTSIDE public_html so a site
 * redeploy (which wipes public_html) never erases a customer. Passwords are
 * always bcrypt-hashed. Email confirmation is a real 6-digit code sent
 * through Brevo's transactional email API — never shown in the response.
 *
 * Registration requires a purchase code, generated and emailed by
 * api/stripe-webhook.php after a successful payment on either plan (both
 * plans work for both the English and Spanish campus — the code isn't
 * tied to a language, only to a paid Stripe Checkout session). Storage,
 * Brevo sending and purchase-code helpers live in dmsl-common.php, shared
 * with stripe-webhook.php.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/dmsl-common.php';

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

function reset_email_html($name, $link, $lang) {
  $safeName = htmlspecialchars($name ?: '', ENT_QUOTES, 'UTF-8');
  $es = $lang === 'es';
  $hi = $es ? ('Hola ' . ($safeName !== '' ? $safeName : '')) : ('Hi ' . ($safeName !== '' ? $safeName : 'there'));
  $body = $es
    ? 'Hemos recibido una solicitud para restablecer la contraseña de tu cuenta del DMSL Course. Haz clic en el siguiente botón para elegir una nueva contraseña:'
    : 'We received a request to reset the password for your DMSL Course account. Click the button below to choose a new password:';
  $btn = $es ? 'Restablecer contraseña' : 'Reset password';
  $expiry = $es
    ? 'Este enlace caduca en 30 minutos. Si no has solicitado esto, puedes ignorar este correo — tu contraseña no cambiará.'
    : 'This link expires in 30 minutes. If you didn\'t request this, you can ignore this email — your password will not change.';
  $sign = $es ? '— Albert Ruiz de la Oliva, DMSL Course' : '— Albert Ruiz de la Oliva, DMSL Course';
  return '<div style="font-family:Poppins,Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px 24px;">'
    . '<p style="font-size:15px;color:#2b2233;">' . $hi . ',</p>'
    . '<p style="font-size:15px;color:#2b2233;">' . $body . '</p>'
    . '<div style="text-align:center;padding:22px 0;"><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#7A00E6;color:#fff;font-weight:700;font-size:15px;text-decoration:none;padding:14px 28px;border-radius:999px;">' . $btn . '</a></div>'
    . '<p style="font-size:12.5px;color:#9a93a5;word-break:break-all;">' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p style="font-size:13.5px;color:#6b6275;">' . $expiry . '</p>'
    . '<p style="font-size:13.5px;color:#6b6275;margin-top:24px;">' . $sign . '</p>'
    . '</div>';
}

// ---------------------------------------------------------------------
// 3. Small helpers local to this file (storage/email/rate-limit helpers,
//    json_body/respond and session helpers now live in dmsl-common.php,
//    shared with stripe-webhook.php and dmsl-feedback.php)
// ---------------------------------------------------------------------
function generate_otp() { return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT); }

function default_progress() {
  return ['modules' => new stdClass(), 'lastModule' => null, 'studentName' => '', 'xp' => 0];
}

// ---------------------------------------------------------------------
// 4. Router
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
  $purchaseCode = normalize_purchase_code($body['purchase_code'] ?? '');
  // Best-effort acquisition tracking — whatever the campus page's UTM-capture
  // snippet found in localStorage, if anything. Never required, just stored
  // for a future Dashboard breakdown of where students actually come from
  // (e.g. the Instagram/ManyChat funnel). Trimmed and length-capped since
  // it's unauthenticated user input landing straight in the JSON store.
  $utmSource = mb_substr(trim((string) ($body['utm_source'] ?? '')), 0, 60);
  $utmMedium = mb_substr(trim((string) ($body['utm_medium'] ?? '')), 0, 60);
  $utmCampaign = mb_substr(trim((string) ($body['utm_campaign'] ?? '')), 0, 60);
  if ($name === '') respond(400, ['ok' => false, 'error' => 'Please enter your full name.']);
  if (!is_valid_email($email)) respond(400, ['ok' => false, 'error' => 'Please enter a valid email address.']);
  if (strlen($password) < 6) respond(400, ['ok' => false, 'error' => 'Password must be at least 6 characters.']);
  if ($purchaseCode === '') respond(400, ['ok' => false, 'error' => 'Please enter your purchase code. You\'ll find it in the confirmation email from your enrolment payment.']);

  $existing = $db['usuarios'][$email] ?? null;
  if ($existing && !empty($existing['verificado'])) {
    respond(409, ['ok' => false, 'error' => 'An account with this email already exists. Please log in.']);
  }

  // The purchase code must exist, not already be spent, and not be
  // reserved by a different, still-unverified registration attempt.
  $codeRec = $db['codigos'][$purchaseCode] ?? null;
  if (!$codeRec) respond(400, ['ok' => false, 'error' => 'This purchase code is not valid. Please check your purchase confirmation email and try again.']);
  if (!empty($codeRec['usado'])) respond(400, ['ok' => false, 'error' => 'This purchase code has already been used to create an account.']);
  if (!empty($codeRec['reservado_email']) && $codeRec['reservado_email'] !== $email) {
    respond(400, ['ok' => false, 'error' => 'This purchase code is already linked to a different email address.']);
  }
  // Retrying registration (e.g. a typo the first time) with a different
  // code than before — release the old reservation so it isn't stuck.
  $prevCode = $existing['codigo_compra'] ?? null;
  if ($prevCode && $prevCode !== $purchaseCode && isset($db['codigos'][$prevCode]) && ($db['codigos'][$prevCode]['reservado_email'] ?? null) === $email) {
    $db['codigos'][$prevCode]['reservado_email'] = null;
  }
  $db['codigos'][$purchaseCode]['reservado_email'] = $email;

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
    'codigo_compra' => $purchaseCode,
    'origen_utm' => ($utmSource || $utmMedium || $utmCampaign)
      ? ['source' => $utmSource, 'medium' => $utmMedium, 'campaign' => $utmCampaign]
      : ($existing['origen_utm'] ?? null),
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
  // The purchase code is only permanently spent once the account is
  // actually confirmed — an abandoned, never-verified registration
  // doesn't burn the buyer's code.
  $usedCode = $rec['codigo_compra'] ?? null;
  if ($usedCode && isset($db['codigos'][$usedCode])) {
    $db['codigos'][$usedCode]['usado'] = true;
    $db['codigos'][$usedCode]['usado_en'] = gmdate('c');
  }
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

if ($action === 'forgot-password' && $method === 'POST') {
  if (rate_limited('forgot', 6, 900)) respond(429, ['ok' => false, 'error' => 'Too many attempts. Please try again later.']);
  $body = json_body();
  $email = normalize_email($body['email'] ?? '');
  $lang = ($body['lang'] ?? '') === 'es' ? 'es' : 'en';
  $campusPath = $lang === 'es' ? '/dmsl-course-campus.html' : '/dmsl-course-campus-en.html';
  $rec = $db['usuarios'][$email] ?? null;
  // Always respond ok — never reveal whether an account exists for this email.
  if ($rec && !empty($rec['verificado'])) {
    $rawToken = random_token();
    $db['usuarios'][$email]['reset_hash'] = hash('sha256', $rawToken);
    $db['usuarios'][$email]['reset_expira'] = time() + 30 * 60;
    dmsl_save_db($db);
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'www.albertruizdelaoliva.com';
    $link = $scheme . '://' . $host . $campusPath . '?reset=' . $rawToken . '&email=' . rawurlencode($email);
    $subject = $lang === 'es' ? 'Restablece tu contraseña — DMSL Course' : 'Reset your password — DMSL Course';
    dmsl_send_email($email, $rec['nombre'] ?? '', $subject, reset_email_html($rec['nombre'] ?? '', $link, $lang));
  }
  respond(200, ['ok' => true]);
}

if ($action === 'reset-password' && $method === 'POST') {
  if (rate_limited('reset', 10, 900)) respond(429, ['ok' => false, 'error' => 'Too many attempts. Please try again later.']);
  $body = json_body();
  $email = normalize_email($body['email'] ?? '');
  $token = trim((string) ($body['token'] ?? ''));
  $password = (string) ($body['password'] ?? '');
  if (strlen($password) < 6) respond(400, ['ok' => false, 'error' => 'Password must be at least 6 characters.']);
  $rec = $db['usuarios'][$email] ?? null;
  if (!$rec || empty($rec['reset_hash']) || $token === '' || !hash_equals((string) $rec['reset_hash'], hash('sha256', $token))) {
    respond(400, ['ok' => false, 'error' => 'This reset link is invalid or has already been used. Please request a new one.']);
  }
  if (($rec['reset_expira'] ?? 0) < time()) {
    respond(400, ['ok' => false, 'error' => 'This reset link has expired. Please request a new one.']);
  }
  $db['usuarios'][$email]['hash'] = password_hash($password, PASSWORD_BCRYPT);
  unset($db['usuarios'][$email]['reset_hash'], $db['usuarios'][$email]['reset_expira']);
  // Invalidate every existing session for this account — a leaked old
  // session shouldn't survive a password reset.
  foreach ($db['sesiones'] as $tok => $s) {
    if (($s['email'] ?? null) === $email) unset($db['sesiones'][$tok]);
  }
  issue_session($db, $email);
  dmsl_save_db($db);
  respond(200, ['ok' => true, 'email' => $email, 'name' => $rec['nombre'] ?? '', 'progress' => $db['usuarios'][$email]['progreso']]);
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
  // Powers the "last active" KPI on the admin Dashboard.
  $db['usuarios'][$email]['actualizado'] = gmdate('c');
  dmsl_save_db($db);
  respond(200, ['ok' => true]);
}

respond(404, ['ok' => false, 'error' => 'Unknown action.']);
