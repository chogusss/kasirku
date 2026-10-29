<?php
/**
 * API: Transactions
 * Endpoint: /kasirku/api/transactions.php
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

function validDateParam(string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

try {
    $user = currentApiUser($pdo);
    if (!$user) {
        session_unset();
        jsonResponse(['success' => false, 'message' => 'Sesi tidak valid'], 401);
    }

    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    if ($method === 'GET') {
        $action = is_string($_GET['action'] ?? null) ? $_GET['action'] : 'list';

        if ($action === 'detail') {
            $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($id === false) {
                jsonResponse(['success' => false, 'message' => 'ID tidak valid'], 422);
            }

            $stmt = $pdo->prepare("
                SELECT t.*, u.name AS cashier_name
                FROM transactions t
                LEFT JOIN users u ON u.id = t.user_id
                WHERE t.id = ?
            ");
            $stmt->execute([$id]);
            $transaction = $stmt->fetch();
            if (!$transaction) {
                jsonResponse(['success' => false, 'message' => 'Transaksi tidak ditemukan'], 404);
            }

            $itemsStmt = $pdo->prepare("
                SELECT id, transaction_id, product_id, product_name, price, quantity, subtotal
                FROM transaction_items WHERE transaction_id = ? ORDER BY id
            ");
            $itemsStmt->execute([$id]);
            jsonResponse([
                'success' => true,
                'transaction' => $transaction,
                'items' => $itemsStmt->fetchAll(),
            ]);
        }

        if ($action === 'today_stats') {
            $stats = $pdo->query("
                SELECT COUNT(*) AS total_transactions,
                       COALESCE(SUM(total_amount), 0) AS total_revenue
                FROM transactions
                WHERE created_at >= CURRENT_DATE
                  AND created_at < CURRENT_DATE + INTERVAL 1 DAY
            ")->fetch();

            $chart = $pdo->query("
                SELECT DATE(created_at) AS date, COUNT(*) AS transactions,
                       COALESCE(SUM(total_amount), 0) AS revenue
                FROM transactions
                WHERE created_at >= CURRENT_DATE - INTERVAL 6 DAY
                  AND created_at < CURRENT_DATE + INTERVAL 1 DAY
                GROUP BY DATE(created_at)
                ORDER BY date
            ")->fetchAll();

            jsonResponse(['success' => true, 'stats' => $stats, 'chart' => $chart]);
        }

        if ($action !== '' && $action !== 'list') {
            jsonResponse(['success' => false, 'message' => 'Action tidak dikenal'], 400);
        }

        $search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
        $dateFrom = is_string($_GET['date_from'] ?? null) ? trim($_GET['date_from']) : '';
        $dateTo = is_string($_GET['date_to'] ?? null) ? trim($_GET['date_to']) : '';
        if (mb_strlen($search) > 100) {
            jsonResponse(['success' => false, 'message' => 'Pencarian terlalu panjang'], 422);
        }
        if (($dateFrom !== '' && !validDateParam($dateFrom)) ||
            ($dateTo !== '' && !validDateParam($dateTo))) {
            jsonResponse(['success' => false, 'message' => 'Format tanggal tidak valid'], 422);
        }
        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            jsonResponse(['success' => false, 'message' => 'Tanggal awal harus sebelum tanggal akhir'], 422);
        }

        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $perPage = filter_var($_GET['per_page'] ?? 15, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);
        if ($page === false || $perPage === false) {
            jsonResponse(['success' => false, 'message' => 'Parameter pagination tidak valid'], 422);
        }
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(t.invoice_no LIKE ? OR u.name LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        if ($dateFrom !== '') {
            $where[] = 't.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[] = 't.created_at < ?';
            $params[] = (new DateTimeImmutable($dateTo))->modify('+1 day')->format('Y-m-d 00:00:00');
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("
            SELECT COUNT(*) FROM transactions t
            LEFT JOIN users u ON u.id = t.user_id
            WHERE $whereSql
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT t.*, u.name AS cashier_name
            FROM transactions t
            LEFT JOIN users u ON u.id = t.user_id
            WHERE $whereSql
            ORDER BY t.created_at DESC, t.id DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute($params);

        jsonResponse([
            'success' => true,
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) ceil($total / $perPage),
        ]);
    }

    requireCsrfToken();
    $data = requestData();
    if (!isset($data['items']) || !is_array($data['items']) ||
        count($data['items']) < 1 || count($data['items']) > 100) {
        jsonResponse(['success' => false, 'message' => 'Transaksi harus berisi 1 sampai 100 item'], 422);
    }

    // Aggregate duplicate product rows before checking and decrementing stock.
    $quantities = [];
    foreach ($data['items'] as $item) {
        if (!is_array($item)) {
            jsonResponse(['success' => false, 'message' => 'Data item tidak valid'], 422);
        }
        $productId = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000000],
        ]);
        if ($productId === false || $quantity === false) {
            jsonResponse(['success' => false, 'message' => 'Produk atau jumlah item tidak valid'], 422);
        }
        $newQuantity = ($quantities[$productId] ?? 0) + $quantity;
        if ($newQuantity > 1000000) {
            jsonResponse(['success' => false, 'message' => 'Jumlah item terlalu besar'], 422);
        }
        $quantities[$productId] = $newQuantity;
    }

    $payment = parseMoneyAmount($data['payment_amount'] ?? null);
    $notes = is_string($data['notes'] ?? null) ? trim($data['notes']) : '';
    if ($payment === null || $payment < 0 || $payment > 999999999999.99) {
        jsonResponse(['success' => false, 'message' => 'Nominal bayar tidak valid'], 422);
    }
    if (mb_strlen($notes) > 2000) {
        jsonResponse(['success' => false, 'message' => 'Catatan maksimal 2000 karakter'], 422);
    }

    $pdo->beginTransaction();
    $productIds = array_keys($quantities);
    sort($productIds, SORT_NUMERIC);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $productStmt = $pdo->prepare("
        SELECT id, name, price, stock, unit
        FROM products
        WHERE id IN ($placeholders)
        ORDER BY id
        FOR UPDATE
    ");
    $productStmt->execute($productIds);
    $products = [];
    foreach ($productStmt->fetchAll() as $product) {
        $products[(int) $product['id']] = $product;
    }

    if (count($products) !== count($productIds)) {
        throw new DomainException('Salah satu produk tidak ditemukan');
    }

    $totalCents = 0;
    $canonicalItems = [];
    foreach ($productIds as $productId) {
        $product = $products[$productId];
        $quantity = $quantities[$productId];
        if ((int) $product['stock'] < $quantity) {
            throw new DomainException('Stok ' . $product['name'] . ' tidak mencukupi');
        }
        $priceCents = (int) round((float) $product['price'] * 100);
        if ($priceCents <= 0) {
            throw new DomainException('Harga ' . $product['name'] . ' tidak valid');
        }
        $totalCents += $priceCents * $quantity;
        if ($totalCents > 99999999999999) {
            throw new DomainException('Total transaksi melebihi batas');
        }
    }

    $paymentCents = (int) round($payment * 100);
    if ($paymentCents < $totalCents) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'Nominal bayar kurang dari total'], 422);
    }

    $total = $totalCents / 100;
    $paymentAmount = $paymentCents / 100;
    $change = ($paymentCents - $totalCents) / 100;
    $invoice = generateInvoice();

    $transactionStmt = $pdo->prepare("
        INSERT INTO transactions
            (invoice_no, user_id, total_amount, payment_amount, change_amount, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $transactionStmt->execute([
        $invoice, $user['id'], $total, $paymentAmount, $change, $notes !== '' ? $notes : null,
    ]);
    $transactionId = (int) $pdo->lastInsertId();

    $itemStmt = $pdo->prepare("
        INSERT INTO transaction_items
            (transaction_id, product_id, product_name, price, quantity, subtotal)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stockStmt = $pdo->prepare(
        'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?'
    );

    foreach ($productIds as $productId) {
        $product = $products[$productId];
        $quantity = $quantities[$productId];
        $price = (float) $product['price'];
        $subtotal = ((int) round($price * 100) * $quantity) / 100;
        $itemStmt->execute([
            $transactionId, $productId, $product['name'], $price, $quantity, $subtotal,
        ]);
        $stockStmt->execute([$quantity, $productId, $quantity]);
        if ($stockStmt->rowCount() !== 1) {
            throw new DomainException('Stok ' . $product['name'] . ' tidak mencukupi');
        }
        $canonicalItems[] = [
            'id' => $productId,
            'name' => $product['name'],
            'price' => $price,
            'quantity' => $quantity,
            'qty' => $quantity,
            'unit' => $product['unit'],
            'subtotal' => $subtotal,
        ];
    }

    $pdo->commit();
    jsonResponse([
        'success' => true,
        'message' => 'Transaksi berhasil!',
        'transaction_id' => $transactionId,
        'invoice_no' => $invoice,
        'total' => $total,
        'payment' => $paymentAmount,
        'change' => $change,
        'items' => $canonicalItems,
    ], 201);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 409);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Transactions API error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Terjadi kesalahan pada server'], 500);
}
