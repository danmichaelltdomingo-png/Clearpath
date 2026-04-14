<?php
require_once 'includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['role'];
    if ($role === 'admin') header('Location: admin/dashboard.php');
    elseif ($role === 'signatory') header('Location: signatory/dashboard.php');
    else header('Location: student/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = trim($_POST['password'] ?? '');

    if ($identifier && $password) {
        $db = getDB();
        $hashed = sha1($password);
        $stmt = $db->prepare("SELECT * FROM users WHERE (email = ? OR student_id = ?) AND password = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param('sss', $identifier, $identifier, $hashed);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['full_name']  = $user['full_name'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['office']     = $user['office'];
            $_SESSION['student_id'] = $user['student_id'];

            logActivity('LOGIN', 'User logged in successfully');

            if ($user['role'] === 'admin') header('Location: admin/dashboard.php');
            elseif ($user['role'] === 'signatory') header('Location: signatory/dashboard.php');
            else header('Location: student/dashboard.php');
            exit;
        } else {
            $error = 'Invalid credentials. Please check your Student ID / Email and password.';
        }
        $db->close();
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ClearPath — BPC Digital School Clearance</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --blue-900: #0f1f5c;
    --blue-800: #1a2f7a;
    --blue-700: #1e3a9e;
    --blue-600: #2046c4;
    --blue-500: #2d55d4;
    --blue-400: #4f73e0;
    --blue-100: #dce6ff;
    --white: #ffffff;
    --gray-50: #f8f9fc;
    --gray-100: #eef0f6;
    --gray-200: #d8dbe8;
    --gray-400: #8b91ae;
    --gray-600: #4a5070;
    --gray-800: #1e2240;
    --error: #e53e3e;
    --success: #38a169;
    --shadow-lg: 0 20px 60px rgba(15, 31, 92, 0.18);
    --shadow-btn: 0 4px 20px rgba(32, 70, 196, 0.4);
  }

  html, body {
    height: 100%;
    font-family: 'DM Sans', sans-serif;
    background: linear-gradient(145deg, #e8edf8 0%, #dce6ff 50%, #c8d6f5 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 20px;
  }

  /* Background orbs */
  body::before {
    content: '';
    position: fixed; inset: 0;
    background:
      radial-gradient(ellipse 600px 400px at 20% 30%, rgba(45, 85, 212, 0.12) 0%, transparent 70%),
      radial-gradient(ellipse 400px 500px at 80% 70%, rgba(30, 58, 158, 0.10) 0%, transparent 70%);
    pointer-events: none;
    z-index: 0;
  }

  .login-card {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    width: 100%;
    max-width: 960px;
    min-height: 560px;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    animation: cardIn 0.7s cubic-bezier(0.16, 1, 0.3, 1) both;
  }

  @keyframes cardIn {
    from { opacity: 0; transform: translateY(32px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
  }

  /* LEFT PANEL */
  .left-panel {
    background: linear-gradient(160deg, var(--blue-900) 0%, var(--blue-700) 60%, var(--blue-600) 100%);
    padding: 48px 44px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
  }

  /* Animated bubbles */
  .bubble {
    position: absolute;
    border-radius: 50%;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    animation: floatBubble linear infinite;
    pointer-events: none;
    backdrop-filter: blur(2px);
  }

  @keyframes floatBubble {
    0%   { transform: translateY(0px) translateX(0px) scale(1);   opacity: 0; }
    10%  { opacity: 1; }
    50%  { transform: translateY(-180px) translateX(20px) scale(1.05); opacity: 0.8; }
    90%  { opacity: 0.4; }
    100% { transform: translateY(-380px) translateX(-10px) scale(0.9); opacity: 0; }
  }

  @keyframes driftBubble {
    0%   { transform: translate(0px, 0px) scale(1); }
    25%  { transform: translate(18px, -25px) scale(1.04); }
    50%  { transform: translate(-12px, -50px) scale(0.97); }
    75%  { transform: translate(22px, -30px) scale(1.02); }
    100% { transform: translate(0px, 0px) scale(1); }
  }

  .bubble-drift {
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
    animation: driftBubble ease-in-out infinite;
  }

  .brand {
    display: flex;
    align-items: center;
    gap: 14px;
    position: relative;
    z-index: 1;
  }
  .brand-logo {
    width: 44px; height: 44px;
    background: rgba(255,255,255,0.15);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Sora', sans-serif;
    font-weight: 800;
    font-size: 14px;
    color: var(--white);
    letter-spacing: -0.5px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
  }
  .brand-text h2 {
    font-family: 'Sora', sans-serif;
    font-weight: 700;
    font-size: 18px;
    color: var(--white);
    letter-spacing: -0.3px;
  }
  .brand-text p {
    font-size: 12px;
    color: rgba(255,255,255,0.6);
    font-weight: 300;
  }

  .hero-text {
    position: relative; z-index: 1;
  }
  .hero-text h1 {
    font-family: 'Sora', sans-serif;
    font-size: 46px;
    font-weight: 800;
    line-height: 1.05;
    color: var(--white);
    letter-spacing: -1.5px;
    margin-bottom: 20px;
  }
  .hero-text p {
    font-size: 14px;
    color: rgba(255,255,255,0.7);
    line-height: 1.7;
    font-weight: 300;
    max-width: 280px;
  }

  .features {
    position: relative; z-index: 1;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .feature-item {
    display: flex;
    align-items: center;
    gap: 12px;
    color: rgba(255,255,255,0.85);
    font-size: 13.5px;
  }
  .feature-icon {
    width: 22px; height: 22px;
    background: rgba(255,255,255,0.15);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    border: 1px solid rgba(255,255,255,0.25);
  }
  .feature-icon svg { width: 11px; height: 11px; }

  .left-footer {
    position: relative; z-index: 1;
    font-size: 11px;
    color: rgba(255,255,255,0.35);
  }

  /* RIGHT PANEL */
  .right-panel {
    background: var(--white);
    padding: 52px 48px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .right-panel h2 {
    font-family: 'Sora', sans-serif;
    font-size: 28px;
    font-weight: 700;
    color: var(--gray-800);
    letter-spacing: -0.8px;
    margin-bottom: 6px;
  }
  .right-panel .subtitle {
    font-size: 14px;
    color: var(--gray-400);
    margin-bottom: 36px;
  }

  .form-group {
    margin-bottom: 20px;
  }
  .form-group label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: var(--gray-600);
    margin-bottom: 8px;
  }
  .form-group input {
    width: 100%;
    padding: 13px 16px;
    border: 1.5px solid var(--gray-200);
    border-radius: 10px;
    font-size: 14.5px;
    font-family: 'DM Sans', sans-serif;
    color: var(--gray-800);
    background: var(--gray-50);
    transition: all 0.2s;
    outline: none;
  }
  .form-group input::placeholder { color: var(--gray-400); }
  .form-group input:focus {
    border-color: var(--blue-500);
    background: var(--white);
    box-shadow: 0 0 0 3px rgba(45, 85, 212, 0.1);
  }

  .error-msg {
    background: #fff5f5;
    border: 1px solid #fed7d7;
    color: var(--error);
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .btn-login {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, var(--blue-600) 0%, var(--blue-800) 100%);
    color: var(--white);
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    font-family: 'Sora', sans-serif;
    cursor: pointer;
    box-shadow: var(--shadow-btn);
    transition: all 0.2s;
    letter-spacing: -0.2px;
    margin-top: 4px;
  }
  .btn-login:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 28px rgba(32, 70, 196, 0.5);
  }
  .btn-login:active { transform: translateY(0); }

  .forgot-link {
    text-align: center;
    margin-top: 20px;
    font-size: 13px;
    color: var(--gray-400);
  }
  .forgot-link a {
    color: var(--blue-600);
    text-decoration: none;
    font-weight: 500;
  }
  .forgot-link a:hover { text-decoration: underline; }

  /* Demo Credentials */
  .demo-creds {
    margin-top: 28px;
    padding: 14px 16px;
    background: var(--gray-50);
    border: 1px dashed var(--gray-200);
    border-radius: 10px;
    font-size: 12px;
    color: var(--gray-400);
  }
  .demo-creds strong { color: var(--gray-600); display: block; margin-bottom: 6px; }
  .demo-creds span { display: block; line-height: 1.8; }

  @media (max-width: 720px) {
    .login-card { grid-template-columns: 1fr; }
    .left-panel { display: none; }
    .right-panel { padding: 40px 28px; }
  }
</style>
</head>
<body>

<div class="login-card">
  <!-- LEFT PANEL -->
  <div class="left-panel">

    <!-- Animated floating bubbles -->
    <div class="bubble-drift" style="width:320px;height:320px;bottom:-90px;right:-90px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);animation-duration:14s;animation-delay:0s;"></div>
    <div class="bubble-drift" style="width:180px;height:180px;top:-50px;right:30px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.10);animation-duration:11s;animation-delay:-3s;"></div>
    <div class="bubble-drift" style="width:100px;height:100px;top:38%;left:-30px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.09);animation-duration:9s;animation-delay:-1.5s;"></div>
    <div class="bubble-drift" style="width:60px;height:60px;top:20%;right:18%;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.14);animation-duration:7s;animation-delay:-4s;"></div>

    <!-- Rising bubbles container (JS-generated) -->
    <div id="bubble-container" style="position:absolute;inset:0;overflow:hidden;pointer-events:none;z-index:0;"></div>

    <div class="brand">
      <div class="brand-logo">CP</div>
      <div class="brand-text">
        <h2>ClearPath</h2>
        <p>BPC Digital School Clearance</p>
      </div>
    </div>

    <div class="hero-text">
      <h1>Paperless.<br>Faster.<br>Trackable.</h1>
      <p>ClearPath modernizes manual school clearance by enabling online submission, digital approvals, and real-time tracking.</p>
    </div>

    <div class="features">
      <div class="feature-item">
        <div class="feature-icon">
          <svg viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        Automated clearance processing
      </div>
      <div class="feature-item">
        <div class="feature-icon">
          <svg viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        Real-time status tracking
      </div>
      <div class="feature-item">
        <div class="feature-icon">
          <svg viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        Digital approval dashboard
      </div>
      <div class="feature-item">
        <div class="feature-icon">
          <svg viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        QR code verification + PDF download
      </div>
    </div>

    <div class="left-footer">BPC Student Clearance System &copy; <?= date('Y') ?></div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="right-panel">
    <h2>Welcome back</h2>
    <p class="subtitle">Sign in to your BPC ClearPath account.</p>

    <?php if ($error): ?>
    <div class="error-msg">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="#e53e3e" stroke-width="1.5"/><path d="M8 5v3.5M8 11h.01" stroke="#e53e3e" stroke-width="1.5" stroke-linecap="round"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label>Student ID / Email</label>
        <input type="text" name="identifier" placeholder="e.g., 2020-0001 or juan@bpc.edu.ph" value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>" autocomplete="username" required>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn-login">Login</button>
    </form>

    <p class="forgot-link">Forgot your password? <a href="forgot_password.php">Reset Password</a></p>

    <div class="demo-creds">
      <strong>🔑 Demo Accounts (password: @bpc123)</strong>
      <span>Admin: admin@bpc.edu.ph</span>
      <span>Signatory: sgadviser@bpc.edu.ph</span>
      <span>Student ID: 2020-0001</span>
    </div>
  </div>
</div>

<script>
// ── Animated rising bubbles for the left panel ──────────────────────────
(function () {
  const container = document.getElementById('bubble-container');
  if (!container) return;

  // Config: variety of bubble sizes and speeds
  const configs = [
    { size: 14,  minDelay: 0,   duration: 7,  opacity: 0.18 },
    { size: 22,  minDelay: 1,   duration: 9,  opacity: 0.12 },
    { size: 10,  minDelay: 0.5, duration: 6,  opacity: 0.20 },
    { size: 34,  minDelay: 2,   duration: 11, opacity: 0.09 },
    { size: 18,  minDelay: 3,   duration: 8,  opacity: 0.15 },
    { size: 8,   minDelay: 1.5, duration: 5,  opacity: 0.22 },
    { size: 28,  minDelay: 0.8, duration: 10, opacity: 0.10 },
    { size: 44,  minDelay: 4,   duration: 13, opacity: 0.07 },
    { size: 12,  minDelay: 2.5, duration: 7,  opacity: 0.16 },
    { size: 20,  minDelay: 1.2, duration: 9,  opacity: 0.13 },
    { size: 16,  minDelay: 3.5, duration: 6,  opacity: 0.18 },
    { size: 36,  minDelay: 0.3, duration: 12, opacity: 0.08 },
  ];

  function spawnBubble(cfg, initialDelay) {
    const el = document.createElement('div');

    const size     = cfg.size + Math.random() * cfg.size * 0.5;
    const leftPct  = 5 + Math.random() * 90; // % from left
    const duration = cfg.duration + Math.random() * 4;
    const delay    = initialDelay !== undefined ? initialDelay : (Math.random() * cfg.minDelay);
    const drift    = (Math.random() - 0.5) * 60; // horizontal drift px

    el.style.cssText = [
      'position:absolute',
      'border-radius:50%',
      'pointer-events:none',
      'will-change:transform,opacity',
      `width:${size}px`,
      `height:${size}px`,
      `left:${leftPct}%`,
      'bottom:-60px',
      `background:rgba(255,255,255,${cfg.opacity})`,
      `border:1px solid rgba(255,255,255,${cfg.opacity * 1.8})`,
      `animation:riseBubble ${duration}s ${delay}s ease-in forwards`,
      `--drift:${drift}px`,
    ].join(';');

    container.appendChild(el);

    // Remove after animation, then respawn
    const totalMs = (duration + delay) * 1000;
    setTimeout(() => {
      el.remove();
      spawnBubble(cfg, 0); // respawn with no extra delay
    }, totalMs + 200);
  }

  // Inject keyframe into the page dynamically
  const style = document.createElement('style');
  style.textContent = `
    @keyframes riseBubble {
      0%   { transform: translateY(0)      translateX(0)          scale(1);    opacity: 0;   }
      8%   { opacity: 1; }
      40%  { transform: translateY(-200px) translateX(var(--drift, 20px))  scale(1.05); opacity: 0.9; }
      80%  { opacity: 0.5; }
      100% { transform: translateY(-520px) translateX(calc(var(--drift, 20px) * 0.6)) scale(0.85); opacity: 0; }
    }
  `;
  document.head.appendChild(style);

  // Spawn all bubbles with staggered initial delays so it doesn't all start at once
  configs.forEach((cfg, i) => {
    const stagger = (i / configs.length) * 10; // spread across 0–10s
    spawnBubble(cfg, stagger);
  });

  // Mouse parallax: drift bubbles slightly with cursor movement
  const panel = document.querySelector('.left-panel');
  if (panel) {
    panel.addEventListener('mousemove', (e) => {
      const rect = panel.getBoundingClientRect();
      const cx   = (e.clientX - rect.left) / rect.width  - 0.5; // -0.5 to 0.5
      const cy   = (e.clientY - rect.top)  / rect.height - 0.5;
      container.style.transform = `translate(${cx * 12}px, ${cy * 8}px)`;
    });
    panel.addEventListener('mouseleave', () => {
      container.style.transform = 'translate(0,0)';
      container.style.transition = 'transform 0.8s ease';
      setTimeout(() => container.style.transition = '', 800);
    });
    panel.addEventListener('mouseenter', () => {
      container.style.transition = 'transform 0.15s ease';
    });
  }
})();
</script>
</body>
</html>
