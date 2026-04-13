<?php
require_once __DIR__ . '/auth.php';
header('X-Content-Type-Options: nosniff');
$action = $_GET['action'] ?? '';

// ── Función para guardar estado de servicios ─────────────────────────
function saveState($key, $value) {
    $file = '/var/lib/mmdvm-state';
    $lines = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $found = false;
    foreach ($lines as &$line) {
        if (strpos($line, $key . '=') === 0) {
            $line = $key . '=' . $value;
            $found = true;
        }
    }
    unset($line);
    if (!$found) $lines[] = $key . '=' . $value;
    file_put_contents($file, implode("\n", $lines) . "\n");
}

// ── Parser genérico de ficheros .ini tipo MMDVMHost ──────────────────
function parseMMDVMIni($path) {
    $result = [];
    if (!file_exists($path)) return $result;
    $section = '';
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
        if (preg_match('/^\[(.+)\]$/', $line, $m)) { $section = trim($m[1]); continue; }
        if (preg_match('/^([^=]+)=(.*)$/', $line, $m)) {
            $result[$section][trim($m[1])] = trim($m[2]);
        }
    }
    return $result;
}

// ── Convertir lat/lon a locator Maidenhead (6 caracteres) ────────────
function latLonToLocator($lat, $lon) {
    $lat = floatval($lat) + 90;
    $lon = floatval($lon) + 180;
    $A = ord('A');
    $f1 = chr($A + intval($lon / 20));
    $f2 = chr($A + intval($lat / 10));
    $f3 = strval(intval(fmod($lon, 20) / 2));
    $f4 = strval(intval(fmod($lat, 10)));
    $f5 = chr($A + intval(fmod($lon, 2) * 12));
    $f6 = chr($A + intval(fmod($lat, 1) * 24));
    return strtoupper($f1 . $f2 . $f3 . $f4) . strtolower($f5 . $f6);
}

// ── Formatear frecuencia Hz → MHz ────────────────────────────────────
function formatFreq($hz) {
    $mhz = intval($hz) / 1000000;
    return number_format($mhz, 3, '.', '') . ' MHz';
}

// ── Datos desde MMDVMHost.ini ────────────────────────────────────────
if ($action === 'station-info') {
    $iniPath = '/home/pi/MMDVMHost/MMDVMHost.ini';
    $ini = parseMMDVMIni($iniPath);

    $callsign = $ini['General']['Callsign']    ?? 'EA3EIZ';
    $dmrid    = $ini['General']['Id']          ?? '214317526';
    $txfreq   = $ini['General']['TXFrequency'] ?? ($ini['General']['Frequency'] ?? '430000000');

    $lat      = $ini['Info']['Latitude']    ?? '41.3851';
    $lon      = $ini['Info']['Longitude']   ?? '2.1734';
    $location = $ini['Info']['Location']    ?? 'Barcelona';
    $desc     = $ini['Info']['Description'] ?? '';

    $locator  = (floatval($lat) != 0 || floatval($lon) != 0)
        ? latLonToLocator($lat, $lon)
        : 'JN11CK';

    // Puerto del modem — [Modem] UARTPort=
    $port = $ini['Modem']['UARTPort'] ?? ($ini['modem']['UARTPort'] ?? '');

    // Frecuencias RX y TX desde [Info]
    $rxhz   = $ini['Info']['RXFrequency'] ?? '0';
    $txhz   = $ini['Info']['TXFrequency'] ?? $txfreq;
    $freqRX = formatFreq($rxhz);
    $freq   = formatFreq($txhz);

    // IP: primero Address del ini, si vacía o 0.0.0.0 usar IP real de la Pi
    $iniIp = trim($ini['General']['Address'] ?? '');
    if ($iniIp === '' || $iniIp === '0.0.0.0') {
        $iniIp = trim(shell_exec("hostname -I 2>/dev/null | awk '{print $1}'"));
    }
    $ip = $iniIp ?: '—';

    // ── Datos desde MMDVMYSF.ini ─────────────────────────────────
    $ysfIniPath = '/home/pi/MMDVMHost/MMDVMYSF.ini';
    $ysfIni = parseMMDVMIni($ysfIniPath);

    $ysfPort   = $ysfIni['Modem']['UARTPort']      ?? ($ysfIni['modem']['UARTPort'] ?? '—');
    $ysfRxHz   = $ysfIni['Info']['RXFrequency']    ?? '0';
    $ysfTxHz   = $ysfIni['Info']['TXFrequency']    ?? '0';
    $ysfFreqRX = formatFreq($ysfRxHz);
    $ysfFreqTX = formatFreq($ysfTxHz);
    $ysfIpRaw  = trim($ysfIni['General']['Address'] ?? '');
    $ysfIp     = ($ysfIpRaw !== '' && $ysfIpRaw !== '0.0.0.0') ? $ysfIpRaw : $ip;

    header('Content-Type: application/json');
    echo json_encode([
        'callsign'  => strtoupper(trim($callsign)),
        'dmrid'     => trim($dmrid),
        'freq'      => $freq,
        'freqRX'    => $freqRX,
        'port'      => $port ?: '—',
        'ip'        => $ip,
        'locator'   => $locator,
        'location'  => trim($location),
        'desc'      => trim($desc),
        'lat'       => $lat,
        'lon'       => $lon,
        'ysfPort'   => $ysfPort ?: '—',
        'ysfFreqRX' => $ysfFreqRX,
        'ysfFreqTX' => $ysfFreqTX,
        'ysfIp'     => $ysfIp ?: '—',
    ]);
    exit;
}

// ── System Info ──────────────────────────────────────────────────────
if ($action === 'sysinfo') {
    $s1 = file('/proc/stat'); $cpu1 = preg_split('/\s+/', trim($s1[0]));
    usleep(300000);
    $s2 = file('/proc/stat'); $cpu2 = preg_split('/\s+/', trim($s2[0]));
    $idle1 = $cpu1[4]; $total1 = array_sum(array_slice($cpu1, 1));
    $idle2 = $cpu2[4]; $total2 = array_sum(array_slice($cpu2, 1));
    $dTotal = $total2 - $total1; $dIdle = $idle2 - $idle1;
    $cpu = $dTotal > 0 ? round(100 * ($dTotal - $dIdle) / $dTotal, 1) : 0;
    $memRaw = file('/proc/meminfo'); $mem = [];
    foreach ($memRaw as $line) { if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) $mem[$m[1]] = intval($m[2]); }
    $ramTotal = round($mem['MemTotal'] / 1048576, 2);
    $ramFree  = round(($mem['MemAvailable'] ?? $mem['MemFree']) / 1048576, 2);
    $ramUsed  = round($ramTotal - $ramFree, 2);
    $diskTotal = round(disk_total_space('/') / 1073741824, 1);
    $diskFree  = round(disk_free_space('/') / 1073741824, 1);
    $diskUsed  = round($diskTotal - $diskFree, 1);
    $temp = '';
    if (file_exists('/sys/class/thermal/thermal_zone0/temp'))
        $temp = round(intval(trim(file_get_contents('/sys/class/thermal/thermal_zone0/temp'))) / 1000, 1) . ' °C';
    header('Content-Type: application/json');
    echo json_encode(['cpu'=>$cpu,'ramTotal'=>$ramTotal,'ramUsed'=>$ramUsed,'ramFree'=>$ramFree,'diskTotal'=>$diskTotal,'diskUsed'=>$diskUsed,'diskFree'=>$diskFree,'temp'=>$temp]);
    exit;
}

// ── DMR status ───────────────────────────────────────────────────────
if ($action === 'status') {
    $gw = trim(shell_exec('systemctl is-active dmrgateway 2>/dev/null'));
    $mmd = trim(shell_exec('systemctl is-active mmdvmhost 2>/dev/null'));
    header('Content-Type: application/json');
    echo json_encode(['gateway' => $gw, 'mmdvm' => $mmd]);
    exit;
}

// ── DMR start ────────────────────────────────────────────────────────
if ($action === 'start') {
    saveState('dmr', 'on');
    shell_exec('sudo systemctl start dmrgateway 2>/dev/null');
    sleep(2);
    shell_exec('sudo systemctl start mmdvmhost 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── DMR stop ─────────────────────────────────────────────────────────
if ($action === 'stop') {
    saveState('dmr', 'off');
    shell_exec('sudo systemctl stop mmdvmhost 2>/dev/null');
    sleep(1);
    shell_exec('sudo systemctl stop dmrgateway 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── Actualizaciones ──────────────────────────────────────────────────
if ($action === 'update-imagen') {
    $output = shell_exec('sudo sh /home/pi/A108/actualiza_imagen.sh 2>&1');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'output' => htmlspecialchars($output ?? '(sin salida)')]);
    exit;
}
if ($action === 'update-ids') {
    $output = shell_exec('sudo sh /home/pi/A108/actualizar_ids.sh 2>&1');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'output' => htmlspecialchars($output ?? '(sin salida)')]);
    exit;
}
if ($action === 'update-ysf') {
    $output = shell_exec('sudo sh /home/pi/A108/actualizar_reflectores_ysf.sh 2>&1');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'output' => htmlspecialchars($output ?? '(sin salida)')]);
    exit;
}

// ── YSF status ───────────────────────────────────────────────────────
if ($action === 'ysf-status') {
    $st = trim(shell_exec('sudo /usr/local/bin/ysf_status.sh 2>/dev/null'));
    if ($st === 'active') { header('Content-Type: application/json'); echo json_encode(['ysf' => 'active']); exit; }
    $pid = trim(@file_get_contents('/tmp/ysfgateway.pid'));
    $active = ($pid && is_numeric($pid) && file_exists('/proc/' . $pid)) ? 'active' : 'inactive';
    header('Content-Type: application/json');
    echo json_encode(['ysf' => $active]);
    exit;
}

// ── YSF start ────────────────────────────────────────────────────────
if ($action === 'ysf-start') {
    saveState('ysf', 'on');
    shell_exec('sudo systemctl start ysfgateway 2>/dev/null');
    sleep(1);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── YSF stop ─────────────────────────────────────────────────────────
if ($action === 'ysf-stop') {
    saveState('ysf', 'off');
    shell_exec('sudo systemctl stop ysfgateway 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── MMDVMHost YSF status ─────────────────────────────────────────────
if ($action === 'mmdvmysf-status') {
    $st = trim(shell_exec('systemctl is-active mmdvmysf 2>/dev/null'));
    header('Content-Type: application/json');
    echo json_encode(['mmdvmysf' => $st]);
    exit;
}

// ── MMDVMHost YSF start ───────────────────────────────────────────────
if ($action === 'mmdvmysf-start') {
    saveState('ysf', 'on');
    shell_exec('sudo systemctl start mmdvmysf 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── MMDVMHost YSF stop ────────────────────────────────────────────────
if ($action === 'mmdvmysf-stop') {
    saveState('ysf', 'off');
    shell_exec('sudo systemctl stop ysfgateway 2>/dev/null');
    sleep(1);
    shell_exec('sudo systemctl stop mmdvmysf 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'mmdvmysf-logs') {
    $lines = intval($_GET['lines'] ?? 15);
    $log = shell_exec("sudo journalctl -u mmdvmysf -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json');
    echo json_encode(['mmdvmysf' => htmlspecialchars($log ?? '')]);
    exit;
}

// ── Reboot ───────────────────────────────────────────────────────────
if ($action === 'reboot') {
    shell_exec('sudo /usr/bin/systemctl reboot 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── Display Driver restart ───────────────────────────────────────────
if ($action === 'display-restart') {
    shell_exec('sudo systemctl daemon-reload 2>/dev/null');
    shell_exec('sudo systemctl enable displaydriver 2>/dev/null');
    shell_exec('sudo systemctl restart displaydriver 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── Instalar Display Driver ──────────────────────────────────────────
if ($action === 'install-display') {
    $output = shell_exec('sudo /home/pi/A108/instalar_displaydriver.sh 2>&1');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'output' => htmlspecialchars($output ?? '')]);
    exit;
}

// ── Backup configuraciones ───────────────────────────────────────────
if ($action === 'backup-configs') {
    $zipName = 'Copia_A108.zip';
    $zipPath = '/tmp/' . $zipName;
    $files = [
        '/home/pi/MMDVMHost/MMDVMHost.ini',
        '/home/pi/MMDVMHost/MMDVMYSF.ini',
        '/home/pi/Display-Driver/DisplayDriver.ini',
        '/home/pi/YSFClients/YSFGateway/YSFGateway.ini',
        '/home/pi/DMRGateway/DMRGateway.ini',
    ];
    $fileList = implode(' ', array_map('escapeshellarg', $files));
    shell_exec("zip -j " . escapeshellarg($zipPath) . " {$fileList} 2>/dev/null");
    if (file_exists($zipPath)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Pragma: no-cache'); header('Expires: 0');
        readfile($zipPath); unlink($zipPath);
    } else {
        header('Content-Type: text/plain');
        echo 'Error: No se pudo crear el ZIP.';
    }
    exit;
}

// ── Restore configuraciones ──────────────────────────────────────────
if ($action === 'restore-configs') {
    ob_start(); error_reporting(0);
    $uploadOk = isset($_FILES['zipfile']) && $_FILES['zipfile']['error'] === UPLOAD_ERR_OK;
    if (!$uploadOk) { $errCode = $_FILES['zipfile']['error'] ?? -1; ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => 'No se recibió el fichero. Error: ' . $errCode]); exit; }
    $tmpZip = $_FILES['zipfile']['tmp_name'];
    if (!file_exists($tmpZip) || filesize($tmpZip) === 0) { ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => 'Fichero vacío.']); exit; }
    $destMap = ['MMDVMHost.ini'=>'/home/pi/MMDVMHost/MMDVMHost.ini','MMDVMYSF.ini'=>'/home/pi/MMDVMHost/MMDVMYSF.ini','DisplayDriver.ini'=>'/home/pi/Display-Driver/DisplayDriver.ini','YSFGateway.ini'=>'/home/pi/YSFClients/YSFGateway/YSFGateway.ini','DMRGateway.ini'=>'/home/pi/DMRGateway/DMRGateway.ini'];
    $zip = new ZipArchive(); $openResult = $zip->open($tmpZip);
    if ($openResult !== true) { ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => 'No se pudo abrir el ZIP. Código: ' . $openResult]); exit; }
    $restored = []; $errors = [];
    for ($i = 0; $i < $zip->numFiles; $i++) { $name = basename($zip->getNameIndex($i)); if (isset($destMap[$name])) { $result = file_put_contents($destMap[$name], $zip->getFromIndex($i)); if ($result !== false) $restored[] = $name; else $errors[] = $name; } }
    $zip->close(); ob_end_clean();
    if (empty($restored)) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => 'No se encontraron ficheros compatibles.']); exit; }
    $msg = 'Restaurados: ' . implode(', ', $restored);
    if ($errors) $msg .= ' | Errores: ' . implode(', ', $errors);
    header('Content-Type: application/json'); echo json_encode(['ok' => true, 'msg' => $msg]); exit;
}

// ── logs ─────────────────────────────────────────────────────────────
if ($action === 'logs') {
    $lines = intval($_GET['lines'] ?? 15);
    $gw = shell_exec("sudo journalctl -u dmrgateway -n {$lines} --no-pager --output=short 2>/dev/null");
    $mmd = shell_exec("sudo journalctl -u mmdvmhost -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json');
    echo json_encode(['gateway' => htmlspecialchars($gw ?? ''), 'mmdvm' => htmlspecialchars($mmd ?? '')]);
    exit;
}

// ── YSF logs ─────────────────────────────────────────────────────────
if ($action === 'ysf-logs') {
    $lines = intval($_GET['lines'] ?? 15);
    $log = shell_exec("sudo journalctl -u ysfgateway -n {$lines} --no-pager --output=short 2>/dev/null");
    if (empty(trim($log))) $log = shell_exec("tail -n {$lines} /tmp/ysfgateway.log 2>/dev/null");
    if (empty(trim($log))) { $logFile = glob('/home/pi/YSFClients/YSFGateway/YSFGateway-*.log'); if ($logFile) { $latest = end($logFile); $log = shell_exec("tail -n {$lines} " . escapeshellarg($latest) . " 2>/dev/null"); } }
    header('Content-Type: application/json');
    echo json_encode(['ysf' => htmlspecialchars($log ?? '')]);
    exit;
}

// ── DMR Id lookup ────────────────────────────────────────────────────
function lookupCall($callsign) {
    $datFiles = ['/home/pi/MMDVMHost/DMRIds.dat','/etc/DMRIds.dat','/usr/local/etc/DMRIds.dat'];
    $cs = strtoupper(trim($callsign));
    foreach ($datFiles as $f) {
        if (!file_exists($f)) continue;
        $cmd = "awk -F'\t' '{if (toupper(\$2)==\"" . $cs . "\") {print \$1\"\t\"\$2\"\t\"\$3; exit}}' " . escapeshellarg($f) . " 2>/dev/null";
        $row = trim(shell_exec($cmd));
        if ($row !== '') { $parts = explode("\t", $row); return ['dmrid' => trim($parts[0] ?? ''), 'name' => trim($parts[2] ?? '')]; }
    }
    return ['dmrid' => '', 'name' => ''];
}

if ($action === 'transmission') {
    $log = shell_exec("sudo journalctl -u mmdvmhost -n 200 --no-pager --output=short 2>/dev/null");
    $lines = array_reverse(explode("\n", $log));
    $active = false; $callsign = ''; $dmrid = ''; $name = ''; $tg = ''; $slot = ''; $source = '';
    foreach ($lines as $line) {
        if (preg_match('/DMR Slot \d.*(end of voice|lost RF|watchdog)/i', $line)) { $active = false; break; }
        if (preg_match('/DMR Slot (\d), received (RF|network) voice header from (\S+) to TG (\d+)/i', $line, $m)) { $active = true; $slot = $m[1]; $source = strtoupper($m[2]); $callsign = strtoupper(rtrim($m[3], ',')); $tg = $m[4]; break; }
    }
    if ($callsign) { $info = lookupCall($callsign); $dmrid = $info['dmrid']; $name = $info['name']; }
    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
        if (preg_match('/(\d{2}:\d{2}:\d{2})\.\d+\s+DMR Slot (\d), received (RF|network) voice header from (\S+) to TG (\d+)/i', $line, $m)) {
            $cs = strtoupper(rtrim($m[4], ','));
            if (!in_array($cs, $seen)) { $inf = lookupCall($cs); $lastHeard[] = ['callsign'=>$cs,'name'=>$inf['name'],'dmrid'=>$inf['dmrid'],'tg'=>$m[5],'slot'=>$m[2],'source'=>strtoupper($m[3]),'time'=>$m[1]]; $seen[] = $cs; if (count($lastHeard) >= 5) break; }
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['active'=>$active,'callsign'=>$callsign,'name'=>$name,'dmrid'=>$dmrid,'tg'=>$tg,'slot'=>$slot,'source'=>$source,'lastHeard'=>$lastHeard]);
    exit;
}

// ── DStar status ─────────────────────────────────────────────────────
if ($action === 'dstar-status') {
    $gw      = trim(shell_exec('systemctl is-active dstargateway 2>/dev/null'));
    $mmd     = trim(shell_exec('systemctl is-active mmdvmdstar 2>/dev/null'));
    $stopped = file_exists('/var/lib/dstar-stopped');
    header('Content-Type: application/json');
    echo json_encode(['gateway' => $gw, 'mmdvm' => $mmd, 'stopped' => $stopped]);
    exit;
}

// ── DStar start ───────────────────────────────────────────────────────
if ($action === 'dstar-start') {
    shell_exec('sudo /usr/local/bin/dstar-start.sh 2>/dev/null &');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── DStar stop ────────────────────────────────────────────────────────
if ($action === 'dstar-stop') {
    shell_exec('sudo /usr/local/bin/dstar-stop.sh 2>/dev/null &');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── DStar logs ────────────────────────────────────────────────────────
if ($action === 'dstar-logs') {
    $lines = intval($_GET['lines'] ?? 15);
    $gw  = shell_exec("sudo journalctl -u dstargateway -n {$lines} --no-pager --output=short 2>/dev/null");
    $mmd = shell_exec("sudo journalctl -u mmdvmdstar  -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json');
    echo json_encode(['gateway' => htmlspecialchars($gw ?? ''), 'mmdvm' => htmlspecialchars($mmd ?? '')]);
    exit;
}

// ── YSF transmission ─────────────────────────────────────────────────
if ($action === 'ysf-transmission') {
    $log = shell_exec("sudo journalctl -u mmdvmysf -n 300 --no-pager --output=short 2>/dev/null");
    if (empty(trim($log))) $log = shell_exec("sudo journalctl -u ysfgateway -n 300 --no-pager --output=short 2>/dev/null");
    $lines = array_reverse(explode("\n", $log));
    $active = false; $callsign = ''; $name = ''; $dest = ''; $source = '';
    foreach ($lines as $line) {
        if (preg_match('/YSF.*(end of|lost RF|lost net|watchdog|timeout|no reply|voice end|fin)/i', $line)) { $active = false; break; }
        if (preg_match('/YSF.*voice (end|fin|stop)/i', $line)) { $active = false; break; }
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*received (RF|network) voice.*from\s+(\S+)/i', $line, $m)) { $active = true; $source = strtoupper($m[2]); $callsign = strtoupper(trim($m[3])); break; }
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*from\s+(\S+)\s+to\s+(\S+)/i', $line, $m)) { $active = true; $source = 'RF'; $callsign = strtoupper(trim($m[2])); $dest = trim($m[3]); break; }
    }
    if ($callsign) { $info = lookupCall($callsign); $name = $info['name']; }
    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
        $cs = ''; $src = ''; $time = ''; $dst = '';
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*received (RF|network) voice.*from\s+(\S+)/i', $line, $m)) { $time = $m[1]; $src = strtoupper($m[2]); $cs = strtoupper(trim($m[3])); }
        elseif (preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*from\s+(\S+)\s+to\s+(\S+)/i', $line, $m)) { $time = $m[1]; $src = 'RF'; $cs = strtoupper(trim($m[2])); $dst = trim($m[3]); }
        if ($cs && !in_array($cs, $seen)) { $inf = lookupCall($cs); $lastHeard[] = ['callsign'=>$cs,'name'=>$inf['name'],'dest'=>$dst,'source'=>$src,'time'=>$time]; $seen[] = $cs; if (count($lastHeard) >= 5) break; }
    }
    header('Content-Type: application/json');
    echo json_encode(['active'=>$active,'callsign'=>$callsign,'name'=>$name,'dest'=>$dest,'source'=>$source,'lastHeard'=>$lastHeard]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel Control</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm/css/xterm.css">
<script src="https://cdn.jsdelivr.net/npm/xterm/lib/xterm.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit/lib/xterm-addon-fit.min.js"></script>
<style>
:root { --bg: #0a0e14; --surface: #111720; --border: #1e2d3d; --green: #00ff9f; --green-dim: #00cc7a; --red: #ff4560; --amber: #ffb300; --cyan: #00d4ff; --violet: #b57aff; --text: #a8b9cc; --text-dim: #4a5568; --font-mono: 'Share Tech Mono', monospace; --font-ui: 'Rajdhani', sans-serif; --font-orb: 'Orbitron', monospace; }
* { box-sizing: border-box; }
body { background: var(--bg); color: var(--text); font-family: var(--font-ui); font-size: 1rem; min-height: 100vh; padding: 0; margin: 0; }
.ctrl-header { border-bottom: 1px solid var(--border); padding: 1rem 2rem; display: flex; flex-direction: column; align-items: center; gap: .6rem; background: var(--surface); }
.ctrl-header-top { display: flex; align-items: center; gap: .8rem; }
.ctrl-header-top h1 { font-family: var(--font-ui); font-weight: 700; font-size: 1.5rem; letter-spacing: .08em; color: #e2eaf5; margin: 0; text-transform: uppercase; }
.ctrl-header-btns { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; justify-content: center; }
.btn-header { font-family: var(--font-mono); font-size: .65rem; letter-spacing: .08em; text-transform: uppercase; background: transparent; border-radius: 4px; padding: .28rem .75rem; cursor: pointer; transition: background .2s; text-decoration: none; display: inline-block; }
.btn-header.cyan { color: var(--cyan); border: 1px solid var(--cyan); }
.btn-header.cyan:hover { background: rgba(0,212,255,.1); }
.btn-header.amber { color: var(--amber); border: 1px solid var(--amber); }
.btn-header.amber:hover { background: rgba(255,179,0,.1); }
.btn-header.red { color: var(--red); border: 1px solid var(--red); }
.btn-header.red:hover { background: rgba(255,69,96,.15); }
button.btn-header { font-family: var(--font-mono); }
.ctrl-body { padding: 2rem; max-width: 1400px; margin: 0 auto; }

/* ── Station Card ─────────────────────────────────────────────── */
.station-card { background: linear-gradient(135deg,#111720 60%,#0d1e2a 100%); border: 1px solid var(--border); border-radius: 10px; padding: 1.2rem 2rem; display: flex; align-items: center; gap: 2.5rem; margin-bottom: 1.8rem; flex-wrap: wrap; position: relative; overflow: hidden; }
.station-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg,transparent,var(--cyan),var(--violet),transparent); }
.station-card-main { display: flex; flex-direction: column; align-items: flex-start; gap: .3rem; }
.station-callsign { font-family: var(--font-orb); font-size: 2.4rem; font-weight: 900; color: var(--cyan); letter-spacing: .08em; line-height: 1; text-shadow: 0 0 20px rgba(0,212,255,.4); }
.station-location { font-family: var(--font-mono); font-size: .78rem; color: var(--text-dim); letter-spacing: .1em; text-transform: uppercase; margin-top: .25rem; }
.station-name-pill { display: inline-block; margin-top: .4rem; background: linear-gradient(90deg,#1a3a5a,#1a2d4a); border: 1px solid rgba(0,212,255,.35); border-radius: 20px; padding: .35rem 1.2rem; font-family: var(--font-ui); font-weight: 700; font-size: 1rem; color: var(--cyan); letter-spacing: .1em; }
.station-divider { width: 1px; height: 70px; background: var(--border); flex-shrink: 0; }
.station-meta { display: flex; gap: 2rem; flex-wrap: wrap; align-items: center; flex: 1; }
.station-meta-item { display: flex; flex-direction: column; gap: .15rem; }
.station-meta-label { font-family: var(--font-mono); font-size: .6rem; color: var(--text-dim); letter-spacing: .15em; text-transform: uppercase; }
.station-meta-value { font-family: var(--font-mono); font-size: .95rem; color: var(--amber); letter-spacing: .06em; font-weight: bold; }
.station-meta-value.cyan { color: var(--cyan); }
.station-meta-value.green { color: var(--green); }
.station-meta-value.violet { color: var(--violet); }
.station-assoc { margin-left: auto; font-family: var(--font-mono); font-size: .7rem; color: var(--text-dim); letter-spacing: .12em; text-transform: uppercase; border: 1px solid var(--border); border-radius: 4px; padding: .3rem .8rem; }
.ini-source-badge { position: absolute; bottom: .45rem; right: .8rem; font-family: var(--font-mono); font-size: .58rem; color: var(--text-dim); letter-spacing: .1em; opacity: .55; }
.ini-source-badge span { color: var(--green-dim); }
@media (max-width:700px) { .station-card { gap: 1.2rem; padding: 1rem; } .station-divider { display: none; } .station-assoc { margin-left: 0; } }

/* ── Status bar ───────────────────────────────────────────────── */
.status-bar { display: flex; gap: 2rem; margin-bottom: 1.8rem; flex-wrap: wrap; align-items: center; }
.status-item { display: flex; align-items: center; gap: .5rem; font-family: var(--font-mono); font-size: .85rem; text-transform: uppercase; letter-spacing: .08em; }
.dot { width: 10px; height: 10px; border-radius: 50%; background: var(--text-dim); transition: background .4s, box-shadow .4s; }
.dot.active { background: var(--green); box-shadow: 0 0 8px var(--green); animation: pulse 2s infinite; }
.dot.error { background: var(--red); box-shadow: 0 0 8px var(--red); }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
.section-divider { width: 1px; height: 20px; background: var(--border); margin: 0 .5rem; }

/* ── Controls ─────────────────────────────────────────────────── */
.controls-section { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; margin-bottom: 2rem; }
@media (max-width:800px) { .controls-section { grid-template-columns: 1fr; } }
.service-card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 1.2rem 1.6rem; }
.service-card-label { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .15em; text-transform: uppercase; margin-bottom: 1rem; }
.service-card-label.dmr { color: var(--amber); }
.service-card-label.ysf { color: var(--violet); }

/* ── Toggle switch ────────────────────────────────────────────── */
.toggle-row { display: flex; align-items: center; gap: 1rem; padding: .5rem 0; }
.toggle-label { font-family: var(--font-mono); font-size: .85rem; letter-spacing: .06em; color: var(--text-dim); text-transform: uppercase; flex: 1; transition: color .3s; }
.toggle-label.on-dmr  { color: var(--amber); }
.toggle-label.on-ysf  { color: var(--violet); }
.toggle-status { font-family: var(--font-mono); font-size: .72rem; letter-spacing: .1em; color: var(--text-dim); min-width: 3rem; text-align: right; transition: color .3s; }
.toggle-status.on { color: var(--green); }
.sw { position: relative; width: 56px; height: 28px; flex-shrink: 0; cursor: pointer; }
.sw input { opacity: 0; width: 0; height: 0; position: absolute; }
.sw-track { position: absolute; inset: 0; border-radius: 2px; background: #1a2535; border: 2px solid #999999; transition: background .3s, border-color .3s, box-shadow .3s; }
.sw-knob { position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; background: #e95c04; box-shadow: 0 1px 4px rgba(0,0,0,.5); transition: transform .3s cubic-bezier(.4,0,.2,1), background .3s, box-shadow .3s; }
.sw.dmr input:checked ~ .sw-track { border-radius: 2px; background: #1a2535; border: 2px solid #999999; }
.sw.dmr input:checked ~ .sw-knob { transform: translateX(28px); background: var(--green); box-shadow: 0 0 8px rgba(0,255,159,.6); }
.sw.ysf input:checked ~ .sw-track { border-radius: 2px; background: #1a2535; border: 2px solid #999999; }
.sw.ysf input:checked ~ .sw-knob { transform: translateX(28px); background: var(--green); box-shadow: 0 0 8px rgba(0,255,159,.6); }
.sw.dstar input:checked ~ .sw-track { border-radius: 2px; background: #1a2535; border: 2px solid #999999; }
.sw.dstar input:checked ~ .sw-knob { transform: translateX(28px); background: var(--green); box-shadow: 0 0 8px rgba(0,255,159,.6); }
.sw-busy-dot { display: none; position: absolute; top: 50%; right: -18px; transform: translateY(-50%); width: 8px; height: 8px; border-radius: 50%; border: 2px solid var(--amber); border-top-color: transparent; animation: spin .7s linear infinite; }
.sw.busy .sw-busy-dot { display: block; }
@keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }
.auto-badge { font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); display: flex; align-items: center; gap: .4rem; margin-top: .4rem; }
.auto-badge .dot-sm { width: 6px; height: 6px; border-radius: 50%; background: var(--green); animation: pulse 2s infinite; }
.auto-badge.ysf .dot-sm { background: var(--violet); }
.service-card-btns { display: flex; gap: .6rem; flex-wrap: nowrap; margin-top: 1rem; }
.ini-btn { font-family: var(--font-mono); font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; padding: .3rem .7rem; border-radius: 3px; border: 1px solid var(--border); background: transparent; cursor: pointer; text-decoration: none; transition: all .2s; display: inline-flex; align-items: center; gap: .3rem; }
.ini-btn.edit { color: var(--amber); border-color: rgba(255,179,0,.3); }
.ini-btn.edit:hover { border-color: var(--amber); background: rgba(255,179,0,.08); }
.ini-btn.view { color: var(--cyan); border-color: rgba(0,212,255,.3); }
.ini-btn.view:hover { border-color: var(--cyan); background: rgba(0,212,255,.08); }
.ini-btn.edit.ysf { color: var(--violet); border-color: rgba(181,122,255,.3); }
.ini-btn.edit.ysf:hover { border-color: var(--violet); background: rgba(181,122,255,.08); }
.ini-btn.view.ysf { color: #c9a0ff; border-color: rgba(181,122,255,.2); }
.ini-btn.view.ysf:hover { border-color: var(--violet); background: rgba(181,122,255,.06); }

/* ── Display rows ─────────────────────────────────────────────── */
.display-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; margin: 2rem 0; align-items: start; }
@media (max-width:900px) { .display-row { grid-template-columns: 1fr; } }
.panel-label { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .15em; color: var(--amber); text-transform: uppercase; margin-bottom: .5rem; }
.panel-label.ysf-label { color: var(--violet); }
.nextion { background: #060c10; border: 2px solid #1a3a4a; border-radius: 6px; box-shadow: 0 0 0 1px #0d2030, inset 0 0 40px rgba(0,212,255,.04), 0 0 30px rgba(0,212,255,.08); position: relative; overflow: hidden; height: 240px; display: flex; align-items: center; justify-content: center; }
.nextion::before,.nextion::after { content: '◈'; position: absolute; font-size: .6rem; color: #1a3a4a; }
.nextion::before { top: .5rem; left: .7rem; }
.nextion::after { bottom: .5rem; right: .7rem; }
.nextion-ysf { background: #08060e; border: 2px solid #2d1a4a; border-radius: 6px; box-shadow: 0 0 0 1px #1a0d30, inset 0 0 40px rgba(181,122,255,.04), 0 0 30px rgba(181,122,255,.1); position: relative; overflow: hidden; height: 240px; display: flex; align-items: center; justify-content: center; }
.nextion-ysf::before,.nextion-ysf::after { content: '◈'; position: absolute; font-size: .6rem; color: #2d1a4a; }
.nextion-ysf::before { top: .5rem; left: .7rem; }
.nextion-ysf::after { bottom: .5rem; right: .7rem; }
.nx-topbar { position: absolute; top: 0; left: 0; right: 0; height: 30px; background: #1c1c24; border-bottom: 1px solid #1a3a4a; display: flex; align-items: center; justify-content: space-between; padding: 0 1rem; font-family: var(--font-mono); font-size: .65rem; color: #2a5a7a; letter-spacing: .1em; }
.nx-topbar.ysf-bar { background: #1a1424; border-bottom: 1px solid #2d1a4a; color: #4a2a7a; }
.nx-topbar .nx-mode { color: var(--cyan); opacity: .7; }
.nx-topbar.ysf-bar .nx-mode { color: var(--violet); opacity: .8; }
.nx-topbar .nx-tg { color: var(--amber); opacity: .85; min-width: 5rem; text-align: right; }
.nx-topbar.ysf-bar .nx-dest { color: #d4a8ff; opacity: .85; min-width: 5rem; text-align: right; font-size: .6rem; }
.nx-botbar { position: absolute; bottom: 0; left: 0; right: 0; height: 28px; background: #0d1e2a; border-top: 1px solid #1a3a4a; display: flex; align-items: center; justify-content: space-between; padding: 0 1rem; font-family: var(--font-mono); font-size: .65rem; color: #2a5a7a; letter-spacing: .08em; }
.nx-botbar.ysf-bar { background: #110d1e; border-top: 1px solid #2d1a4a; color: #4a2a7a; }
.nx-botbar .nx-dmrid { color: #3a6a8a; min-width: 6rem; }
.nx-botbar .nx-source { padding: .1rem .45rem; border-radius: 2px; font-size: .6rem; letter-spacing: .1em; }
.nx-botbar .nx-source.rf { background: rgba(0,255,159,.15); color: var(--green); border: 1px solid rgba(0,255,159,.3); }
.nx-botbar .nx-source.net { background: rgba(0,212,255,.15); color: var(--cyan); border: 1px solid rgba(0,212,255,.3); }
.nx-vu { position: absolute; left: 1rem; top: 56px; bottom: 32px; width: 6px; display: flex; flex-direction: column-reverse; gap: 2px; }
.nx-vu.right { left: auto; right: 1rem; }
.nx-vu-bar { height: 5px; border-radius: 1px; background: #0d2030; transition: background .08s; }
.nx-vu-bar.lit-g { background: var(--green); box-shadow: 0 0 4px var(--green); }
.nx-vu-bar.lit-a { background: var(--amber); box-shadow: 0 0 4px var(--amber); }
.nx-vu-bar.lit-r { background: var(--red); box-shadow: 0 0 4px var(--red); }
.nx-vu-bar.lit-v { background: var(--violet); box-shadow: 0 0 4px var(--violet); }
.nx-vu-bar.lit-vd { background: #d4a8ff; box-shadow: 0 0 4px #d4a8ff; }
.nx-txbar { position: absolute; bottom: 28px; left: 0; right: 0; height: 3px; }
.nx-txbar.active { background: linear-gradient(90deg,transparent,var(--green),transparent); background-size: 200% 100%; animation: scan 1.4s linear infinite; }
.nx-txbar.active-ysf { background: linear-gradient(90deg,transparent,var(--violet),transparent); background-size: 200% 100%; animation: scan 1.4s linear infinite; }
@keyframes scan { from{background-position:200% 0} to{background-position:-200% 0} }
.nx-center { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .15rem; z-index: 1; }
.nx-clock { font-family: var(--font-orb); font-size: 4rem; font-weight: 700; color: #f5bd06; letter-spacing: .06em; line-height: 1; }
.nx-date { font-family: var(--font-mono); font-size: .7rem; color: #ff0; letter-spacing: .12em; text-transform: uppercase; margin-top: .2rem; }
.nx-callsign { font-family: var(--font-orb); font-size: 3.4rem; font-weight: 900; letter-spacing: .04em; line-height: 1; color: var(--green); text-shadow: 0 0 20px rgba(0,255,159,.55); }
.nx-callsign.ysf { color: var(--violet); text-shadow: 0 0 20px rgba(181,122,255,.6); }
.nx-name { font-family: var(--font-ui); font-weight: 500; font-size: 1.2rem; color: var(--cyan); letter-spacing: .18em; text-transform: uppercase; opacity: .9; margin-top: .15rem; }
.nx-name.ysf { color: #d4a8ff; }

/* ── Nextion info bar (Port / FRX / FTX / IP) ────────────────── */
.nx-infobar { position: absolute; top: 30px; left: 0; right: 0; height: 26px; background: rgba(0,0,0,.35); border-bottom: 1px solid #0d2030; display: flex; align-items: center; justify-content: space-around; padding: 0 3rem; gap: 1rem; z-index: 2; }
.nx-info-item { display: flex; align-items: center; gap: .4rem; }
.nx-info-lbl { font-family: var(--font-mono); font-size: .58rem; color: var(--text-dim); letter-spacing: .12em; text-transform: uppercase; }
.nx-info-val { font-family: var(--font-mono); font-size: .72rem; color: var(--text); letter-spacing: .06em; font-weight: bold; }
.nx-info-val.cyan  { color: var(--cyan); }
.nx-info-val.amber { color: var(--amber); }
.nx-info-val.green { color: var(--green); }
.nx-infobar-ysf { background: rgba(0,0,0,.4); border-bottom: 1px solid #1a0d30; }

/* ── Last Heard ───────────────────────────────────────────────── */
.lh-panel { background: var(--surface); border: 3px solid #1a3a4a; border-radius: 6px; display: flex; flex-direction: column; }
.lh-header { background: #1c1c24; border-bottom: 1px solid var(--border); padding: .4rem 1rem; display: grid; grid-template-columns: 1.1fr 1.5fr .7fr .7fr .5fr; gap: .3rem; font-family: var(--font-mono); font-size: .6rem; color: var(--text-dim); letter-spacing: .1em; text-transform: uppercase; }
.lh-body { flex: 1; overflow-y: auto; }
.lh-body::-webkit-scrollbar { width: 3px; }
.lh-body::-webkit-scrollbar-thumb { background: var(--border); }
.lh-row { display: grid; grid-template-columns: 1.1fr 1.5fr .7fr .7fr .5fr; gap: .3rem; padding: .45rem 1rem; border-bottom: 1px solid rgba(30,45,61,.6); align-items: center; transition: background .2s; }
.lh-row:last-child { border-bottom: none; }
.lh-row:hover { background: rgba(0,212,255,.04); }
.lh-row.lh-active { background: rgba(0,255,159,.06); }
.lh-call-wrap { display: flex; align-items: center; gap: .35rem; }
.lh-tx-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green); box-shadow: 0 0 6px var(--green); animation: pulse 1s infinite; flex-shrink: 0; }
.lh-call { font-family: var(--font-mono); font-size: .82rem; color: var(--green); letter-spacing: .05em; font-weight: bold; }
.lh-name { font-family: var(--font-ui); font-size: .82rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lh-tg { font-family: var(--font-mono); font-size: .72rem; color: var(--amber); }
.lh-time { font-family: var(--font-mono); font-size: .68rem; color: var(--text-dim); }
.lh-src { font-family: var(--font-mono); font-size: .6rem; }
.lh-src.rf { color: var(--green); }
.lh-src.net { color: var(--cyan); }
.lh-empty { padding: 1.5rem 1rem; font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); text-align: center; }
.lh-panel-ysf { background: var(--surface); border: 3px solid #2d1a4a; border-radius: 6px; display: flex; flex-direction: column; }
.lh-header-ysf { background: #1a1424; border-bottom: 1px solid #2d1a4a; padding: .4rem 1rem; display: grid; grid-template-columns: 1.2fr 1.8fr 1fr .6fr; gap: .3rem; font-family: var(--font-mono); font-size: .6rem; color: #4a2a7a; letter-spacing: .1em; text-transform: uppercase; }
.lh-row-ysf { display: grid; grid-template-columns: 1.2fr 1.8fr 1fr .6fr; gap: .3rem; padding: .45rem 1rem; border-bottom: 1px solid rgba(45,26,74,.5); align-items: center; transition: background .2s; }
.lh-row-ysf:last-child { border-bottom: none; }
</style>
</head>
<body>

<header class="ctrl-header">
    <div class="ctrl-header-top">
        <h1>Panel Control</h1>
    </div>
    <div class="ctrl-header-btns">
        <a href="#" class="btn-header cyan">Terminal</a>
        <a href="?action=backup-configs" class="btn-header amber">Backup</a>
        <a href="?action=reboot" class="btn-header red">Reboot</a>
    </div>
</header>

<div class="ctrl-body">
    <!-- Tu contenido original sigue aquí -->
</div>

<div class="modal fade" id="terminalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" style="background:#0a0e14;border:1px solid #1e2d3d;">
      <div class="modal-header" style="border-bottom:1px solid #1e2d3d;">
        <h5 class="modal-title" style="font-family:var(--font-mono);color:#00d4ff;">Terminal</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div id="terminal" style="height:60vh;width:100%;background:#000;"></div>
      </div>
    </div>
  </div>
</div>

<script>
let terminalInstance = null;
let terminalFit = null;
let terminalReady = false;

function initTerminal() {
    if (terminalReady) return;

    terminalInstance = new Terminal({
        theme: {
            background: '#000000',
            foreground: '#00ff9f',
            cursor: '#00d4ff'
        },
        fontFamily: 'monospace',
        fontSize: 14,
        cursorBlink: true,
        convertEol: true
    });

    terminalFit = new FitAddon.FitAddon();
    terminalInstance.loadAddon(terminalFit);
    terminalInstance.open(document.getElementById('terminal'));
    terminalFit.fit();
    terminalInstance.write('A108 Terminal ready\r\n$ ');

    terminalReady = true;
}

document.addEventListener('DOMContentLoaded', function () {
    const terminalBtn = document.querySelector('.btn-header.cyan');
    if (terminalBtn) {
        terminalBtn.setAttribute('data-bs-toggle', 'modal');
        terminalBtn.setAttribute('data-bs-target', '#terminalModal');
        terminalBtn.removeAttribute('href');
        terminalBtn.style.cursor = 'pointer';
    }

    const modal = document.getElementById('terminalModal');
    if (modal) {
        modal.addEventListener('shown.bs.modal', function () {
            initTerminal();
            setTimeout(() => {
                if (terminalFit) terminalFit.fit();
            }, 150);
        });
    }

    window.addEventListener('resize', function () {
        if (terminalFit) terminalFit.fit();
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
