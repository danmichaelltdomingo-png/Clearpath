<?php
require_once '../includes/config.php';
requireLogin();
$db   = getDB();
$user = currentUser();
$uid  = (int)$user['id'];
$msg  = '';

// Fetch current user data
$userData = $db->query("SELECT * FROM users WHERE id=$uid LIMIT 1")->fetch_assoc();

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $current = trim($_POST['current_password']);
    $new     = trim($_POST['new_password']);
    $confirm = trim($_POST['confirm_password']);

    $currentHash = sha1($current);

    if ($userData['password'] !== $currentHash) {
        $msg = 'error:Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $msg = 'error:New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $msg = 'error:New password and confirmation do not match.';
    } else {
        $newHash = sha1($new);
        $db->query("UPDATE users SET password='$newHash' WHERE id=$uid");
        logActivity('CHANGE_PASSWORD', 'User changed their password');
        $msg = 'success:Password changed successfully!';
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $fullName = trim($_POST['full_name']);
    $email    = trim($_POST['email']);

    if (!$fullName || !$email) {
        $msg = 'error:Full name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'error:Invalid email address.';
    } else {
        // Check if email is taken by another user
        $taken = $db->query("SELECT id FROM users WHERE email='" . $db->real_escape_string($email) . "' AND id != $uid LIMIT 1")->fetch_row();
        if ($taken) {
            $msg = 'error:That email is already used by another account.';
        } else {
            $safeEmail = $db->real_escape_string($email);
            $safeName  = $db->real_escape_string($fullName);
            $db->query("UPDATE users SET full_name='$safeName', email='$safeEmail' WHERE id=$uid");
            $_SESSION['full_name'] = $fullName;
            $_SESSION['email']     = $email;
            logActivity('UPDATE_PROFILE', 'User updated profile info');
            $msg = 'success:Profile updated successfully!';
            // Refresh
            $userData = $db->query("SELECT * FROM users WHERE id=$uid LIMIT 1")->fetch_assoc();
        }
    }
}

// Set back link per role
$backLink = match($user['role']) {
    'admin'     => '../admin/dashboard.php',
    'signatory' => '../signatory/dashboard.php',
    default     => '../student/dashboard.php',
};

$pageTitle    = 'Settings';
$pageSubtitle = 'Manage your account and security';
require_once '../includes/header.php';

if ($msg) {
    [$t, $tx] = explode(':', $msg, 2);
    echo "<div class='alert alert-$t'>" . htmlspecialchars($tx) . "</div>";
}
?>

<div style="max-width: 760px;">

  <!-- Profile Card -->
  <div class="card mb-6">
    <div class="card-header">
      <div>
        <h3>Profile Information</h3>
        <p>Update your name and email address</p>
      </div>
      <div style="width:48px;height:48px;border-radius:50%;background:var(--blue-600);display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-weight:800;font-size:18px;color:#fff">
        <?= strtoupper(substr($userData['full_name'], 0, 1)) ?>
      </div>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="update_profile">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($userData['full_name']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($userData['email']) ?>" required>
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Student / Staff ID</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($userData['student_id'] ?? '—') ?>" disabled style="background:var(--gray-100);color:var(--gray-400);cursor:not-allowed">
          </div>
          <div class="form-group">
            <label class="form-label">Role</label>
            <input type="text" class="form-control" value="<?= ucfirst($userData['role']) ?><?= $userData['office'] ? ' — ' . $userData['office'] : '' ?>" disabled style="background:var(--gray-100);color:var(--gray-400);cursor:not-allowed">
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Profile</button>
      </form>
    </div>
  </div>

  <!-- Change Password Card -->
  <div class="card mb-6">
    <div class="card-header">
      <div>
        <h3>Change Password</h3>
        <p>Update your account password</p>
      </div>
      <div style="width:40px;height:40px;background:var(--warning-bg);border-radius:10px;display:flex;align-items:center;justify-content:center">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="color:var(--warning)">
          <rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.5"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          <circle cx="12" cy="16" r="1.5" fill="currentColor"/>
        </svg>
      </div>
    </div>
    <div class="card-body">
      <form method="POST" id="pwForm">
        <input type="hidden" name="action" value="change_password">

        <div class="form-group">
          <label class="form-label">Current Password</label>
          <div style="position:relative">
            <input type="password" name="current_password" id="cur_pw" class="form-control" placeholder="Enter your current password" required style="padding-right:44px">
            <button type="button" onclick="togglePw('cur_pw','eye1')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400)">
              <svg id="eye1" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/></svg>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">New Password</label>
          <div style="position:relative">
            <input type="password" name="new_password" id="new_pw" class="form-control" placeholder="At least 6 characters" required style="padding-right:44px" oninput="checkStrength(this.value)">
            <button type="button" onclick="togglePw('new_pw','eye2')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400)">
              <svg id="eye2" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/></svg>
            </button>
          </div>
          <!-- Strength bar -->
          <div style="margin-top:8px">
            <div style="height:4px;background:var(--gray-100);border-radius:10px;overflow:hidden">
              <div id="strength-bar" style="height:100%;width:0%;border-radius:10px;transition:all 0.3s"></div>
            </div>
            <div id="strength-label" style="font-size:11px;color:var(--gray-400);margin-top:4px"></div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <div style="position:relative">
            <input type="password" name="confirm_password" id="conf_pw" class="form-control" placeholder="Re-enter new password" required style="padding-right:44px" oninput="checkMatch()">
            <button type="button" onclick="togglePw('conf_pw','eye3')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400)">
              <svg id="eye3" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/></svg>
            </button>
          </div>
          <div id="match-msg" style="font-size:11.5px;margin-top:4px"></div>
        </div>

        <!-- Password rules -->
        <div style="background:var(--gray-50);border:1px solid var(--gray-100);border-radius:9px;padding:14px 16px;margin-bottom:20px">
          <div style="font-size:12px;font-weight:600;color:var(--gray-600);margin-bottom:8px">Password Requirements</div>
          <div style="display:flex;flex-direction:column;gap:5px">
            <div class="pw-rule" id="rule-len" style="font-size:12px;color:var(--gray-400);display:flex;align-items:center;gap:6px">
              <span class="rule-icon">○</span> At least 6 characters
            </div>
            <div class="pw-rule" id="rule-num" style="font-size:12px;color:var(--gray-400);display:flex;align-items:center;gap:6px">
              <span class="rule-icon">○</span> Contains a number
            </div>
            <div class="pw-rule" id="rule-sym" style="font-size:12px;color:var(--gray-400);display:flex;align-items:center;gap:6px">
              <span class="rule-icon">○</span> Contains a special character (recommended)
            </div>
          </div>
        </div>

        <div style="display:flex;gap:10px">
          <button type="submit" class="btn btn-primary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            Update Password
          </button>
          <button type="reset" class="btn btn-outline" onclick="resetForm()">Clear</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Account Info Card -->
  <div class="card">
    <div class="card-header">
      <div>
        <h3>Account Information</h3>
        <p>Read-only account details</p>
      </div>
    </div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;border:1.5px solid var(--gray-100);border-radius:10px;overflow:hidden">
        <?php
        $rows = [
          'Account ID'    => '#' . $userData['id'],
          'Member Since'  => date('F j, Y', strtotime($userData['created_at'])),
          'Last Updated'  => date('F j, Y g:i a', strtotime($userData['updated_at'])),
          'Account Status'=> $userData['is_active'] ? '<span class="badge approved">Active</span>' : '<span class="badge rejected">Inactive</span>',
        ];
        $i = 0;
        foreach ($rows as $label => $value):
          $border = (++$i < count($rows)) ? 'border-bottom:1px solid var(--gray-100)' : '';
        ?>
        <div style="background:var(--gray-50);padding:11px 16px;font-size:11.5px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--gray-400);<?= $border ?>">
          <?= $label ?>
        </div>
        <div style="background:#fff;padding:11px 16px;font-size:13.5px;color:var(--gray-700);<?= $border ?>">
          <?= $value ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<script>
function togglePw(inputId, eyeId) {
  const input = document.getElementById(inputId);
  const eye   = document.getElementById(eyeId);
  if (input.type === 'password') {
    input.type = 'text';
    eye.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="1" y1="1" x2="23" y2="23" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>';
  } else {
    input.type = 'password';
    eye.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/>';
  }
}

function checkStrength(val) {
  const bar   = document.getElementById('strength-bar');
  const label = document.getElementById('strength-label');
  const rLen  = document.getElementById('rule-len');
  const rNum  = document.getElementById('rule-num');
  const rSym  = document.getElementById('rule-sym');

  const hasLen = val.length >= 6;
  const hasNum = /\d/.test(val);
  const hasSym = /[^a-zA-Z0-9]/.test(val);

  setRule(rLen, hasLen);
  setRule(rNum, hasNum);
  setRule(rSym, hasSym);

  let score = 0;
  if (hasLen) score++;
  if (hasNum) score++;
  if (hasSym) score++;
  if (val.length >= 10) score++;

  const configs = [
    { w: '0%',   color: 'transparent',      text: '' },
    { w: '25%',  color: 'var(--error)',      text: 'Weak' },
    { w: '50%',  color: 'var(--warning)',    text: 'Fair' },
    { w: '75%',  color: 'var(--blue-400)',   text: 'Good' },
    { w: '100%', color: 'var(--success)',    text: 'Strong' },
  ];
  bar.style.width           = configs[score].w;
  bar.style.background      = configs[score].color;
  label.textContent         = configs[score].text;
  label.style.color         = configs[score].color;
}

function setRule(el, met) {
  const icon = el.querySelector('.rule-icon');
  if (met) {
    el.style.color  = 'var(--success)';
    icon.textContent = '✓';
  } else {
    el.style.color  = 'var(--gray-400)';
    icon.textContent = '○';
  }
}

function checkMatch() {
  const newPw  = document.getElementById('new_pw').value;
  const confPw = document.getElementById('conf_pw').value;
  const msg    = document.getElementById('match-msg');
  if (!confPw) { msg.textContent = ''; return; }
  if (newPw === confPw) {
    msg.textContent = '✓ Passwords match';
    msg.style.color = 'var(--success)';
  } else {
    msg.textContent = '✗ Passwords do not match';
    msg.style.color = 'var(--error)';
  }
}

function resetForm() {
  document.getElementById('strength-bar').style.width = '0%';
  document.getElementById('strength-label').textContent = '';
  document.getElementById('match-msg').textContent = '';
  ['rule-len','rule-num','rule-sym'].forEach(id => setRule(document.getElementById(id), false));
}
</script>

<?php require_once '../includes/footer.php'; ?>
