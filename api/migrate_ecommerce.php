<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../includes/db.php';

$response = [
  'ok' => true,
  'steps' => [],
];

function step($label, $ok, $error = null) {
  return [ 'label' => $label, 'ok' => $ok, 'error' => $error ];
}

try {
  // Ensure categories table
  $sql = "CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  $ok = mysqli_query($conn, $sql);
  $response['steps'][] = step('create_table_categories', (bool)$ok, $ok ? null : mysqli_error($conn));

  // Junction table product_categories
  $sql = "CREATE TABLE IF NOT EXISTS product_categories (
    product_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (product_id, category_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  $ok = mysqli_query($conn, $sql);
  $response['steps'][] = step('create_table_product_categories', (bool)$ok, $ok ? null : mysqli_error($conn));

  // product_images table
  $sql = "CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_url VARCHAR(1024) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  $ok = mysqli_query($conn, $sql);
  $response['steps'][] = step('create_table_product_images', (bool)$ok, $ok ? null : mysqli_error($conn));

  // Helper to check column existence
  function column_exists($conn, $table, $column) {
    $table_safe = mysqli_real_escape_string($conn, $table);
    $column_safe = mysqli_real_escape_string($conn, $column);
    $db = mysqli_query($conn, 'SELECT DATABASE() as db');
    $dbName = mysqli_fetch_assoc($db)['db'];
    $dbName_safe = mysqli_real_escape_string($conn, $dbName);
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='{$dbName_safe}' AND TABLE_NAME='{$table_safe}' AND COLUMN_NAME='{$column_safe}' LIMIT 1";
    $res = mysqli_query($conn, $sql);
    return $res && mysqli_num_rows($res) > 0;
  }

  // Alter products: slug
  if (!column_exists($conn, 'products', 'slug')) {
    $ok = mysqli_query($conn, "ALTER TABLE products ADD COLUMN slug VARCHAR(255) UNIQUE NULL");
    $response['steps'][] = step('alter_products_add_slug', (bool)$ok, $ok ? null : mysqli_error($conn));
  }

  // is_published
  if (!column_exists($conn, 'products', 'is_published')) {
    $ok = mysqli_query($conn, "ALTER TABLE products ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0");
    $response['steps'][] = step('alter_products_add_is_published', (bool)$ok, $ok ? null : mysqli_error($conn));
  }

  // seo_title
  if (!column_exists($conn, 'products', 'seo_title')) {
    $ok = mysqli_query($conn, "ALTER TABLE products ADD COLUMN seo_title VARCHAR(255) NULL");
    $response['steps'][] = step('alter_products_add_seo_title', (bool)$ok, $ok ? null : mysqli_error($conn));
  }

  // seo_desc
  if (!column_exists($conn, 'products', 'seo_desc')) {
    $ok = mysqli_query($conn, "ALTER TABLE products ADD COLUMN seo_desc VARCHAR(512) NULL");
    $response['steps'][] = step('alter_products_add_seo_desc', (bool)$ok, $ok ? null : mysqli_error($conn));
  }

  // web_price
  if (!column_exists($conn, 'products', 'web_price')) {
    $ok = mysqli_query($conn, "ALTER TABLE products ADD COLUMN web_price DECIMAL(10,2) NULL");
    $response['steps'][] = step('alter_products_add_web_price', (bool)$ok, $ok ? null : mysqli_error($conn));
  }

  // featured
  if (!column_exists($conn, 'products', 'featured')) {
    $ok = mysqli_query($conn, "ALTER TABLE products ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0");
    $response['steps'][] = step('alter_products_add_featured', (bool)$ok, $ok ? null : mysqli_error($conn));
  }

  // sort_order
  if (!column_exists($conn, 'products', 'sort_order')) {
    $ok = mysqli_query($conn, "ALTER TABLE products ADD COLUMN sort_order INT NOT NULL DEFAULT 0");
    $response['steps'][] = step('alter_products_add_sort_order', (bool)$ok, $ok ? null : mysqli_error($conn));
  }

  // description (long)
  if (!column_exists($conn, 'products', 'description')) {
    $ok = mysqli_query($conn, "ALTER TABLE products ADD COLUMN description TEXT NULL");
    $response['steps'][] = step('alter_products_add_description', (bool)$ok, $ok ? null : mysqli_error($conn));
  }

  echo json_encode($response);
} catch (Exception $e) {
  http_response_code(500);
  $response['ok'] = false;
  $response['error'] = $e->getMessage();
  echo json_encode($response);
}


