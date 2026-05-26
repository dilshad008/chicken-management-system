-- MySQL dump for Chicken Management System with Sellers, Inventory, and Updated Invoices

CREATE DATABASE IF NOT EXISTS chicken_management;
USE chicken_management;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255),
    email VARCHAR(100),
    role ENUM('admin', 'staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Chicken Types Table
CREATE TABLE IF NOT EXISTS chicken_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Daily Rates Table
CREATE TABLE IF NOT EXISTS daily_rates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chicken_type_id INT NOT NULL,
    rate DECIMAL(10, 2) NOT NULL,
    rate_date DATE NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chicken_type_id) REFERENCES chicken_types(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE KEY unique_rate_date (chicken_type_id, rate_date)
);

-- Sellers Table (NEW)
CREATE TABLE IF NOT EXISTS sellers (
    seller_id INT PRIMARY KEY AUTO_INCREMENT,
    seller_name VARCHAR(100) NOT NULL,
    seller_phone VARCHAR(20) NOT NULL,
    seller_email VARCHAR(100),
    seller_address TEXT,
    seller_city VARCHAR(50),
    seller_type ENUM('Individual', 'Company', 'Wholesaler', 'Retailer') DEFAULT 'Individual',
    seller_status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Purchasers Table
CREATE TABLE IF NOT EXISTS purchasers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    city VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inventory Table (NEW)
CREATE TABLE IF NOT EXISTS inventory (
    inventory_id INT PRIMARY KEY AUTO_INCREMENT,
    chicken_type_id INT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    warehouse_location VARCHAR(100),
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (chicken_type_id) REFERENCES chicken_types(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Updated Invoices Table
CREATE TABLE IF NOT EXISTS invoices (
    invoice_id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(20) UNIQUE NOT NULL,
    invoice_type ENUM('purchase', 'sale') DEFAULT 'sale',
    seller_id INT,
    purchaser_id INT,
    invoice_date DATE NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES sellers(seller_id),
    FOREIGN KEY (purchaser_id) REFERENCES purchasers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Invoice Items Table
CREATE TABLE IF NOT EXISTS invoice_items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    chicken_type_id INT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    rate DECIMAL(10, 2) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE,
    FOREIGN KEY (chicken_type_id) REFERENCES chicken_types(id)
);

-- Sample Data

-- Insert Users
DELETE FROM users;
INSERT INTO users (username, password, email, role) VALUES 
('admin', '', 'admin@chickenmgmt.local', 'admin'),
('staff', '', 'staff@chickenmgmt.local', 'staff');

-- Insert Chicken Types
DELETE FROM chicken_types;
INSERT INTO chicken_types (name, description) VALUES 
('Broiler', 'Meat chicken - Heavy breed, fast growing'),
('Layer', 'Egg-laying chicken - High egg production'),
('Desi', 'Country chicken - High quality meat, slower growth');

-- Insert Daily Rates
DELETE FROM daily_rates;
INSERT INTO daily_rates (chicken_type_id, rate, rate_date, created_by) VALUES 
(1, 150.00, CURDATE(), 1),
(2, 120.00, CURDATE(), 1),
(3, 200.00, CURDATE(), 1);

-- Insert Sellers
DELETE FROM sellers;
INSERT INTO sellers (seller_name, seller_phone, seller_email, seller_address, seller_city, seller_type, seller_status, created_by) VALUES 
('Ahmad Poultry Farm', '03001234567', 'ahmad@poultry.com', '123 Farm Road', 'Karachi', 'Company', 'active', 1),
('Hassan Chicken Suppliers', '03009876543', 'hassan@suppliers.com', '456 Market Street', 'Lahore', 'Wholesaler', 'active', 1),
('Rehman Farms', '03005555555', 'rehman@farms.com', '789 Agriculture Lane', 'Islamabad', 'Company', 'active', 1),
('Individual Seller Ali', '03003333333', 'ali@seller.com', '321 Local Street', 'Multan', 'Individual', 'active', 1);

-- Insert Purchasers
DELETE FROM purchasers;
INSERT INTO purchasers (name, phone, email, address, city) VALUES 
('Ahmed Traders', '03001234567', 'ahmed@traders.com', '123 Main Street', 'Karachi'),
('Chicken Hub', '03009876543', 'info@chickenhub.com', '456 Market Road', 'Lahore'),
('Fresh Poultry', '03005555555', 'sales@freshpoultry.com', '789 Trade Center', 'Islamabad');

-- Insert Inventory
DELETE FROM inventory;
INSERT INTO inventory (chicken_type_id, quantity, unit_price, warehouse_location, notes, created_by) VALUES 
(1, 500.00, 150.00, 'Warehouse A - Section 1', 'Fresh stock received today', 1),
(2, 300.00, 120.00, 'Warehouse B - Section 2', 'Layer birds in good condition', 1),
(3, 200.00, 200.00, 'Warehouse A - Section 3', 'Premium Desi chicken stock', 1),
(1, 150.00, 145.00, 'Warehouse C - Section 1', 'Slightly aged birds', 1);

-- Create Indexes for better performance
CREATE INDEX idx_rate_date ON daily_rates(rate_date);
CREATE INDEX idx_invoice_date ON invoices(invoice_date);
CREATE INDEX idx_seller_status ON sellers(seller_status);
CREATE INDEX idx_inventory_chicken ON inventory(chicken_type_id);
