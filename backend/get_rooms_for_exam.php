<?php
// backend/get_rooms_for_exam.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/db.php'; 
require 'utils/auth.php'; 
require_role(['admin']); 

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if (!$exam_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing exam_id']);
    exit;
}

try {
  
    $stmt_time = $mysqli->prepare("SELECT exam_date, session, exam_type FROM exams WHERE id = ?");
    $stmt_time->bind_param("i", $exam_id);
    $stmt_time->execute();
    $exam_details = $stmt_time->get_result()->fetch_assoc();
    $stmt_time->close();

    if (!$exam_details) {
        throw new Exception("Exam not found.");
    }

    $exam_date = $exam_details['exam_date'];
    $session = $exam_details['session'];
    $exam_type = $exam_details['exam_type'];


    $stmt_rooms = $mysqli->prepare("
        SELECT DISTINCT r.id, r.room_no
        FROM seating s
        JOIN exams e ON s.exam_id = e.id
        JOIN room r ON s.room_id = r.id
        WHERE 
            e.exam_date = ? 
            AND e.session = ? 
            AND e.exam_type = ?
        ORDER BY r.room_no ASC
    ");
    $stmt_rooms->bind_param("sss", $exam_date, $session, $exam_type);
    $stmt_rooms->execute();
    $result = $stmt_rooms->get_result();
    $rooms = $result->fetch_all(MYSQLI_ASSOC);
    $stmt_rooms->close();
    
    
    echo json_encode(['success' => true, 'rooms' => $rooms]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>