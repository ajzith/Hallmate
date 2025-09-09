<?php
header('Content-Type: application/json');
require 'db.php'; // make sure the path is correct
$response = ['success' => false, 'students' => 0, 'rooms' => 0];

try {
    $studentResult = $mysqli->query("SELECT COUNT(*) AS total FROM students");
    $studentRow = $studentResult->fetch_assoc();
    $response['students'] = (int)$studentRow['total'];

    $roomResult = $mysqli->query("SELECT COUNT(*) AS total FROM room");
    $roomRow = $roomResult->fetch_assoc();
    $response['rooms'] = (int)$roomRow['total'];

    $response['success'] = true;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
