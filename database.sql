-- POS System Database Schema (updated)
-- Create database
CREATE DATABASE IF NOT EXISTS pos_system;
USE pos_system;

-- Users table
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
DROP TABLE IF EXISTS products;
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    sku VARCHAR(50) UNIQUE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock INT NOT NULL DEFAULT 0,
    category_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Sales table
DROP TABLE IF EXISTS sales;
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    payment_method ENUM('cash', 'card', 'mixed') DEFAULT 'cash',
    cash_amount DECIMAL(10,2) DEFAULT 0.00,
    card_amount DECIMAL(10,2) DEFAULT 0.00,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sale items table
DROP TABLE IF EXISTS sale_items;
CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Customers table (for future use)
DROP TABLE IF EXISTS customers;
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Vendors table
DROP TABLE IF EXISTS vendors;
CREATE TABLE vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(150) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(150),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Purchases table
DROP TABLE IF EXISTS purchases;
CREATE TABLE purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    purchase_date DATE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash','bank_transfer','check','credit') NOT NULL DEFAULT 'cash',
    notes TEXT,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);

-- Purchase items table
DROP TABLE IF EXISTS purchase_items;
CREATE TABLE purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    cost_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- Insert sample data

-- Insert users (username: admin/cashier, password: admin123)
INSERT INTO users (name, username, password, role) VALUES 
('Administrator', 'admin', '$2y$10$Yz42SKjwQGeUnzD/YqC6B.Unw6iSyOUbghKwLGh0nFTYGkwdkvBzG', 'admin'),
('Cashier User', 'cashier', '$2y$10$Yz42SKjwQGeUnzD/YqC6B.Unw6iSyOUbghKwLGh0nFTYGkwdkvBzG', 'cashier');

-- Insert sample categories
INSERT INTO categories (name) VALUES 
('Electronics'),
('Clothing'),
('Food & Beverages'),
('Books'),
('Home & Garden');

-- Insert sample products
INSERT INTO products (name, sku, price, cost_price, stock, category_id) VALUES 
('iPhone 13', 'IPH13-128', 799.99, 700.00, 25, 1),
('Samsung Galaxy S21', 'SAMS21-256', 699.99, 620.00, 20, 1),
('MacBook Pro 14"', 'MBP14-512', 1999.99, 1800.00, 10, 1),
('Nike Air Max', 'NIKE-AM-001', 129.99, 100.00, 50, 2),
('Adidas Ultraboost', 'ADID-UB-001', 179.99, 150.00, 30, 2),
('Coffee Beans 1kg', 'COFFEE-ARABICA', 24.99, 18.00, 100, 3),
('Organic Tea Bags', 'TEA-ORGANIC-50', 12.99, 9.50, 75, 3),
('The Great Gatsby', 'BOOK-GATSBY', 9.99, 7.00, 40, 4),
('To Kill a Mockingbird', 'BOOK-MOCKINGBIRD', 11.99, 8.50, 35, 4),
('Garden Hose 50ft', 'GARDEN-HOSE-50', 39.99, 28.00, 15, 5),
('LED Desk Lamp', 'LAMP-LED-DESK', 29.99, 22.00, 20, 5),
('Wireless Mouse', 'MOUSE-WIRELESS', 19.99, 14.00, 45, 1);

-- Optional sample vendors
INSERT INTO vendors (name, contact_person, phone, email, address) VALUES
('ABC Suppliers', 'Ali Raza', '+92-300-0000001', 'abc@suppliers.pk', 'Lahore, PK'),
('XYZ Trading Co.', 'Bilal Khan', '+92-300-0000002', 'xyz@trading.pk', 'Karachi, PK'),
('Quality Goods Ltd.', 'Saad Ahmed', '+92-300-0000003', 'quality@goods.pk', 'Islamabad, PK');

-- Sample sales
INSERT INTO sales (date, total_amount, discount_amount, payment_method, cash_amount, card_amount, user_id) VALUES 
(NOW() - INTERVAL 2 HOUR, 159.98, 0.00, 'cash', 159.98, 0.00, 2),
(NOW() - INTERVAL 1 HOUR, 89.97, 5.00, 'mixed', 50.00, 39.97, 2),
(NOW() - INTERVAL 30 MINUTE, 2049.98, 0.00, 'card', 0.00, 2049.98, 1);

-- Sample sale items
INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES 
(1, 4, 1, 129.99),
(1, 6, 1, 24.99),
(1, 7, 1, 4.99),
(2, 8, 3, 9.99),
(2, 9, 5, 11.99),
(3, 3, 1, 1999.99),
(3, 12, 1, 19.99),
(3, 11, 1, 29.99);

-- Indexes
CREATE INDEX idx_products_sku ON products(sku);
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_sales_date ON sales(date);
CREATE INDEX idx_sales_user ON sales(user_id);
CREATE INDEX idx_sale_items_sale ON sale_items(sale_id);
CREATE INDEX idx_sale_items_product ON sale_items(product_id);
CREATE INDEX idx_vendors_name ON vendors(name);
CREATE INDEX idx_purchases_date ON purchases(purchase_date);
CREATE INDEX idx_purchases_vendor ON purchases(vendor_id);
CREATE INDEX idx_purchase_items_purchase ON purchase_items(purchase_id);
CREATE INDEX idx_purchase_items_product ON purchase_items(product_id);
-- POS System Database Schema
-- Create database
CREATE DATABASE IF NOT EXISTS pos_system;
USE pos_system;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    sku VARCHAR(50) UNIQUE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    category_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Sales table
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    payment_method ENUM('cash', 'card', 'mixed') DEFAULT 'cash',
    cash_amount DECIMAL(10,2) DEFAULT 0.00,
    card_amount DECIMAL(10,2) DEFAULT 0.00,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sale items table
CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Customers table (for future use)
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample data

-- Insert admin user (username: admin, password: admin123)
INSERT INTO users (name, username, password, role) VALUES 
('Administrator', 'admin', '$2y$10$uO1qW/jH5VE80MTRbSDMmuTJ3J5ly/iJgzaWEZAYX9ToqzN6hCgVm', 'admin'),
('Cashier User', 'cashier', '$2y$10$uO1qW/jH5VE80MTRbSDMmuTJ3J5ly/iJgzaWEZAYX9ToqzN6hCgVm', 'cashier');

-- Insert sample categories
INSERT INTO categories (name) VALUES 
('Electronics'),
('Clothing'),
('Food & Beverages'),
('Books'),
('Home & Garden');

-- Insert sample products
INSERT INTO products (name, sku, price, stock, category_id) VALUES 
('iPhone 13', 'IPH13-128', 799.99, 25, 1),
('Samsung Galaxy S21', 'SAMS21-256', 699.99, 20, 1),
('MacBook Pro 14"', 'MBP14-512', 1999.99, 10, 1),
('Nike Air Max', 'NIKE-AM-001', 129.99, 50, 2),
('Adidas Ultraboost', 'ADID-UB-001', 179.99, 30, 2),
('Coffee Beans 1kg', 'COFFEE-ARABICA', 24.99, 100, 3),
('Organic Tea Bags', 'TEA-ORGANIC-50', 12.99, 75, 3),
('The Great Gatsby', 'BOOK-GATSBY', 9.99, 40, 4),
('To Kill a Mockingbird', 'BOOK-MOCKINGBIRD', 11.99, 35, 4),
('Garden Hose 50ft', 'GARDEN-HOSE-50', 39.99, 15, 5),
('LED Desk Lamp', 'LAMP-LED-DESK', 29.99, 20, 5),
('Wireless Mouse', 'MOUSE-WIRELESS', 19.99, 45, 1);

-- Insert sample sales data
INSERT INTO sales (date, total_amount, user_id) VALUES 
(NOW() - INTERVAL 2 HOUR, 159.98, 2),
(NOW() - INTERVAL 1 HOUR, 89.97, 2),
(NOW() - INTERVAL 30 MINUTE, 2049.98, 1);

-- Insert sample sale items
INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES 
(1, 4, 1, 129.99),
(1, 6, 1, 24.99),
(1, 7, 1, 4.99),
(2, 8, 3, 9.99),
(2, 9, 5, 11.99),
(3, 3, 1, 1999.99),
(3, 12, 1, 19.99),
(3, 11, 1, 29.99);

-- Create indexes for better performance
CREATE INDEX idx_products_sku ON products(sku);
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_sales_date ON sales(date);
CREATE INDEX idx_sales_user ON sales(user_id);
CREATE INDEX idx_sale_items_sale ON sale_items(sale_id);
CREATE INDEX idx_sale_items_product ON sale_items(product_id);
