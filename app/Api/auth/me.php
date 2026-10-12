<?php
require_once __DIR__ . '/../../Core/Helpers.php';

if (empty($_SESSION['user'])) {
    json_response(['authenticated' => false], 401);
}

json_response(['authenticated' => true, 'user' => $_SESSION['user']]);
