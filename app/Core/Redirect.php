<?php
require_once __DIR__ . '/Auth.php';

function redirect_if_logged_in(): void {
    if (!empty($_SESSION['user'])) {
        header('Location: ' . get_home_for_role($_SESSION['user']['role']));
        exit;
    }
}
