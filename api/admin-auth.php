<?php
/**
 * api/admin-auth.php — authentication for the private DMSL Course
 * Dashboard (dashboard.html). Exactly one account exists, hardcoded here —
 * there is no registration flow and never should be one.
 *
 * Endpoints (all JSON in/out):
 *   POST ?action=login   {email, password}
 *   POST ?action=logout  {}
 *   GET  ?action=me
 *
 * The password is never stored in plaintext — only its bcrypt hash, below.
 * Sessions use their own cookie (dmsl_admin_session), completely separate
 * from the student session cookie used by dmsl-auth.php, so a student
 * session can never be mistaken for admin access or vice versa.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/dmsl-common.php';

const ADMIN_EMAIL = 'albertsbd94@gmail.com';
// Generated once, offline, via password_hash(). The plaintext password is
// never written anywhere in this repo or on the server.
const ADMIN_HASH = '$2y$12$M0egve5aHPjUGuHRq3PEpOCtwr80GmnJDkhSYD7//dIPWIqk1XHla';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$db = dmsl_load_db();

if ($action === 'login' && $method === 'POST') {
  if (rate_limited('admin-login', 8, 900)) respond(429, ['ok' => false, 'error' => 'Too many attempts. Please try again in a few minutes.']);
  $body = json_body();
  $email = normalize_email($body['email'] ?? '');
  $password = (string) ($body['password'] ?? '');
  if ($email !== ADMIN_EMAIL || !password_verify($password, ADMIN_HASH)) {
    respond(401, ['ok' => false, 'error' => 'Incorrect email or password.']);
  }
  issue_admin_session($db);
  dmsl_save_db($db);
  respond(200, ['ok' => true, 'email' => ADMIN_EMAIL]);
}

if ($action === 'logout' && $method === 'POST') {
  $token = current_admin_session($db);
  if ($token) { unset($db['admin_sesiones'][$token]); dmsl_save_db($db); }
  clear_admin_session_cookie();
  respond(200, ['ok' => true]);
}

if ($action === 'me' && $method === 'GET') {
  $token = current_admin_session($db);
  if (!$token) respond(200, ['ok' => true, 'authenticated' => false]);
  respond(200, ['ok' => true, 'authenticated' => true, 'email' => ADMIN_EMAIL]);
}

respond(404, ['ok' => false, 'error' => 'Unknown action.']);
