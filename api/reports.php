<?php
/**
 * API: Reports
 * Endpoint: /kasir/api/reports.php
 */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET');
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    $user = currentApiUser($pdo);
    if (!$user) {
        session_unset();
        jsonResponse(['success' => false, 'message' => 'Sesi tidak valid'], 401);
    }
    if ($user['role'] !== 'owner') {
        jsonResponse(['success' => false, 'message' => 'Akses ditolak'], 403);
    }

    $year = filter_var($_GET['year'] ?? date('Y'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 2000, 'max_range' => 2100],
    ]);
    if ($year === false) {
        jsonResponse(['success' => false, 'message' => 'Tahun tidak valid'], 422);
    }
    $action = is_string($_GET['action'] ?? null) ? $_GET['action'] : 'monthly';

    if ($action === 'years') {
        // Get available years
        $stmt = $pdo->query("SELECT DISTINCT YEAR(created_at) AS year FROM transactions ORDER BY year DESC");
        $years = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!in_array((int) date('Y'), $years, true)) {
            array_unshift($years, (int) date('Y'));
        }
        jsonResponse(['success' => true, 'data' => $years]);
    }

    if ($action === 'dashboard') {
        // Dashboard stats
        $today = date('Y-m-d');

        // Today revenue
        $todayStmt = $pdo->prepare("
            SELECT COUNT(*) AS txn_count, COALESCE(SUM(total_amount), 0) AS revenue
            FROM transactions
            WHERE created_at >= ? AND created_at < ?
        ");
        $todayStmt->execute([$today . ' 00:00:00', date('Y-m-d', strtotime('+1 day')) . ' 00:00:00']);
        $todayStats = $todayStmt->fetch();

        // Total products
        $prodCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

        // Keep the counter and the detail list on the same low-stock threshold.
        $lowStock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 10")->fetchColumn();

        // Last 7 days chart
        $chartStmt = $pdo->query("
            SELECT
                DATE(created_at) AS date,
                COUNT(*) AS transactions,
                COALESCE(SUM(total_amount), 0) AS revenue
            FROM transactions
            WHERE created_at >= CURRENT_DATE - INTERVAL 6 DAY
              AND created_at < CURRENT_DATE + INTERVAL 1 DAY
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $chartData = $chartStmt->fetchAll();

        // Recent transactions (5)
        $recentStmt = $pdo->query("
            SELECT t.invoice_no, t.total_amount, t.created_at, u.name AS cashier_name
            FROM transactions t
            LEFT JOIN users u ON u.id = t.user_id
            ORDER BY t.created_at DESC LIMIT 5
        ");
        $recent = $recentStmt->fetchAll();

        // Low stock products list
        $lowStockList = $pdo->query("
            SELECT name, stock, unit FROM products WHERE stock <= 10 ORDER BY stock ASC LIMIT 5
        ")->fetchAll();

        jsonResponse([
            'success'       => true,
            'today'         => $todayStats,
            'products'      => $prodCount,
            'low_stock'     => $lowStock,
            'chart'         => $chartData,
            'recent'        => $recent,
            'low_stock_list'=> $lowStockList,
        ]);
    }

    if ($action !== '' && $action !== 'monthly') {
        jsonResponse(['success' => false, 'message' => 'Action tidak dikenal'], 400);
    }

    // Monthly report
    $stmt = $pdo->prepare("
        SELECT
            MONTH(created_at)  AS month,
            COUNT(*)           AS total_transactions,
            COALESCE(SUM(total_amount), 0) AS total_revenue,
            COALESCE(AVG(total_amount), 0) AS avg_per_transaction
        FROM transactions
        WHERE YEAR(created_at) = ?
        GROUP BY MONTH(created_at)
        ORDER BY month ASC
    ");
    $stmt->execute([$year]);
    $monthly = $stmt->fetchAll();

    // Fill missing months with zeros
    $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                   'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $fullData = [];
    $monthlyByNum = [];
    foreach ($monthly as $m) $monthlyByNum[$m['month']] = $m;

    for ($i = 1; $i <= 12; $i++) {
        if (isset($monthlyByNum[$i])) {
            $row = $monthlyByNum[$i];
            $row['month_name'] = $monthNames[$i];
            $fullData[] = $row;
        } else {
            $fullData[] = [
                'month'              => $i,
                'month_name'         => $monthNames[$i],
                'total_transactions' => 0,
                'total_revenue'      => 0,
                'avg_per_transaction'=> 0,
            ];
        }
    }

    // Annual total
    $totalStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_transactions,
            COALESCE(SUM(total_amount), 0) AS total_revenue
        FROM transactions WHERE YEAR(created_at) = ?
    ");
    $totalStmt->execute([$year]);
    $annual = $totalStmt->fetch();

    jsonResponse([
        'success'  => true,
        'year'     => $year,
        'data'     => $fullData,
        'annual'   => $annual,
    ]);

} catch (Throwable $e) {
    error_log('Reports API error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Terjadi kesalahan pada server'], 500);
}
