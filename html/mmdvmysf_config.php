<?php
require_once __DIR__ . '/auth.php';

$INI_PATH = '/home/pi/MMDVMHost/MMDVMYSF.ini';

$SECTIONS = [
    'General' => [
        ['key' => 'Callsign',    'label' => 'Indicativo',          'type' => 'str'],
        ['key' => 'Id',          'label' => 'DMR ID',               'type' => 'int'],
        ['key' => 'Timeout',     'label' => 'Timeout (s)',          'type' => 'int'],
        ['key' => 'Duplex',      'label' => 'Duplex (0=simplex)',   'type' => 'int'],
        ['key' => 'RFModeHang',  'label' => 'RF Mode Hang (s)',     'type' => 'int'],
        ['key' => 'NetModeHang', 'label' => 'Net Mode Hang (s)',    'type' => 'int'],
        ['key' => 'Daemon',      'label' => 'Daemon (0/1)',         'type' => 'int'],
    ],
    'Info' => [
        ['key' => 'RXFrequency', 'label' => 'Frecuencia RX (Hz)',  'type' => 'int'],
        ['key' => 'TXFrequency', 'label' => 'Frecuencia TX (Hz)',  'type' => 'int'],
        ['key' => 'Power',       'label' => 'Potencia (W)',         'type' => 'int'],
        ['key' => 'Latitude',    'label' => 'Latitud',              'type' => 'str'],
        ['key' => 'Longitude',   'label' => 'Longitud',             'type' => 'str'],
        ['key' => 'Height',      'label' => 'Altura (m)',           'type' => 'int'],
        ['key' => 'Location',    'label' => 'Localización',         'type' => 'str'],
        ['key' => 'Description', 'label' => 'Descripción',          'type' => 'str'],
        ['key' => 'URL',         'label' => 'URL',                  'type' => 'str'],
    ],
    'Modem' => [
        ['key' => 'Protocol',   'label' => 'Protocolo',            'type' => 'str'],
        ['key' => 'UARTPort',   'label' => 'Puerto UART',          'type' => 'select', 'options' => [
            ['label' => '/dev/ttyACM0', 'value' => '/dev/ttyACM0'],
            ['label' => '/dev/ttyACM1', 'value' => '/dev/ttyACM1'],
            ['label' => '/dev/ttyAMA0', 'value' => '/dev/ttyAMA0'],
        ]],
        ['key' => 'UARTSpeed',  'label' => 'Velocidad UART',       'type' => 'select', 'options' => [
            ['label' => '115200', 'value' => '115200'],
            ['label' => '460800', 'value' => '460800'],
        ]],
        ['key' => 'TXInvert',   'label' => 'TX Invertido (0/1)',   'type' => 'int'],
        ['key' => 'RXInvert',   'label' => 'RX Invertido (0/1)',   'type' => 'int'],
        ['key' => 'PTTInvert',  'label' => 'PTT Invertido (0/1)',  'type' => 'int'],
        ['key' => 'TXDelay',    'label' => 'TX Delay (ms)',        'type' => 'int'],
        ['key' => 'RXOffset',   'label' => 'RX Offset',            'type' => 'int'],
        ['key' => 'TXOffset',   'label' => 'TX Offset',            'type' => 'int'],
        ['key' => 'RXLevel',    'label' => 'Nivel RX (%)',         'type' => 'int'],
        ['key' => 'TXLevel',    'label' => 'Nivel TX (%)',         'type' => 'int'],
        ['key' => 'RFLevel',    'label' => 'Nivel RF (%)',         'type' => 'int'],
        ['key' => 'Debug',      'label' => 'Debug (0/1)',          'type' => 'int'],
    ],
    'System Fusion' => [
        ['key' => 'Enable',         'label' => 'Habilitado (0/1)',       'type' => 'int'],
        ['key' => 'LowDeviation',   'label' => 'Low Deviation (0/1)',    'type' => 'int'],
        ['key' => 'SelfOnly',       'label' => 'Solo propio (0/1)',      'type' => 'int'],
        ['key' => 'TXHang',         'label' => 'TX Hang (s)',            'type' => 'int'],
        ['key' => 'RemoteGateway',  'label' => 'Remote Gateway (0/1)',   'type' => 'int'],
    ],
    'System Fusion Network' => [
        ['key' => 'Enable',         'label' => 'Habilitado (0/1)',       'type' => 'int'],
        ['key' => 'LocalAddress',   'label' => 'Dirección local',        'type' => 'str'],
        ['key' => 'LocalPort',      'label' => 'Puerto local',           'type' => 'int'],
        ['key' => 'GatewayAddress', 'label' => 'Dirección YSFGateway',  'type' => 'str'],
        ['key' => 'GatewayPort',    'label' => 'Puerto YSFGateway',     'type' => 'int'],
        ['key' => 'Debug',          'label' => 'Debug (0/1)',            'type' => 'int'],
    ],
    'MQTT' => [
        ['key' => 'Host',      'label' => 'Broker MQTT',         'type' => 'str'],
        ['key' => 'Port',      'label' => 'Puerto MQTT',         'type' => 'int'],
        ['key' => 'Auth',      'label' => 'Autenticación (0/1)', 'type' => 'int'],
        ['key' => 'Username',  'label' => 'Usuario',             'type' => 'str'],
        ['key' => 'Password',  'label' => 'Contraseña',          'type' => 'str'],
        ['key' => 'Keepalive', 'label' => 'Keepalive (s)',       'type' => 'int'],
        ['key' => 'Name',      'label' => 'Nombre cliente',      'type' => 'str'],
    ],
];

$SEC_COLORS = [
    'General'               => '#00ff9f',
    'Info'                  => '#ffb300',
    'Modem'                 => '#00d4ff',
    'System Fusion'         => '#ff7043',
    'System Fusion Network' => '#26c6da',
    'MQTT'                  => '#b57aff',
];

function readIniValues($path, $sections) {
    $values = []; if (!file_exists($path)) return $values;
    $lines = file($path); $currentSec = '';
    foreach ($lines as $line) {
        $s = trim($line);
        if (preg_match('/^\[(.+)\]/', $s, $m)) { $currentSec = $m[1]; continue; }
        if ($s === '' || $s[0] === '#' || $s[0] === ';') continue;
        if (strpos($s, '=') !== false && isset($sections[$currentSec])) {
            [$k, $v] = explode('=', $s, 2); $k = trim($k); $v = trim($v);
            foreach ($sections[$currentSec] as $field)
                if (strtolower($field['key']) === strtolower($k))
                    $values[$currentSec][$field['key']] = $v;
        }
    }
    return $values;
}

function writeIniValues($path, $sections, $newValues) {
    if (!file_exists($path)) return ['ok' => false, 'msg' => 'Fichero no encontrado'];
    $lines = file($path); $currentSec = ''; $result = [];
    foreach ($lines as $line) {
        $s = trim($line);
        if (preg_match('/^\[(.+)\]/', $s, $m)) { $currentSec = $m[1]; $result[] = $line; continue; }
        if ($s === '' || $s[0] === '#' || $s[0] === ';') { $result[] = $line; continue; }
        if (strpos($s, '=') !== false && isset($sections[$currentSec])) {
            [$k] = explode('=', $s, 2); $k = trim($k); $matched = false;
            foreach ($sections[$currentSec] as $field) {
                if (strtolower($field['key']) === strtolower($k)) {
                    if (isset($newValues[$currentSec][$field['key']])) {
                        $result[] = $k . '=' . $newValues[$currentSec][$field['key']] . "\n";
                        $matched = true;
                    }
                    break;
                }
            }
            if (!$matched) $result[] = $line;
        } else { $result[] = $line; }
    }
    file_put_contents($path, implode('', $result));
    return ['ok' => true, 'msg' => 'Configuración guardada correctamente'];
}

function postKey($sec, $key) { return str_replace([' ', '-'], '_', $sec) . '_' . $key; }

$message = ''; $msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = []; $newValues = [];
    foreach ($SECTIONS as $sec => $fields) {
        foreach ($fields as $field) {
            $val = trim($_POST[postKey($sec, $field['key'])] ?? '');
            if ($field['type'] === 'int' && $val !== '' && !is_numeric($val))
                $errors[] = "[{$sec}] '{$field['label']}' debe ser un número.";
            else $newValues[$sec][$field['key']] = $val;
        }
    }
    if ($errors) { $message = implode('<br>', $errors); $msgType = 'error'; }
    else { $res = writeIniValues($INI_PATH, $SECTIONS, $newValues); $message = $res['msg']; $msgType = $res['ok'] ? 'success' : 'error'; }
}
$values = readIniValues($INI_PATH, $SECTIONS);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MMDVMYSF Config · EA3EIZ</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:#0a0e14; --surface:#111720; --border:#1e2d3d;
    --green:#00ff9f; --red:#ff4560; --amber:#ffb300; --cyan:#00d4ff; --violet:#b57aff;
    --text:#a8b9cc; --text-dim:#4a5568;
    --font-mono:'Share Tech Mono',monospace; --font-ui:'Rajdhani',sans-serif;
  }
  * { box-sizing: border-box; }
  body { background: var(--bg); color: var(--text); font-family: var(--font-ui); margin: 0; min-height: 100vh; }
  .ctrl-header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 1rem 2rem; display: flex; align-items: center; gap: 1rem; }
  .ctrl-header img { height: 40px; }
  .ctrl-header h1 { font-weight: 700; font-size: 1.4rem; letter-spacing: .08em; color: #e2eaf5; margin: 0; text-transform: uppercase; }
  .page-body { padding: 2rem; max-width: 900px; margin: 0 auto; }
  .ini-path { font-family: var(--font-mono); font-size: .75rem; color: var(--text-dim); background: #0d1e2a; border: 1px solid var(--border); border-radius: 4px; padding: .4rem .8rem; margin-bottom: 1.5rem; }
  .ini-path span { color: var(--amber); }
  .alert-custom { border-radius: 6px; padding: .8rem 1.2rem; margin-bottom: 1.5rem; font-family: var(--font-mono); font-size: .85rem; border: 1px solid; }
  .alert-success { background: rgba(0,255,159,.08); border-color: var(--green); color: var(--green); }
  .alert-error   { background: rgba(255,69,96,.08);  border-color: var(--red);   color: var(--red); }
  .tabs { display: flex; gap: .3rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
  .tab-btn { font-family: var(--font-mono); font-size: .75rem; letter-spacing: .08em; text-transform: uppercase; padding: .45rem 1rem; border-radius: 4px 4px 0 0; border: 1px solid var(--border); border-bottom: none; background: var(--surface); color: var(--text-dim); cursor: pointer; }
  .tab-btn.active { background: #0d1e2a; border-bottom: 2px solid; }
  .tab-panel { display: none; }
  .tab-panel.active { display: block; }
  .sec-card { background: #0d1e2a; border: 1px solid var(--border); border-radius: 0 6px 6px 6px; padding: 1.5rem; }
  .field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  @media (max-width: 600px) { .field-grid { grid-template-columns: 1fr; } }
  label { font-family: var(--font-ui); font-size: .95rem; color: var(--text); display: block; margin-bottom: .3rem; }
  input[type=text], input[type=number] { width: 100%; background: var(--surface); border: 1px solid var(--border); border-radius: 4px; color: var(--green); font-family: var(--font-mono); font-size: .9rem; padding: .5rem .8rem; outline: none; transition: border-color .2s; }
  input[type=text]:focus, input[type=number]:focus { border-color: var(--green); }
  select.field-select { width: 100%; background: var(--surface); border: 1px solid var(--border); border-radius: 4px; color: var(--green); font-family: var(--font-mono); font-size: .9rem; padding: .5rem .8rem; outline: none; cursor: pointer; }
  select.field-select option { background: var(--surface); }
  .field-hint { font-family: var(--font-mono); font-size: .65rem; color: var(--text-dim); margin-top: .2rem; }
  .btn-row { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-top: 1.5rem; }
  .btn-save { background: #28a745; color: #fff; border: none; border-radius: 6px; font-family: var(--font-ui); font-weight: 700; font-size: 1rem; letter-spacing: .1em; text-transform: uppercase; padding: .75rem 2.5rem; cursor: pointer; transition: background .2s; }
  .btn-save:hover { background: #218838; }
  .btn-back { background: #28a745; color: #fff; border: none; border-radius: 6px; font-family: var(--font-mono); font-size: .8rem; letter-spacing: .08em; text-transform: uppercase; padding: .4rem 1.2rem; text-decoration: none; margin-left: auto; transition: background .2s; display: inline-block; }
  .btn-back:hover { background: #218838; color: #fff; }
  .btn-reload { background: var(--surface); color: var(--text-dim); border: 1px solid var(--border); border-radius: 6px; font-family: var(--font-ui); font-size: 1rem; text-transform: uppercase; padding: .75rem 2rem; cursor: pointer; text-decoration: none; display: inline-block; }
  .note { font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); }
</style>
</head>
<body>
<header class="ctrl-header">
  <img src="Logo_ea3eiz.png" alt="EA3EIZ">
  <h1>MMDVMYSF Config Editor</h1>
  <a href="mmdvm.php" class="btn-back">← Volver</a>
</header>

<div class="page-body">
  <div class="ini-path">📄 Fichero: <span><?= htmlspecialchars($INI_PATH) ?></span></div>

  <?php if ($message): ?>
  <div class="alert-custom alert-<?= $msgType ?>">
    <?= $msgType === 'success' ? '✔ ' : '✖ ' ?><?= $message ?>
    <?php if ($msgType === 'success'): ?><br><small>Reinicia MMDVMHost YSF para aplicar los cambios.</small><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!file_exists($INI_PATH)): ?>
  <div class="alert-custom alert-error">✖ Fichero no encontrado: <?= htmlspecialchars($INI_PATH) ?></div>
  <?php else: ?>

  <form method="POST">
    <div class="tabs">
      <?php $first = true; foreach ($SECTIONS as $sec => $fields):
        $color = $SEC_COLORS[$sec] ?? '#a8b9cc';
        $tabId = 'tab_' . str_replace([' ','-'], '_', $sec);
      ?>
      <button type="button" class="tab-btn <?= $first ? 'active' : '' ?>"
              style="<?= $first ? "border-bottom-color:{$color};color:{$color}" : '' ?>"
              onclick="switchTab(this,'<?= $tabId ?>','<?= $color ?>')">
        <?= htmlspecialchars($sec) ?>
      </button>
      <?php $first = false; endforeach; ?>
    </div>

    <?php $first = true; foreach ($SECTIONS as $sec => $fields):
      $color = $SEC_COLORS[$sec] ?? '#a8b9cc';
      $tabId = 'tab_' . str_replace([' ','-'], '_', $sec);
    ?>
    <div class="tab-panel <?= $first ? 'active' : '' ?>" id="<?= $tabId ?>">
      <div class="sec-card" style="border-top: 3px solid <?= $color ?>;">
        <div class="field-grid">
          <?php foreach ($fields as $field):
            $pKey = postKey($sec, $field['key']);
            $val  = $values[$sec][$field['key']] ?? '';
          ?>
          <div>
            <label for="<?= $pKey ?>"><?= htmlspecialchars($field['label']) ?></label>
            <?php if ($field['type'] === 'select'): ?>
              <select id="<?= $pKey ?>" name="<?= $pKey ?>" class="field-select" style="color:<?= $color ?>">
                <?php foreach ($field['options'] as $opt): ?>
                <option value="<?= htmlspecialchars($opt['value']) ?>" <?= $val == $opt['value'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($opt['label']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="<?= $field['type'] === 'int' ? 'number' : 'text' ?>"
                     id="<?= $pKey ?>" name="<?= $pKey ?>"
                     value="<?= htmlspecialchars($val) ?>">
            <?php endif; ?>
            <div class="field-hint"><?= htmlspecialchars($field['key']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php $first = false; endforeach; ?>

    <div class="btn-row">
      <button type="submit" class="btn-save">💾 Guardar</button>
      <a href="mmdvmysf_config.php" class="btn-reload">🔄 Recargar</a>
      <span class="note">Los cambios requieren reiniciar MMDVMHost YSF</span>
    </div>
  </form>
  <?php endif; ?>
</div>

<script>
function switchTab(btn, tabId, color) {
  document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('active'); b.style.borderBottomColor=''; b.style.color=''; });
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active'); btn.style.borderBottomColor=color; btn.style.color=color;
  document.getElementById(tabId).classList.add('active');
}
</script>
</body>
</html>
