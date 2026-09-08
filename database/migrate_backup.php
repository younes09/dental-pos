<?php
/**
 * Dental POS - Script d'adaptation & migration de backup
 * 
 * Permet d'adapter n'importe quelle ancienne base ou ancien backup 
 * aux nouvelles fonctionnalités de l'architecture :
 *  1. stock_batches : ajout et valorisation de `purchase_price`
 *  2. sales : ajout et calcul de `paid_at` et `invoice_number`
 *  3. sale_payments : création de la table et reprise des règlements comptoirs
 */

require_once __DIR__ . '/../api/config/db.php';

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Migration Base de Données</title>";
    echo "<style>body{font-family:Segoe UI,sans-serif;padding:30px;background:#f8fafc;color:#1e293b;}";
    echo ".card{background:white;padding:24px;border-radius:12px;box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1);max-width:800px;margin:0 auto;}";
    echo ".log{background:#0f172a;color:#38bdf8;padding:16px;border-radius:8px;font-family:monospace;white-space:pre-wrap;line-height:1.6;}";
    echo ".badge-success{color:#10b981;font-weight:bold;}";
    echo ".badge-info{color:#38bdf8;}";
    echo ".badge-warn{color:#f59e0b;}";
    echo "</style></head><body><div class='card'>";
    echo "<h2>🛠️ Adaptation & Migration Dental POS</h2><div class='log'>";
}

function out($msg) {
    global $isCli;
    if ($isCli) {
        echo $msg . "\n";
    } else {
        echo htmlspecialchars($msg) . "\n";
    }
}

try {
    out("=====================================================");
    out("  DEMARRAGE DE L'ADAPTATION DE LA BASE DE DONNEES    ");
    out("=====================================================\n");

    // 1. Mise à niveau de la table stock_batches (purchase_price)
    out("[ETAPE 1/4] Vérification de 'stock_batches.purchase_price'...");
    $colBatch = $pdo->query("SHOW COLUMNS FROM `stock_batches` LIKE 'purchase_price'")->fetch();
    if (!$colBatch) {
        $pdo->exec("ALTER TABLE `stock_batches` ADD COLUMN `purchase_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER `remaining_qty`");
        out("  -> Colonne 'purchase_price' ajoutée avec succès à 'stock_batches'.");
    } else {
        out("  -> Colonne 'purchase_price' déjà présente dans 'stock_batches'.");
    }

    // Synchronisation du prix d'achat depuis les produits
    $updatedBatches = $pdo->exec("
        UPDATE stock_batches sb 
        JOIN products p ON sb.product_id = p.id 
        SET sb.purchase_price = p.purchase_price
        WHERE sb.purchase_price = 0.00 OR sb.purchase_price IS NULL
    ");
    out("  -> $updatedBatches lots de stock mis à jour avec le prix d'achat de leur produit catalogue.\n");

    // 2. Mise à niveau de la table sales (paid_at et invoice_number)
    out("[ETAPE 2/4] Vérification des colonnes de 'sales'...");
    $colPaidAt = $pdo->query("SHOW COLUMNS FROM `sales` LIKE 'paid_at'")->fetch();
    if (!$colPaidAt) {
        $pdo->exec("ALTER TABLE `sales` ADD COLUMN `paid_at` TIMESTAMP NULL DEFAULT NULL AFTER `payment_status`");
        out("  -> Colonne 'paid_at' ajoutée avec succès à 'sales'.");
    } else {
        out("  -> Colonne 'paid_at' déjà présente dans 'sales'.");
    }

    $colInvoice = $pdo->query("SHOW COLUMNS FROM `sales` LIKE 'invoice_number'")->fetch();
    if (!$colInvoice) {
        $pdo->exec("ALTER TABLE `sales` ADD COLUMN `invoice_number` VARCHAR(50) DEFAULT NULL AFTER `points_redeemed`");
        out("  -> Colonne 'invoice_number' ajoutée avec succès à 'sales'.");
    } else {
        out("  -> Colonne 'invoice_number' déjà présente dans 'sales'.");
    }

    // Backfill paid_at pour les ventes réglées
    $affPaidAt = $pdo->exec("UPDATE `sales` SET `paid_at` = `date` WHERE `payment_status` = 'Paid' AND `paid_at` IS NULL");
    out("  -> 'paid_at' renseigné pour $affPaidAt vente(s) payée(s).");

    // Backfill invoice_number format POS (ex: BL-2026-0001 ou BV-2026-0001)
    $affInvoice = $pdo->exec("
        UPDATE `sales` 
        SET `invoice_number` = CONCAT(IFNULL(`invoice_type`, 'BV'), '-', YEAR(`date`), '-', LPAD(`id`, 4, '0')) 
        WHERE `invoice_number` IS NULL OR `invoice_number` = ''
    ");
    out("  -> Numéro de facture généré pour $affInvoice vente(s).\n");

    // 3. Création de la table sale_payments
    out("[ETAPE 3/4] Vérification de la table 'sale_payments'...");
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'sale_payments'")->fetch();
    if (!$tableCheck) {
        $pdo->exec("
            CREATE TABLE `sale_payments` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `sale_id` INT NOT NULL,
                `customer_payment_id` INT NULL,
                `amount` DECIMAL(10, 2) NOT NULL,
                `payment_method` ENUM('Cash', 'Card', 'Bank Transfer', 'Cheque', 'Insurance') DEFAULT 'Cash',
                `notes` TEXT,
                `user_id` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`customer_payment_id`) REFERENCES `customer_payments`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        out("  -> Table 'sale_payments' créée avec succès.");
    } else {
        out("  -> Table 'sale_payments' déjà présente.");
    }

    // 4. Initialisation des règlements dans sale_payments
    out("\n[ETAPE 4/4] Reprise des paiements existants dans 'sale_payments'...");
    $seededPayments = $pdo->exec("
        INSERT INTO `sale_payments` (`sale_id`, `customer_payment_id`, `amount`, `payment_method`, `notes`, `user_id`, `created_at`)
        SELECT s.`id`, NULL, s.`paid_amount`, s.`payment_method`, 'Paiement initial comptoir', s.`user_id`, s.`date`
        FROM `sales` s
        WHERE s.`paid_amount` > 0 
          AND NOT EXISTS (SELECT 1 FROM `sale_payments` sp WHERE sp.`sale_id` = s.`id`)
    ");
    out("  -> $seededPayments règlement(s) enregistré(s) dans 'sale_payments'.\n");

    out("=====================================================");
    out("  ✅ ADAPTATION TERMINEE AVEC SUCCES !               ");
    out("  Votre base est désormais 100% opérationnelle.     ");
    out("=====================================================");

} catch (Exception $e) {
    out("\n[ERREUR CRITIQUE] " . $e->getMessage());
}

if (!$isCli) {
    echo "</div><p style='margin-top:20px;'><a href='../index.php' style='display:inline-block;padding:10px 20px;background:#2563eb;color:white;text-decoration:none;border-radius:6px;font-weight:600;'>← Retour à l'application</a></p></div></body></html>";
}
