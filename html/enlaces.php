<?php
// =============================================
// enlaces.php  –  Panel de Acceso Rápido
// EA3EIZ · Associació ADER
// =============================================

$JSON_FILE = __DIR__ . '/enlaces.json';

// ── AJAX handler ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $links = file_exists($JSON_FILE) ? (json_decode(file_get_contents($JSON_FILE), true) ?: []) : [];

    switch ($_POST['action']) {

        case 'save_link':
            $key_old = $_POST['key_old'] ?? '';
            $icono   = $_POST['icono']   ?? '';
            $nombre  = trim($_POST['nombre'] ?? '');
            $url     = trim($_POST['url']    ?? '');
            $color   = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#0e639c';
            $fila    = max(1, (int)($_POST['fila'] ?? 1));
            $col     = max(0, min(9, (int)($_POST['col'] ?? 0)));

            if (!$nombre || !$url) {
                echo json_encode(['ok' => false, 'msg' => 'Nombre y URL son obligatorios']);
                exit;
            }
            $key_new = trim("$icono $nombre");
            if ($key_old && $key_old !== $key_new && isset($links[$key_old])) {
                unset($links[$key_old]);
            }
            $links[$key_new] = ['url' => $url, 'color' => $color, 'fila' => $fila, 'col' => $col];
            file_put_contents($JSON_FILE, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo json_encode(['ok' => true]);
            break;

        case 'delete_link':
            $key = $_POST['key'] ?? '';
            if (!isset($links[$key])) {
                echo json_encode(['ok' => false, 'msg' => 'Enlace no encontrado']);
                exit;
            }
            unset($links[$key]);
            file_put_contents($JSON_FILE, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo json_encode(['ok' => true]);
            break;

        case 'exec_command':
            $cmd = trim($_POST['cmd'] ?? '');
            if (!$cmd) {
                echo json_encode(['ok' => false, 'msg' => 'Comando vacío']);
                exit;
            }
            // Solo permitir ffplay / ffmpeg
            if (!preg_match('/^(ffplay|ffmpeg|\/opt\/homebrew\/bin\/ffplay|\/usr\/(local\/)?bin\/ffplay)\b/', $cmd)) {
                echo json_encode(['ok' => false, 'msg' => 'Solo se permiten comandos ffplay/ffmpeg']);
                exit;
            }
            exec("DISPLAY=:0.0 nohup $cmd > /tmp/enlaces_cam.log 2>&1 &");
            echo json_encode(['ok' => true, 'msg' => 'Ejecutado en servidor (DISPLAY=:0.0)']);
            break;

        default:
            echo json_encode(['ok' => false, 'msg' => 'Acción desconocida']);
    }
    exit;
}

// ── Carga y preparación de datos ──────────────
$links = file_exists($JSON_FILE) ? (json_decode(file_get_contents($JSON_FILE), true) ?: []) : [];
uasort($links, fn($a, $b) => $a['fila'] !== $b['fila'] ? $a['fila'] - $b['fila'] : $a['col'] - $b['col']);

$max_fila = 1;
$max_col  = 2;
foreach ($links as $d) {
    $max_fila = max($max_fila, (int)$d['fila']);
    $max_col  = max($max_col,  (int)$d['col']);
}
$num_cols = $max_col + 1;

function isCommand(string $url): bool {
    return !str_starts_with(trim($url), 'http://') && !str_starts_with(trim($url), 'https://');
}

// Para pasar datos al JS de forma segura
$links_js = json_encode($links, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

// Lista de iconos disponibles
$iconos = ["🎥","📷","📡","🌍","🛰️","📹","📺","🔴","🟢","🛡️","👁️","📟","🔗","🔊","🌐","🔵","🟡",
           "🔑","🔔","🔓","🔒","🏠","💾","📸","🖥️","✅","📲","⚠️","❌","⭕","⚡","🔆","🚫","⛔",
           "🔋","🪫","⚙️","🛠️","💿","📌","⭐","🗂️","🧲","🔁","🎙️","🎦","🎞️","🚀","♾️","🗑️",
           "🖨️","📭","🟣","⚫","▶️","⏸️","🗼","🔦","🎮","🕹️","✨","🔥","🚁","🍺","🍷","🧿",
           "❇️","✳️","🎲","🎯","👽","☕","⌚","🔌","📂"];
$iconos_js = json_encode($iconos, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔗 MIS ENLACES — EA3EIZ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">

    <style>
        /* ══ CSS VARIABLES ══════════════════════════════════════ */
        :root {
            --bg-primary:    #0d0f14;
            --bg-secondary:  #13151e;
            --bg-card:       #191b27;
            --bg-card2:      #1e2030;
            --border:        #2a2d3e;
            --border-glow:   #3a4d7a;
            --text:          #dde3ef;
            --text-muted:    #6b7899;
            --accent-cyan:   #00d4ff;
            --accent-blue:   #3a7bd5;
            --accent-green:  #00e676;
            --accent-amber:  #f0a500;
            --font-display:  'Orbitron', monospace;
            --font-ui:       'Rajdhani', sans-serif;
            --font-mono:     'Share Tech Mono', monospace;
            --radius:        6px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg-primary);
            color: var(--text);
            font-family: var(--font-ui);
            min-height: 100vh;
        }

        /* ══ SCROLLBAR ══════════════════════════════════════════ */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-secondary); }
        ::-webkit-scrollbar-thumb { background: #3a4a6a; border-radius: 3px; }

        /* ══ HEADER ═════════════════════════════════════════════ */
        .panel-header {
            background: linear-gradient(135deg, #0a0c11 0%, #131627 50%, #0a0c11 100%);
            border-bottom: 1px solid var(--border);
            padding: 18px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .header-left { display: flex; align-items: center; gap: 14px; }

        .header-icon {
            font-size: 2rem;
            filter: drop-shadow(0 0 8px rgba(0, 212, 255, 0.6));
        }

        .panel-title {
            font-family: var(--font-display);
            font-size: 1.4rem;
            font-weight: 900;
            letter-spacing: 3px;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .panel-subtitle {
            font-family: var(--font-mono);
            font-size: 0.7rem;
            color: var(--text-muted);
            letter-spacing: 2px;
            margin-top: 3px;
        }

        .header-badge {
            background: var(--bg-card2);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 5px 14px;
            font-family: var(--font-mono);
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        .header-badge span { color: var(--accent-cyan); font-weight: bold; }

        /* ══ TOOLBAR ════════════════════════════════════════════ */
        .toolbar {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            padding: 10px 28px;
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .toolbar-label {
            font-family: var(--font-mono);
            font-size: 0.7rem;
            color: var(--text-muted);
            letter-spacing: 1px;
            margin-right: 4px;
        }

        .btn-tool {
            font-family: var(--font-ui);
            font-weight: 700;
            font-size: 0.82rem;
            letter-spacing: 0.5px;
            padding: 6px 15px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: filter 0.18s, transform 0.15s;
            text-transform: uppercase;
        }
        .btn-tool:hover  { filter: brightness(1.25); transform: translateY(-1px); }
        .btn-tool:active { filter: brightness(0.9);  transform: translateY(0); }

        .btn-add    { background: #0e639c; color: #fff; }
        .btn-edit   { background: #1a5c35; color: #fff; }
        .btn-delete { background: #7a1c14; color: #fff; }

        /* ══ MAIN GRID ══════════════════════════════════════════ */
        .grid-wrapper {
            padding: 22px 28px 30px;
        }

        .links-grid {
            display: grid;
            grid-template-columns: repeat(<?= $num_cols ?>, 1fr);
            gap: 7px;
        }

        /* ══ LINK BUTTONS ═══════════════════════════════════════ */
        .link-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 11px 14px;
            border-radius: var(--radius);
            border: 1px solid rgba(255,255,255,0.08);
            cursor: pointer;
            font-family: var(--font-ui);
            font-weight: 700;
            font-size: 0.88rem;
            color: #fff;
            text-align: center;
            line-height: 1.3;
            min-height: 52px;
            word-break: break-word;
            transition: filter 0.18s ease, transform 0.15s ease, box-shadow 0.18s ease;
            text-shadow: 0 1px 3px rgba(0,0,0,0.6);
            box-shadow: 0 2px 6px rgba(0,0,0,0.35), inset 0 1px 0 rgba(255,255,255,0.1);
            position: relative;
            overflow: hidden;
        }

        .link-btn::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
        }

        .link-btn:hover {
            filter: brightness(1.28);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.15);
        }

        .link-btn:active {
            transform: translateY(0);
            filter: brightness(0.88);
        }

        .link-btn.is-command::after {
            content: '⚡';
            position: absolute;
            bottom: 3px;
            right: 5px;
            font-size: 0.6rem;
            opacity: 0.65;
        }

        /* ══ MODALS ═════════════════════════════════════════════ */
        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border-glow);
            border-radius: 8px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.7);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-card2));
            border-bottom: 1px solid var(--border);
            padding: 14px 20px;
            border-radius: 8px 8px 0 0;
        }

        .modal-title {
            font-family: var(--font-display);
            font-size: 0.92rem;
            color: var(--accent-cyan);
            letter-spacing: 2px;
        }

        .btn-close { filter: invert(1) brightness(0.6); }
        .btn-close:hover { filter: invert(1) brightness(1); }

        .modal-body { padding: 20px; }
        .modal-footer {
            border-top: 1px solid var(--border);
            padding: 12px 20px;
            background: var(--bg-secondary);
            border-radius: 0 0 8px 8px;
        }

        /* Form elements */
        .form-label {
            font-family: var(--font-mono);
            font-size: 0.73rem;
            color: var(--text-muted);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 5px;
            display: block;
        }

        .form-control, .form-select {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            color: var(--text);
            font-family: var(--font-mono);
            font-size: 0.83rem;
            border-radius: var(--radius);
            padding: 7px 10px;
        }
        .form-control:focus, .form-select:focus {
            background: var(--bg-secondary);
            border-color: var(--accent-blue);
            color: var(--text);
            box-shadow: 0 0 0 2px rgba(58,123,213,0.3);
            outline: none;
        }
        .form-control::placeholder { color: var(--text-muted); }

        /* Color row */
        .color-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .color-swatch {
            width: 38px;
            height: 36px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            flex-shrink: 0;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .color-swatch:hover { border-color: var(--accent-cyan); }
        .color-input-hidden { position: absolute; opacity: 0; width: 0; height: 0; }

        /* Emoji picker */
        .emoji-selected-display {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 6px 12px;
            font-size: 1.3rem;
            cursor: pointer;
            min-width: 52px;
            text-align: center;
            transition: border-color 0.2s;
        }
        .emoji-selected-display:hover { border-color: var(--accent-blue); }

        .emoji-picker-panel {
            display: none;
            grid-template-columns: repeat(9, 1fr);
            gap: 3px;
            max-height: 170px;
            overflow-y: auto;
            background: var(--bg-secondary);
            border: 1px solid var(--border-glow);
            border-radius: var(--radius);
            padding: 8px;
            margin-top: 6px;
        }
        .emoji-picker-panel.open { display: grid; }

        .emoji-opt {
            background: none;
            border: 1px solid transparent;
            border-radius: 4px;
            padding: 3px;
            cursor: pointer;
            font-size: 1.15rem;
            text-align: center;
            transition: all 0.12s;
            line-height: 1.4;
        }
        .emoji-opt:hover  { background: rgba(58,123,213,0.25); border-color: var(--accent-blue); }
        .emoji-opt.active { background: rgba(0,212,255,0.2);   border-color: var(--accent-cyan); }

        /* Position inputs */
        .pos-row {
            display: flex;
            gap: 10px;
        }
        .pos-row .form-control { text-align: center; }

        /* Divider */
        .modal-divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 14px 0;
        }

        /* Command display */
        .cmd-display {
            background: #0a0c11;
            border: 1px solid var(--border);
            border-left: 3px solid var(--accent-amber);
            border-radius: var(--radius);
            padding: 12px 14px;
            font-family: var(--font-mono);
            font-size: 0.78rem;
            color: var(--accent-amber);
            word-break: break-all;
            line-height: 1.6;
        }

        .cmd-type-badge {
            display: inline-block;
            font-family: var(--font-mono);
            font-size: 0.68rem;
            padding: 2px 8px;
            border-radius: 12px;
            margin-bottom: 10px;
        }
        .cmd-type-web  { background: rgba(0,230,118,0.15); color: var(--accent-green); border: 1px solid rgba(0,230,118,0.3); }
        .cmd-type-cmd  { background: rgba(240,165,0,0.15); color: var(--accent-amber); border: 1px solid rgba(240,165,0,0.3); }

        /* Modal buttons */
        .btn-modal-ok     { background: #0e639c; color: #fff; border: none; font-family: var(--font-ui); font-weight: 700; font-size: 0.85rem; letter-spacing: 0.5px; padding: 7px 18px; border-radius: var(--radius); cursor: pointer; transition: filter 0.18s; }
        .btn-modal-ok:hover { filter: brightness(1.2); }

        .btn-modal-warn   { background: var(--accent-amber); color: #000; border: none; font-family: var(--font-ui); font-weight: 700; font-size: 0.85rem; padding: 7px 18px; border-radius: var(--radius); cursor: pointer; transition: filter 0.18s; }
        .btn-modal-warn:hover { filter: brightness(1.15); }

        .btn-modal-danger { background: #9b2c2c; color: #fff; border: none; font-family: var(--font-ui); font-weight: 700; font-size: 0.85rem; padding: 7px 18px; border-radius: var(--radius); cursor: pointer; transition: filter 0.18s; }
        .btn-modal-danger:hover { filter: brightness(1.2); }

        .btn-modal-cancel { background: var(--bg-card2); color: var(--text-muted); border: 1px solid var(--border); font-family: var(--font-ui); font-weight: 600; font-size: 0.85rem; padding: 7px 16px; border-radius: var(--radius); cursor: pointer; transition: all 0.18s; }
        .btn-modal-cancel:hover { background: var(--bg-card); color: var(--text); }

        .btn-copy { background: #2d3458; color: var(--text); border: 1px solid var(--border); font-family: var(--font-mono); font-size: 0.78rem; padding: 5px 12px; border-radius: var(--radius); cursor: pointer; transition: all 0.18s; }
        .btn-copy:hover { background: var(--accent-blue); color: #fff; border-color: var(--accent-blue); }
        .btn-copy.copied { background: #1a5c35; color: var(--accent-green); border-color: var(--accent-green); }

        /* ══ TOAST ══════════════════════════════════════════════ */
        .toast-area {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .toast-item {
            background: var(--bg-card2);
            border: 1px solid var(--border-glow);
            border-radius: var(--radius);
            padding: 11px 18px;
            font-family: var(--font-ui);
            font-size: 0.88rem;
            color: var(--text);
            min-width: 240px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            animation: slideInToast 0.25s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toast-item.ok     { border-left: 3px solid var(--accent-green); }
        .toast-item.error  { border-left: 3px solid #e74856; }
        .toast-item.info   { border-left: 3px solid var(--accent-cyan); }

        @keyframes slideInToast {
            from { transform: translateX(40px); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }

        /* ══ FOOTER ═════════════════════════════════════════════ */
        .panel-footer {
            text-align: center;
            padding: 14px;
            border-top: 1px solid var(--border);
            font-family: var(--font-mono);
            font-size: 0.68rem;
            color: var(--text-muted);
            letter-spacing: 1px;
        }

        /* ══ UTILITIES ══════════════════════════════════════════ */
        .text-cyan  { color: var(--accent-cyan); }
        .text-amber { color: var(--accent-amber); }
        .mb-10 { margin-bottom: 10px; }
        .mt-10 { margin-top: 10px; }
        .note-text {
            font-family: var(--font-mono);
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 8px;
            line-height: 1.5;
        }
    </style>
</head>
<body>

<!-- ══════════════════════════════════════════════
     HEADER
══════════════════════════════════════════════ -->
<div class="panel-header">
    <div class="header-left">
        <div class="header-icon">🔗</div>
        <div>
            <div class="panel-title">MIS ENLACES</div>
            <div class="panel-subtitle">PANEL DE ACCESO RÁPIDO · EA3EIZ</div>
        </div>
    </div>
    <div class="header-badge">
        TOTAL: <span id="totalCount"><?= count($links) ?></span> ENLACES
    </div>
</div>

<!-- ══════════════════════════════════════════════
     TOOLBAR
══════════════════════════════════════════════ -->
<div class="toolbar">
    <span class="toolbar-label">GESTIÓN:</span>
    <button class="btn-tool btn-add"    onclick="openAddModal()">✅ Agregar</button>
    <button class="btn-tool btn-edit"   onclick="openEditModal()">✏️ Editar</button>
    <button class="btn-tool btn-delete" onclick="openDeleteModal()">🗑️ Eliminar</button>
</div>

<!-- ══════════════════════════════════════════════
     GRID PRINCIPAL
══════════════════════════════════════════════ -->
<div class="grid-wrapper">
    <div class="links-grid" id="linksGrid">
<?php foreach ($links as $key => $data):
    $url    = $data['url']   ?? '#';
    $color  = $data['color'] ?? '#0e639c';
    $fila   = (int)($data['fila'] ?? 1);
    $col    = (int)($data['col']  ?? 0);
    $isCmd  = isCommand($url);
    $cmdClass = $isCmd ? ' is-command' : '';
    $keyEsc = htmlspecialchars($key, ENT_QUOTES);
    $urlEsc = htmlspecialchars($url, ENT_QUOTES);
    $colPos = $col + 1;
?>
        <button
            class="link-btn<?= $cmdClass ?>"
            style="background:<?= htmlspecialchars($color, ENT_QUOTES) ?>; grid-row:<?= $fila ?>; grid-column:<?= $colPos ?>;"
            onclick="handleLinkClick(<?= htmlspecialchars(json_encode($url, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>, <?= $isCmd ? 'true' : 'false' ?>, <?= htmlspecialchars(json_encode($key, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)"
            title="<?= $urlEsc ?>">
            <?= htmlspecialchars($key, ENT_QUOTES) ?>
        </button>
<?php endforeach; ?>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     TOAST AREA
══════════════════════════════════════════════ -->
<div class="toast-area" id="toastArea"></div>

<!-- ══════════════════════════════════════════════
     MODAL: CLICK EN ENLACE WEB / COMANDO
══════════════════════════════════════════════ -->
<div class="modal fade" id="linkModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <span class="modal-title" id="linkModalTitle">ENLACE</span>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="linkTypeBadge" class="cmd-type-badge mb-10"></div>
        <div class="cmd-display" id="linkUrlDisplay"></div>
        <p class="note-text" id="linkModalNote"></p>
      </div>
      <div class="modal-footer">
        <button class="btn-copy" id="btnCopyLink" onclick="copyLinkCmd()">📋 Copiar</button>
        <button class="btn-modal-warn" id="btnExecCmd" onclick="execCommand()" style="display:none;">⚡ Ejecutar en Pi</button>
        <button class="btn-modal-ok"  id="btnOpenUrl"  onclick="openUrl()"    style="display:none;">🌐 Abrir en navegador</button>
        <button class="btn-modal-cancel" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL: AGREGAR ENLACE
══════════════════════════════════════════════ -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <span class="modal-title">✅ AGREGAR ENLACE</span>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <!-- Icono + Nombre -->
        <label class="form-label">ICONO + NOMBRE DEL ENLACE</label>
        <div style="display:flex; gap:8px; margin-bottom:10px;">
            <div>
                <div class="emoji-selected-display" id="addEmojiDisplay" onclick="toggleEmojiPicker('add')">📡</div>
                <input type="hidden" id="addEmojiValue" value="📡">
            </div>
            <input type="text" class="form-control" id="addNombre" placeholder="Nombre del enlace" style="flex:1;">
        </div>
        <div class="emoji-picker-panel" id="addEmojiPanel"></div>

        <hr class="modal-divider">

        <!-- URL -->
        <label class="form-label">URL O COMANDO</label>
        <input type="text" class="form-control mb-10" id="addUrl"
               placeholder="https://... o ffplay -x 800 -y 450 rtsp://...">
        <p class="note-text">URLs http/https se abren en el navegador. Comandos ffplay se ejecutan en el servidor.</p>

        <hr class="modal-divider">

        <!-- Color -->
        <label class="form-label">COLOR DEL BOTÓN</label>
        <div class="color-row mb-10">
            <div class="color-swatch" id="addColorSwatch" style="background:#0e639c;"
                 onclick="document.getElementById('addColorPicker').click()"></div>
            <input type="color" class="color-input-hidden" id="addColorPicker" value="#0e639c"
                   oninput="updateColorSwatch('add', this.value)">
            <input type="text" class="form-control" id="addColorText" value="#0e639c"
                   maxlength="7" placeholder="#rrggbb"
                   oninput="syncColorFromText('add', this.value)">
        </div>

        <!-- Posición -->
        <label class="form-label">POSICIÓN EN EL GRID (fila / columna)</label>
        <div class="pos-row">
            <div style="flex:1;">
                <label class="form-label" style="font-size:0.65rem;">FILA (desde 1)</label>
                <input type="number" class="form-control" id="addFila" value="1" min="1" max="50">
            </div>
            <div style="flex:1;">
                <label class="form-label" style="font-size:0.65rem;">COLUMNA (desde 0)</label>
                <input type="number" class="form-control" id="addCol"  value="0" min="0" max="9">
            </div>
        </div>

      </div>
      <div class="modal-footer">
        <button class="btn-modal-ok"     onclick="saveAdd()">✅ Agregar</button>
        <button class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL: EDITAR ENLACE
══════════════════════════════════════════════ -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <span class="modal-title">✏️ EDITAR ENLACE</span>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <!-- Seleccionar enlace -->
        <label class="form-label">SELECCIONAR ENLACE</label>
        <select class="form-select mb-10" id="editSelector" onchange="loadEditLink(this.value)"></select>
        <input type="hidden" id="editKeyOld">

        <hr class="modal-divider">

        <!-- Icono + Nombre -->
        <label class="form-label">ICONO + NOMBRE</label>
        <div style="display:flex; gap:8px; margin-bottom:10px;">
            <div>
                <div class="emoji-selected-display" id="editEmojiDisplay" onclick="toggleEmojiPicker('edit')">📡</div>
                <input type="hidden" id="editEmojiValue" value="📡">
            </div>
            <input type="text" class="form-control" id="editNombre" placeholder="Nombre" style="flex:1;">
        </div>
        <div class="emoji-picker-panel" id="editEmojiPanel"></div>

        <hr class="modal-divider">

        <!-- URL -->
        <label class="form-label">URL O COMANDO</label>
        <input type="text" class="form-control mb-10" id="editUrl" placeholder="https://...">

        <hr class="modal-divider">

        <!-- Color -->
        <label class="form-label">COLOR DEL BOTÓN</label>
        <div class="color-row mb-10">
            <div class="color-swatch" id="editColorSwatch" style="background:#0e639c;"
                 onclick="document.getElementById('editColorPicker').click()"></div>
            <input type="color" class="color-input-hidden" id="editColorPicker" value="#0e639c"
                   oninput="updateColorSwatch('edit', this.value)">
            <input type="text" class="form-control" id="editColorText" value="#0e639c"
                   maxlength="7" placeholder="#rrggbb"
                   oninput="syncColorFromText('edit', this.value)">
        </div>

        <!-- Posición -->
        <label class="form-label">POSICIÓN EN EL GRID</label>
        <div class="pos-row">
            <div style="flex:1;">
                <label class="form-label" style="font-size:0.65rem;">FILA (desde 1)</label>
                <input type="number" class="form-control" id="editFila" value="1" min="1" max="50">
            </div>
            <div style="flex:1;">
                <label class="form-label" style="font-size:0.65rem;">COLUMNA (desde 0)</label>
                <input type="number" class="form-control" id="editCol"  value="0" min="0" max="9">
            </div>
        </div>

      </div>
      <div class="modal-footer">
        <button class="btn-modal-warn"   onclick="saveEdit()">💾 Guardar cambios</button>
        <button class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL: ELIMINAR ENLACE
══════════════════════════════════════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <span class="modal-title">🗑️ ELIMINAR ENLACE</span>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">SELECCIONAR ENLACE A ELIMINAR</label>
        <select class="form-select mb-10" id="deleteSelector"></select>
        <p class="note-text" style="color:#e74856;">⚠️ Esta acción no se puede deshacer.</p>
      </div>
      <div class="modal-footer">
        <button class="btn-modal-danger" onclick="confirmDelete()">🗑️ Eliminar</button>
        <button class="btn-modal-cancel" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     FOOTER
══════════════════════════════════════════════ -->
<div class="panel-footer">
    EA3EIZ · ASSOCIACIÓ ADER · PANEL DE ENLACES ·
    <span style="color:var(--accent-cyan)">JN11CK</span> ·
    <?= date('Y') ?>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ══════════════════════════════════════════════
// DATOS DESDE PHP
// ══════════════════════════════════════════════
const LINKS_DATA = <?= $links_js ?>;
const ICONOS     = <?= $iconos_js ?>;

// ══════════════════════════════════════════════
// MODAL INSTANCES
// ══════════════════════════════════════════════
const linkModal   = new bootstrap.Modal(document.getElementById('linkModal'));
const addModal    = new bootstrap.Modal(document.getElementById('addModal'));
const editModal   = new bootstrap.Modal(document.getElementById('editModal'));
const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

// ══════════════════════════════════════════════
// ESTADO
// ══════════════════════════════════════════════
let currentCmd  = '';
let currentUrl  = '';

// ══════════════════════════════════════════════
// CLICK EN BOTÓN DE ENLACE
// ══════════════════════════════════════════════
function handleLinkClick(url, isCommand, label) {
    if (!isCommand) {
        // URL web: abre directamente en nueva pestaña
        window.open(url, '_blank', 'noopener');
        return;
    }

    // Comando: mostrar modal informativo
    currentCmd = url;
    currentUrl = '';
    document.getElementById('linkModalTitle').textContent = label;
    document.getElementById('linkTypeBadge').className    = 'cmd-type-badge mb-10 cmd-type-cmd';
    document.getElementById('linkTypeBadge').textContent  = '⚡ COMANDO LOCAL';
    document.getElementById('linkUrlDisplay').textContent = url;
    document.getElementById('linkModalNote').textContent  =
        'Este es un comando para ejecutar localmente (ffplay/RTSP). ' +
        'Puedes copiarlo para ejecutarlo en tu terminal, o usar el botón ' +
        '"Ejecutar en Pi" si la Pi tiene pantalla conectada (DISPLAY=:0.0).';
    document.getElementById('btnExecCmd').style.display = 'inline-block';
    document.getElementById('btnOpenUrl').style.display = 'none';

    const copyBtn = document.getElementById('btnCopyLink');
    copyBtn.textContent = '📋 Copiar comando';
    copyBtn.classList.remove('copied');

    linkModal.show();
}

function openUrl() {
    if (currentUrl) window.open(currentUrl, '_blank', 'noopener');
}

function copyLinkCmd() {
    const text = currentCmd || currentUrl;
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('btnCopyLink');
        btn.textContent = '✅ Copiado';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.textContent = '📋 Copiar';
            btn.classList.remove('copied');
        }, 2000);
    });
}

function execCommand() {
    if (!currentCmd) return;
    ajaxPost({ action: 'exec_command', cmd: currentCmd }, data => {
        if (data.ok) {
            showToast('✅ ' + (data.msg || 'Ejecutado en Pi'), 'ok');
            linkModal.hide();
        } else {
            showToast('❌ ' + (data.msg || 'Error al ejecutar'), 'error');
        }
    });
}

// ══════════════════════════════════════════════
// MODAL AGREGAR
// ══════════════════════════════════════════════
function openAddModal() {
    // Calcular posición siguiente disponible
    let maxFila = 1, maxCol = -1;
    for (const d of Object.values(LINKS_DATA)) {
        if (d.fila > maxFila || (d.fila === maxFila && d.col > maxCol)) {
            maxFila = d.fila;
            maxCol  = d.col;
        }
    }
    let nextCol  = maxCol + 1;
    let nextFila = maxFila;
    if (nextCol > 2) { nextCol = 0; nextFila++; }

    document.getElementById('addNombre').value = '';
    document.getElementById('addUrl').value    = '';
    document.getElementById('addFila').value   = nextFila;
    document.getElementById('addCol').value    = nextCol;
    setEmojiPicker('add', '📡');
    setColorSwatch('add', '#0e639c');

    buildEmojiPanel('add');
    addModal.show();
}

function saveAdd() {
    const icono  = document.getElementById('addEmojiValue').value;
    const nombre = document.getElementById('addNombre').value.trim();
    const url    = document.getElementById('addUrl').value.trim();
    const color  = document.getElementById('addColorPicker').value;
    const fila   = parseInt(document.getElementById('addFila').value) || 1;
    const col    = parseInt(document.getElementById('addCol').value)  || 0;

    if (!nombre || !url) { showToast('⚠️ Nombre y URL son obligatorios', 'error'); return; }

    ajaxPost({ action: 'save_link', key_old: '', icono, nombre, url, color, fila, col }, data => {
        if (data.ok) {
            showToast('✅ Enlace añadido correctamente', 'ok');
            addModal.hide();
            location.reload();
        } else {
            showToast('❌ ' + (data.msg || 'Error al guardar'), 'error');
        }
    });
}

// ══════════════════════════════════════════════
// MODAL EDITAR
// ══════════════════════════════════════════════
function openEditModal() {
    const sel = document.getElementById('editSelector');
    sel.innerHTML = '';
    for (const key of Object.keys(LINKS_DATA)) {
        const opt = document.createElement('option');
        opt.value = key;
        opt.textContent = key;
        sel.appendChild(opt);
    }
    if (sel.options.length > 0) {
        loadEditLink(sel.options[0].value);
    }
    buildEmojiPanel('edit');
    editModal.show();
}

function loadEditLink(key) {
    const d = LINKS_DATA[key];
    if (!d) return;
    document.getElementById('editKeyOld').value = key;

    // Separar icono del nombre
    const parts = key.match(/^(\S+)\s(.+)$/);
    const icono  = parts ? parts[1] : '🔗';
    const nombre = parts ? parts[2] : key;

    document.getElementById('editNombre').value = nombre;
    document.getElementById('editUrl').value    = d.url   || '';
    document.getElementById('editFila').value   = d.fila  || 1;
    document.getElementById('editCol').value    = d.col   !== undefined ? d.col : 0;

    setEmojiPicker('edit', icono);
    setColorSwatch('edit', d.color || '#0e639c');
}

function saveEdit() {
    const icono  = document.getElementById('editEmojiValue').value;
    const nombre = document.getElementById('editNombre').value.trim();
    const url    = document.getElementById('editUrl').value.trim();
    const color  = document.getElementById('editColorPicker').value;
    const fila   = parseInt(document.getElementById('editFila').value) || 1;
    const col    = parseInt(document.getElementById('editCol').value)  || 0;
    const keyOld = document.getElementById('editKeyOld').value;

    if (!nombre || !url) { showToast('⚠️ Nombre y URL son obligatorios', 'error'); return; }

    ajaxPost({ action: 'save_link', key_old: keyOld, icono, nombre, url, color, fila, col }, data => {
        if (data.ok) {
            showToast('✅ Enlace actualizado', 'ok');
            editModal.hide();
            location.reload();
        } else {
            showToast('❌ ' + (data.msg || 'Error al guardar'), 'error');
        }
    });
}

// ══════════════════════════════════════════════
// MODAL ELIMINAR
// ══════════════════════════════════════════════
function openDeleteModal() {
    const sel = document.getElementById('deleteSelector');
    sel.innerHTML = '';
    for (const key of Object.keys(LINKS_DATA)) {
        const opt = document.createElement('option');
        opt.value = key;
        opt.textContent = key;
        sel.appendChild(opt);
    }
    deleteModal.show();
}

function confirmDelete() {
    const key = document.getElementById('deleteSelector').value;
    if (!key) return;
    if (!confirm('¿Eliminar "' + key + '"?')) return;

    ajaxPost({ action: 'delete_link', key }, data => {
        if (data.ok) {
            showToast('🗑️ Enlace eliminado', 'ok');
            deleteModal.hide();
            location.reload();
        } else {
            showToast('❌ ' + (data.msg || 'Error al eliminar'), 'error');
        }
    });
}

// ══════════════════════════════════════════════
// EMOJI PICKER
// ══════════════════════════════════════════════
function buildEmojiPanel(prefix) {
    const panel = document.getElementById(prefix + 'EmojiPanel');
    if (panel.children.length > 0) return; // ya construido
    ICONOS.forEach(emoji => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'emoji-opt';
        btn.textContent = emoji;
        btn.addEventListener('click', () => selectEmoji(prefix, emoji));
        panel.appendChild(btn);
    });
}

function toggleEmojiPicker(prefix) {
    buildEmojiPanel(prefix);
    const panel = document.getElementById(prefix + 'EmojiPanel');
    panel.classList.toggle('open');
}

function selectEmoji(prefix, emoji) {
    setEmojiPicker(prefix, emoji);
    document.getElementById(prefix + 'EmojiPanel').classList.remove('open');
}

function setEmojiPicker(prefix, emoji) {
    document.getElementById(prefix + 'EmojiDisplay').textContent = emoji;
    document.getElementById(prefix + 'EmojiValue').value = emoji;
    // Marcar activo en el panel si está construido
    const panel = document.getElementById(prefix + 'EmojiPanel');
    panel.querySelectorAll('.emoji-opt').forEach(btn => {
        btn.classList.toggle('active', btn.textContent === emoji);
    });
}

// ══════════════════════════════════════════════
// COLOR SWATCH
// ══════════════════════════════════════════════
function setColorSwatch(prefix, color) {
    document.getElementById(prefix + 'ColorSwatch').style.background = color;
    document.getElementById(prefix + 'ColorPicker').value = color;
    document.getElementById(prefix + 'ColorText').value   = color;
}

function updateColorSwatch(prefix, color) {
    document.getElementById(prefix + 'ColorSwatch').style.background = color;
    document.getElementById(prefix + 'ColorText').value = color;
}

function syncColorFromText(prefix, value) {
    if (/^#[0-9a-fA-F]{6}$/.test(value)) {
        document.getElementById(prefix + 'ColorSwatch').style.background = value;
        document.getElementById(prefix + 'ColorPicker').value = value;
    }
}

// ══════════════════════════════════════════════
// AJAX HELPER
// ══════════════════════════════════════════════
function ajaxPost(params, callback) {
    const body = new URLSearchParams(params);
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(callback)
    .catch(e => showToast('❌ Error de comunicación: ' + e.message, 'error'));
}

// ══════════════════════════════════════════════
// TOAST NOTIFICATIONS
// ══════════════════════════════════════════════
function showToast(msg, type = 'ok') {
    const area = document.getElementById('toastArea');
    const t = document.createElement('div');
    t.className = 'toast-item ' + type;
    t.innerHTML = msg;
    area.appendChild(t);
    setTimeout(() => {
        t.style.transition = 'opacity 0.4s, transform 0.4s';
        t.style.opacity    = '0';
        t.style.transform  = 'translateX(40px)';
        setTimeout(() => t.remove(), 420);
    }, 3200);
}

// ══════════════════════════════════════════════
// INIT: cerrar emoji panels al clickar fuera
// ══════════════════════════════════════════════
document.addEventListener('click', e => {
    ['add', 'edit'].forEach(prefix => {
        const display = document.getElementById(prefix + 'EmojiDisplay');
        const panel   = document.getElementById(prefix + 'EmojiPanel');
        if (panel && display && !display.contains(e.target) && !panel.contains(e.target)) {
            panel.classList.remove('open');
        }
    });
});
</script>
</body>
</html>
