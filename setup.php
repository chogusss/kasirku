<?php
/**
 * Setup Helper - Generate bcrypt hashes & optionally seed database
 * Akses: http://localhost/kasir/setup.php
 * HAPUS file ini setelah setup selesai!
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/config/db.php';

$message = '';
$success = false;
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = PHP_SAPI === 'cli' || in_array($remoteAddress, ['127.0.0.1', '::1'], true);

if (!$isLocal) {
    http_response_code(403);
    exit('Setup hanya dapat diakses dari komputer server.');
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed');
}

try {
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $currentUser = isset($_SESSION['user_id']) ? currentApiUser($pdo) : null;
    $canSetup = $userCount === 0 || ($currentUser && $currentUser['role'] === 'owner');
} catch (Throwable $e) {
    error_log('Setup access check failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Tidak dapat memeriksa status setup.');
}

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$adminHash = password_hash('admin123', PASSWORD_DEFAULT);
$staffHash = password_hash('staff123', PASSWORD_DEFAULT);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_setup'])) {
    $submittedToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    if (!$canSetup) {
        http_response_code(403);
        $message = 'Hanya owner yang dapat menjalankan ulang setup.';
    } elseif ($submittedToken === '' || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(403);
        $message = 'Token keamanan tidak valid. Muat ulang halaman.';
    } elseif ($adminHash === false || $staffHash === false) {
        http_response_code(500);
        $message = 'Gagal membuat password yang aman.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO users (name, username, password, role)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name), password = VALUES(password), role = VALUES(role)
            ");
            $stmt->execute(['Administrator', 'admin', $adminHash, 'owner']);
            $stmt->execute(['Staff Kasir', 'staff', $staffHash, 'staff']);
            $pdo->commit();
            $message = 'Akun default berhasil dibuat/diperbarui. Admin: admin123 | Staff: staff123';
            $success = true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Setup update failed: ' . $e->getMessage());
            http_response_code(500);
            $message = 'Setup gagal. Periksa log server.';
        }
    }
}

// Verify current passwords
try {
    $users = $canSetup
        ? $pdo->query('SELECT username, password, role FROM users ORDER BY id')->fetchAll()
        : [];
    if (!$canSetup && $message === '') {
        http_response_code(403);
        $message = 'Login sebagai owner untuk menjalankan setup.';
    }
} catch (Throwable $e) {
    $users = [];
    error_log('Setup user listing failed: ' . $e->getMessage());
    $message = 'Tidak dapat membaca daftar pengguna.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Setup KasirKu</title>
  <style>
    body { font-family: monospace; background: #0F172A; color: #F1F5F9; padding: 30px; }
    .card { background: #1E293B; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 24px; max-width: 600px; margin: 0 auto; }
    h1 { color: #06B6D4; margin-bottom: 20px; }
    .hash { word-break: break-all; background: #0F172A; padding: 8px; border-radius: 6px; font-size: 12px; color: #94A3B8; margin: 4px 0 12px; }
    .msg-ok  { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #10B981; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
    .msg-err { background: rgba(239,68,68,0.1);  border: 1px solid rgba(239,68,68,0.3);  color: #EF4444; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
    .btn { background: #06B6D4; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 15px; margin-top: 16px; }
    table { width: 100%; border-collapse: collapse; margin: 12px 0; }
    th, td { padding: 8px 12px; border: 1px solid rgba(255,255,255,0.1); font-size: 13px; }
    th { background: rgba(255,255,255,0.05); }
    .ok { color: #10B981; } .fail { color: #EF4444; }
    .warn { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); color: #F59E0B; padding: 12px; border-radius: 8px; margin-top: 20px; }
  </style>
</head>
<body>
<div class="card">
  <h1>🔧 KasirKu Setup Helper</h1>

  <?php if ($message): ?>
    <div class="<?= $success ? 'msg-ok' : 'msg-err' ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <h3>Generated Password Hashes:</h3>
  <div>admin123:<div class="hash"><?= $adminHash ?></div></div>
  <div>staff123:<div class="hash"><?= $staffHash ?></div></div>

  <h3>Current Users in DB:</h3>
  <?php if (empty($users)): ?>
    <p style="color:#EF4444">Tidak ada user di database. Klik tombol di bawah untuk setup.</p>
  <?php else: ?>
    <table>
      <tr><th>Username</th><th>Role</th><th>admin123</th><th>staff123</th></tr>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></td>
        <td class="<?= password_verify('admin123', $u['password']) ? 'ok' : 'fail' ?>">
          <?= password_verify('admin123', $u['password']) ? '✅ OK' : '❌ Tidak cocok' ?>
        </td>
        <td class="<?= password_verify('staff123', $u['password']) ? 'ok' : 'fail' ?>">
          <?= password_verify('staff123', $u['password']) ? '✅ OK' : '❌ Tidak cocok' ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <?php if ($canSetup): ?>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" name="do_setup" class="btn">🔄 Reset Password Users ke Default</button>
  </form>
  <?php endif; ?>

  <div class="warn">
    ⚠️ <strong>PENTING:</strong> Hapus file <code>setup.php</code> setelah setup selesai!
    Jangan biarkan file ini accessible di production.
  </div>
</div>
</body>
</html>
