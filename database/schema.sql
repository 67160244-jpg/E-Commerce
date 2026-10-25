-- Mochi Vita schema (MySQL 8 / MariaDB, utf8mb4)
CREATE DATABASE IF NOT EXISTS mochi_vita CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mochi_vita;
SET NAMES utf8mb4;
DROP TABLE IF EXISTS login_attempts, stock_log, order_items, orders, addresses, products, categories, users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(30),
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE addresses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  label VARCHAR(40),
  recipient VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  address_line VARCHAR(255) NOT NULL,
  district VARCHAR(100),
  province VARCHAR(100) NOT NULL,
  postcode VARCHAR(10) NOT NULL,
  is_default TINYINT(1) DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name_th VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  description VARCHAR(255)
);
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(30) NOT NULL UNIQUE,
  name_th VARCHAR(160) NOT NULL,
  name_en VARCHAR(160),
  slug VARCHAR(180) NOT NULL UNIQUE,
  category_id INT NOT NULL,
  brand VARCHAR(80),
  price DECIMAL(10,2) NOT NULL,
  compare_at_price DECIMAL(10,2) NULL,
  stock_qty INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NOT NULL DEFAULT 10,
  size VARCHAR(60),
  description TEXT,
  image VARCHAR(255),
  is_featured TINYINT(1) DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  FOREIGN KEY (category_id) REFERENCES categories(id)
);
CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  status ENUM('pending','paid','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  payment_method ENUM('promptpay','credit_card','cod') NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  ship_name VARCHAR(120) NOT NULL,
  ship_phone VARCHAR(30) NOT NULL,
  ship_address VARCHAR(400) NOT NULL,
  tracking_no VARCHAR(40) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  product_name VARCHAR(160) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  quantity INT NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
);
CREATE TABLE stock_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  change_qty INT NOT NULL,
  reason VARCHAR(80),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);
CREATE TABLE login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(160) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  success TINYINT(1) DEFAULT 0,
  INDEX idx_attempt (email, ip, attempted_at)
);
