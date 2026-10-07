<?php
declare(strict_types=1);

/*
 * Business-rule tests against a throwaway SQLite database.
 * Run: php tests/domain_test.php
 * Against MySQL/MariaDB (uses and EMPTIES the given database):
 *   POS_TEST_MYSQL="dbname;user;password" php tests/domain_test.php
 */

$dir = sys_get_temp_dir() . '/mfpos-test-' . bin2hex(random_bytes(4));
mkdir($dir);
$cfgFile = $dir . '/config.php';
$dbCfg = ['driver' => 'sqlite', 'sqlite_path' => $dir . '/pos.sqlite'];
if ($my = getenv('POS_TEST_MYSQL')) {
    [$name, $user, $pass] = explode(';', $my) + ['', '', ''];
    $dbCfg = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'name' => $name, 'user' => $user, 'pass' => $pass];
}
file_put_contents($cfgFile, '<?php return ' . var_export(['timezone' => 'Africa/Douala', 'db' => $dbCfg], true) . ';');
putenv('POS_CONFIG=' . $cfgFile);

require __DIR__ . '/../app/bootstrap.php';
app_boot();
if (db_driver() === 'mysql') {
    db()->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        db()->exec("DROP TABLE `$t`");
    }
    db()->exec('SET FOREIGN_KEY_CHECKS = 1');
}
echo 'Database: ' . db_driver() . "\n";
install_database(db(), ['username' => 'admin', 'full_name' => 'Admin', 'password' => 'secret123'], ['name' => 'Test Co', 'location' => 'Kumba', 'phone' => '']);

$fails = 0;
$count = 0;
function check(string $name, bool $ok): void
{
    global $fails, $count;
    $count++;
    echo ($ok ? "  ok   " : "  FAIL ") . $name . "\n";
    if (!$ok) {
        $fails++;
    }
}
function throws(string $name, callable $fn, string $contains = ''): void
{
    try {
        $fn();
        check($name . ' (expected error)', false);
    } catch (DomainException $e) {
        check($name . ' → "' . $e->getMessage() . '"', $contains === '' || stripos($e->getMessage(), $contains) !== false);
    }
}

$_SESSION = ['uid' => 1];

// Users with each role
$clerkId = insert('users', ['username' => 'clerk', 'full_name' => 'Clerk', 'role' => 'clerk', 'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'active' => 1, 'created_at' => now()]);

$cocoa1 = (int) q_val("SELECT g.id FROM grades g JOIN commodities c ON c.id = g.commodity_id WHERE c.code = 'COCOA' AND g.name = 'Grade I'");
$cocoa2 = (int) q_val("SELECT g.id FROM grades g JOIN commodities c ON c.id = g.commodity_id WHERE c.code = 'COCOA' AND g.name = 'Grade II'");

echo "Farmers\n";
$f1 = create_farmer(['full_name' => 'Ebot Ashu', 'phone' => '+237 670 000 000', 'village' => 'Kake']);
check('auto farmer code', farmer($f1)['code'] === 'F00001');
$f2 = create_farmer(['full_name' => 'Manyi Enow', 'code' => 'coop-12']);
check('custom code uppercased', farmer($f2)['code'] === 'COOP-12');
throws('duplicate code', fn () => create_farmer(['full_name' => 'X Y', 'code' => 'COOP-12']), 'already');
throws('half GPS', fn () => create_farmer(['full_name' => 'X Y', 'gps_lat' => '4.6']), 'GPS');

echo "Prices\n";
$_SESSION['uid'] = $clerkId;
throws('purchase without price', fn () => create_purchase(['farmer_id' => $f1, 'grade_id' => $cocoa1, 'gross_kg' => '100', 'payment_method' => 'cash']), 'No buying price');
$_SESSION['uid'] = 1;
set_price($cocoa1, 1500, date('Y-m-d H:i:s', time() - 60), true, '');
set_price($cocoa1, 9999, date('Y-m-d H:i:s', time() + 86400), false, 'future');
check('current price ignores future price', (int) current_price($cocoa1)['price_per_kg'] === 1500);
set_price($cocoa2, 1200, date('Y-m-d H:i:s', time() - 60), false, '');

echo "Purchases\n";
$_SESSION['uid'] = $clerkId;
$p1 = create_purchase(['farmer_id' => $f1, 'grade_id' => $cocoa1, 'bags' => '2', 'gross_kg' => '125,5', 'tare_kg' => '1.5', 'payment_method' => 'cash', 'price_per_kg' => '5000']);
$row = purchase($p1);
check('net = gross - tare', (float) $row['net_kg'] === 124.0);
check('clerk cannot override price', (int) $row['price_per_kg'] === 1500);
check('amount = net × price', (int) $row['gross_amount'] === 186000 && (int) $row['amount_paid'] === 186000);
check('receipt number assigned', (bool) preg_match('/^R\d{4}-000001$/', $row['receipt_no']));
throws('tare ≥ gross', fn () => create_purchase(['farmer_id' => $f1, 'grade_id' => $cocoa1, 'gross_kg' => '10', 'tare_kg' => '10', 'payment_method' => 'cash']), 'Tare');
throws('momo needs reference', fn () => create_purchase(['farmer_id' => $f1, 'grade_id' => $cocoa1, 'gross_kg' => '10', 'payment_method' => 'mtn_momo']), 'reference');
throws('deduction without advance', fn () => create_purchase(['farmer_id' => $f1, 'grade_id' => $cocoa1, 'gross_kg' => '10', 'payment_method' => 'cash', 'advance_deducted' => '100']), 'owes');

$_SESSION['uid'] = 1;
throws('manager special price needs note', fn () => create_purchase(['farmer_id' => $f2, 'grade_id' => $cocoa1, 'gross_kg' => '10', 'payment_method' => 'cash', 'price_per_kg' => '1600']), 'note');
$p2 = create_purchase(['farmer_id' => $f2, 'grade_id' => $cocoa1, 'gross_kg' => '10', 'payment_method' => 'cash', 'price_per_kg' => '1600', 'notes' => 'premium lot']);
check('manager special price applied', (int) purchase($p2)['price_per_kg'] === 1600);

echo "Advances\n";
create_advance(['farmer_id' => $f1, 'amount' => '50 000', 'payment_method' => 'cash']);
check('balance after advance', farmer_balance($f1) === 50000);
$_SESSION['uid'] = $clerkId;
throws('deduction above purchase value', fn () => create_purchase(['farmer_id' => $f1, 'grade_id' => $cocoa2, 'gross_kg' => '10', 'payment_method' => 'cash', 'advance_deducted' => '20000']), 'purchase value');
$p3 = create_purchase(['farmer_id' => $f1, 'grade_id' => $cocoa2, 'gross_kg' => '40', 'payment_method' => 'cash', 'advance_deducted' => '30000']);
$r3 = purchase($p3);
check('deduction recorded and paid reduced', (int) $r3['gross_amount'] === 48000 && (int) $r3['amount_paid'] === 18000);
check('balance reduced', farmer_balance($f1) === 20000);
check('total outstanding', total_outstanding_advances() === 20000);
$_SESSION['uid'] = 1;
$advId = (int) q_val('SELECT id FROM advances WHERE farmer_id = ?', [$f1]);
throws('cannot void partly recovered advance', fn () => void_advance($advId, 'mistake'), 'recovered');

echo "Stock & dispatch\n";
check('stock grade I', stock_for_grade($cocoa1) === 134.0);
throws('dispatch more than stock', fn () => create_dispatch(['grade_id' => $cocoa1, 'net_kg' => '200', 'buyer_name' => 'Exporter']), 'Only');
$d1 = create_dispatch(['grade_id' => $cocoa1, 'net_kg' => '130', 'buyer_name' => 'Exporter SA', 'sale_price_per_kg' => '1700']);
check('stock after dispatch', stock_for_grade($cocoa1) === 4.0);
check('sale amount', (int) q_val('SELECT sale_amount FROM dispatches WHERE id = ?', [$d1]) === 221000);
throws('void purchase already dispatched', fn () => void_purchase($p1, 'wrong weight'), 'dispatched');
void_dispatch($d1, 'truck cancelled');
check('stock restored after dispatch void', stock_for_grade($cocoa1) === 134.0);
throws('double void dispatch', fn () => void_dispatch($d1, 'again'), 'already');

echo "Voids\n";
void_purchase($p3, 'wrong grade');
check('void purchase restores advance balance', farmer_balance($f1) === 50000);
check('void purchase removes stock', stock_for_grade($cocoa2) === 0.0);
throws('void needs reason', fn () => void_purchase($p1, ''), 'reason');
void_advance($advId, 'entered twice');
check('advance void clears balance', farmer_balance($f1) === 0);

echo "Reports\n";
$sum = purchase_summary(today(), today());
check('summary excludes voids', array_sum(array_column($sum, 'n')) == 2);
check('audit log written', (int) q_val('SELECT COUNT(*) FROM audit_log') > 10);

echo "\n$count checks, $fails failed\n";
array_map('unlink', glob($dir . '/*'));
rmdir($dir);
exit($fails ? 1 : 0);
