<?php
require_once 'includes/config.php';

if (isLoggedIn()) { header('Location: ' . APP_URL . '/index.php'); exit; }

$token    = trim($_GET['token'] ?? '');
$msg      = '';
$type     = '';
$done     = false;
$validToken = null;

// Validate token
if (!$token) {
    $msg  = 'Invalid or missing reset token. Please request a new link.';
    $type = 'error';
} else {
    $db   = getDB();
    $safe = $db->real_escape_string($token);
    $row  = $db->query("
        SELECT pr.*, u.full_name, u.email
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token = '$safe'
          AND pr.used = 0
          AND pr.expires_at > NOW()
        LIMIT 1
    ")->fetch_assoc();

    if (!$row) {
        $msg  = 'This reset link is invalid or has expired. Please request a new one.';
        $type = 'expired';
    } else {
        $validToken = $row;
    }
    $db->close();
}

// Handle new password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $new     = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if (strlen($new) < 6) {
        $msg  = 'Password must be at least 6 characters.';
        $type = 'error';
    } elseif ($new !== $confirm) {
        $msg  = 'Passwords do not match.';
        $type = 'error';
    } else {
        $db      = getDB();
        $uid     = (int)$validToken['user_id'];
        $hashed  = sha1($new);
        $tokenSafe = $db->real_escape_string($token);

        $db->query("UPDATE users SET password='$hashed' WHERE id=$uid");
        $db->query("UPDATE password_resets SET used=1 WHERE token='$tokenSafe'");

        logActivity('RESET_PASSWORD', 'Password reset via email link for user ID: ' . $uid);
        $db->close();

        $done = true;
        $msg  = 'Your password has been reset successfully! You can now log in.';
        $type = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — ClearPath BPC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --blue-900: #0f1f5c; --blue-800: #1a2f7a; --blue-700: #1e3a9e;
    --blue-600: #2046c4; --blue-500: #2d55d4;
    --white: #ffffff;
    --gray-50: #f8f9fc; --gray-100: #eef0f6; --gray-200: #d8dbe8;
    --gray-400: #8b91ae; --gray-600: #4a5070; --gray-800: #1e2240;
    --success: #059669; --success-bg: #d1fae5; --success-border: #6ee7b7;
    --error: #dc2626;   --error-bg: #fee2e2;   --error-border: #fca5a5;
    --warning: #d97706; --warning-bg: #fef3c7; --warning-border: #fcd34d;
    --shadow-lg: 0 20px 60px rgba(15,31,92,0.18);
    --shadow-btn: 0 4px 20px rgba(32,70,196,0.35);
  }

  html, body {
    min-height: 100vh; font-family: 'DM Sans', sans-serif;
    background: linear-gradient(145deg, #e8edf8 0%, #dce6ff 50%, #c8d6f5 100%);
    display: flex; align-items: center; justify-content: center; padding: 24px;
  }
  body::before {
    content: ''; position: fixed; inset: 0;
    background: radial-gradient(ellipse 600px 400px at 15% 25%, rgba(45,85,212,0.12) 0%, transparent 70%),
                radial-gradient(ellipse 400px 500px at 85% 75%, rgba(30,58,158,0.10) 0%, transparent 70%);
    pointer-events: none; z-index: 0;
  }

  .page-card {
    position: relative; z-index: 1;
    width: 100%; max-width: 480px;
    background: var(--white); border-radius: 24px;
    box-shadow: var(--shadow-lg); overflow: hidden;
    animation: cardIn 0.6s cubic-bezier(0.16,1,0.3,1) both;
  }
  @keyframes cardIn {
    from { opacity: 0; transform: translateY(28px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
  }

  .card-top {
    background: linear-gradient(135deg, var(--blue-900) 0%, var(--blue-700) 60%, var(--blue-600) 100%);
    padding: 32px 40px; position: relative; overflow: hidden;
  }
  .card-top::before {
    content: ''; position: absolute; width: 220px; height: 220px;
    border-radius: 50%; background: rgba(255,255,255,0.05); bottom: -80px; right: -60px;
  }
  .card-top::after {
    content: ''; position: absolute; width: 120px; height: 120px;
    border-radius: 50%; background: rgba(255,255,255,0.06); top: -40px; right: 60px;
  }

  .brand { display: flex; align-items: center; gap: 12px; position: relative; z-index: 1; margin-bottom: 24px; }
  .brand-logo {
    width: 40px; height: 40px; background: rgba(255,255,255,0.15); border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Sora', sans-serif; font-weight: 800; font-size: 13px; color: #fff;
    border: 1px solid rgba(255,255,255,0.2);
  }
  .brand-text h2 { font-family: 'Sora', sans-serif; font-size: 16px; font-weight: 700; color: #fff; }
  .brand-text p  { font-size: 11px; color: rgba(255,255,255,0.55); }

  .key-icon {
    position: relative; z-index: 1;
    width: 58px; height: 58px; background: rgba(255,255,255,0.12);
    border-radius: 14px; display: flex; align-items: center; justify-content: center;
    border: 1px solid rgba(255,255,255,0.2); margin-bottom: 14px;
  }
  .key-icon svg { width: 26px; height: 26px; }
  .card-top h1 { position: relative; z-index: 1; font-family: 'Sora', sans-serif; font-size: 22px; font-weight: 800; color: #fff; letter-spacing: -0.5px; margin-bottom: 6px; }
  .card-top p  { position: relative; z-index: 1; font-size: 13px; color: rgba(255,255,255,0.65); line-height: 1.6; }

  /* User pill */
  .user-pill {
    position: relative; z-index: 1;
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2);
    border-radius: 20px; padding: 6px 14px; margin-top: 14px;
    font-size: 12.5px; color: rgba(255,255,255,0.85);
  }
  .user-pill svg { width: 13px; height: 13px; opacity: 0.7; }

  .card-body { padding: 36px 40px; }

  .alert {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 14px 16px; border-radius: 10px; font-size: 13.5px;
    line-height: 1.5; margin-bottom: 24px;
  }
  .alert svg { flex-shrink: 0; margin-top: 1px; }
  .alert.success { background: var(--success-bg); border: 1px solid var(--success-border); color: var(--success); }
  .alert.error   { background: var(--error-bg);   border: 1px solid var(--error-border);   color: var(--error); }
  .alert.warning { background: var(--warning-bg); border: 1px solid var(--warning-border); color: var(--warning); }

  .form-group { margin-bottom: 20px; }
  .form-label { display: block; font-size: 13px; font-weight: 500; color: var(--gray-600); margin-bottom: 7px; }
  .input-wrap { position: relative; }
  .form-control {
    width: 100%; padding: 12px 44px 12px 16px;
    border: 1.5px solid var(--gray-200); border-radius: 10px;
    font-size: 14.5px; font-family: 'DM Sans', sans-serif;
    color: var(--gray-800); background: var(--gray-50);
    transition: all 0.2s; outline: none;
  }
  .form-control::placeholder { color: var(--gray-400); }
  .form-control:focus { border-color: var(--blue-500); background: #fff; box-shadow: 0 0 0 3px rgba(45,85,212,0.1); }
  .form-control.valid   { border-color: var(--success); }
  .form-control.invalid { border-color: var(--error); }

  .eye-btn {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; color: var(--gray-400);
    padding: 4px; transition: color 0.15s;
  }
  .eye-btn:hover { color: var(--blue-500); }
  .eye-btn svg { width: 17px; height: 17px; }

  /* Strength bar */
  .strength-wrap { margin-top: 8px; }
  .strength-bar-bg { height: 4px; background: var(--gray-100); border-radius: 10px; overflow: hidden; }
  .strength-bar    { height: 100%; border-radius: 10px; transition: all 0.35s; width: 0%; }
  .strength-label  { font-size: 11px; margin-top: 4px; }

  /* Password rules */
  .rules-box {
    background: var(--gray-50); border: 1px solid var(--gray-100);
    border-radius: 9px; padding: 12px 16px; margin-bottom: 20px;
  }
  .rule { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--gray-400); margin-bottom: 5px; }
  .rule:last-child { margin-bottom: 0; }
  .rule-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--gray-200); flex-shrink: 0; transition: all 0.2s; }
  .rule.met { color: var(--success); }
  .rule.met .rule-dot { background: var(--success); }

  /* Match indicator */
  .match-msg { font-size: 11.5px; margin-top: 6px; min-height: 16px; }

  .btn-submit {
    width: 100%; padding: 13px;
    background: linear-gradient(135deg, var(--blue-600), var(--blue-800));
    color: #fff; border: none; border-radius: 10px;
    font-size: 15px; font-weight: 600; font-family: 'Sora', sans-serif;
    cursor: pointer; box-shadow: var(--shadow-btn); transition: all 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
  }
  .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(32,70,196,0.5); }
  .btn-submit:active { transform: translateY(0); }
  .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

  .back-link {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    margin-top: 20px; font-size: 13px; color: var(--gray-400);
    text-decoration: none; transition: color 0.15s;
  }
  .back-link:hover { color: var(--blue-600); }

  /* Done state */
  .done-state { text-align: center; padding: 8px 0; }
  .done-icon {
    width: 72px; height: 72px; border-radius: 50%;
    background: var(--success-bg); border: 2px solid var(--success-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px;
  }
  .done-icon svg { width: 36px; height: 36px; color: var(--success); }
  .done-state h3 { font-family: 'Sora', sans-serif; font-size: 20px; font-weight: 700; color: var(--gray-800); margin-bottom: 10px; }
  .done-state p  { font-size: 13.5px; color: var(--gray-400); line-height: 1.7; margin-bottom: 24px; }
  .btn-login {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 32px; background: linear-gradient(135deg, var(--blue-600), var(--blue-800));
    color: #fff; text-decoration: none; border-radius: 10px;
    font-size: 14px; font-weight: 600; font-family: 'Sora', sans-serif;
    box-shadow: var(--shadow-btn); transition: all 0.2s;
  }
  .btn-login:hover { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(32,70,196,0.5); }

  @keyframes spin { to { transform: rotate(360deg); } }
  .spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }

  /* Countdown bar for redirect */
  .countdown-bar { height: 3px; background: var(--blue-100); border-radius: 10px; margin-top: 16px; overflow: hidden; }
  .countdown-fill { height: 100%; background: var(--blue-500); border-radius: 10px; width: 100%; animation: shrink 5s linear forwards; }
  @keyframes shrink { to { width: 0%; } }
</style>
</head>
<body>

<div class="page-card">

  <div class="card-top">
    <div class="brand">
      <div class="brand-logo">CP</div>
      <div class="brand-text"><h2>ClearPath</h2><p>BPC Digital School Clearance</p></div>
    </div>
    <div class="key-icon">
      <svg viewBox="0 0 24 24" fill="none">
        <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4" stroke="rgba(255,255,255,0.9)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <h1>Set New Password</h1>
    <p>Choose a strong password to secure your ClearPath account.</p>
    <?php if ($validToken): ?>
    <div class="user-pill">
      <svg viewBox="0 0 24 24" fill="none"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="1.5"/></svg>
      <?= htmlspecialchars($validToken['full_name']) ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="card-body">

    <?php if ($done): ?>
    <!-- ── DONE ── -->
    <div class="done-state">
      <div class="done-icon">
        <svg viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <h3>Password Updated!</h3>
      <p>Your password has been changed successfully. You can now log in with your new password.</p>
      <a href="<?= APP_URL ?>/index.php" class="btn-login">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Go to Login
      </a>
      <div class="countdown-bar"><div class="countdown-fill"></div></div>
      <p style="font-size:11px;color:var(--gray-400);margin-top:8px">Redirecting automatically in 5 seconds…</p>
    </div>
    <script>setTimeout(() => { window.location = '<?= APP_URL ?>/index.php'; }, 5000);</script>

    <?php elseif ($type === 'expired' || ($type === 'error' && !$validToken)): ?>
    <!-- ── EXPIRED / INVALID ── -->
    <div class="alert warning">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="currentColor" stroke-width="1.5"/><line x1="12" y1="9" x2="12" y2="13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="12" y1="17" x2="12.01" y2="17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      <?= htmlspecialchars($msg) ?>
    </div>
    <a href="<?= APP_URL ?>/forgot_password.php" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;background:linear-gradient(135deg,var(--blue-600),var(--blue-800));color:#fff;text-decoration:none;border-radius:10px;font-size:14px;font-weight:600;font-family:'Sora',sans-serif;box-shadow:var(--shadow-btn)">
      Request a New Reset Link
    </a>

    <?php else: ?>
    <!-- ── FORM ── -->
    <?php if ($msg && $type === 'error'): ?>
    <div class="alert error">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/><path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="resetForm">
      <div class="form-group">
        <label class="form-label">New Password</label>
        <div class="input-wrap">
          <input type="password" name="new_password" id="new_pw" class="form-control" placeholder="At least 6 characters" required oninput="checkStrength(this.value)">
          <button type="button" class="eye-btn" onclick="togglePw('new_pw', 'eye1')">
            <svg id="eye1" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/></svg>
          </button>
        </div>
        <div class="strength-wrap">
          <div class="strength-bar-bg"><div class="strength-bar" id="strength-bar"></div></div>
          <div class="strength-label" id="strength-label"></div>
        </div>
      </div>

      <div class="rules-box">
        <div class="rule" id="r-len"><div class="rule-dot"></div>At least 6 characters</div>
        <div class="rule" id="r-num"><div class="rule-dot"></div>Contains a number</div>
        <div class="rule" id="r-sym"><div class="rule-dot"></div>Contains a special character (recommended)</div>
      </div>

      <div class="form-group">
        <label class="form-label">Confirm New Password</label>
        <div class="input-wrap">
          <input type="password" name="confirm_password" id="conf_pw" class="form-control" placeholder="Re-enter new password" required oninput="checkMatch()">
          <button type="button" class="eye-btn" onclick="togglePw('conf_pw', 'eye2')">
            <svg id="eye2" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/></svg>
          </button>
        </div>
        <div class="match-msg" id="match-msg"></div>
      </div>

      <button type="submit" class="btn-submit" id="submitBtn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        Update Password
      </button>
    </form>
    <?php endif; ?>

    <a href="<?= APP_URL ?>/index.php" class="back-link">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M19 12H5M12 19l-7-7 7-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Back to Login
    </a>

  </div>
</div>

<script>
const EYE_OPEN  = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/>';
const EYE_CLOSE = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="1" y1="1" x2="23" y2="23" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>';

function togglePw(inputId, eyeId) {
  const inp = document.getElementById(inputId);
  const eye = document.getElementById(eyeId);
  if (inp.type === 'password') { inp.type = 'text';     eye.innerHTML = EYE_CLOSE; }
  else                          { inp.type = 'password'; eye.innerHTML = EYE_OPEN;  }
}

function setRule(id, met) {
  const el = document.getElementById(id);
  el.classList.toggle('met', met);
}

function checkStrength(val) {
  const hasLen = val.length >= 6;
  const hasNum = /\d/.test(val);
  const hasSym = /[^a-zA-Z0-9]/.test(val);
  const hasLng = val.length >= 10;

  setRule('r-len', hasLen);
  setRule('r-num', hasNum);
  setRule('r-sym', hasSym);

  const score = [hasLen, hasNum, hasSym, hasLng].filter(Boolean).length;
  const bar   = document.getElementById('strength-bar');
  const lbl   = document.getElementById('strength-label');
  const cfg   = [
    { w:'0%',   c:'transparent',    t:'',       tc:'var(--gray-400)' },
    { w:'25%',  c:'var(--error)',    t:'Weak',   tc:'var(--error)' },
    { w:'50%',  c:'var(--warning)',  t:'Fair',   tc:'var(--warning)' },
    { w:'75%',  c:'var(--blue-500)', t:'Good',   tc:'var(--blue-500)' },
    { w:'100%', c:'var(--success)',  t:'Strong', tc:'var(--success)' },
  ][score];
  bar.style.width      = cfg.w;
  bar.style.background = cfg.c;
  lbl.textContent      = cfg.t;
  lbl.style.color      = cfg.tc;

  checkMatch();
}

function checkMatch() {
  const np = document.getElementById('new_pw').value;
  const cp = document.getElementById('conf_pw').value;
  const el = document.getElementById('match-msg');
  const ci = document.getElementById('conf_pw');
  if (!cp) { el.textContent = ''; ci.classList.remove('valid','invalid'); return; }
  if (np === cp) {
    el.innerHTML  = '<span style="color:var(--success)">✓ Passwords match</span>';
    ci.classList.add('valid'); ci.classList.remove('invalid');
  } else {
    el.innerHTML  = '<span style="color:var(--error)">✗ Passwords do not match</span>';
    ci.classList.add('invalid'); ci.classList.remove('valid');
  }
}

const form = document.getElementById('resetForm');
const btn  = document.getElementById('submitBtn');
if (form) {
  form.addEventListener('submit', () => {
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner"></div> Updating…';
  });
}
</script>

</body>
</html>
