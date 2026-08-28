<?php
/**
 * api/dmsl-feedback.php — "Soporte" (direct support messages) and
 * "Sugerencias de mejora" (platform feedback) from logged-in students on
 * either campus (English or Spanish).
 *
 * Endpoints (all JSON in/out, require a logged-in student session cookie —
 * the same one issued by dmsl-auth.php):
 *   POST ?action=soporte     {mensaje}
 *   POST ?action=sugerencia  {mensaje}
 *
 * Both actions:
 *   1. Store the message (shared storage in dmsl-common.php) so it shows up
 *      in the relevant section of the private admin Dashboard.
 *   2. Email a copy to contact@albertruiz.com via Brevo, with the student's
 *      own address set as Reply-To so a plain "reply" goes straight to them.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/dmsl-common.php';

const ALBERT_CONTACT_EMAIL = 'contact@albertruiz.com';

function feedback_notify_html($tipoLabel, $nombre, $email, $mensaje, $lang) {
  $safeName = htmlspecialchars($nombre ?: $email, ENT_QUOTES, 'UTF-8');
  $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
  $safeMsg = nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'));
  $campus = $lang === 'es' ? 'Campus (Español)' : 'Campus (English)';
  return '<div style="font-family:Poppins,Arial,sans-serif;max-width:560px;margin:0 auto;padding:32px 24px;">'
    . '<p style="font-size:13px;color:#9a93a5;text-transform:uppercase;letter-spacing:.08em;font-weight:700;">' . htmlspecialchars($tipoLabel, ENT_QUOTES, 'UTF-8') . ' &middot; ' . htmlspecialchars($campus, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p style="font-size:15px;color:#2b2233;"><strong>' . $safeName . '</strong> (' . $safeEmail . ')</p>'
    . '<div style="background:#F0EAF7;border-radius:14px;padding:18px 20px;margin-top:12px;font-size:14.5px;color:#2b2233;line-height:1.6;">' . $safeMsg . '</div>'
    . '</div>';
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$db = dmsl_load_db();

$found = current_session_email($db);
if (!$found) respond(401, ['ok' => false, 'error' => 'Not logged in.']);
[$email] = $found;
$user = $db['usuarios'][$email] ?? null;
if (!$user) respond(401, ['ok' => false, 'error' => 'Not logged in.']);
$nombre = $user['nombre'] ?? '';
$lang = ($db['codigos'][$user['codigo_compra'] ?? '']['lang'] ?? '') === 'es' ? 'es' : 'en';

if ($action === 'soporte' && $method === 'POST') {
  if (rate_limited('soporte', 10, 3600)) respond(429, ['ok' => false, 'error' => 'Too many messages sent recently. Please try again later.']);
  $body = json_body();
  $mensaje = trim((string) ($body['mensaje'] ?? ''));
  if ($mensaje === '') respond(400, ['ok' => false, 'error' => 'Please write a message before sending.']);
  if (mb_strlen($mensaje) > 4000) respond(400, ['ok' => false, 'error' => 'Message is too long.']);

  $id = random_token(8);
  $db['soporte'][$id] = [
    'id' => $id,
    'email' => $email,
    'nombre' => $nombre,
    'mensaje' => $mensaje,
    'lang' => $lang,
    'creado' => gmdate('c'),
    'leido' => false,
  ];
  dmsl_save_db($db);

  $tipoLabel = $lang === 'es' ? 'Nuevo mensaje de soporte' : 'New support message';
  dmsl_send_email(ALBERT_CONTACT_EMAIL, 'Albert Ruiz', $tipoLabel . ' — DMSL Course', feedback_notify_html($tipoLabel, $nombre, $email, $mensaje, $lang), $email);
  respond(200, ['ok' => true]);
}

if ($action === 'sugerencia' && $method === 'POST') {
  if (rate_limited('sugerencia', 10, 3600)) respond(429, ['ok' => false, 'error' => 'Too many messages sent recently. Please try again later.']);
  $body = json_body();
  $mensaje = trim((string) ($body['mensaje'] ?? ''));
  if ($mensaje === '') respond(400, ['ok' => false, 'error' => 'Please write your suggestion before sending.']);
  if (mb_strlen($mensaje) > 4000) respond(400, ['ok' => false, 'error' => 'Message is too long.']);

  $id = random_token(8);
  $db['sugerencias'][$id] = [
    'id' => $id,
    'email' => $email,
    'nombre' => $nombre,
    'mensaje' => $mensaje,
    'lang' => $lang,
    'creado' => gmdate('c'),
    'leido' => false,
  ];
  dmsl_save_db($db);

  $tipoLabel = $lang === 'es' ? 'Nueva sugerencia de mejora' : 'New improvement suggestion';
  dmsl_send_email(ALBERT_CONTACT_EMAIL, 'Albert Ruiz', $tipoLabel . ' — DMSL Course', feedback_notify_html($tipoLabel, $nombre, $email, $mensaje, $lang), $email);
  respond(200, ['ok' => true]);
}

respond(404, ['ok' => false, 'error' => 'Unknown action.']);
