<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logout();
    flash('ok', 'You have signed out.');
}
redirect('login');
