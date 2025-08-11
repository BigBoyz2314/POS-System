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
