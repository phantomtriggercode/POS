<?php
declare(strict_types=1);

const SCHEMA_VERSION = 1;

/**
 * Table definitions shared by MySQL/MariaDB (production on Hostinger) and SQLite (local testing).
 * Money is stored as whole XAF (BIGINT); weights as kilograms with 2 decimals.
 */
function schema_sql(string $driver): array
{
    $my  = $driver === 'mysql';
    $pk  = $my ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fk  = $my ? 'INT UNSIGNED' : 'INTEGER';
    $end = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $kg  = 'DECIMAL(12,2)';

    return [
        "CREATE TABLE settings (
            skey VARCHAR(64) NOT NULL PRIMARY KEY,
            svalue TEXT
        )$end",

        "CREATE TABLE users (
            id $pk,
            username VARCHAR(50) NOT NULL UNIQUE,
            full_name VARCHAR(120) NOT NULL,
            role VARCHAR(20) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            active TINYINT NOT NULL DEFAULT 1,
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL
        )$end",

        "CREATE TABLE login_attempts (
            id $pk,
            username VARCHAR(50) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            attempted_at DATETIME NOT NULL
        )$end",

        "CREATE TABLE commodities (
            id $pk,
            name VARCHAR(60) NOT NULL,
            code VARCHAR(20) NOT NULL UNIQUE,
            active TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0
        )$end",

        "CREATE TABLE grades (
            id $pk,
            commodity_id $fk NOT NULL,
            name VARCHAR(60) NOT NULL,
            active TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            FOREIGN KEY (commodity_id) REFERENCES commodities(id)
        )$end",

        "CREATE TABLE prices (
            id $pk,
            grade_id $fk NOT NULL,
            price_per_kg BIGINT NOT NULL,
            effective_from DATETIME NOT NULL,
            is_public TINYINT NOT NULL DEFAULT 0,
            note VARCHAR(255) NULL,
            created_by $fk NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (grade_id) REFERENCES grades(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )$end",

        "CREATE TABLE farmers (
            id $pk,
            code VARCHAR(30) NULL UNIQUE,
            full_name VARCHAR(120) NOT NULL,
            phone VARCHAR(40) NULL,
            village VARCHAR(120) NULL,
            id_number VARCHAR(60) NULL,
            farm_location VARCHAR(255) NULL,
            farm_size_ha DECIMAL(10,2) NULL,
            gps_lat DECIMAL(10,7) NULL,
            gps_lng DECIMAL(10,7) NULL,
            notes TEXT NULL,
            active TINYINT NOT NULL DEFAULT 1,
            created_by $fk NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )$end",

        "CREATE TABLE purchases (
            id $pk,
            receipt_no VARCHAR(30) NULL UNIQUE,
            farmer_id $fk NOT NULL,
            grade_id $fk NOT NULL,
            bags INT NOT NULL DEFAULT 0,
            gross_kg $kg NOT NULL,
            tare_kg $kg NOT NULL DEFAULT 0,
            net_kg $kg NOT NULL,
            moisture_pct DECIMAL(5,2) NULL,
            price_per_kg BIGINT NOT NULL,
            gross_amount BIGINT NOT NULL,
            advance_deducted BIGINT NOT NULL DEFAULT 0,
            amount_paid BIGINT NOT NULL,
            payment_method VARCHAR(20) NOT NULL,
            payment_ref VARCHAR(80) NULL,
            notes VARCHAR(500) NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'valid',
            void_reason VARCHAR(255) NULL,
            voided_by $fk NULL,
            voided_at DATETIME NULL,
            created_by $fk NOT NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (farmer_id) REFERENCES farmers(id),
            FOREIGN KEY (grade_id) REFERENCES grades(id),
            FOREIGN KEY (created_by) REFERENCES users(id),
            FOREIGN KEY (voided_by) REFERENCES users(id)
        )$end",

        "CREATE TABLE advances (
            id $pk,
            farmer_id $fk NOT NULL,
            amount BIGINT NOT NULL,
            payment_method VARCHAR(20) NOT NULL,
            note VARCHAR(255) NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'valid',
            void_reason VARCHAR(255) NULL,
            voided_by $fk NULL,
            voided_at DATETIME NULL,
            created_by $fk NOT NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (farmer_id) REFERENCES farmers(id),
            FOREIGN KEY (created_by) REFERENCES users(id),
            FOREIGN KEY (voided_by) REFERENCES users(id)
        )$end",

        "CREATE TABLE dispatches (
            id $pk,
            ref_no VARCHAR(30) NULL UNIQUE,
            grade_id $fk NOT NULL,
            bags INT NOT NULL DEFAULT 0,
            net_kg $kg NOT NULL,
            buyer_name VARCHAR(120) NOT NULL,
            vehicle_no VARCHAR(40) NULL,
            driver_name VARCHAR(120) NULL,
            sale_price_per_kg BIGINT NULL,
            sale_amount BIGINT NULL,
            notes VARCHAR(500) NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'valid',
            void_reason VARCHAR(255) NULL,
            voided_by $fk NULL,
            voided_at DATETIME NULL,
            created_by $fk NOT NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (grade_id) REFERENCES grades(id),
            FOREIGN KEY (created_by) REFERENCES users(id),
            FOREIGN KEY (voided_by) REFERENCES users(id)
        )$end",

        "CREATE TABLE audit_log (
            id $pk,
            user_id $fk NULL,
            action VARCHAR(40) NOT NULL,
            entity VARCHAR(40) NOT NULL,
            entity_id $fk NULL,
            details VARCHAR(1000) NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL
        )$end",

        'CREATE INDEX idx_purchases_created ON purchases (created_at)',
        'CREATE INDEX idx_advances_created ON advances (created_at)',
        'CREATE INDEX idx_dispatches_created ON dispatches (created_at)',
        'CREATE INDEX idx_prices_grade_eff ON prices (grade_id, effective_from)',
        'CREATE INDEX idx_login_attempts ON login_attempts (username, attempted_at)',
        'CREATE INDEX idx_audit_created ON audit_log (created_at)',
        'CREATE INDEX idx_farmers_name ON farmers (full_name)',
    ];
}

/** Creates all tables, seeds commodities/grades and the first admin. Runs inside the installer. */
function install_database(PDO $pdo, array $admin, array $company): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    foreach (schema_sql($driver) as $sql) {
        $pdo->exec($sql);
    }
    $now = date('Y-m-d H:i:s');

    $ins = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
    foreach ([
        'schema_version'   => (string) SCHEMA_VERSION,
        'company_name'     => $company['name'],
        'company_phone'    => $company['phone'],
        'company_location' => $company['location'],
        'currency'         => 'XAF',
        'receipt_footer'   => 'Thank you for selling to us. Keep this receipt.',
    ] as $k => $v) {
        $ins->execute([$k, $v]);
    }

    // Starter grades: rename or disable them under Settings to match your buying practice.
    $seed = [
        ['Cocoa', 'COCOA', ['Grade I', 'Grade II', 'Hors standard']],
        ['Coffee (Robusta)', 'ROBUSTA', ['Grade 1', 'Grade 2', 'Grade 3']],
        ['Coffee (Arabica)', 'ARABICA', ['Grade 1', 'Grade 2', 'Grade 3']],
    ];
    $c = $pdo->prepare('INSERT INTO commodities (name, code, active, sort_order) VALUES (?, ?, 1, ?)');
    $g = $pdo->prepare('INSERT INTO grades (commodity_id, name, active, sort_order) VALUES (?, ?, 1, ?)');
    foreach ($seed as $i => [$name, $code, $grades]) {
        $c->execute([$name, $code, $i]);
        $cid = (int) $pdo->lastInsertId();
        foreach ($grades as $j => $gname) {
            $g->execute([$cid, $gname, $j]);
        }
    }

    $u = $pdo->prepare('INSERT INTO users (username, full_name, role, password_hash, active, created_at) VALUES (?, ?, ?, ?, 1, ?)');
    $u->execute([$admin['username'], $admin['full_name'], 'admin', password_hash($admin['password'], PASSWORD_DEFAULT), $now]);
}
