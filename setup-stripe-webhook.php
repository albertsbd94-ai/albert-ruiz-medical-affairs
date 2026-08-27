<?php
// setup-stripe-webhook.php — activar el código de compra automático (Stripe →
// Brevo) UNA sola vez, desde el navegador. El dueño de la web nunca pega la
// clave en un chat: se guarda directamente en el servidor.
//
// IMPORTANTE: cuando termines, borra este archivo (o protégelo) desde tu
// panel de hosting.

$FUERA = __DIR__ . '/../stripe_webhook_secret.php';
$DENTRO = __DIR__ . '/datos/stripe_webhook_secret.php';

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

$msg = ''; $tono = 'warn';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $k = trim((string) ($_POST['clave'] ?? ''));
  if ($k === '') {
    $msg = 'Pega el "Signing secret" del endpoint de webhook de Stripe.';
  } elseif (strpos($k, 'whsec_') !== 0) {
    $msg = 'Eso no parece un signing secret de Stripe — debe empezar por "whsec_". Cópialo de nuevo desde el endpoint en tu Dashboard de Stripe.';
  } else {
    $donde = guardar($FUERA, $DENTRO, $k);
    if ($donde === false) {
      $msg = 'El secreto tiene el formato correcto, pero el servidor no me deja escribirlo. Revisa los permisos.';
    } else {
      $msg = '¡Listo! El secreto de Stripe ha quedado guardado ' . $donde . '. Los códigos de compra ya se generarán y enviarán automáticamente tras cada pago.';
      $tono = 'ok';
    }
  }
}

$tieneClave = leer($FUERA, $DENTRO) !== '';
?><!doctype html>
<html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>Activar el código de compra automático (Stripe)</title>
<style>
  :root{--bg:#0f1115;--card:#171a21;--tx:#e7e9ee;--mu:#9aa3b2;--ac:#7A00E6;--ok:#2fa46a;--wa:#d08b28}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--tx);
       font:16px/1.6 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:24px}
  .card{background:var(--card);border:1px solid #262b36;border-radius:16px;padding:32px;max-width:620px;width:100%}
  h1{margin:0 0 8px;font-size:22px}
  h2{margin:22px 0 8px;font-size:15px;color:var(--tx)}
  p{color:var(--mu);margin:0 0 16px}
  a{color:var(--ac)}
  code{background:#0d1016;padding:1px 6px;border-radius:5px}
  ol{color:var(--mu);font-size:13.5px;padding-left:20px;margin:0 0 16px;}
  ol li{margin-bottom:6px;}
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
  <h1>Activar el código de compra automático (Stripe)</h1>
  <?php if ($msg): ?><div class="msg <?= $tono === 'ok' ? 'ok' : 'warn' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="estado">
    <span class="pin"><span class="dot <?= $tieneClave ? 'on' : '' ?>"></span> Webhook de Stripe <?= $tieneClave ? '(activo)' : '(sin configurar)' ?></span>
  </div>

  <h2>Paso 1 — Crea el endpoint en Stripe (una sola vez)</h2>
  <ol>
    <li>En tu <a href="https://dashboard.stripe.com/webhooks" target="_blank" rel="noopener">Dashboard de Stripe → Developers → Webhooks</a>, pulsa <strong>Add endpoint</strong>.</li>
    <li>URL del endpoint: <code>https://albertruiz.com/api/stripe-webhook.php</code></li>
    <li>Eventos a escuchar: <code>checkout.session.completed</code> y <code>checkout.session.async_payment_succeeded</code>.</li>
    <li>Guarda el endpoint y abre su página de detalle.</li>
    <li>Copia el <strong>Signing secret</strong> (empieza por <code>whsec_</code>) y pégalo abajo.</li>
  </ol>

  <h2>Paso 2 — Pega el signing secret</h2>
  <?php if ($tieneClave): ?>
    <p>El código de compra ya se está generando y enviando de verdad tras cada pago.
    Por seguridad, cuando termines <strong>borra este archivo (<code>setup-stripe-webhook.php</code>)</strong>
    desde tu panel de hosting. Puedes pegar otro secreto abajo para actualizarlo (por ejemplo, si recreas el endpoint).</p>
  <?php else: ?>
    <p>Se guarda directamente en el servidor — nunca en el navegador, nunca en un chat.</p>
  <?php endif; ?>
  <form method="post">
    <input name="clave" type="password" placeholder="whsec_..." autocomplete="off" required>
    <button type="submit">Guardar</button>
  </form>
</div></body></html>
