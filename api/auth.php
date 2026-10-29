<?php
/**
 * API: Auth (Change Password)
 * Endpoint: /kasirku/api/auth.php
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $user = currentApiUser($pdo);
    if (!$user) {
        session_unset();
        jsonResponse(['success' => false, 'message' => 'Sesi tidak valid'], 401);
    }

    requireCsrfToken();

    $data = requestData();
    if (($data['action'] ?? '') !== 'change_password') {
        jsonResponse(['success' => false, 'message' => 'Action tidak dikenal'], 400);
    }

    $oldPassword = is_string($data['old_password'] ?? null) ? $data['old_password'] : '';
    $newPassword = is_string($data['new_password'] ?? null) ? $data['new_password'] : '';
    $confirmPassword = is_string($data['confirm_password'] ?? null) ? $data['confirm_password'] : '';

    if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
        jsonResponse(['success' => false, 'message' => 'Semua field wajib diisi'], 422);
    }
    if (strlen($newPassword) < 6) {
        jsonResponse(['success' => false, 'message' => 'Password baru minimal 6 karakter'], 422);
    }
    if (strlen($newPassword) > 72) {
        jsonResponse(['success' => false, 'message' => 'Password baru maksimal 72 byte'], 422);
    }
    if (!hash_equals($newPassword, $confirmPassword)) {
        jsonResponse(['success' => false, 'message' => 'Konfirmasi password tidak cocok'], 422);
    }

    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$user['id']]);
    $passwordHash = $stmt->fetchColumn();

    if (!is_string($passwordHash) || !password_verify($oldPassword, $passwordHash)) {
        jsonResponse(['success' => false, 'message' => 'Password lama tidak benar'], 422);
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if ($newHash === false) {
        throw new RuntimeException('Password hashing failed');
    }

    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$newHash, $user['id']]);
    session_regenerate_id(true);

    jsonResponse(['success' => true, 'message' => 'Password berhasil diperbarui']);
} catch (InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('Auth API error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Terjadi kesalahan pada server'], 500);
}
