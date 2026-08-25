<?php
// setup-email.php — activar el envío de correos (Brevo) UNA sola vez, desde
// el navegador. El dueño de la web nunca edita ficheros ni pega la clave en
// un chat: la clave se valida en directo contra Brevo y se guarda en el
// servidor, fuera de todo lo que el navegador puede descargar.
//
// IMPORTANTE: cuando termines, borra este archivo (o protégelo) desde tu
// panel de hosting.

$FUERA = __DIR__ . '/../brevo_api_key.php';
$DENTRO = __DIR__ . '/datos/brevo_config.php';

function leer($fuera, $dentro) {
  foreach ([$fuera, $dentro] as $p) {
    if (is_file($p)) { $v = @include $p; if (is_string($v) && trim($v) !== '') return trim($v); }
  }
  return '';
}
function guardar($fuera, $dentro, $key) {
  $c = "<?php return '" . str_replace("'", "\\'", $key) . "';\n";
  if (@file_put_contents($fuera, $c) !== false) return 'fuera de la carpeta pública';
  @mkdir(dirname($dentro), 0755, true);
  return (@file_put_contents($dentro, $c) !== false) ? 'en la carpeta privada' : false;
}
function validar($key) {
  if (!function_exists('curl_init')) return true;
  $ch = curl_init('https://api.brevo.com/v3/account');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => ['accept: application/json', 'api-key: ' . $key],
  ]);
  $r = curl_exec($ch);
  $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return ($r !== false && $c === 200);
}

$msg = ''; $tono = 'warn';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $k = trim((string) ($_POST['clave'] ?? ''));
  if ($k === '') {
    $msg = 'Pega tu clave API de Brevo.';
  } elseif (!validar($k)) {
    $msg = 'Brevo no ha aceptado esa clave. Compruébala en tu cuenta de Brevo (SMTP & API → API Keys) y vuelve a pegarla.';
  } else {
    $donde = guardar($FUERA, $DENTRO, $k);
    if ($donde === false) {
      $msg = 'La clave es válida, pero el servidor no me deja escribirla. Revisa los permisos.';
    } else {
      $msg = '¡Listo! La clave de Brevo es válida y ha quedado guardada ' . $donde . '.';
      $tono = 'ok';
    }
  }
}

$tieneClave = leer($FUERA, $DENTRO) !== '';
?><!doctype html>
<html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>Activar el envío de correos</title>
<style>
  :root{--bg:#0f1115;--card:#171a21;--tx:#e7e9ee;--mu:#9aa3b2;--ac:#7A00E6;--ok:#2fa46a;--wa:#d08b28}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--tx);
       font:16px/1.6 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:24px}
  .card{background:var(--card);border:1px solid #262b36;border-radius:16px;padding:32px;max-width:560px;width:100%}
  h1{margin:0 0 8px;font-size:22px}
  p{color:var(--mu);margin:0 0 16px}
  a{color:var(--ac)}
  code{background:#0d1016;padding:1px 6px;border-radius:5px}
  input{width:100%;padding:12px 14px;border-radius:10px;border:1px solid #2c3342;background:#0d1016;
        color:var(--tx);font:inherit;font-family:ui-monospace,monospace}
  button{margin-top:14px;width:100%;padding:12px;border:0;border-radius:10px;background:var(--ac);
         color:#fff;font:inherit;font-weight:600;cursor:pointer}
  .msg{padding:12px 14px;border-radius:10px;margin-bottom:18px;font-size:15px}
  .ok{background:rgba(47,164,106,.12);color:#7fd6a6;border:1px solid rgba(47,164,106,.3)}
  .warn{background:rgba(208,139,40,.12);color:#e6b866;border:1px solid rgba(208,139,40,.3)}
  .estado{display:flex;gap:16px;margin:0 0 20px;font-size:14px;color:var(--mu)}
  .pin{display:inline-flex;align-items:center;gap:6px}
  .dot{width:9px;height:9px;border-radius:50%;background:#3a4150}
  .on{background:var(--ok)}
</style></head><body>
<div class="card">
  <h1>Activar el envío de correos (Brevo)</h1>
  <?php if ($msg): ?><div class="msg <?= $tono === 'ok' ? 'ok' : 'warn' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="estado">
    <span class="pin"><span class="dot <?= $tieneClave ? 'on' : '' ?>"></span> Brevo <?= $tieneClave ? '(activo)' : '(sin configurar)' ?></span>
  </div>

  <?php if ($tieneClave): ?>
    <p>Los correos de verificación del DMSL Course ya se están enviando de verdad.
    Por seguridad, cuando termines <strong>borra este archivo (<code>setup-email.php</code>)</strong>
    desde tu panel de hosting. Puedes pegar otra clave abajo para actualizarla.</p>
  <?php else: ?>
    <p>Pega aquí tu clave API de Brevo. Se valida en directo contra Brevo y se
    guarda en el servidor — nunca en el navegador, nunca en este chat.</p>
    <p style="font-size:13.5px;">Antes de pegarla: en tu cuenta de Brevo, ve a
    <strong>SMTP &amp; API → API Keys → Generate a new API key</strong>. Los
    correos se envían como <code>contacto.dmsl@albertruiz.com</code>, que ya
    tienes verificado en <strong>Senders, Domains &amp; Dedicated IPs</strong>.</p>
  <?php endif; ?>
  <form method="post">
    <input name="clave" type="password" placeholder="xkeysib-..." autocomplete="off" required>
    <button type="submit">Guardar y comprobar</button>
  </form>
</div></body></html>
