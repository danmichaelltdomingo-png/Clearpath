<?php
// require_once 'includes/config.php';

// // Redirect if already logged in
// if (isLoggedIn()) {
//     header('Location: ' . APP_URL . '/index.php');
//     exit;
// }

// $msg   = '';
// $type  = '';
// $sent  = false;

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $identifier = trim($_POST['identifier'] ?? '');

//     if (!$identifier) {
//         $msg  = 'Please enter your Student ID or email address.';
//         $type = 'error';
//     } else {
//         $db   = getDB();
//         $safe = $db->real_escape_string($identifier);
//         $user = $db->query("SELECT * FROM users WHERE (email='$safe' OR student_id='$safe') AND is_active=1 LIMIT 1")->fetch_assoc();

//         if (!$user) {
//             // Intentionally vague for security
//             $msg  = 'If that account exists, a reset link has been sent to the registered email.';
//             $type = 'success';
//             $sent = true;
//         } else {
//             // Delete any existing unused tokens for this user
//             $uid = (int)$user['id'];
//             $db->query("DELETE FROM password_resets WHERE user_id=$uid AND used=0");

//             // Generate token
//             $token   = bin2hex(random_bytes(32));
//             $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
//             $db->query("INSERT INTO password_resets (user_id, token, expires_at) VALUES ($uid, '$token', '$expires')");

//             $resetLink = APP_URL . '/reset_password.php?token=' . $token;

//             require_once 'includes/mailer.php';
//             $result = sendPasswordResetEmail($user['email'], $user['full_name'], $resetLink);

//             if ($result === true) {
//                 logActivity('FORGOT_PASSWORD', 'Password reset email sent to: ' . $user['email']);
//                 $msg  = 'A password reset link has been sent to your registered email address. Please check your inbox (and spam folder).';
//                 $type = 'success';
//                 $sent = true;
//             } else {
//                 $msg  = 'Failed to send email. Please contact the Registrar directly. Error: ' . $result;
//                 $type = 'error';
//             }
//             $db->close();
//         }
//     }
// }

require_once 'includes/config.php';

$msg = '';
$type = '';
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');

    if (!$identifier) {
        $msg = 'Please enter your Student ID or email.';
        $type = 'error';
    } else {
        $db = getDB();
        $safe = $db->real_escape_string($identifier);

        $user = $db->query("
            SELECT * FROM users 
            WHERE (email='$safe' OR student_id='$safe') 
            LIMIT 1
        ")->fetch_assoc();

        if ($user) {
            $uid = (int)$user['id'];

            // delete old tokens
            $db->query("DELETE FROM password_resets WHERE user_id=$uid");

            // create new token
            $token = bin2hex(random_bytes(32));

            // FIXED: consistent time
            $expires = date('Y-m-d H:i:s', time() + (30 * 60));

            $db->query("
                INSERT INTO password_resets (user_id, token, expires_at, used)
                VALUES ($uid, '$token', '$expires', 0)
            ");

            $resetLink = APP_URL . "/reset_password.php?token=$token";

            require_once 'includes/mailer.php';
            sendPasswordResetEmail($user['email'], $user['full_name'], $resetLink);
        }

        $msg = 'If that account exists, a reset link has been sent.';
        $type = 'success';
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — ClearPath BPC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --blue-900: #0f1f5c; --blue-800: #1a2f7a; --blue-700: #1e3a9e;
    --blue-600: #2046c4; --blue-500: #2d55d4; --blue-400: #4f73e0;
    --blue-50:  #eef2ff; --blue-100: #dce6ff;
    --white: #ffffff;
    --gray-50: #f8f9fc; --gray-100: #eef0f6; --gray-200: #d8dbe8;
    --gray-400: #8b91ae; --gray-600: #4a5070; --gray-800: #1e2240;
    --success: #059669; --success-bg: #d1fae5; --success-border: #6ee7b7;
    --error: #dc2626;   --error-bg: #fee2e2;   --error-border: #fca5a5;
    --shadow-lg: 0 20px 60px rgba(15,31,92,0.18);
    --shadow-btn: 0 4px 20px rgba(32,70,196,0.35);
  }

  html, body {
    min-height: 100vh;
    font-family: 'DM Sans', sans-serif;
    background: linear-gradient(145deg, #e8edf8 0%, #dce6ff 50%, #c8d6f5 100%);
    display: flex; align-items: center; justify-content: center;
    padding: 24px;
  }

  body::before {
    content: '';
    position: fixed; inset: 0;
    background:
      radial-gradient(ellipse 600px 400px at 15% 25%, rgba(45,85,212,0.12) 0%, transparent 70%),
      radial-gradient(ellipse 400px 500px at 85% 75%, rgba(30,58,158,0.10) 0%, transparent 70%);
    pointer-events: none; z-index: 0;
  }

  .page-card {
    position: relative; z-index: 1;
    width: 100%; max-width: 480px;
    background: var(--white);
    border-radius: 24px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    animation: cardIn 0.6s cubic-bezier(0.16,1,0.3,1) both;
  }

  @keyframes cardIn {
    from { opacity: 0; transform: translateY(28px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
  }

  /* Top blue strip */
  .card-top {
    background: linear-gradient(135deg, var(--blue-900) 0%, var(--blue-700) 60%, var(--blue-600) 100%);
    padding: 32px 40px;
    position: relative; overflow: hidden;
  }
  .card-top::before {
    content: ''; position: absolute;
    width: 220px; height: 220px; border-radius: 50%;
    background: rgba(255,255,255,0.05);
    bottom: -80px; right: -60px;
  }
  .card-top::after {
    content: ''; position: absolute;
    width: 120px; height: 120px; border-radius: 50%;
    background: rgba(255,255,255,0.06);
    top: -40px; right: 60px;
  }

  .brand {
    display: flex; align-items: center; gap: 12px;
    position: relative; z-index: 1; margin-bottom: 28px;
  }
  .brand-logo {
    width: 40px; height: 40px; background: rgba(255,255,255,0.15);
    border-radius: 10px; display: flex; align-items: center; justify-content: center;
    font-family: 'Sora', sans-serif; font-weight: 800; font-size: 13px; color: #fff;
    border: 1px solid rgba(255,255,255,0.2);
  }
  .brand-text h2 { font-family: 'Sora', sans-serif; font-size: 16px; font-weight: 700; color: #fff; }
  .brand-text p  { font-size: 11px; color: rgba(255,255,255,0.55); }

  .lock-icon {
    position: relative; z-index: 1;
    width: 60px; height: 60px; background: rgba(255,255,255,0.12);
    border-radius: 16px; display: flex; align-items: center; justify-content: center;
    border: 1px solid rgba(255,255,255,0.2); margin-bottom: 14px;
  }
  .lock-icon svg { width: 28px; height: 28px; }

  .card-top h1 {
    position: relative; z-index: 1;
    font-family: 'Sora', sans-serif; font-size: 22px; font-weight: 800;
    color: #fff; letter-spacing: -0.5px; margin-bottom: 6px;
  }
  .card-top p {
    position: relative; z-index: 1;
    font-size: 13px; color: rgba(255,255,255,0.65); line-height: 1.6;
  }

  /* Form area */
  .card-body { padding: 36px 40px; }

  .alert {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 14px 16px; border-radius: 10px;
    font-size: 13.5px; line-height: 1.5; margin-bottom: 24px;
  }
  .alert svg { flex-shrink: 0; margin-top: 1px; }
  .alert.success { background: var(--success-bg); border: 1px solid var(--success-border); color: var(--success); }
  .alert.error   { background: var(--error-bg);   border: 1px solid var(--error-border);   color: var(--error); }

  .form-group { margin-bottom: 20px; }
  .form-label {
    display: block; font-size: 13px; font-weight: 500;
    color: var(--gray-600); margin-bottom: 7px;
  }
  .form-control {
    width: 100%; padding: 12px 16px;
    border: 1.5px solid var(--gray-200); border-radius: 10px;
    font-size: 14.5px; font-family: 'DM Sans', sans-serif;
    color: var(--gray-800); background: var(--gray-50);
    transition: all 0.2s; outline: none;
  }
  .form-control::placeholder { color: var(--gray-400); }
  .form-control:focus {
    border-color: var(--blue-500); background: #fff;
    box-shadow: 0 0 0 3px rgba(45,85,212,0.1);
  }

  .btn-submit {
    width: 100%; padding: 13px;
    background: linear-gradient(135deg, var(--blue-600), var(--blue-800));
    color: #fff; border: none; border-radius: 10px;
    font-size: 15px; font-weight: 600; font-family: 'Sora', sans-serif;
    cursor: pointer; box-shadow: var(--shadow-btn);
    transition: all 0.2s; letter-spacing: -0.2px;
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
  .back-link svg { width: 14px; height: 14px; }

  /* Success state */
  .success-state {
    text-align: center; padding: 8px 0 4px;
  }
  .success-icon {
    width: 72px; height: 72px; border-radius: 50%;
    background: var(--success-bg); border: 2px solid var(--success-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px;
  }
  .success-icon svg { width: 36px; height: 36px; color: var(--success); }
  .success-state h3 {
    font-family: 'Sora', sans-serif; font-size: 20px; font-weight: 700;
    color: var(--gray-800); margin-bottom: 10px; letter-spacing: -0.3px;
  }
  .success-state p { font-size: 13.5px; color: var(--gray-400); line-height: 1.7; margin-bottom: 24px; }

  .step-list {
    background: var(--gray-50); border: 1px solid var(--gray-100);
    border-radius: 10px; padding: 16px 20px; text-align: left;
    margin-bottom: 24px;
  }
  .step-list p { font-size: 12px; font-weight: 600; color: var(--gray-600); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
  .step-item {
    display: flex; align-items: center; gap: 10px;
    font-size: 13px; color: var(--gray-600); margin-bottom: 8px;
  }
  .step-item:last-child { margin-bottom: 0; }
  .step-num {
    width: 22px; height: 22px; border-radius: 50%;
    background: var(--blue-600); color: #fff;
    font-size: 11px; font-weight: 700;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  }

  /* Loading spinner */
  @keyframes spin { to { transform: rotate(360deg); } }
  .spinner {
    width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.4);
    border-top-color: #fff; border-radius: 50%;
    animation: spin 0.7s linear infinite;
  }
</style>
</head>
<body>

<div class="page-card">

  <!-- Blue Top Strip -->
  <div class="card-top">
    <div class="brand">
      <div class="brand-logo">CP</div>
      <div class="brand-text">
        <h2>ClearPath</h2>
        <p>BPC Digital School Clearance</p>
      </div>
    </div>
    <div class="lock-icon">
      <svg viewBox="0 0 24 24" fill="none">
        <rect x="3" y="11" width="18" height="11" rx="2" stroke="rgba(255,255,255,0.9)" stroke-width="1.8"/>
        <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="rgba(255,255,255,0.9)" stroke-width="1.8" stroke-linecap="round"/>
        <circle cx="12" cy="16" r="1.5" fill="rgba(255,255,255,0.9)"/>
      </svg>
    </div>
    <h1>Forgot Password?</h1>
    <p>Enter your Student ID or email and we'll send you a secure reset link.</p>
  </div>

  <!-- Card Body -->
  <div class="card-body">

    <?php if ($sent && $type === 'success'): ?>
    <!-- SUCCESS STATE -->
    <div class="success-state">
      <div class="success-icon">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h3>Check Your Email!</h3>
      <p>A password reset link has been sent to your registered email address. The link will expire in <strong>30 minutes</strong>.</p>

      <div class="step-list">
        <p>Next Steps</p>
        <div class="step-item"><div class="step-num">1</div> Open your email inbox</div>
        <div class="step-item"><div class="step-num">2</div> Find the email from BPC ClearPath</div>
        <div class="step-item"><div class="step-num">3</div> Click "Reset My Password"</div>
        <div class="step-item"><div class="step-num">4</div> Set your new password</div>
      </div>

      <p style="font-size:12px;color:var(--gray-400)">Didn't receive it? Check your spam folder or <a href="forgot_password.php" style="color:var(--blue-600);text-decoration:none;font-weight:500">try again</a>.</p>
    </div>

    <?php else: ?>
    <!-- FORM STATE -->
    <?php if ($msg && $type === 'error'): ?>
    <div class="alert error">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/><path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="forgotForm">
      <div class="form-group">
        <label class="form-label">Student ID or Email Address</label>
        <input
          type="text"
          name="identifier"
          class="form-control"
          placeholder="e.g., 2020-0001 or juan@bpc.edu.ph"
          value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
          autocomplete="email"
          required
        >
      </div>

      <button type="submit" class="btn-submit" id="submitBtn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Send Reset Link
      </button>
    </form>
    <?php endif; ?>

    <a href="<?= APP_URL ?>/index.php" class="back-link">
      <svg viewBox="0 0 24 24" fill="none"><path d="M19 12H5M12 19l-7-7 7-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Back to Login
    </a>

  </div>
</div>

<script>
const form = document.getElementById('forgotForm');
const btn  = document.getElementById('submitBtn');
if (form) {
  form.addEventListener('submit', () => {
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner"></div> Sending...';
  });
}
</script>

</body>
</html>
