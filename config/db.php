<?php
/**
 * Database Configuration
 * Kasir UMKM - Koneksi PDO ke MySQL
 */

/** Return an environment override, including an intentionally empty password. */
$configValue = static function (string $name, string $default): string {
    $value = getenv($name);
    return $value === false ? $default : $value;
};

define('DB_HOST', $configValue('KASIRKU_DB_HOST', 'localhost'));
define('DB_NAME', $configValue('KASIRKU_DB_NAME', 'kasir_umkm'));
define('DB_USER', $configValue('KASIRKU_DB_USER', 'root'));
define('DB_PASS', $configValue('KASIRKU_DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

// Keep PHP dates (invoice numbers) and MySQL dates (reports) in the same zone.
$appTimezone = $configValue('KASIRKU_TZ', 'Asia/Jakarta');
if (!@date_default_timezone_set($appTimezone)) {
    error_log('Invalid KASIRKU_TZ value; falling back to Asia/Jakarta');
    $appTimezone = 'Asia/Jakarta';
    date_default_timezone_set($appTimezone);
}
define('APP_TIMEZONE', $appTimezone);
unset($configValue, $appTimezone);

define('APP_NAME', 'KasirKu');
define('STORE_NAME', 'Toko Serba Ada');
define('STORE_ADDRESS', 'Jl. Raya No. 1, Jakarta');
define('STORE_PHONE', '0812-3456-7890');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $timezoneOffset = (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->format('P');
    $pdo->exec('SET time_zone = ' . $pdo->quote($timezoneOffset));
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => false,
        'message' => 'Koneksi database gagal'
    ], JSON_UNESCAPED_UNICODE));
}

/**
 * Send a consistent JSON response and stop request processing.
 */
function jsonResponse(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Read an object-shaped JSON request, while retaining support for form posts.
 */
function requestData(): array {
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if ($_POST !== [] && !str_contains($contentType, 'application/json')) {
        return $_POST;
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return $_POST;
    }

    try {
        $object = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new InvalidArgumentException('Format JSON tidak valid');
    }

    if (!$object instanceof stdClass || !is_array($data)) {
        throw new InvalidArgumentException('Payload harus berupa objek JSON');
    }

    return $data;
}

/**
 * Parse a decimal amount from a JSON number or common Indonesian formatting.
 */
function parseMoneyAmount(mixed $value): ?float {
    if (is_int($value) || is_float($value)) {
        $amount = (float) $value;
        return is_finite($amount) ? round($amount, 2) : null;
    }

    if (!is_string($value)) {
        return null;
    }

    $value = preg_replace('/[^0-9,.\-]/u', '', trim($value));
    if ($value === null || $value === '' || $value === '-') {
        return null;
    }

    $lastComma = strrpos($value, ',');
    $lastDot = strrpos($value, '.');
    if ($lastComma !== false && $lastDot !== false) {
        if ($lastComma > $lastDot) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
    } elseif ($lastComma !== false) {
        $value = str_replace(',', '.', $value);
    } elseif (preg_match('/^-?\d{1,3}(?:\.\d{3})+$/', $value)) {
        $value = str_replace('.', '', $value);
    }

    if (!is_numeric($value)) {
        return null;
    }

    $amount = (float) $value;
    return is_finite($amount) ? round($amount, 2) : null;
}

/**
 * Return the current database user, so deleted users and stale roles cannot use APIs.
 */
function currentApiUser(PDO $pdo): ?array {
    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($userId === false) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, name, username, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['name'] = $user['name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
    }

    return $user ?: null;
}

/**
 * Reject state-changing API calls that do not originate from this session.
 */
function requireCsrfToken(): void {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $requestToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!is_string($sessionToken) || !is_string($requestToken) ||
        $sessionToken === '' || $requestToken === '' ||
        !hash_equals($sessionToken, $requestToken)) {
        jsonResponse(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman.'], 403);
    }
}

/**
 * Helper: format rupiah
 */
function formatRupiah(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Helper: generate invoice number
 */
function generateInvoice(): string {
    return 'INV-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
}
