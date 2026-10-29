<?php
/**
 * API: Products CRUD
 * Endpoint: /kasirku/api/products.php
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
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

    $action = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';

    if ($method === 'GET') {
        if ($action === 'categories') {
            $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
            jsonResponse(['success' => true, 'data' => $categories]);
        }
        if ($action !== '' && $action !== 'list') {
            jsonResponse(['success' => false, 'message' => 'Action tidak dikenal'], 400);
        }

        $search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
        if (mb_strlen($search) > 100) {
            jsonResponse(['success' => false, 'message' => 'Pencarian terlalu panjang'], 422);
        }

        $categoryId = 0;
        if (isset($_GET['category_id']) && $_GET['category_id'] !== '') {
            $categoryId = filter_var($_GET['category_id'], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($categoryId === false) {
                jsonResponse(['success' => false, 'message' => 'Kategori tidak valid'], 422);
            }
        }

        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $perPage = filter_var($_GET['per_page'] ?? 20, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 200],
        ]);
        if ($page === false || $perPage === false) {
            jsonResponse(['success' => false, 'message' => 'Parameter pagination tidak valid'], 422);
        }
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];
        if ($search !== '') {
            $where[] = 'p.name LIKE ?';
            $params[] = '%' . $search . '%';
        }
        if ($categoryId > 0) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Pagination values are validated integers and deliberately interpolated.
        $stmt = $pdo->prepare("
            SELECT p.id, p.category_id, p.name, p.price, p.stock, p.unit,
                   p.created_at, p.updated_at, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE $whereSql
            ORDER BY p.name, p.id
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

    if ($user['role'] !== 'owner') {
        jsonResponse(['success' => false, 'message' => 'Akses ditolak'], 403);
    }

    requireCsrfToken();
    $data = requestData();
    $postAction = is_string($data['action'] ?? null) ? $data['action'] : $action;
    if (!in_array($postAction, ['add', 'edit', 'delete'], true)) {
        jsonResponse(['success' => false, 'message' => 'Action tidak dikenal'], 400);
    }

    $id = null;
    if ($postAction !== 'add') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($id === false) {
            jsonResponse(['success' => false, 'message' => 'ID tidak valid'], 422);
        }
    }

    if ($postAction === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            jsonResponse(['success' => false, 'message' => 'Produk tidak ditemukan'], 404);
        }
        jsonResponse(['success' => true, 'message' => 'Produk berhasil dihapus']);
    }

    $name = is_string($data['name'] ?? null) ? trim($data['name']) : '';
    $unit = is_string($data['unit'] ?? null) ? trim($data['unit']) : 'pcs';
    $price = parseMoneyAmount($data['price'] ?? null);
    $stock = filter_var($data['stock'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => 2147483647],
    ]);

    if ($name === '' || mb_strlen($name) > 150) {
        jsonResponse(['success' => false, 'message' => 'Nama produk wajib diisi (maksimal 150 karakter)'], 422);
    }
    if ($unit === '' || mb_strlen($unit) > 20) {
        jsonResponse(['success' => false, 'message' => 'Satuan wajib diisi (maksimal 20 karakter)'], 422);
    }
    if ($price === null || $price <= 0 || $price > 9999999999.99) {
        jsonResponse(['success' => false, 'message' => 'Harga tidak valid'], 422);
    }
    if ($stock === false) {
        jsonResponse(['success' => false, 'message' => 'Stok harus berupa bilangan bulat nol atau lebih'], 422);
    }

    $categoryId = null;
    if (isset($data['category_id']) && $data['category_id'] !== '' && $data['category_id'] !== null) {
        $categoryId = filter_var($data['category_id'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($categoryId === false) {
            jsonResponse(['success' => false, 'message' => 'Kategori tidak valid'], 422);
        }
        $catStmt = $pdo->prepare('SELECT 1 FROM categories WHERE id = ?');
        $catStmt->execute([$categoryId]);
        if (!$catStmt->fetchColumn()) {
            jsonResponse(['success' => false, 'message' => 'Kategori tidak ditemukan'], 422);
        }
    }

    if ($postAction === 'add') {
        $stmt = $pdo->prepare(
            'INSERT INTO products (category_id, name, price, stock, unit) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$categoryId, $name, $price, $stock, $unit]);
        jsonResponse([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan',
            'id' => (int) $pdo->lastInsertId(),
        ], 201);
    }

    $existsStmt = $pdo->prepare('SELECT 1 FROM products WHERE id = ?');
    $existsStmt->execute([$id]);
    if (!$existsStmt->fetchColumn()) {
        jsonResponse(['success' => false, 'message' => 'Produk tidak ditemukan'], 404);
    }

    $stmt = $pdo->prepare(
        'UPDATE products SET category_id = ?, name = ?, price = ?, stock = ?, unit = ? WHERE id = ?'
    );
    $stmt->execute([$categoryId, $name, $price, $stock, $unit, $id]);
    jsonResponse(['success' => true, 'message' => 'Produk berhasil diperbarui']);
} catch (InvalidArgumentException $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('Products API error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Terjadi kesalahan pada server'], 500);
}
