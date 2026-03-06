-- Migration script for adding Supplier Debt capabilities
-- Run this on existing databases to update the schema

USE dental_pos;

-- 1. Add balance to suppliers
ALTER TABLE suppliers ADD COLUMN balance DECIMAL(10, 2) DEFAULT 0.00 AFTER email;

-- 2. Add paid_amount and payment_status to purchase_orders
ALTER TABLE purchase_orders ADD COLUMN paid_amount DECIMAL(10, 2) DEFAULT 0.00 AFTER total;
ALTER TABLE purchase_orders ADD COLUMN payment_status ENUM('Unpaid', 'Partial', 'Paid') DEFAULT 'Unpaid' AFTER paid_amount;

-- Update existing POs to pretend they were fully paid (to prevent existing history from suddenly becoming massive "debt")
-- Since previous functionality assumed full payment when received.
UPDATE purchase_orders 
SET paid_amount = total, payment_status = 'Paid' 
WHERE status = 'Received';

-- 3. Create supplier_payments table to track individual debt settlements
CREATE TABLE IF NOT EXISTS supplier_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('Cash', 'Bank Transfer', 'Cheque') DEFAULT 'Cash',
    notes TEXT,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id INT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
