<?php
/**
 * api/stripe-webhook.php — Stripe webhook receiver for DMSL Course purchases.
 *
 * Listens for checkout.session.completed / checkout.session.async_payment_succeeded
 * on Albert's Stripe account. On a paid session it:
 *   1. Generates a unique, human-typable purchase code.
 *   2. Stores it (shared storage in dmsl-common.php) tied to the buyer's
 *      email, the plan purchased, and the Checkout Session id (for
 *      idempotency — Stripe can and does redeliver the same event).
 *   3. Emails the code to the buyer via Brevo, in the language of the
 *      landing page they bought from (session metadata 'lang').
 *
 * The code is later required by api/dmsl-auth.php's `register` action to
 * create a campus account — for either the English or the Spanish campus,
 * since a code isn't tied to a language, only to a paid session.
 *
 * No Stripe SDK — this is a small shared-hosting PHP app, so the webhook
 * signature is verified by hand following Stripe's documented algorithm
 * (https://docs.stripe.com/webhooks#verify-manually).
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/dmsl-common.php';

function stripe_webhook_secret() {
  $k = getenv('STRIPE_WEBHOOK_SECRET');
  if (!$k && is_file(dirname(__DIR__, 2) . '/stripe_webhook_secret.php')) $k = @include dirname(__DIR__, 2) . '/stripe_webhook_secret.php';
  if (!$k && is_file(__DIR__ . '/../datos/stripe_webhook_secret.php')) $k = @include __DIR__ . '/../datos/stripe_webhook_secret.php';
  return is_string($k) ? trim($k) : '';
}

// Manual Stripe-Signature verification: header is "t=<ts>,v1=<sig>[,v1=<sig>...]".
// Signed payload is "{ts}.{raw_body}"; expected sig is HMAC-SHA256 of that
// with the endpoint's signing secret. A 5-minute tolerance guards replay.
function verify_stripe_signature($rawBody, $sigHeader, $secret, $toleranceSeconds = 300) {
  if ($secret === '' || $sigHeader === '') return false;
  $parts = explode(',', $sigHeader);
  $timestamp = null;
  $signatures = [];
  foreach ($parts as $part) {
    $kv = explode('=', $part, 2);
    if (count($kv) !== 2) continue;
    if ($kv[0] === 't') $timestamp = $kv[1];
    if ($kv[0] === 'v1') $signatures[] = $kv[1];
  }
  if ($timestamp === null || empty($signatures)) return false;
  if (abs(time() - (int) $timestamp) > $toleranceSeconds) return false;
  $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
  foreach ($signatures as $sig) {
    if (hash_equals($expected, $sig)) return true;
  }
  return false;
}

function plan_label($plan, $lang) {
  $labels = [
    'en' => ['semipresencial' => 'Semi-presential Programme', 'online' => 'Self-Paced Online Programme'],
    'es' => ['semipresencial' => 'Programa Semipresencial', 'online' => 'Programa Online a tu Ritmo'],
  ];
  $set = $labels[$lang] ?? $labels['en'];
  return $set[$plan] ?? ($lang === 'es' ? 'DMSL Course' : 'DMSL Course');
}

function purchase_code_email_html($name, $code, $plan, $lang) {
  $safeName = htmlspecialchars($name ?: '', ENT_QUOTES, 'UTF-8');
  $es = $lang === 'es';
  $campusPath = $es ? '/dmsl-course-campus.html' : '/dmsl-course-campus-en.html';
  $planLabel = htmlspecialchars(plan_label($plan, $lang), ENT_QUOTES, 'UTF-8');
  $hi = $es ? ('Hola ' . ($safeName !== '' ? $safeName : '')) : ('Hi ' . ($safeName !== '' ? $safeName : 'there'));
  $intro = $es
    ? 'Gracias por tu compra del <b>' . $planLabel . '</b> del DMSL Course. Este es tu código de compra — lo necesitarás para crear tu cuenta en el campus:'
    : 'Thank you for your purchase of the <b>' . $planLabel . '</b> for the DMSL Course. Here is your purchase code — you\'ll need it to create your account on the campus:';
  $howTo = $es
    ? 'Ve al campus, elige "Crear Cuenta" e introduce este código junto con tu nombre, email y una contraseña.'
    : 'Go to the campus, choose "Create Account" and enter this code along with your name, email and a password.';
  $btn = $es ? 'Ir al campus' : 'Go to the campus';
  $note = $es
    ? 'Este código es de un solo uso y no caduca. Guárdalo hasta que hayas creado tu cuenta. Si tienes cualquier problema, responde a este correo.'
    : 'This code is single-use and does not expire. Keep it until you\'ve created your account. If you run into any trouble, just reply to this email.';
  $sign = '— Albert Ruiz de la Oliva, DMSL Course';
  return '<div style="font-family:Poppins,Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px 24px;">'
    . '<p style="font-size:15px;color:#2b2233;">' . $hi . ',</p>'
    . '<p style="font-size:15px;color:#2b2233;">' . $intro . '</p>'
    . '<div style="font-size:26px;font-weight:800;letter-spacing:2px;color:#7A00E6;text-align:center;padding:18px 0;font-family:\'SF Mono\',ui-monospace,Menlo,Consolas,monospace;">' . htmlspecialchars($code) . '</div>'
    . '<p style="font-size:14px;color:#2b2233;">' . $howTo . '</p>'
    . '<div style="text-align:center;padding:14px 0 22px;"><a href="https://albertruiz.com' . $campusPath . '" style="display:inline-block;background:#7A00E6;color:#fff;font-weight:700;font-size:15px;text-decoration:none;padding:14px 28px;border-radius:999px;">' . $btn . '</a></div>'
    . '<p style="font-size:13px;color:#6b6275;">' . $note . '</p>'
    . '<p style="font-size:13.5px;color:#6b6275;margin-top:24px;">' . $sign . '</p>'
    . '</div>';
}

$rawBody = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret = stripe_webhook_secret();

if ($secret === '') {
  // Not configured yet — fail loudly (5xx) so Stripe keeps retrying this
  // event until setup-stripe-webhook.php has been used to store the secret.
  // A real payment's code must never be silently dropped.
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'webhook_not_configured']);
  exit;
}

if (!verify_stripe_signature($rawBody, $sigHeader, $secret)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_signature']);
  exit;
}

$event = json_decode($rawBody, true);
if (!is_array($event) || empty($event['type'])) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
  exit;
}

$handledTypes = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];
if (!in_array($event['type'], $handledTypes, true)) {
  // Acknowledge anything we don't act on so Stripe doesn't keep retrying it.
  http_response_code(200);
  echo json_encode(['ok' => true, 'ignored' => true]);
  exit;
}

$session = $event['data']['object'] ?? [];
$paymentStatus = $session['payment_status'] ?? '';
$sessionId = $session['id'] ?? '';

if ($paymentStatus !== 'paid' || $sessionId === '') {
  // e.g. checkout.session.completed for an async payment method that
  // hasn't actually settled yet — wait for async_payment_succeeded instead.
  http_response_code(200);
  echo json_encode(['ok' => true, 'skipped' => true]);
  exit;
}

$db = dmsl_load_db();
if (!isset($db['codigos_por_sesion'])) $db['codigos_por_sesion'] = [];

// Idempotency — Stripe redelivers events; never mint a second code (or
// send a second email) for a session we've already processed.
if (isset($db['codigos_por_sesion'][$sessionId])) {
  http_response_code(200);
  echo json_encode(['ok' => true, 'duplicate' => true]);
  exit;
}

$email = normalize_email($session['customer_details']['email'] ?? $session['customer_email'] ?? '');
$name = trim((string) ($session['customer_details']['name'] ?? ''));
$plan = (string) ($session['metadata']['plan'] ?? 'unknown');
// Prefer an explicit lang set on the Payment Link's metadata (deterministic,
// tied to which landing page's button was clicked). If a link predates that
// metadata, fall back to the Checkout Session's own locale, which Stripe
// auto-detects from the buyer's browser even under the default 'auto' mode.
$metaLang = $session['metadata']['lang'] ?? null;
if ($metaLang === 'es' || $metaLang === 'en') {
  $lang = $metaLang;
} else {
  $sessionLocale = (string) ($session['locale'] ?? '');
  $lang = (strpos($sessionLocale, 'es') === 0) ? 'es' : 'en';
}

if ($email === '' || !is_valid_email($email)) {
  // Nothing sane to do without an email — acknowledge so Stripe stops
  // retrying, but don't mint a code that could never be delivered.
  http_response_code(200);
  echo json_encode(['ok' => true, 'skipped' => true, 'reason' => 'no_email']);
  exit;
}

// Generate a code, guarding (in practice never needed) against collision.
do {
  $code = generate_purchase_code();
} while (isset($db['codigos'][$code]));

$db['codigos'][$code] = [
  'email' => $email,
  'nombre_comprador' => $name,
  'plan' => $plan,
  'lang' => $lang,
  'session_id' => $sessionId,
  'monto' => $session['amount_total'] ?? null,
  'moneda' => $session['currency'] ?? null,
  'creado' => gmdate('c'),
  'usado' => false,
  'reservado_email' => null,
  'usado_en' => null,
];
$db['codigos_por_sesion'][$sessionId] = $code;
dmsl_save_db($db);

$subject = $lang === 'es' ? 'Tu código de compra — DMSL Course' : 'Your purchase code — DMSL Course';
dmsl_send_email($email, $name, $subject, purchase_code_email_html($name, $code, $plan, $lang));

// Hand off to Brevo's CRM side (separate from the transactional email
// above): tag the buyer as a paying customer so a Brevo automation (e.g.
// the Instagram/ManyChat nurture sequence) can stop sending them "enrol
// now" emails, and move them into the 'alumnos' list once Albert creates
// it and maps its ID in brevo_lists.php. Never blocks the webhook response.
brevo_upsert_contact($email, [
  'FIRSTNAME' => $name,
  'FASE_FUNNEL' => 'comprador',
  'PLAN_DMSL' => $plan,
  'IDIOMA_CAMPUS' => $lang,
  'FECHA_COMPRA' => substr(gmdate('c'), 0, 10),
], 'alumnos');

http_response_code(200);
echo json_encode(['ok' => true]);
