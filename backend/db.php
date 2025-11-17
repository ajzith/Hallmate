<?php
// backend/db.php

if (!function_exists('mysqli_connect')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'MySQLi extension is not enabled on this server.']);
    exit;
}

$DB_HOST = "sql100.infinityfree.com";
$DB_USER = "if0_40290912";
$DB_PASS = "Amma81244"; // Make sure this is correct
$DB_NAME = "if0_40290912_hallmate_v2";


$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    http_response_code(500);
    header('Content-Type: application/json');
    error_log("Database Connection Failed: " . $mysqli->connect_error);
    echo json_encode(["error" => "Database connection failed. Please try again later."]);
    exit;
}

$mysqli->set_charset("utf8");
?>
