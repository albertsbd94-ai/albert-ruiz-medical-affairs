<?php
/**
 * api/admin-data.php — KPI aggregation + Soporte/Sugerencias inbox for the
 * private DMSL Course Dashboard (dashboard.html). Every action requires a
 * valid admin session (see admin-auth.php) — nothing here is reachable by
 * a student session cookie.
 *
 * Endpoints (all JSON, admin session cookie required):
 *   GET  ?action=overview        -> purchase + student KPIs for the charts
 *   GET  ?action=alumnos         -> per-student progress list
 *   GET  ?action=soporte         -> support messages, newest first
 *   GET  ?action=sugerencias     -> improvement suggestions, newest first
 *   POST ?action=marcar-leido    {tipo:'soporte'|'sugerencias', id}
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/dmsl-common.php';

// Lesson counts per module, in COURSE_DATA order (12 modules, 110 lessons
// total) — mirrors the campus SPAs' course content. Kept here, not derived,
// since the course content lives client-side in the campus HTML files.
const DMSL_MODULE_LESSON_COUNTS = [8, 8, 8, 8, 8, 10, 10, 10, 10, 10, 10, 10];
const DMSL_TOTAL_LESSONS = 110; // array_sum(DMSL_MODULE_LESSON_COUNTS)

function student_completion_percent($progreso) {
  if (!is_array($progreso) || empty($progreso['modules']) || !is_array($progreso['modules'])) return 0;
  $done = 0;
  $i = 0;
  foreach (DMSL_MODULE_LESSON_COUNTS as $moduleNum1based) {
    $i++;
    $mod = $progreso['modules'][(string) $i] ?? $progreso['modules'][$i] ?? null;
    if (!is_array($mod) || empty($mod['lessonsViewed']) || !is_array($mod['lessonsViewed'])) continue;
    foreach ($mod['lessonsViewed'] as $seen) { if ($seen) $done++; }
  }
  if (DMSL_TOTAL_LESSONS <= 0) return 0;
  return (int) round(($done / DMSL_TOTAL_LESSONS) * 100);
}

function require_admin(&$db) {
  $token = current_admin_session($db);
  if (!$token) respond(401, ['ok' => false, 'error' => 'Not logged in.']);
  return $token;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$db = dmsl_load_db();
require_admin($db);

if ($action === 'overview' && $method === 'GET') {
  $codigos = $db['codigos'] ?? [];
  $usuarios = $db['usuarios'] ?? [];

  $totalPurchases = count($codigos);
  $usedCodes = 0;
  $revenueByPlan = ['semipresencial' => 0, 'online' => 0, 'other' => 0];
  $countByPlan = ['semipresencial' => 0, 'online' => 0, 'other' => 0];
  $revenueByCurrency = [];
  $purchasesByDay = []; // 'YYYY-MM-DD' => ['count'=>n, 'revenue'=>cents]
  $purchasesByLang = ['en' => 0, 'es' => 0];

  foreach ($codigos as $code => $c) {
    if (!empty($c['usado'])) $usedCodes++;
    $plan = in_array($c['plan'] ?? '', ['semipresencial', 'online'], true) ? $c['plan'] : 'other';
    $amount = (int) ($c['monto'] ?? 0);
    $countByPlan[$plan]++;
    $revenueByPlan[$plan] += $amount;
    $currency = strtoupper((string) ($c['moneda'] ?? 'eur'));
    $revenueByCurrency[$currency] = ($revenueByCurrency[$currency] ?? 0) + $amount;
    $lang = ($c['lang'] ?? '') === 'es' ? 'es' : 'en';
    $purchasesByLang[$lang]++;
    $day = substr((string) ($c['creado'] ?? ''), 0, 10);
    if ($day !== '') {
      if (!isset($purchasesByDay[$day])) $purchasesByDay[$day] = ['count' => 0, 'revenue' => 0];
      $purchasesByDay[$day]['count']++;
      $purchasesByDay[$day]['revenue'] += $amount;
    }
  }
  ksort($purchasesByDay);
  // Cumulative revenue series, useful for a growth line chart.
  $cumulative = 0;
  $series = [];
  foreach ($purchasesByDay as $day => $row) {
    $cumulative += $row['revenue'];
    $series[] = ['date' => $day, 'count' => $row['count'], 'revenue' => $row['revenue'], 'cumulativeRevenue' => $cumulative];
  }

  $totalStudents = 0;
  $verifiedStudents = 0;
  $pendingStudents = 0;
  $completionBuckets = ['0-25' => 0, '25-50' => 0, '50-75' => 0, '75-99' => 0, '100' => 0];
  $completionSum = 0;
  $studentsByLang = ['en' => 0, 'es' => 0];
  $lastActive = null;

  foreach ($usuarios as $email => $u) {
    $totalStudents++;
    if (!empty($u['verificado'])) {
      $verifiedStudents++;
      $pct = student_completion_percent($u['progreso'] ?? null);
      $completionSum += $pct;
      if ($pct >= 100) $completionBuckets['100']++;
      elseif ($pct >= 75) $completionBuckets['75-99']++;
      elseif ($pct >= 50) $completionBuckets['50-75']++;
      elseif ($pct >= 25) $completionBuckets['25-50']++;
      else $completionBuckets['0-25']++;
      $codeRec = $codigos[$u['codigo_compra'] ?? ''] ?? null;
      $lang = ($codeRec['lang'] ?? '') === 'es' ? 'es' : 'en';
      $studentsByLang[$lang]++;
      $updated = $u['actualizado'] ?? $u['creado'] ?? null;
      if ($updated && ($lastActive === null || $updated > $lastActive)) $lastActive = $updated;
    } else {
      $pendingStudents++;
    }
  }
  $avgCompletion = $verifiedStudents > 0 ? (int) round($completionSum / $verifiedStudents) : 0;

  respond(200, ['ok' => true, 'overview' => [
    'purchases' => [
      'total' => $totalPurchases,
      'usedCodes' => $usedCodes,
      'conversionPercent' => $totalPurchases > 0 ? (int) round(($usedCodes / $totalPurchases) * 100) : 0,
      'countByPlan' => $countByPlan,
      'revenueByPlan' => $revenueByPlan,
      'revenueByCurrency' => $revenueByCurrency,
      'byLang' => $purchasesByLang,
      'series' => $series,
    ],
    'students' => [
      'total' => $totalStudents,
      'verified' => $verifiedStudents,
      'pending' => $pendingStudents,
      'avgCompletionPercent' => $avgCompletion,
      'completionBuckets' => $completionBuckets,
      'byLang' => $studentsByLang,
      'lastActive' => $lastActive,
    ],
    'inbox' => [
      'soporteUnread' => count(array_filter($db['soporte'] ?? [], function ($m) { return empty($m['leido']); })),
      'sugerenciasUnread' => count(array_filter($db['sugerencias'] ?? [], function ($m) { return empty($m['leido']); })),
    ],
  ]]);
}

if ($action === 'alumnos' && $method === 'GET') {
  $codigos = $db['codigos'] ?? [];
  $out = [];
  foreach ($db['usuarios'] ?? [] as $email => $u) {
    $codeRec = $codigos[$u['codigo_compra'] ?? ''] ?? null;
    $out[] = [
      'email' => $email,
      'nombre' => $u['nombre'] ?? '',
      'verificado' => !empty($u['verificado']),
      'plan' => $codeRec['plan'] ?? null,
      'lang' => ($codeRec['lang'] ?? '') === 'es' ? 'es' : 'en',
      'creado' => $u['creado'] ?? null,
      'actualizado' => $u['actualizado'] ?? null,
      'completionPercent' => !empty($u['verificado']) ? student_completion_percent($u['progreso'] ?? null) : 0,
    ];
  }
  usort($out, function ($a, $b) { return strcmp((string) ($b['actualizado'] ?? $b['creado'] ?? ''), (string) ($a['actualizado'] ?? $a['creado'] ?? '')); });
  respond(200, ['ok' => true, 'alumnos' => $out]);
}

if ($action === 'soporte' && $method === 'GET') {
  $list = array_values($db['soporte'] ?? []);
  usort($list, function ($a, $b) { return strcmp((string) $b['creado'], (string) $a['creado']); });
  respond(200, ['ok' => true, 'mensajes' => $list]);
}

if ($action === 'sugerencias' && $method === 'GET') {
  $list = array_values($db['sugerencias'] ?? []);
  usort($list, function ($a, $b) { return strcmp((string) $b['creado'], (string) $a['creado']); });
  respond(200, ['ok' => true, 'mensajes' => $list]);
}

if ($action === 'marcar-leido' && $method === 'POST') {
  $body = json_body();
  $tipo = (string) ($body['tipo'] ?? '');
  $id = (string) ($body['id'] ?? '');
  if (!in_array($tipo, ['soporte', 'sugerencias'], true) || $id === '' || !isset($db[$tipo][$id])) {
    respond(400, ['ok' => false, 'error' => 'Invalid request.']);
  }
  $db[$tipo][$id]['leido'] = true;
  dmsl_save_db($db);
  respond(200, ['ok' => true]);
}

respond(404, ['ok' => false, 'error' => 'Unknown action.']);
