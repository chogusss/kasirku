-- ============================================
-- KASIR UMKM - Database Setup Script
-- Versi: 1.0 | KasirKu
-- ============================================

-- ============================================
-- TABEL: users
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('owner', 'staff') NOT NULL DEFAULT 'staff',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABEL: categories
-- ============================================
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABEL: products
-- ============================================
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED NULL,
  `name` VARCHAR(150) NOT NULL,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `stock` INT NOT NULL DEFAULT 0,
  `unit` VARCHAR(20) NOT NULL DEFAULT 'pcs',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_products_price` CHECK (`price` > 0),
  CONSTRAINT `chk_products_stock` CHECK (`stock` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABEL: transactions
-- ============================================
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT UNSIGNED NULL,
  `total_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `payment_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `change_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_transactions_created_at` (`created_at`),
  KEY `idx_transactions_user_created` (`user_id`, `created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_transactions_total` CHECK (`total_amount` > 0),
  CONSTRAINT `chk_transactions_payment` CHECK (`payment_amount` >= `total_amount`),
  CONSTRAINT `chk_transactions_change` CHECK (`change_amount` = `payment_amount` - `total_amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABEL: transaction_items
-- ============================================
CREATE TABLE IF NOT EXISTS `transaction_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `transaction_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `price` DECIMAL(12,2) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `subtotal` DECIMAL(14,2) NOT NULL,
  UNIQUE KEY `uq_transaction_product` (`transaction_id`, `product_id`),
  FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_transaction_items_price` CHECK (`price` > 0),
  CONSTRAINT `chk_transaction_items_quantity` CHECK (`quantity` > 0),
  CONSTRAINT `chk_transaction_items_subtotal` CHECK (`subtotal` = `price` * `quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- SEED DATA: Users
-- admin password = admin123
-- staff password = staff123
-- (Hashes generated with password_hash($pass, PASSWORD_DEFAULT))
-- ============================================
INSERT IGNORE INTO `users` (`id`, `name`, `username`, `password`, `role`) VALUES
(1, 'Administrator', 'admin', '$2y$10$DAoBvkzVbbVIcUwFIM6JxukgBus3.N7oSld1rFZlUwMHqtW.UFpD6', 'owner'),
(2, 'Staff Kasir',   'staff', '$2y$10$uogDJmyz/D6a/r8RC8g.reSO8riG1bc9ZkMM9Cs6fkhczuLq.G.iW', 'staff');

-- NOTE: To rotate the default passwords, use setup.php locally as an authenticated owner,
-- or generate a replacement hash with:
-- <?php echo password_hash('admin123', PASSWORD_DEFAULT); ?>

-- ============================================
-- SEED DATA: Categories
-- ============================================
INSERT IGNORE INTO `categories` (`id`, `name`) VALUES
(1, 'Makanan'),
(2, 'Minuman'),
(3, 'Snack'),
(4, 'Rokok'),
(5, 'Sembako'),
(6, 'Lainnya');

-- ============================================
-- SEED DATA: Products (20 produk contoh)
-- ============================================
INSERT IGNORE INTO `products` (`id`, `category_id`, `name`, `price`, `stock`, `unit`) VALUES
(1,  1, 'Nasi Goreng',          15000, 50,  'porsi'),
(2,  1, 'Mie Goreng',           12000, 45,  'porsi'),
(3,  1, 'Nasi Putih',            5000, 100, 'porsi'),
(4,  1, 'Ayam Goreng',          18000, 30,  'pcs'),
(5,  1, 'Tempe Goreng',          5000, 60,  'pcs'),
(6,  2, 'Es Teh Manis',          5000, 100, 'gelas'),
(7,  2, 'Es Jeruk',              7000, 80,  'gelas'),
(8,  2, 'Air Mineral 600ml',     4000, 120, 'botol'),
(9,  2, 'Kopi Hitam',            8000, 70,  'gelas'),
(10, 2, 'Es Campur',            12000, 40,  'gelas'),
(11, 3, 'Keripik Singkong',      8000, 25,  'bungkus'),
(12, 3, 'Roti Bakar',           10000, 35,  'pcs'),
(13, 3, 'Gorengan',              2000, 8,   'pcs'),
(14, 4, 'Rokok Surya 12',       24000, 15,  'bungkus'),
(15, 4, 'Rokok Gudang Garam',   22000, 20,  'bungkus'),
(16, 5, 'Gula Pasir 1kg',       15000, 40,  'kg'),
(17, 5, 'Minyak Goreng 1L',     18000, 30,  'liter'),
(18, 5, 'Telur Ayam',            2500, 5,   'butir'),
(19, 6, 'Sabun Mandi Batang',    5000, 50,  'pcs'),
(20, 6, 'Shampo Sachet',         1500, 100, 'sachet');

-- ============================================
-- SEED DATA: Sample Transactions
-- ============================================
INSERT IGNORE INTO `transactions` (`id`, `invoice_no`, `user_id`, `total_amount`, `payment_amount`, `change_amount`, `created_at`) VALUES
(1, CONCAT('INV-', DATE_FORMAT(NOW(), '%Y%m%d'), '-0001'), 1, 37000, 50000, 13000, DATE_SUB(NOW(), INTERVAL 0 DAY)),
(2, CONCAT('INV-', DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 DAY), '%Y%m%d'), '-0001'), 2, 24000, 30000, 6000, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, CONCAT('INV-', DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 DAY), '%Y%m%d'), '-0002'), 2, 53000, 60000, 7000, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, CONCAT('INV-', DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 2 DAY), '%Y%m%d'), '-0001'), 1, 46000, 50000, 4000, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, CONCAT('INV-', DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 3 DAY), '%Y%m%d'), '-0001'), 2, 33000, 35000, 2000, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(6, CONCAT('INV-', DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 4 DAY), '%Y%m%d'), '-0001'), 1, 67000, 70000, 3000, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(7, CONCAT('INV-', DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 5 DAY), '%Y%m%d'), '-0001'), 2, 50000, 50000, 0, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(8, CONCAT('INV-', DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 6 DAY), '%Y%m%d'), '-0001'), 1, 30000, 30000, 0, DATE_SUB(NOW(), INTERVAL 6 DAY));

-- ============================================
-- SEED DATA: Transaction Items
-- ============================================
INSERT IGNORE INTO `transaction_items` (`transaction_id`, `product_id`, `product_name`, `price`, `quantity`, `subtotal`) VALUES
-- Transaksi 1
(1, 1,  'Nasi Goreng',     15000, 1, 15000),
(1, 7,  'Es Jeruk',         7000, 2, 14000),
(1, 6,  'Es Teh Manis',     5000, 1,  5000),
(1, 5,  'Tempe Goreng',     3000, 1,  3000),
-- Transaksi 2
(2, 11, 'Keripik Singkong', 8000, 1,  8000),
(2, 9,  'Kopi Hitam',       8000, 2, 16000),
-- Transaksi 3
(3, 1,  'Nasi Goreng',     15000, 2, 30000),
(3, 4,  'Ayam Goreng',     18000, 1, 18000),
(3, 6,  'Es Teh Manis',     5000, 1,  5000),
-- Transaksi 4
(4, 2,  'Mie Goreng',      12000, 2, 24000),
(4, 10, 'Es Campur',       12000, 1, 12000),
(4, 8,  'Air Mineral 600ml', 4000, 1,  4000),
(4, 5,  'Tempe Goreng',     3000, 2,  6000),
-- Transaksi 5
(5, 3,  'Nasi Putih',       5000, 2, 10000),
(5, 4,  'Ayam Goreng',     18000, 1, 18000),
(5, 6,  'Es Teh Manis',     5000, 1,  5000),
-- Transaksi 6
(6, 14, 'Rokok Surya 12',  24000, 1, 24000),
(6, 16, 'Gula Pasir 1kg',  15000, 1, 15000),
(6, 20, 'Shampo Sachet',    1500, 4,  6000),
(6, 8,  'Air Mineral 600ml', 4000, 1,  4000),
(6, 9,  'Kopi Hitam',       8000, 1,  8000),
(6, 13, 'Gorengan',         2000, 5, 10000),
-- Transaksi 7
(7, 1,  'Nasi Goreng',     15000, 2, 30000),
(7, 6,  'Es Teh Manis',     5000, 2, 10000),
(7, 12, 'Roti Bakar',      10000, 1, 10000),
-- Transaksi 8
(8, 2,  'Mie Goreng',      12000, 1, 12000),
(8, 9,  'Kopi Hitam',       8000, 1,  8000),
(8, 11, 'Keripik Singkong', 8000, 1,  8000),
(8, 13, 'Gorengan',         2000, 1,  2000);
