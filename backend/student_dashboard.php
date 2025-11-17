<?php
// FILE: ../backend/student_dashboard.php
// (Updated)

header('Content-Type: application/json');
session_start();
require_once __DIR__ . "/db.php";

$student_id = $_SESSION['user_id'] ?? 0;
if (!$student_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    
    $query = "SELECT e.subject, e.course_code, e.exam_date, e.session, 
                     r.room_no, r.location, s.seat_no,
                     s.room_id, s.exam_id
              FROM seating s
              JOIN exams e ON s.exam_id = e.id
              JOIN room r ON s.room_id = r.id 
              WHERE s.student_id = ?
              ORDER BY e.exam_date ASC";
              
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $allocs = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'exams' => $allocs]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>