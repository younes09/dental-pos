-- USE THE dental_pos DATABASE
USE dental_pos;

-- 1. Categories (12)
INSERT INTO categories (name) VALUES
('Hygiene & Prevention'),
('Restorative Dentistry'),
('Endodontics'),
('Orthodontics'),
('Periodontics'),
('Oral Surgery'),
('Anesthesia & Sedation'),
('Impression Materials'),
('Instruments'),
('Personal Protective Equipment (PPE)'),
('Dental Lab Supplies'),
('Sterilization Supplies');

-- 2. Brands (10)
INSERT INTO brands (name) VALUES
('3M Oral Care'),
('Dentsply Sirona'),
('GC America'),
('Kerr Dental'),
('Ivoclar Vivadent'),
('Hu-Friedy'),
('Kuraray Noritake'),
('Colgate Oral Pharmaceuticals'),
('Septodont'),
('Philips Sonicare');

-- 3. Users (10 total - 1 already exists via admin@dentalpos.com)
-- Note: All passwords are set to 'admin123' hashed (approx. same as default)
INSERT INTO users (name, email, password, role) VALUES
('Dr. Sarah Ahmed', 'sarah@dentalpos.com', '$2a$12$Zka66PNoO.Ryd5K.993dtuZBiZw7IJr3Fs1Q3UdyW78umKIrdF/2q', 'Admin'),
('John Smith', 'john@dentalpos.com', '$2a$12$Zka66PNoO.Ryd5K.993dtuZBiZw7IJr3Fs1Q3UdyW78umKIrdF/2q', 'Cashier'),
('Mary Johnson', 'mary@dentalpos.com', '$2a$12$Zka66PNoO.Ryd5K.993dtuZBiZw7IJr3Fs1Q3UdyW78umKIrdF/2q', 'Stock Manager'),
('David Wilson', 'david@dentalpos.com', '$2a$12$Zka66PNoO.Ryd5K.993dtuZBiZw7IJr3Fs1Q3UdyW78umKIrdF/2q', 'Cashier'),
('Emily Brown', 'emily@dentalpos.com', '$2a$12$Zka66PNoO.Ryd5K.993dtuZBiZw7IJr3Fs1Q3UdyW78umKIrdF/2q', 'Cashier'),
('Michael Davis', 'michael@dentalpos.com', '$2a$12$Zka66PNoO.Ryd5K.993dtuZBiZw7IJr3Fs1Q3UdyW78umKIrdF/2q', 'Stock Manager'),
('Jessica Miller', 'jessica@dentalpos.com', '$2a$12$Zka66PNoO.Ryd5K.993dtuZBiZw7IJr3Fs1Q3UdyW78umKIrdF/2q', 'Cashier'),
('Chris Taylor', 'chris@dentalpos.com', '$2a$12$Zka66PNoO.Ryd5K.993dtuZBiZw7IJr3Fs1Q3UdyW78umKIrdF/2q', 'Cashier'),
('Amanda White', 'amanda@dentalpos.com', '$2a$12$Zka66PNoO.Ryd5K.993dtuZBiZw7IJr3Fs1Q3UdyW78umKIrdF/2q', 'Admin');

-- 4. Products (20)
INSERT INTO products (name, category_id, brand_id, barcode, purchase_price, selling_price, stock_qty, min_stock, expiry_date, status) VALUES
('Filtek Universal Restorative (4g)', 2, 1, '710001', 35.50, 65.00, 50, 10, '2027-12-31', 'Active'),
('Cavex CA37 Impression Alginate', 8, 3, '710002', 12.00, 25.00, 30, 5, '2026-06-30', 'Active'),
('K-Files Assorted 25mm (Pk 6)', 3, 4, '710003', 18.00, 42.00, 100, 20, '2028-01-01', 'Active'),
('Septocaine 4% with Epinephrine', 7, 9, '710004', 45.00, 85.00, 24, 6, '2025-08-15', 'Active'),
('Hygienist Prophy Paste (Medium)', 1, 8, '710005', 15.00, 35.00, 15, 5, '2026-11-20', 'Active'),
('Nitrile Exam Gloves (Medium, Box 100)', 10, 6, '710006', 8.50, 18.00, 200, 40, '2029-01-01', 'Active'),
('Clearfil Majesty ES-2 Composite', 2, 7, '710007', 42.00, 89.00, 40, 8, '2027-05-10', 'Active'),
('AH Plus Resin Sealer', 3, 2, '710008', 65.00, 125.00, 12, 3, '2026-03-30', 'Active'),
('Self-Seal Sterilization Pouches (2.2x13)', 12, 5, '710009', 14.50, 28.00, 500, 100, '2030-01-01', 'Active'),
('Explorer Hu-Friedy #5', 9, 6, '710010', 22.00, 45.00, 25, 5, NULL, 'Active'),
('Mirror Head #4 Front Surface', 9, 6, '710011', 4.50, 12.00, 60, 10, NULL, 'Active'),
('Sonicare DiamondClean Brush Heads', 1, 10, '710012', 12.00, 24.00, 48, 12, NULL, 'Active'),
('Dycal Calcium Hydroxide', 2, 2, '710013', 28.00, 55.00, 20, 5, '2026-12-31', 'Active'),
('ProTaper Gold NiTi Files F1', 3, 2, '710014', 55.00, 95.00, 30, 10, '2028-06-01', 'Active'),
('Transbond XT Orthodontic Adhesive', 4, 1, '710015', 75.00, 145.00, 15, 5, '2026-04-15', 'Active'),
('Oraqix Non-Injectable Anesthetic', 7, 9, '710016', 95.00, 185.00, 8, 2, '2025-12-01', 'Active'),
('G-Premio Bond (5ml)', 2, 3, '710017', 48.00, 98.00, 12, 3, '2027-09-20', 'Active'),
('Alginate Mixer Cups (Small)', 8, 3, '710018', 2.50, 7.00, 100, 20, NULL, 'Active'),
('Lidocaine 2% Red', 7, 9, '710019', 38.00, 72.00, 20, 5, '2026-01-15', 'Active'),
('Disposable Bibs (Blue, Box 500)', 10, 5, '710020', 18.00, 35.00, 80, 15, NULL, 'Active');

-- 5. Customers (12)
INSERT INTO customers (name, phone, email, balance, loyalty_points) VALUES
('Abigail Thompson', '555-0101', 'abigail@example.com', 45.00, 120),
('Benjamin Carter', '555-0102', 'ben@example.com', 0.00, 450),
('Charlotte Green', '555-0103', 'charlotte@example.com', 250.00, 80),
('Daniel White', '555-0104', 'daniel@example.com', 0.00, 15),
('Elizabeth Baker', '555-0105', 'liz@example.com', 12.50, 300),
('Franklin Harris', '555-0106', 'frank@example.com', 500.00, 0),
('Grace Martin', '555-0107', 'grace@example.com', 0.00, 55),
('Henry Adams', '555-0108', 'henry@example.com', 0.00, 920),
('Isabella Reed', '555-0109', 'bella@example.com', 15.00, 40),
('James Cooper', '555-0110', 'james@example.com', 0.00, 210),
('Katherine Hill', '555-0111', 'kath@example.com', 0.00, 8),
('Liam Ward', '555-0112', 'liam@example.com', 75.00, 125);

-- 6. Suppliers (10)
INSERT INTO suppliers (name, company, phone, email) VALUES
('Sam Dental Supplies', 'Sam Medical Corp', '888-001', 'orders@samdent.com'),
('Clinical Direct', 'ClinicDirect Global', '888-002', 'sales@clinicaldirect.com'),
('Oral Excellence', 'OE Distribution', '888-003', 'support@oralex.com'),
('Dental Depot', 'DD Supply Chain', '888-004', 'info@dentaldepot.com'),
('Surgi-Care Inc', 'Surgicare', '888-005', 'logistics@surgicare.com'),
('Global Pharma', 'GP Dental', '888-006', 'orders@globalpharma.com'),
('Premium Instruments', 'PI Manufacturing', '888-007', 'contact@premiuminstr.com'),
('Elite Bio-Care', 'EliteBio', '888-008', 'elite@biocare.com'),
('Standard Dental', 'Standard Supply', '888-009', 'supply@standard.com'),
('Universal Med', 'UnivMed Solutions', '888-010', 'sales@univmed.com');

-- 7. Purchase Orders (10)
INSERT INTO purchase_orders (supplier_id, date, status, total) VALUES
(1, '2026-03-01', 'Received', 1450.00),
(2, '2026-03-02', 'Received', 890.00),
(3, '2026-03-05', 'Pending', 2100.50),
(4, '2026-03-10', 'Received', 450.00),
(5, '2026-03-12', 'Partial', 1200.00),
(6, '2026-03-15', 'Pending', 350.00),
(7, '2026-03-18', 'Received', 675.00),
(8, '2026-03-20', 'Pending', 110.00),
(9, '2026-03-22', 'Received', 940.00),
(10, '2026-03-25', 'Received', 500.00);

-- 8. Purchase Order Items (at least 1 per PO)
INSERT INTO purchase_order_items (po_id, product_id, qty, unit_cost) VALUES
(1, 1, 20, 35.50),
(1, 2, 10, 12.00),
(2, 4, 12, 45.00),
(3, 5, 5, 15.00),
(3, 6, 100, 8.50),
(4, 10, 5, 22.00),
(5, 14, 10, 55.00),
(6, 15, 2, 75.00),
(7, 19, 10, 38.00),
(8, 20, 5, 18.00),
(9, 7, 10, 42.00),
(10, 13, 5, 28.00);

-- 9. Sales (15)
INSERT INTO sales (customer_id, user_id, date, subtotal, discount, tax, total, payment_method, status) VALUES
(1, 2, '2026-03-26 09:15:00', 130.00, 0, 19.50, 149.50, 'Cash', 'Completed'),
(2, 2, '2026-03-26 10:20:00', 65.00, 5.00, 9.00, 69.00, 'Card', 'Completed'),
(3, 4, '2026-03-26 11:45:00', 25.00, 0, 3.75, 28.75, 'Cash', 'Completed'),
(null, 4, '2026-03-26 13:10:00', 42.00, 0, 6.30, 48.30, 'Cash', 'Completed'),
(4, 5, '2026-03-26 14:30:00', 85.00, 0, 12.75, 97.75, 'Insurance', 'Completed'),
(5, 5, '2026-03-27 09:00:00', 18.00, 0, 2.70, 20.70, 'Cash', 'Completed'),
(6, 7, '2026-03-27 10:15:00', 89.00, 10.00, 11.85, 90.85, 'Card', 'Completed'),
(7, 7, '2026-03-27 11:30:00', 125.00, 0, 18.75, 143.75, 'Cash', 'Completed'),
(8, 8, '2026-03-27 14:00:00', 28.00, 0, 4.20, 32.20, 'Card', 'Completed'),
(null, 8, '2026-03-27 15:20:00', 45.00, 0, 6.75, 51.75, 'Cash', 'Completed'),
(9, 2, '2026-03-28 09:45:00', 12.00, 0, 1.80, 13.80, 'Cash', 'Completed'),
(10, 2, '2026-03-28 10:10:00', 24.00, 2.00, 3.30, 25.30, 'Card', 'Completed'),
(11, 4, '2026-03-28 11:20:00', 55.00, 0, 8.25, 63.25, 'Cash', 'Completed'),
(12, 4, '2026-03-28 12:40:00', 95.00, 0, 14.25, 109.25, 'Insurance', 'Completed'),
(1, 5, '2026-03-28 14:50:00', 145.00, 15.00, 19.50, 149.50, 'Card', 'Completed');

-- 10. Sale Items (at least 1 per sale)
INSERT INTO sale_items (sale_id, product_id, qty, unit_price, total) VALUES
(1, 1, 2, 65.00, 130.00),
(2, 1, 1, 65.00, 65.00),
(3, 2, 1, 25.00, 25.00),
(4, 3, 1, 42.00, 42.00),
(5, 4, 1, 85.00, 85.00),
(6, 6, 1, 18.00, 18.00),
(7, 7, 1, 89.00, 89.00),
(8, 8, 1, 125.00, 125.00),
(9, 9, 1, 28.00, 28.00),
(10, 10, 1, 45.00, 45.00),
(11, 11, 1, 12.00, 12.00),
(12, 12, 1, 24.00, 24.00),
(13, 13, 1, 55.00, 55.00),
(14, 14, 1, 95.00, 95.00),
(15, 15, 1, 145.00, 145.00);

-- 11. Stock Adjustments (10)
INSERT INTO stock_adjustments (product_id, type, qty, reason, user_id) VALUES
(1, 'Add Stock', 10, 'Inventory Reconciliation', 3),
(2, 'Write-off', 2, 'Expired Product', 3),
(3, 'Add Stock', 50, 'Monthly replenishment', 6),
(4, 'Return Stock', 2, 'Manufacturer Recall', 6),
(5, 'Write-off', 1, 'Damaged during storage', 3),
(6, 'Add Stock', 100, 'Bulk purchase', 3),
(7, 'Add Stock', 10, 'Initial setup', 6),
(8, 'Write-off', 1, 'Broken seal', 3),
(9, 'Add Stock', 50, 'Regular reorder', 6),
(10, 'Add Stock', 5, 'Inventory adjustment', 3);
