-- =============================================================
-- DentalPOS Audit Fixes Migration
-- Run this ONCE against an existing dental_pos database.
-- =============================================================
USE dental_pos;

-- F1.2: Snapshot cost price at time of sale for accurate COGS
ALTER TABLE sale_items
    ADD COLUMN cost_price DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER unit_price;

-- F2.4: Track loyalty points earned/redeemed per sale for correct reversal
ALTER TABLE sales
    ADD COLUMN points_earned INT NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN points_redeemed INT NOT NULL DEFAULT 0 AFTER points_earned;

-- F2.1: Prevent negative stock at the database level
ALTER TABLE products
    ADD CONSTRAINT chk_stock_nonneg CHECK (stock_qty >= 0);

-- F5.4: Prevent over-receiving on PO items
ALTER TABLE purchase_order_items
    ADD CONSTRAINT chk_received_lte_qty CHECK (received_qty <= qty);
