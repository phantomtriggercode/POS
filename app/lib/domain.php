<?php
declare(strict_types=1);

/**
 * Business rules for the buying operation. Pages call these functions; they validate input,
 * enforce balances/stock inside a transaction, write the audit log, and throw
 * DomainException with a user-facing message when something is not allowed.
 *
 * Nothing is ever deleted: purchases, advances and dispatches are voided with a reason.
 */

/* ---------- Commodities, grades, prices ---------- */

function commodities(bool $activeOnly = true): array
{
    return q_all('SELECT * FROM commodities' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY sort_order, name');
}

/** Grades joined with their commodity, ordered for dropdowns. */
function grades(bool $activeOnly = true): array
{
    $where = $activeOnly ? ' WHERE g.active = 1 AND c.active = 1' : '';
    return q_all(
        'SELECT g.*, c.name AS commodity_name, c.code AS commodity_code
         FROM grades g JOIN commodities c ON c.id = g.commodity_id' . $where . '
         ORDER BY c.sort_order, c.name, g.sort_order, g.name'
    );
}

function grade(int $id): ?array
{
    return q_one(
        'SELECT g.*, c.name AS commodity_name, c.active AS commodity_active
         FROM grades g JOIN commodities c ON c.id = g.commodity_id WHERE g.id = ?',
        [$id]
    );
}

function grade_label(array $row): string
{
    return $row['commodity_name'] . ' — ' . ($row['grade_name'] ?? $row['name']);
}

function current_price(int $gradeId): ?array
{
    return q_one(
        'SELECT * FROM prices WHERE grade_id = ? AND effective_from <= ? ORDER BY effective_from DESC, id DESC LIMIT 1',
        [$gradeId, now()]
    );
}

/** One row per active grade with its current price (or null). */
function current_prices(): array
{
    $out = [];
    foreach (grades() as $g) {
        $g['price'] = current_price((int) $g['id']);
        $out[] = $g;
    }
    return $out;
}

function set_price(int $gradeId, int $pricePerKg, string $effectiveFrom, bool $public, string $note): int
{
    $g = grade($gradeId);
    if (!$g) {
        throw new DomainException('Choose a produce grade.');
    }
    if ($pricePerKg <= 0 || $pricePerKg > 100000) {
        throw new DomainException('Enter a price per kg between 1 and 100 000.');
    }
    $id = insert('prices', [
        'grade_id'       => $gradeId,
        'price_per_kg'   => $pricePerKg,
        'effective_from' => $effectiveFrom,
        'is_public'      => $public ? 1 : 0,
        'note'           => $note !== '' ? $note : null,
        'created_by'     => current_user()['id'],
        'created_at'     => now(),
    ]);
    audit('price_set', 'price', $id, grade_label($g) . ': ' . $pricePerKg . '/kg from ' . $effectiveFrom . ($public ? ' (public)' : ''));
    return $id;
}

/* ---------- Farmers ---------- */

function farmer(int $id): ?array
{
    return q_one('SELECT * FROM farmers WHERE id = ?', [$id]);
}

/** Outstanding advance owed by a farmer (advances given minus amounts recovered from purchases). */
function farmer_balance(int $farmerId): int
{
    $given = (int) q_val("SELECT COALESCE(SUM(amount), 0) FROM advances WHERE farmer_id = ? AND status = 'valid'", [$farmerId]);
    $recovered = (int) q_val("SELECT COALESCE(SUM(advance_deducted), 0) FROM purchases WHERE farmer_id = ? AND status = 'valid'", [$farmerId]);
    return $given - $recovered;
}

function total_outstanding_advances(): int
{
    $given = (int) q_val("SELECT COALESCE(SUM(amount), 0) FROM advances WHERE status = 'valid'");
    $recovered = (int) q_val("SELECT COALESCE(SUM(advance_deducted), 0) FROM purchases WHERE status = 'valid'");
    return $given - $recovered;
}

function farmer_input(array $in): array
{
    $data = [
        'code'          => strtoupper(in_str($in, 'code', 30)),
        'full_name'     => in_str($in, 'full_name', 120),
        'phone'         => in_str($in, 'phone', 40),
        'village'       => in_str($in, 'village', 120),
        'id_number'     => in_str($in, 'id_number', 60),
        'farm_location' => in_str($in, 'farm_location', 255),
        'farm_size_ha'  => in_num($in, 'farm_size_ha'),
        'gps_lat'       => in_num($in, 'gps_lat'),
        'gps_lng'       => in_num($in, 'gps_lng'),
        'notes'         => in_text($in, 'notes', 2000),
    ];
    if (mb_strlen($data['full_name']) < 2) {
        throw new DomainException('Enter the farmer’s full name.');
    }
    if ($data['code'] !== '' && !preg_match('/^[A-Z0-9\-\/]+$/', $data['code'])) {
        throw new DomainException('Farmer code may only contain letters, numbers, - and /.');
    }
    if ($data['farm_size_ha'] !== null && ($data['farm_size_ha'] < 0 || $data['farm_size_ha'] > 10000)) {
        throw new DomainException('Farm size looks wrong.');
    }
    if (($data['gps_lat'] === null) !== ($data['gps_lng'] === null)) {
        throw new DomainException('Enter both GPS latitude and longitude, or neither.');
    }
    if ($data['gps_lat'] !== null && (abs($data['gps_lat']) > 90 || abs($data['gps_lng']) > 180)) {
        throw new DomainException('GPS coordinates are out of range.');
    }
    foreach (['phone', 'village', 'id_number', 'farm_location', 'notes', 'code'] as $k) {
        if ($data[$k] === '') {
            $data[$k] = null;
        }
    }
    return $data;
}

function farmer_code_taken(string $code, int $exceptId = 0): bool
{
    return (bool) q_val('SELECT COUNT(*) FROM farmers WHERE code = ? AND id <> ?', [$code, $exceptId]);
}

function create_farmer(array $in): int
{
    $data = farmer_input($in);
    if ($data['code'] !== null && farmer_code_taken($data['code'])) {
        throw new DomainException('That farmer code is already used.');
    }
    return transaction(function () use ($data) {
        $id = insert('farmers', $data + [
            'active'     => 1,
            'created_by' => current_user()['id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($data['code'] === null) {
            update('farmers', $id, ['code' => 'F' . str_pad((string) $id, 5, '0', STR_PAD_LEFT)]);
        }
        audit('create', 'farmer', $id, $data['full_name']);
        return $id;
    });
}

function update_farmer(int $id, array $in, bool $active): void
{
    $data = farmer_input($in);
    if ($data['code'] === null) {
        throw new DomainException('Farmer code cannot be empty.');
    }
    if (farmer_code_taken($data['code'], $id)) {
        throw new DomainException('That farmer code is already used.');
    }
    update('farmers', $id, $data + ['active' => $active ? 1 : 0, 'updated_at' => now()]);
    audit('update', 'farmer', $id, $data['full_name'] . ($active ? '' : ' (inactive)'));
}

/* ---------- Purchases ---------- */

/** Computes the purchase figures from raw form input without saving. Shared by the form preview and create. */
function purchase_figures(float $gross, float $tare, int $price, int $deduct): array
{
    $net = round($gross - $tare, 2);
    $amount = (int) round($net * $price);
    return ['net_kg' => $net, 'gross_amount' => $amount, 'advance_deducted' => $deduct, 'amount_paid' => $amount - $deduct];
}

function create_purchase(array $in): int
{
    $user = current_user();
    $farmerId = (int) ($in['farmer_id'] ?? 0);
    $gradeId  = (int) ($in['grade_id'] ?? 0);
    $bags     = in_int($in, 'bags') ?? 0;
    $gross    = in_num($in, 'gross_kg');
    $tare     = in_num($in, 'tare_kg') ?? 0.0;
    $moisture = in_num($in, 'moisture_pct');
    $deduct   = in_int($in, 'advance_deducted') ?? 0;
    $method   = (string) ($in['payment_method'] ?? '');
    $ref      = in_str($in, 'payment_ref', 80);
    $notes    = in_str($in, 'notes', 500);

    $g = grade($gradeId);
    if (!$g || !$g['active'] || !$g['commodity_active']) {
        throw new DomainException('Choose the produce and grade.');
    }
    if ($bags < 0 || $bags > 10000) {
        throw new DomainException('Number of bags looks wrong.');
    }
    if ($gross === null || $gross <= 0 || $gross > 100000) {
        throw new DomainException('Enter the gross weight in kg.');
    }
    if ($tare < 0 || $tare >= $gross) {
        throw new DomainException('Tare (bag weight) must be zero or more and less than the gross weight.');
    }
    if ($moisture !== null && ($moisture < 0 || $moisture > 100)) {
        throw new DomainException('Moisture must be between 0 and 100%.');
    }
    if (!isset(PAYMENT_METHODS[$method])) {
        throw new DomainException('Choose how the farmer was paid.');
    }
    if ($method !== 'cash' && $ref === '') {
        throw new DomainException('Enter the transaction reference for ' . PAYMENT_METHODS[$method] . '.');
    }
    if ($deduct < 0) {
        throw new DomainException('Advance deduction cannot be negative.');
    }

    // Clerks always buy at the current official price. Managers may override with a note.
    $current = current_price($gradeId);
    $override = in_int($in, 'price_per_kg');
    if (has_role('manager') && $override !== null && (!$current || $override !== (int) $current['price_per_kg'])) {
        if ($override <= 0 || $override > 100000) {
            throw new DomainException('Price per kg looks wrong.');
        }
        if ($notes === '') {
            throw new DomainException('Add a note explaining the special price.');
        }
        $price = $override;
    } elseif ($current) {
        $price = (int) $current['price_per_kg'];
    } else {
        throw new DomainException('No buying price is set for ' . grade_label($g) . '. A manager must set it under Prices.');
    }

    return transaction(function () use ($farmerId, $gradeId, $g, $bags, $gross, $tare, $moisture, $price, $deduct, $method, $ref, $notes, $user) {
        $f = q_one('SELECT id, full_name, active FROM farmers WHERE id = ?' . for_update(), [$farmerId]);
        if (!$f || !(int) $f['active']) {
            throw new DomainException('Choose an active farmer.');
        }
        $fig = purchase_figures($gross, $tare, $price, $deduct);
        if ($fig['net_kg'] <= 0) {
            throw new DomainException('Net weight must be more than zero.');
        }
        $balance = farmer_balance($farmerId);
        if ($deduct > $balance) {
            throw new DomainException('Advance deduction (' . money($deduct) . ') is more than what the farmer owes (' . money($balance) . ').');
        }
        if ($deduct > $fig['gross_amount']) {
            throw new DomainException('Advance deduction cannot be more than the purchase value (' . money($fig['gross_amount']) . ').');
        }
        $id = insert('purchases', [
            'farmer_id'        => $farmerId,
            'grade_id'         => $gradeId,
            'bags'             => $bags,
            'gross_kg'         => round($gross, 2),
            'tare_kg'          => round($tare, 2),
            'net_kg'           => $fig['net_kg'],
            'moisture_pct'     => $moisture,
            'price_per_kg'     => $price,
            'gross_amount'     => $fig['gross_amount'],
            'advance_deducted' => $deduct,
            'amount_paid'      => $fig['amount_paid'],
            'payment_method'   => $method,
            'payment_ref'      => $ref !== '' ? $ref : null,
            'notes'            => $notes !== '' ? $notes : null,
            'status'           => 'valid',
            'created_by'       => $user['id'],
            'created_at'       => now(),
        ]);
        $receipt = 'R' . date('ym') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        update('purchases', $id, ['receipt_no' => $receipt]);
        audit('create', 'purchase', $id, "$receipt {$f['full_name']} " . grade_label($g) . " {$fig['net_kg']}kg @ $price = {$fig['gross_amount']}");
        return $id;
    });
}

function purchase(int $id): ?array
{
    return q_one(
        'SELECT p.*, f.full_name AS farmer_name, f.code AS farmer_code, f.phone AS farmer_phone, f.village AS farmer_village,
                g.name AS grade_name, c.name AS commodity_name, g.commodity_id,
                u.full_name AS clerk_name, v.full_name AS voided_by_name
         FROM purchases p
         JOIN farmers f ON f.id = p.farmer_id
         JOIN grades g ON g.id = p.grade_id
         JOIN commodities c ON c.id = g.commodity_id
         JOIN users u ON u.id = p.created_by
         LEFT JOIN users v ON v.id = p.voided_by
         WHERE p.id = ?',
        [$id]
    );
}

function void_purchase(int $id, string $reason): void
{
    if (mb_strlen($reason) < 3) {
        throw new DomainException('Give a reason for voiding.');
    }
    transaction(function () use ($id, $reason) {
        $p = q_one('SELECT * FROM purchases WHERE id = ?' . for_update(), [$id]);
        if (!$p || $p['status'] !== 'valid') {
            throw new DomainException('This purchase is already void.');
        }
        q('SELECT id FROM grades WHERE id = ?' . for_update(), [$p['grade_id']]);
        $stock = stock_for_grade((int) $p['grade_id']);
        if ($stock - (float) $p['net_kg'] < -0.001) {
            throw new DomainException('Cannot void: this produce has already been dispatched, so stock would go below zero. Void the dispatch first.');
        }
        q("UPDATE purchases SET status = 'void', void_reason = ?, voided_by = ?, voided_at = ? WHERE id = ?", [$reason, current_user()['id'], now(), $id]);
        audit('void', 'purchase', $id, $p['receipt_no'] . ': ' . $reason);
    });
}

/* ---------- Advances ---------- */

function create_advance(array $in): int
{
    $farmerId = (int) ($in['farmer_id'] ?? 0);
    $amount = in_int($in, 'amount');
    $method = (string) ($in['payment_method'] ?? '');
    $note = in_str($in, 'note', 255);
    if ($amount === null || $amount <= 0 || $amount > 1000000000) {
        throw new DomainException('Enter the advance amount.');
    }
    if (!isset(PAYMENT_METHODS[$method])) {
        throw new DomainException('Choose how the advance was paid.');
    }
    return transaction(function () use ($farmerId, $amount, $method, $note) {
        $f = q_one('SELECT id, full_name, active FROM farmers WHERE id = ?' . for_update(), [$farmerId]);
        if (!$f || !(int) $f['active']) {
            throw new DomainException('Choose an active farmer.');
        }
        $id = insert('advances', [
            'farmer_id'      => $farmerId,
            'amount'         => $amount,
            'payment_method' => $method,
            'note'           => $note !== '' ? $note : null,
            'status'         => 'valid',
            'created_by'     => current_user()['id'],
            'created_at'     => now(),
        ]);
        audit('create', 'advance', $id, $f['full_name'] . ': ' . $amount);
        return $id;
    });
}

function void_advance(int $id, string $reason): void
{
    if (mb_strlen($reason) < 3) {
        throw new DomainException('Give a reason for voiding.');
    }
    transaction(function () use ($id, $reason) {
        $a = q_one('SELECT * FROM advances WHERE id = ?' . for_update(), [$id]);
        if (!$a || $a['status'] !== 'valid') {
            throw new DomainException('This advance is already void.');
        }
        q('SELECT id FROM farmers WHERE id = ?' . for_update(), [$a['farmer_id']]);
        if (farmer_balance((int) $a['farmer_id']) - (int) $a['amount'] < 0) {
            throw new DomainException('Cannot void: part of this advance has already been recovered from purchases.');
        }
        q("UPDATE advances SET status = 'void', void_reason = ?, voided_by = ?, voided_at = ? WHERE id = ?", [$reason, current_user()['id'], now(), $id]);
        audit('void', 'advance', $id, $reason);
    });
}

/* ---------- Stock & dispatches ---------- */

function stock_for_grade(int $gradeId): float
{
    $in = (float) q_val("SELECT COALESCE(SUM(net_kg), 0) FROM purchases WHERE grade_id = ? AND status = 'valid'", [$gradeId]);
    $out = (float) q_val("SELECT COALESCE(SUM(net_kg), 0) FROM dispatches WHERE grade_id = ? AND status = 'valid'", [$gradeId]);
    return round($in - $out, 2);
}

/** Stock position for every grade that is active or still holds stock. */
function stock_levels(): array
{
    $bought = [];
    foreach (q_all("SELECT grade_id, SUM(net_kg) AS kg, SUM(gross_amount) AS cost, SUM(bags) AS bags FROM purchases WHERE status = 'valid' GROUP BY grade_id") as $r) {
        $bought[$r['grade_id']] = $r;
    }
    $sent = [];
    foreach (q_all("SELECT grade_id, SUM(net_kg) AS kg, SUM(bags) AS bags FROM dispatches WHERE status = 'valid' GROUP BY grade_id") as $r) {
        $sent[$r['grade_id']] = $r;
    }
    $rows = [];
    foreach (grades(false) as $g) {
        $in = (float) ($bought[$g['id']]['kg'] ?? 0);
        $out = (float) ($sent[$g['id']]['kg'] ?? 0);
        if (!$g['active'] && $in == 0.0) {
            continue;
        }
        $rows[] = $g + [
            'in_kg'    => $in,
            'out_kg'   => $out,
            'stock_kg' => round($in - $out, 2),
            'bags'     => (int) ($bought[$g['id']]['bags'] ?? 0) - (int) ($sent[$g['id']]['bags'] ?? 0),
            'avg_cost' => $in > 0 ? (int) round((float) $bought[$g['id']]['cost'] / $in) : null,
        ];
    }
    return $rows;
}

function create_dispatch(array $in): int
{
    $gradeId = (int) ($in['grade_id'] ?? 0);
    $bags = in_int($in, 'bags') ?? 0;
    $kg = in_num($in, 'net_kg');
    $buyer = in_str($in, 'buyer_name', 120);
    $vehicle = strtoupper(in_str($in, 'vehicle_no', 40));
    $driver = in_str($in, 'driver_name', 120);
    $price = in_int($in, 'sale_price_per_kg');
    $notes = in_str($in, 'notes', 500);

    $g = grade($gradeId);
    if (!$g) {
        throw new DomainException('Choose the produce and grade.');
    }
    if ($kg === null || $kg <= 0) {
        throw new DomainException('Enter the net weight dispatched.');
    }
    if ($bags < 0) {
        throw new DomainException('Number of bags looks wrong.');
    }
    if ($buyer === '') {
        throw new DomainException('Enter the buyer or destination.');
    }
    if ($price !== null && $price <= 0) {
        throw new DomainException('Sale price must be more than zero, or leave it empty.');
    }
    $kg = round($kg, 2);
    return transaction(function () use ($gradeId, $g, $bags, $kg, $buyer, $vehicle, $driver, $price, $notes) {
        q('SELECT id FROM grades WHERE id = ?' . for_update(), [$gradeId]);
        $stock = stock_for_grade($gradeId);
        if ($kg > $stock + 0.001) {
            throw new DomainException('Only ' . kg($stock) . ' of ' . grade_label($g) . ' is in stock.');
        }
        $id = insert('dispatches', [
            'grade_id'          => $gradeId,
            'bags'              => $bags,
            'net_kg'            => $kg,
            'buyer_name'        => $buyer,
            'vehicle_no'        => $vehicle !== '' ? $vehicle : null,
            'driver_name'       => $driver !== '' ? $driver : null,
            'sale_price_per_kg' => $price,
            'sale_amount'       => $price !== null ? (int) round($kg * $price) : null,
            'notes'             => $notes !== '' ? $notes : null,
            'status'            => 'valid',
            'created_by'        => current_user()['id'],
            'created_at'        => now(),
        ]);
        $ref = 'D' . date('ym') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
        update('dispatches', $id, ['ref_no' => $ref]);
        audit('create', 'dispatch', $id, "$ref " . grade_label($g) . " {$kg}kg to $buyer");
        return $id;
    });
}

function void_dispatch(int $id, string $reason): void
{
    if (mb_strlen($reason) < 3) {
        throw new DomainException('Give a reason for voiding.');
    }
    $d = q_one('SELECT * FROM dispatches WHERE id = ?', [$id]);
    if (!$d || $d['status'] !== 'valid') {
        throw new DomainException('This dispatch is already void.');
    }
    $st = q("UPDATE dispatches SET status = 'void', void_reason = ?, voided_by = ?, voided_at = ? WHERE id = ? AND status = 'valid'", [$reason, current_user()['id'], now(), $id]);
    if ($st->rowCount() === 0) {
        throw new DomainException('This dispatch is already void.');
    }
    audit('void', 'dispatch', $id, $d['ref_no'] . ': ' . $reason);
}

/* ---------- Reporting ---------- */

/** [start, endExclusive] datetime strings for a Y-m-d date range. */
function date_bounds(string $from, string $to): array
{
    return [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
}

function purchase_summary(string $from, string $to): array
{
    [$a, $b] = date_bounds($from, $to);
    return q_all(
        "SELECT c.name AS commodity_name, g.name AS grade_name, COUNT(*) AS n, SUM(p.bags) AS bags, SUM(p.net_kg) AS kg,
                SUM(p.gross_amount) AS amount, SUM(p.advance_deducted) AS deducted, SUM(p.amount_paid) AS paid
         FROM purchases p JOIN grades g ON g.id = p.grade_id JOIN commodities c ON c.id = g.commodity_id
         WHERE p.status = 'valid' AND p.created_at >= ? AND p.created_at < ?
         GROUP BY c.id, c.name, c.sort_order, g.id, g.name, g.sort_order
         ORDER BY c.sort_order, g.sort_order",
        [$a, $b]
    );
}

function payments_summary(string $from, string $to): array
{
    [$a, $b] = date_bounds($from, $to);
    $rows = [];
    foreach (q_all("SELECT payment_method, COUNT(*) AS n, SUM(amount_paid) AS paid FROM purchases WHERE status = 'valid' AND created_at >= ? AND created_at < ? GROUP BY payment_method", [$a, $b]) as $r) {
        $rows[$r['payment_method']] = ['purchases' => (int) $r['paid'], 'advances' => 0];
    }
    foreach (q_all("SELECT payment_method, SUM(amount) AS amt FROM advances WHERE status = 'valid' AND created_at >= ? AND created_at < ? GROUP BY payment_method", [$a, $b]) as $r) {
        $rows[$r['payment_method']] = ($rows[$r['payment_method']] ?? ['purchases' => 0]) + ['advances' => 0];
        $rows[$r['payment_method']]['advances'] = (int) $r['amt'];
    }
    return $rows;
}
