<?php
session_start();

if (!empty($_SESSION['authenticated'])) {
    header('Location: mmdvm.php');
    exit;
}

$error   = '';
$pwdFile = __DIR__ . '/password.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = trim($_POST['password'] ?? '');

    if (!file_exists($pwdFile)) {
        $error = 'Fichero de contraseña no encontrado.';
    } else {
        $data  = json_decode(file_get_contents($pwdFile), true);

        // Soporte formato antiguo (un solo hash)
        if (isset($data['hash'])) {
            $users = [['user' => 'admin', 'hash' => $data['hash']]];
        } else {
            $users = $data['users'] ?? [];
        }

        $ok = false;
        foreach ($users as $u) {
            if (!empty($u['hash']) && password_verify($input, $u['hash'])) {
                $ok = true;
                $_SESSION['username'] = $u['user'];
                break;
            }
        }

        if ($ok) {
            $_SESSION['authenticated'] = true;
            header('Location: mmdvm.php');
            exit;
        } else {
            $error = 'Contraseña incorrecta.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · MMDVM Control</title>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:        #0a0e14;
    --surface:   #111720;
    --border:    #1e2d3d;
    --green:     #00ff9f;
    --red:       #ff4560;
    --text:      #a8b9cc;
    --text-dim:  #4a5568;
    --font-mono: 'Share Tech Mono', monospace;
    --font-orb:  'Orbitron', monospace;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: var(--bg); color: var(--text); font-family: var(--font-mono);
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
  }
  .login-box {
    background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
    padding: 2.5rem 2.5rem 2rem; width: 100%; max-width: 380px;
    box-shadow: 0 0 40px rgba(0,212,255,.06);
  }
  .login-logo { display: flex; justify-content: center; margin-bottom: 1.5rem; }
  .login-logo img { height: 56px; width: auto; }
  .login-title {
    font-family: var(--font-orb); font-size: 1rem; font-weight: 700;
    color: #e2eaf5; text-align: center; letter-spacing: .1em;
    text-transform: uppercase; margin-bottom: .4rem;
  }
  .login-sub {
    font-size: .72rem; color: var(--text-dim); text-align: center;
    letter-spacing: .1em; text-transform: uppercase; margin-bottom: 2rem;
  }
  label {
    font-size: .75rem; color: var(--text-dim); letter-spacing: .1em;
    text-transform: uppercase; display: block; margin-bottom: .5rem;
  }
  .input-wrap { position: relative; margin-bottom: 1.2rem; }
  input[type=password] {
    width: 100%; background: #0d1e2a; border: 1px solid var(--border);
    border-radius: 6px; color: var(--green); font-family: var(--font-mono);
    font-size: 1.1rem; padding: .65rem 2.8rem .65rem .9rem; outline: none;
    letter-spacing: .2em; transition: border-color .2s;
  }
  input[type=password]:focus { border-color: var(--green); }
  .eye-btn {
    position: absolute; right: .7rem; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; color: var(--text-dim);
    font-size: 1rem; padding: 0; transition: color .2s;
  }
  .eye-btn:hover { color: var(--green); }
  .btn-login {
    width: 100%; background: #28a745; color: #fff; border: none; border-radius: 6px;
    font-family: var(--font-mono); font-size: .9rem; letter-spacing: .15em;
    text-transform: uppercase; padding: .75rem; cursor: pointer; transition: background .2s;
  }
  .btn-login:hover { background: #218838; }
  .error-msg {
    background: rgba(255,69,96,.1); border: 1px solid var(--red); border-radius: 6px;
    color: var(--red); font-size: .78rem; padding: .6rem .9rem; margin-bottom: 1.2rem;
    text-align: center; letter-spacing: .05em;
  }
  .lock-icon { text-align: center; font-size: 2rem; margin-bottom: 1rem; opacity: .3; }
</style>
</head>
<body>
<div class="login-box">
  <div class="login-logo">
    <img src="Logo_ea3eiz.png" alt="EA3EIZ"
         onerror="this.style.display='none'">
  </div>
  <div class="login-title">MMDVM Control</div>
  <div class="login-sub">EA3EIZ · Associació ADER</div>
  <div class="lock-icon">🔒</div>

  <?php if ($error): ?>
  <div class="error-msg">✖ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <label for="password">Contraseña</label>
    <div class="input-wrap">
      <input type="password" id="password" name="password" placeholder="••••••" autofocus>
      <button type="button" class="eye-btn" onclick="togglePwd()"><span id="eyeIcon">👁</span></button>
    </div>
    <button type="submit" class="btn-login">Entrar →</button>
  </form>
</div>

<script>
function togglePwd() {
  const inp  = document.getElementById('password');
  const icon = document.getElementById('eyeIcon');
  inp.type   = inp.type === 'password' ? 'text' : 'password';
  icon.textContent = inp.type === 'password' ? '👁' : '🙈';
}
</script>
</body>
</html>
