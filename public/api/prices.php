<?php
declare(strict_types=1);

/*
 * Public, read-only price feed for the company website.
 * Returns only prices a manager ticked "Show on the public website". No authentication, no personal data.
 */
require __DIR__ . '/../../app/bootstrap.php';

app_boot(false);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, (array) config('public_price_origins', []), true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

if (!is_file(CONFIG_FILE)) {
    http_response_code(503);
    echo json_encode(['error' => 'not_configured']);
    exit;
}

try {
    $rows = [];
    $latest = null;
    foreach (current_prices() as $g) {
        $p = $g['price'];
        if (!$p || !(int) $p['is_public']) {
            continue;
        }
        $rows[] = [
            'produce'      => $g['commodity_name'],
            'grade'        => $g['name'],
            'price_per_kg' => (int) $p['price_per_kg'],
            'effective'    => date(DATE_ATOM, strtotime($p['effective_from'])),
        ];
        $latest = max($latest ?? '', $p['effective_from']);
    }
    echo json_encode([
        'company'      => setting('company_name', 'Motambarea Farmers'),
        'currency'     => setting('currency', 'XAF'),
        'unit'         => 'kg',
        'last_updated' => $latest ? date(DATE_ATOM, strtotime($latest)) : null,
        'prices'       => $rows,
        'notice'       => 'Indicative buying prices. Confirm with our team before travelling.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    error_log('[POS api] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'unavailable']);
}
