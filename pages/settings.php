<?php
/**
 * Settings / Change Password
 */
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle    = 'Pengaturan — KasirKu';
$pageSubtitle = 'Kelola Akun Anda';

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:520px">

  <!-- Profile Card -->
  <div class="card mb-3">
    <div class="card-header">
      <div class="card-title">👤 Profil Akun</div>
    </div>
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:16px">
        <div style="width:60px;height:60px;border-radius:50%;background:var(--primary);color:oklch(0.10 0 0);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700" aria-hidden="true">
          <?= appEscape($userInitial) ?>
        </div>
        <div>
          <div style="font-size:18px;font-weight:700"><?= appEscape($_SESSION['name']) ?></div>
          <div style="font-size:13px;color:var(--text-muted)">@<?= appEscape($_SESSION['username']) ?></div>
          <span class="badge <?= $_SESSION['role'] === 'owner' ? 'badge-primary' : 'badge-info' ?>" style="margin-top:6px">
            <?= $_SESSION['role'] === 'owner' ? '👑 Owner' : '👷 Staff' ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">🔒 Ganti Password</div>
    </div>
    <form class="card-body" id="changePasswordForm" onsubmit="changePassword(event)">
      <div class="form-group">
        <label class="form-label" for="oldPassword">Password Lama *</label>
        <div style="position:relative">
          <input type="password" id="oldPassword" class="form-control" placeholder="Masukkan password lama" autocomplete="current-password" required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="newPassword">Password Baru *</label>
        <input type="password" id="newPassword" class="form-control" placeholder="Minimal 6 karakter" autocomplete="new-password" minlength="6" maxlength="72" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="confirmPassword">Konfirmasi Password Baru *</label>
        <input type="password" id="confirmPassword" class="form-control" placeholder="Ulangi password baru" autocomplete="new-password" minlength="6" maxlength="72" required>
      </div>

      <div id="pwdFeedback" style="margin-bottom:12px;display:none" role="status" aria-live="polite"></div>

      <button type="submit" class="btn btn-primary btn-block" id="changePwdBtn">
        🔒 Simpan Password Baru
      </button>
    </form>
  </div>

  <!-- App Info -->
  <div class="card mt-3">
    <div class="card-header">
      <div class="card-title">ℹ️ Informasi Aplikasi</div>
    </div>
    <div class="card-body" style="font-size:13px;color:var(--text-secondary)">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px">
        <span>Nama Aplikasi</span><span style="font-weight:600"><?= appEscape(APP_NAME) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:8px">
        <span>Nama Toko</span><span style="font-weight:600"><?= appEscape(STORE_NAME) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:8px">
        <span>Versi</span><span style="font-weight:600">1.0.0</span>
      </div>
      <div style="display:flex;justify-content:space-between">
        <span>Stack</span><span style="font-weight:600">PHP + MySQL (XAMPP)</span>
      </div>
    </div>
  </div>
</div>

<script>
let changePasswordInFlight = false;

function settingsUrl(path) {
  const baseUrl = String(window.KASIRKU_CONFIG?.baseUrl || '').replace(/\/$/, '');
  return `${baseUrl}/${String(path).replace(/^\//, '')}`;
}

async function changePassword(event) {
  event?.preventDefault();
  if (changePasswordInFlight) return;

  const oldPwd  = document.getElementById('oldPassword').value;
  const newPwd  = document.getElementById('newPassword').value;
  const confPwd = document.getElementById('confirmPassword').value;
  const btn = document.getElementById('changePwdBtn');

  // Client validation
  if (!oldPwd || !newPwd || !confPwd) {
    showFeedback('Semua field wajib diisi', 'error');
    return;
  }
  if (newPwd.length < 6) {
    showFeedback('Password baru minimal 6 karakter', 'error');
    document.getElementById('newPassword').focus();
    return;
  }
  if (new TextEncoder().encode(newPwd).length > 72) {
    showFeedback('Password baru maksimal 72 byte', 'error');
    document.getElementById('newPassword').focus();
    return;
  }
  if (newPwd !== confPwd) {
    showFeedback('Konfirmasi password tidak cocok', 'error');
    document.getElementById('confirmPassword').focus();
    return;
  }

  changePasswordInFlight = true;
  btn.disabled = true;
  btn.textContent = '⏳ Memproses...';

  try {
    const res = await fetch(settingsUrl('api/auth.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        action: 'change_password',
        old_password: oldPwd,
        new_password: newPwd,
        confirm_password: confPwd,
      })
    });
    const json = await res.json();

    if (res.status === 401) {
      window.location.assign(settingsUrl('login.php'));
      return;
    }

    if (res.ok && json.success) {
      showFeedback(json.message || 'Password berhasil diperbarui', 'success');
      document.getElementById('changePasswordForm').reset();
      showToast('Password berhasil diperbarui!', 'success');
    } else {
      showFeedback(json.message || 'Password tidak dapat diperbarui', 'error');
    }
  } catch (e) {
    console.error(e);
    showFeedback(e.message || 'Terjadi kesalahan koneksi', 'error');
  } finally {
    changePasswordInFlight = false;
    btn.disabled = false;
    btn.textContent = '🔒 Simpan Password Baru';
  }
}

function showFeedback(msg, type) {
  const el = document.getElementById('pwdFeedback');
  el.style.display = 'block';
  const colors = {
    success: { bg: 'var(--success-surface)', border: 'var(--success)', text: 'var(--success)' },
    error:   { bg: 'var(--danger-surface)', border: 'var(--danger)', text: 'var(--danger)' },
  };
  const c = colors[type] || colors.error;
  el.style.cssText = `display:block;background:${c.bg};border:1px solid ${c.border};border-radius:8px;padding:10px 14px;font-size:13px;color:${c.text}`;
  el.textContent = (type === 'success' ? '✅ ' : '❌ ') + msg;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
