<?php
// backend/db.php
$DB_HOST = "127.0.0.1";
$DB_USER = "root";
$DB_PASS = "";        // default for WAMP; set if you changed it
$DB_NAME = "hallmate";

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to connect to MySQL: " . $mysqli->connect_error]);
    exit;
}

// set charset
$mysqli->set_charset("utf8mb4");
