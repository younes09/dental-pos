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

-- 3. Users (Already contains Admin, adding 9 more)
INSERT INTO users (name, email, phone, password, role, status) VALUES
('Sarah Jones', 'sarah@dentalpos.com', '555-0101', '$2y$10$YndX.m18KUnRCOFh2p29.eI8k/YvWp1b9V3Q4G.5Fm/L8f3q6vBv2', 'Admin', 'Active'),
('Younes Sales', 'younes@dentalpos.com', '555-0102', '$2y$10$YndX.m18KUnRCOFh2p29.eI8k/YvWp1b9V3Q4G.5Fm/L8f3q6vBv2', 'Cashier', 'Active'),
('Mark Warehouse', 'mark@dentalpos.com', '555-0103', '$2y$10$YndX.m18KUnRCOFh2p29.eI8k/YvWp1b9V3Q4G.5Fm/L8f3q6vBv2', 'Stock Manager', 'Active'),
('David Roberts', 'david@dentalpos.com', '555-0104', '$2y$10$YndX.m18KUnRCOFh2p29.eI8k/YvWp1b9V3Q4G.5Fm/L8f3q6vBv2', 'Cashier', 'Active'),
('Emily Clerk', 'emily@dentalpos.com', '555-0105', '$2y$10$YndX.m18KUnRCOFh2p29.eI8k/YvWp1b9V3Q4G.5Fm/L8f3q6vBv2', 'Cashier', 'Active'),
('Michael Logistics', 'michael@dentalpos.com', '555-0106', '$2y$10$YndX.m18KUnRCOFh2p29.eI8k/YvWp1b9V3Q4G.5Fm/L8f3q6vBv2', 'Stock Manager', 'Active'),
('Jessica Sales', 'jessica@dentalpos.com', '555-0107', '$2y$10$YndX.m18KUnRCOFh2p29.eI8k/YvWp1b9V3Q4G.5Fm/L8f3q6vBv2', 'Cashier', 'Active'),
('Chris Store', 'chris@dentalpos.com', '555-0108', '$2y$10$YndX.m18KUnRCOFh2p29.eI8k/YvWp1b9V3Q4G.5Fm/L8f3q6vBv2', 'Cashier', 'Active'),
('Amanda Admin', 'amanda@dentalpos.com', '555-0109', '$2y$10$YndX.m18KUnRCOFh2p29.eI8k/YvWp1b9V3Q4G.5Fm/L8f3q6vBv2', 'Admin', 'Active');

-- 4. Products (20)
INSERT INTO products (name, category_id, brand_id, barcode, purchase_price, selling_price, stock_qty, min_stock, expiry_date, image, status) VALUES
('Filtek Universal Restorative (4g)', 2, 1, '710001', 35.50, 65.00, 50, 10, '2027-12-31', '1.jpg', 'Active'),
('Cavex CA37 Impression Alginate', 8, 3, '710002', 12.00, 25.00, 30, 5, '2026-06-30', '2.jpg', 'Active'),
('K-Files Assorted 25mm (Pk 6)', 3, 4, '710003', 18.00, 42.00, 100, 20, '2028-01-01', '3.jpg', 'Active'),
('Septocaine 4% with Epinephrine', 7, 9, '710004', 45.00, 85.00, 24, 6, '2025-08-15', '4.jpg', 'Active'),
('Hygienist Prophy Paste (Medium)', 1, 8, '710005', 15.00, 35.00, 15, 5, '2026-11-20', '5.jpg', 'Active'),
('Nitrile Exam Gloves (Medium, Box 100)', 10, 6, '710006', 8.50, 18.00, 200, 40, '2029-01-01', '6.jpg', 'Active'),
('Clearfil Majesty ES-2 Composite', 2, 7, '710007', 42.00, 89.00, 40, 8, '2027-05-10', '7.jpg', 'Active'),
('AH Plus Resin Sealer', 3, 2, '710008', 65.00, 125.00, 12, 3, '2026-03-30', '8.jpg', 'Active'),
('Self-Seal Sterilization Pouches (2.2x13)', 12, 5, '710009', 14.50, 28.00, 500, 100, '2030-01-01', '9.jpg', 'Active'),
('Explorer Hu-Friedy #5', 9, 6, '710010', 22.00, 45.00, 25, 5, NULL, '10.jpg', 'Active'),
('Mirror Head #4 Front Surface', 9, 6, '710011', 4.50, 12.00, 60, 10, NULL, '11.jpg', 'Active'),
('Sonicare DiamondClean Brush Heads', 1, 10, '710012', 12.00, 24.00, 48, 12, NULL, '12.jpg', 'Active'),
('Dycal Calcium Hydroxide', 2, 2, '710013', 28.00, 55.00, 20, 5, '2026-12-31', '13.jpg', 'Active'),
('ProTaper Gold NiTi Files F1', 3, 2, '710014', 55.00, 95.00, 30, 10, '2028-06-01', '14.jpg', 'Active'),
('Transbond XT Orthodontic Adhesive', 4, 1, '710015', 75.00, 145.00, 15, 5, '2026-04-15', '15.jpg', 'Active'),
('Oraqix Non-Injectable Anesthetic', 7, 9, '710016', 95.00, 185.00, 8, 2, '2025-12-01', '16.jpg', 'Active'),
('G-Premio Bond (5ml)', 2, 3, '710017', 48.00, 98.00, 12, 3, '2027-09-20', '17.jpg', 'Active'),
('Alginate Mixer Cups (Small)', 8, 3, '710018', 2.50, 7.00, 100, 20, NULL, '18.jpg', 'Active'),
('Lidocaine 2% Red', 7, 9, '710019', 38.00, 72.00, 20, 5, '2026-01-15', '19.jpg', 'Active'),
('Disposable Bibs (Blue, Box 500)', 10, 5, '710020', 18.00, 35.00, 80, 15, NULL, '20.jpg', 'Active');

-- 5. Customers (12)
INSERT INTO customers (name, phone, email, balance, loyalty_points) VALUES
('Dr. Abigail Thompson', '555-0101', 'abigail@clinic.com', 45.00, 120),
('Elite Dental Care', '555-0102', 'contact@elitedental.com', 0.00, 450),
('Dr. Charlotte Green', '555-0103', 'charlotte@dentist.com', 250.00, 80),
('White Smile Center', '555-0104', 'info@whitesmile.com', 0.00, 15),
('City Oral Surgery', '555-0105', 'admin@cityoral.com', 12.50, 300),
('Dr. Franklin Harris', '555-0106', 'frank@dentist.com', 500.00, 0),
('Modern Orthodontics', '555-0107', 'office@modernortho.com', 0.00, 55),
('Dr. Henry Adams', '555-0108', 'henry@dentalcare.com', 0.00, 920),
('Premium Implant Center', '555-0109', 'info@premiumimplants.com', 15.00, 40),
('Dr. James Cooper', '555-0110', 'james@dentist.com', 0.00, 210),
('Family Dental Group', '555-0111', 'hello@familydental.com', 0.00, 8),
('Dr. Liam Ward', '555-0112', 'liam@ward-dental.com', 75.00, 125);

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

-- 7. Vault Accounts
INSERT INTO vault_accounts (name, type, balance, is_default) VALUES
('Principal Safe', 'Safe', 150000.00, TRUE),
('Cash Drawer 1', 'Cash', 5000.00, FALSE),
('Bank Account (BOA)', 'Bank', 450000.00, FALSE);

-- 8. Staff
INSERT INTO staff (name, position, phone, email, base_salary, status, hiring_date) VALUES
('Dr. Amine Rahmani', 'Senior Dentist', '0550112233', 'amine@dental.com', 120000.00, 'Active', '2023-01-15'),
('Nour El Houda', 'Dental Assistant', '0550445566', 'nour@dental.com', 45000.00, 'Active', '2024-02-01'),
('Sofiane Merah', 'Receptionist', '0550778899', 'sofiane@dental.com', 35000.00, 'Active', '2024-05-10'),
('Dr. Leila Benali', 'Orthodontist', '0550123123', 'leila@dental.com', 95000.00, 'Active', '2023-11-20');

-- 9. Equipment
INSERT INTO equipment (name, purchase_price, condition_status, quantity) VALUES
('Dental Chair A1', 450000.00, 'Excellent', 2),
('Digital X-Ray Scanner', 280000.00, 'New', 1),
('Autoclave Sterilizer', 85000.00, 'Good', 2),
('Curing Light LED', 12000.00, 'Excellent', 5);

-- 10. Cash Sessions
INSERT INTO cash_sessions (user_id, opening_balance, expected_balance, status) VALUES
(1, 5000.00, 5000.00, 'Open');

-- 11. Stock Batches
INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty, expiry_date) VALUES
(1, 'BA', 50, 50, '2027-12-31'),
(2, 'BA', 30, 30, '2026-06-30'),
(3, 'BA', 100, 100, '2028-01-01'),
(4, 'BA', 24, 24, '2025-08-15'),
(5, 'BA', 15, 15, '2026-11-20'),
(6, 'BA', 200, 200, '2029-01-01'),
(7, 'BA', 40, 40, '2027-05-10'),
(8, 'BA', 12, 12, '2026-03-30'),
(9, 'BA', 500, 500, '2030-01-01'),
(10, 'BA', 25, 25, NULL);

-- 12. Purchase Orders
INSERT INTO purchase_orders (supplier_id, date, status, total, paid_amount, payment_status) VALUES
(1, '2026-03-01', 'Received', 1450.00, 1450.00, 'Paid'),
(2, '2026-03-02', 'Received', 890.00, 0.00, 'Unpaid'),
(3, '2026-03-05', 'Pending', 2100.50, 0.00, 'Unpaid');

-- 13. Purchase Order Items
INSERT INTO purchase_order_items (po_id, product_id, qty, received_qty, unit_cost, old_unit_cost) VALUES
(1, 1, 20, 20, 35.50, 35.50),
(1, 2, 10, 10, 12.00, 12.00),
(2, 4, 12, 12, 45.00, 45.00);

-- 14. Sales
INSERT INTO sales (customer_id, user_id, cash_session_id, subtotal, discount, tax, total, paid_amount, payment_status, payment_method, points_earned) VALUES
(1, 1, 1, 130.00, 0, 0, 130.00, 130.00, 'Paid', 'Cash', 13),
(2, 1, 1, 65.00, 5.00, 0, 60.00, 60.00, 'Paid', 'Cash', 6);

-- 15. Sale Items (including cost_price)
INSERT INTO sale_items (sale_id, product_id, qty, unit_price, cost_price, total) VALUES
(1, 1, 2, 65.00, 35.50, 130.00),
(2, 1, 1, 65.00, 35.50, 65.00);

-- 16. Salary Payments
INSERT INTO salary_payments (staff_id, vault_account_id, amount, payment_date, period_month, period_year, payment_method, user_id) VALUES
(1, 3, 120000.00, '2026-02-28', 2, 2026, 'Bank Transfer', 1),
(2, 2, 45000.00, '2026-02-28', 2, 2026, 'Cash', 1);

-- 17. Vault Transactions
INSERT INTO vault_transactions (account_id, type, amount, description, user_id) VALUES
(3, 'Expense', 120000.00, 'Salaire Février - Dr. Amine', 1),
(2, 'Expense', 45000.00, 'Salaire Février - Nour El Houda', 1),
(2, 'Income', 130.00, 'Vente #1', 1),
(2, 'Income', 60.00, 'Vente #2', 1);
