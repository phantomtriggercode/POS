<?php
require_role('manager');
$id = (int) ($_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        void_purchase($id, in_str($_POST, 'reason', 255));
        flash('ok', 'Purchase voided.');
    } catch (DomainException $ex) {
        flash('error', $ex->getMessage());
    }
}
redirect('purchase', ['id' => $id]);
