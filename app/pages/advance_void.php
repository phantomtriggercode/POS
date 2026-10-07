<?php
require_role('manager');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        void_advance((int) ($_GET['id'] ?? 0), in_str($_POST, 'reason', 255));
        flash('ok', 'Advance voided.');
    } catch (DomainException $ex) {
        flash('error', $ex->getMessage());
    }
}
redirect('advances');
