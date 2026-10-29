<?php
declare(strict_types=1);

/**
 * End-to-end smoke test for the local KasirKu HTTP application.
 *
 * Usage:
 *   C:\\xampp\\php\\php.exe tests\\http_smoke.php http://127.0.0.1:8765
 *
 * The script creates uniquely named test data and removes it in a finally
 * block. It refuses to run outside CLI and never changes a real password.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

final class SmokeFailure extends RuntimeException
{
}

final class HttpClient
{
    private string $cookieFile;
    private CurlHandle $handle;

    public function __construct(private readonly string $baseUrl)
    {
        $cookieFile = tempnam(sys_get_temp_dir(), 'kasirku-smoke-');
        if ($cookieFile === false) {
            throw new RuntimeException('Tidak dapat membuat cookie jar sementara.');
        }
        $this->cookieFile = $cookieFile;
        $handle = curl_init();
        if ($handle === false) {
            throw new RuntimeException('Tidak dapat memulai cURL.');
        }
        curl_setopt_array($handle, [
            CURLOPT_COOKIEFILE => '',
            CURLOPT_COOKIEJAR => $this->cookieFile,
        ]);
        $this->handle = $handle;
    }

    public function __destruct()
    {
        curl_close($this->handle);
        if (is_file($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    /** @return list<string> */
    public function cookies(): array
    {
        $cookies = curl_getinfo($this->handle, CURLINFO_COOKIELIST);
        return is_array($cookies) ? array_values($cookies) : [];
    }

    /** @return array{status:int,headers:array<string,string>,body:string,json:?array} */
    public function request(
        string $method,
        string $path,
        ?array $json = null,
        ?array $form = null,
        array $headers = []
    ): array {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $requestHeaders = ['Accept: application/json, text/html;q=0.9'];
        $body = null;

        if ($json !== null) {
            $body = json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $requestHeaders[] = 'Content-Type: application/json';
        } elseif ($form !== null) {
            $body = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
            $requestHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        foreach ($headers as $name => $value) {
            $requestHeaders[] = $name . ': ' . $value;
        }

        $handle = $this->handle;

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_ENCODING => '',
            CURLOPT_POSTFIELDS => $body,
        ]);

        $raw = curl_exec($handle);
        if ($raw === false) {
            $error = curl_error($handle);
            throw new RuntimeException('HTTP gagal: ' . $error);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);

        $headerText = substr($raw, 0, $headerSize);
        $responseBody = substr($raw, $headerSize);
        $responseHeaders = [];
        foreach (preg_split('/\r?\n/', trim($headerText)) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $responseHeaders[strtolower(trim($name))] = trim($value);
        }

        $decoded = null;
        if (trim($responseBody) !== '') {
            try {
                $candidate = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($candidate)) {
                    $decoded = $candidate;
                }
            } catch (JsonException) {
                // HTML responses are expected for pages and redirects.
            }
        }

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $responseBody,
            'json' => $decoded,
        ];
    }
}

$assertions = 0;

function ensure(bool $condition, string $message): void
{
    global $assertions;
    if (!$condition) {
        throw new SmokeFailure($message);
    }
    $assertions++;
    echo "[OK] {$message}\n";
}

/** @param array{status:int,headers:array<string,string>,body:string,json:?array} $response */
function ensureNoRuntimeError(array $response, string $context): void
{
    $bad = preg_match(
        '/<b>(?:Fatal error|Parse error|Warning|Notice)<\/b>|(?:Fatal error|Parse error):\s|Uncaught (?:Error|Exception).*?\sin\s/i',
        $response['body']
    ) === 1;
    ensure(!$bad, $context . ' tidak memuat error PHP');
}

function htmlCsrf(string $html): string
{
    foreach ([
        '/<meta\s+name="csrf-token"\s+content="([^"]+)"/i',
        '/<input\s+type="hidden"\s+name="csrf_token"\s+value="([^"]+)"/i',
    ] as $pattern) {
        if (preg_match($pattern, $html, $matches) === 1) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    throw new SmokeFailure('Token CSRF tidak ditemukan pada HTML.');
}

/** @return array{client:HttpClient,csrf:string} */
function login(string $baseUrl, string $username, string $password, string $expectedPage): array
{
    $client = new HttpClient($baseUrl);
    $page = $client->request('GET', 'login.php');
    ensure($page['status'] === 200, "Halaman login untuk {$username} dapat dibuka");
    ensureNoRuntimeError($page, 'Halaman login');
    ensure($client->cookies() !== [], "Cookie sesi login {$username} diterima");
    $token = htmlCsrf($page['body']);

    $result = $client->request('POST', 'login.php', form: [
        'csrf_token' => $token,
        'username' => $username,
        'password' => $password,
    ]);
    $location = $result['headers']['location'] ?? '';
    $loginDiagnostic = "status {$result['status']}";
    if ($location !== '') {
        $loginDiagnostic .= ", lokasi {$location}";
    } elseif (preg_match('/<div class="error-alert"[^>]*>.*?<\/div>/is', $result['body'], $errorMatch) === 1) {
        $loginDiagnostic .= ', pesan ' . trim(preg_replace('/\s+/', ' ', strip_tags($errorMatch[0])) ?? '');
    }
    ensure(
        in_array($result['status'], [302, 303], true),
        "Login {$username} mengalihkan pengguna ({$loginDiagnostic})"
    );
    ensure(str_ends_with($location, $expectedPage), "Login {$username} menuju {$expectedPage}");

    $home = $client->request('GET', ltrim($expectedPage, '/'));
    ensure($home['status'] === 200, "Beranda {$username} dapat dibuka");
    ensureNoRuntimeError($home, "Beranda {$username}");

    return ['client' => $client, 'csrf' => htmlCsrf($home['body'])];
}

function database(): PDO
{
    $env = static function (string $name, string $default): string {
        $value = getenv($name);
        return $value === false ? $default : $value;
    };

    return new PDO(
        'mysql:host=' . $env('KASIRKU_DB_HOST', 'localhost')
            . ';dbname=' . $env('KASIRKU_DB_NAME', 'kasir_umkm')
            . ';charset=utf8mb4',
        $env('KASIRKU_DB_USER', 'root'),
        $env('KASIRKU_DB_PASS', ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8765', '/');
if (!preg_match('#^https?://(?:127\.0\.0\.1|localhost)(?::\d+)?(?:/.*)?$#i', $baseUrl)) {
    fwrite(STDERR, "Smoke test hanya boleh diarahkan ke server lokal.\n");
    exit(2);
}

$pdo = database();
$runToken = '__CODEX_SMOKE_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$originalName = $runToken;
$editedName = $runToken . '_EDITED';
$notes = $runToken . '_TRANSACTION';
$productId = null;
$transactionId = null;
$exitCode = 0;

try {
    echo "Menjalankan smoke test pada {$baseUrl}\n";

    $guest = new HttpClient($baseUrl);
    $unauthorizedApi = $guest->request('GET', 'api/products.php');
    ensure($unauthorizedApi['status'] === 401, 'API produk menolak pengguna anonim');
    $setup = $guest->request('GET', 'setup.php');
    ensure($setup['status'] === 403, 'Setup menolak pengguna anonim saat akun sudah tersedia');

    ['client' => $owner, 'csrf' => $ownerCsrf] = login(
        $baseUrl,
        'admin',
        'admin123',
        '/pages/dashboard.php'
    );

    foreach (['pos.php', 'history.php', 'stock.php', 'report.php', 'settings.php'] as $pageName) {
        $page = $owner->request('GET', 'pages/' . $pageName);
        ensure($page['status'] === 200, "Owner dapat membuka {$pageName}");
        ensureNoRuntimeError($page, $pageName);
    }

    ['client' => $staff, 'csrf' => $staffCsrf] = login(
        $baseUrl,
        'staff',
        'staff123',
        '/pages/pos.php'
    );
    $staffOwnerPage = $staff->request('GET', 'pages/stock.php');
    ensure(in_array($staffOwnerPage['status'], [302, 303], true), 'Staff dialihkan dari halaman khusus owner');
    ensure(str_ends_with($staffOwnerPage['headers']['location'] ?? '', '/pages/pos.php'), 'Staff dialihkan kembali ke POS');
    $staffReport = $staff->request('GET', 'api/reports.php?action=dashboard');
    ensure($staffReport['status'] === 403, 'API laporan menolak staff');
    $staffMutation = $staff->request('POST', 'api/products.php', [
        'action' => 'add',
        'name' => $runToken . '_STAFF',
        'price' => 1000,
        'stock' => 1,
        'unit' => 'pcs',
    ], headers: ['X-CSRF-Token' => $staffCsrf]);
    ensure($staffMutation['status'] === 403, 'Staff tidak dapat mengubah produk');

    $categories = $owner->request('GET', 'api/products.php?action=categories');
    ensure($categories['status'] === 200 && ($categories['json']['success'] ?? false) === true, 'Daftar kategori dapat dimuat');
    $categoryId = isset($categories['json']['data'][0]['id'])
        ? (int) $categories['json']['data'][0]['id']
        : null;

    $create = $owner->request('POST', 'api/products.php', [
        'action' => 'add',
        'name' => $originalName,
        'category_id' => $categoryId,
        'price' => 12345,
        'stock' => 7,
        'unit' => 'pcs',
    ], headers: ['X-CSRF-Token' => $ownerCsrf]);
    ensure($create['status'] === 201 && ($create['json']['success'] ?? false) === true, 'Owner dapat menambah produk');
    $productId = (int) ($create['json']['id'] ?? 0);
    ensure($productId > 0, 'Produk baru memiliki ID valid');

    $edit = $owner->request('POST', 'api/products.php', [
        'action' => 'edit',
        'id' => $productId,
        'name' => $editedName,
        'category_id' => $categoryId,
        'price' => 12345,
        'stock' => 7,
        'unit' => 'pak',
    ], headers: ['X-CSRF-Token' => $ownerCsrf]);
    ensure($edit['status'] === 200 && ($edit['json']['success'] ?? false) === true, 'Owner dapat mengubah produk');

    $invalidStock = $owner->request('POST', 'api/products.php', [
        'action' => 'edit',
        'id' => $productId,
        'name' => $editedName,
        'category_id' => $categoryId,
        'price' => 12345,
        'stock' => -1,
        'unit' => 'pak',
    ], headers: ['X-CSRF-Token' => $ownerCsrf]);
    ensure($invalidStock['status'] === 422, 'Stok negatif ditolak');

    $lookup = $owner->request('GET', 'api/products.php?search=' . rawurlencode($runToken) . '&per_page=20');
    $found = null;
    foreach ($lookup['json']['data'] ?? [] as $candidate) {
        if ((int) ($candidate['id'] ?? 0) === $productId) {
            $found = $candidate;
            break;
        }
    }
    ensure(is_array($found) && $found['name'] === $editedName, 'Produk hasil edit dapat dicari');
    ensure((int) $found['stock'] === 7 && $found['unit'] === 'pak', 'Data produk tersimpan sesuai perubahan valid terakhir');

    $missingCsrf = $owner->request('POST', 'api/transactions.php', [
        'items' => [['id' => $productId, 'quantity' => 1]],
        'payment_amount' => 20000,
    ]);
    ensure($missingCsrf['status'] === 403, 'Transaksi tanpa CSRF ditolak');

    $tampered = $owner->request('POST', 'api/transactions.php', [
        'items' => [[
            'id' => $productId,
            'name' => 'Harga Palsu',
            'price' => 1,
            'quantity' => 1,
        ]],
        'payment_amount' => 1,
    ], headers: ['X-CSRF-Token' => $ownerCsrf]);
    ensure($tampered['status'] === 422, 'Harga dari klien tidak dipercaya oleh transaksi');

    $negativeQuantity = $owner->request('POST', 'api/transactions.php', [
        'items' => [['id' => $productId, 'quantity' => -1]],
        'payment_amount' => 30000,
    ], headers: ['X-CSRF-Token' => $ownerCsrf]);
    ensure($negativeQuantity['status'] === 422, 'Jumlah item negatif ditolak');

    $transaction = $owner->request('POST', 'api/transactions.php', [
        'items' => [
            ['id' => $productId, 'name' => 'Palsu', 'price' => 1, 'quantity' => 1],
            ['id' => $productId, 'name' => 'Palsu Lagi', 'price' => 2, 'quantity' => 1],
        ],
        'payment_amount' => 30000,
        'notes' => $notes,
    ], headers: ['X-CSRF-Token' => $ownerCsrf]);
    ensure($transaction['status'] === 201 && ($transaction['json']['success'] ?? false) === true, 'Transaksi valid berhasil disimpan');
    $transactionId = (int) ($transaction['json']['transaction_id'] ?? 0);
    ensure($transactionId > 0, 'Transaksi baru memiliki ID valid');
    ensure(abs((float) ($transaction['json']['total'] ?? 0) - 24690.0) < 0.001, 'Total transaksi memakai harga server');
    ensure(count($transaction['json']['items'] ?? []) === 1, 'Produk duplikat pada keranjang digabung');
    ensure((int) ($transaction['json']['items'][0]['quantity'] ?? 0) === 2, 'Jumlah produk gabungan benar');
    ensure(($transaction['json']['items'][0]['name'] ?? '') === $editedName, 'Nama produk pada struk berasal dari server');

    $stockQuery = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
    $stockQuery->execute([$productId]);
    ensure((int) $stockQuery->fetchColumn() === 5, 'Stok berkurang atomik setelah transaksi');

    $detail = $owner->request('GET', 'api/transactions.php?action=detail&id=' . $transactionId);
    ensure($detail['status'] === 200 && (int) ($detail['json']['transaction']['id'] ?? 0) === $transactionId, 'Detail transaksi dapat dimuat');
    ensure(count($detail['json']['items'] ?? []) === 1, 'Detail transaksi memuat item');
    $history = $owner->request('GET', 'api/transactions.php?search=' . rawurlencode((string) ($transaction['json']['invoice_no'] ?? '')));
    ensure($history['status'] === 200 && (int) ($history['json']['total'] ?? 0) >= 1, 'Transaksi dapat ditemukan di riwayat');

    $report = $owner->request('GET', 'api/reports.php?action=monthly&year=' . date('Y'));
    ensure($report['status'] === 200 && count($report['json']['data'] ?? []) === 12, 'Laporan bulanan mengembalikan 12 bulan');
    $dashboard = $owner->request('GET', 'api/reports.php?action=dashboard');
    ensure($dashboard['status'] === 200 && ($dashboard['json']['success'] ?? false) === true, 'Ringkasan dashboard dapat dimuat');

    $badPassword = $owner->request('POST', 'api/auth.php', [
        'action' => 'change_password',
        'old_password' => '__salah__',
        'new_password' => 'tidak-diubah-123',
        'confirm_password' => 'tidak-diubah-123',
    ], headers: ['X-CSRF-Token' => $ownerCsrf]);
    ensure($badPassword['status'] === 422, 'Penggantian password dengan password lama salah ditolak');

    $delete = $owner->request('POST', 'api/products.php', [
        'action' => 'delete',
        'id' => $productId,
    ], headers: ['X-CSRF-Token' => $ownerCsrf]);
    ensure($delete['status'] === 200 && ($delete['json']['success'] ?? false) === true, 'Owner dapat menghapus produk');

    $deletedLookup = $owner->request('GET', 'api/products.php?search=' . rawurlencode($runToken));
    $stillExists = false;
    foreach ($deletedLookup['json']['data'] ?? [] as $candidate) {
        $stillExists = $stillExists || (int) ($candidate['id'] ?? 0) === $productId;
    }
    ensure(!$stillExists, 'Produk terhapus tidak lagi muncul');
    $productId = null;

    $logout = $owner->request('POST', 'logout.php', form: ['csrf_token' => $ownerCsrf]);
    ensure($logout['status'] === 303, 'Logout memakai POST dan berhasil');
    ensure(str_ends_with($logout['headers']['location'] ?? '', '/login.php'), 'Logout mengarah ke login');
    $afterLogout = $owner->request('GET', 'pages/dashboard.php');
    ensure(in_array($afterLogout['status'], [302, 303], true), 'Halaman terlindungi menolak sesi setelah logout');

    echo "Smoke test selesai: {$assertions} pemeriksaan lulus.\n";
} catch (Throwable $error) {
    $exitCode = 1;
    fwrite(STDERR, '[GAGAL] ' . $error->getMessage() . "\n");
} finally {
    try {
        $pdo->beginTransaction();

        $findTransactions = $pdo->prepare('SELECT id FROM transactions WHERE notes = ?');
        $findTransactions->execute([$notes]);
        $testTransactionIds = array_map('intval', $findTransactions->fetchAll(PDO::FETCH_COLUMN));
        if ($transactionId !== null && !in_array($transactionId, $testTransactionIds, true)) {
            throw new RuntimeException('Catatan transaksi uji tidak cocok; pembersihan dihentikan.');
        }
        if ($testTransactionIds !== []) {
            $placeholders = implode(',', array_fill(0, count($testTransactionIds), '?'));
            $deleteTransactions = $pdo->prepare("DELETE FROM transactions WHERE id IN ({$placeholders}) AND notes = ?");
            $deleteTransactions->execute([...$testTransactionIds, $notes]);
        }

        $findProducts = $pdo->prepare('SELECT id, name FROM products WHERE name IN (?, ?)');
        $findProducts->execute([$originalName, $editedName]);
        foreach ($findProducts->fetchAll() as $testProduct) {
            if (!str_starts_with((string) $testProduct['name'], '__CODEX_SMOKE_')) {
                throw new RuntimeException('Nama produk uji tidak aman; pembersihan dihentikan.');
            }
            $deleteProduct = $pdo->prepare('DELETE FROM products WHERE id = ? AND name = ?');
            $deleteProduct->execute([(int) $testProduct['id'], (string) $testProduct['name']]);
        }

        $pdo->commit();
        echo "Data uji sementara sudah dibersihkan.\n";
    } catch (Throwable $cleanupError) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $exitCode = 1;
        fwrite(STDERR, '[GAGAL CLEANUP] ' . $cleanupError->getMessage() . "\n");
    }
}

exit($exitCode);
