<?php
require_role('manager');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        void_dispatch((int) ($_GET['id'] ?? 0), in_str($_POST, 'reason', 255));
        flash('ok', 'Dispatch voided. The weight is back in stock.');
    } catch (DomainException $ex) {
        flash('error', $ex->getMessage());
    }
}
redirect('dispatches');
