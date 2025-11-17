<?php
// FILE: ../backend/get_room_layout.php
// (Corrected the fatal 'break;,' typo)

header('Content-Type: application/json');
session_start();
require_once __DIR__ . "/db.php";

$user_id = $_SESSION['user_id'] ?? 0; 
$user_role = $_SESSION['role'] ?? 'guest';

if ($user_id === 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$room_id = $_GET['room_id'] ?? 0;
$exam_id = $_GET['exam_id'] ?? 0; 

if ($room_id === 0 || $exam_id === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing room_id or exam_id']);
    exit;
}

try {
    
    $stmt_room = $mysqli->prepare("SELECT total_rows, total_cols, seating_type FROM room WHERE id = ?");
    $stmt_room->bind_param("i", $room_id);
    $stmt_room->execute();
    $room_data = $stmt_room->get_result()->fetch_assoc();
    if (!$room_data) throw new Exception("Room not found.");
    $stmt_room->close();

  
    $stmt_exam = $mysqli->prepare("SELECT exam_date, session FROM exams WHERE id = ?");
    $stmt_exam->bind_param("i", $exam_id);
    $stmt_exam->execute();
    $exam_details = $stmt_exam->get_result()->fetch_assoc();
    if (!$exam_details) throw new Exception("Exam details not found.");
    $stmt_exam->close();
    
    $exam_date = $exam_details['exam_date'];
    $session = $exam_details['session'];

   
    $my_assigned_seat_no = null;
    $my_student_id = null; 
    
    if ($user_role === 'student') {
        $my_student_id = (int)$user_id;
    }

 
    $stmt_allocs = $mysqli->prepare(
        "SELECT s.seat_no, s.student_id, u.name AS student_name, s.seat_row, s.seat_col
         FROM seating s
         LEFT JOIN students u ON s.student_id = u.id
         JOIN exams e ON s.exam_id = e.id
         WHERE s.room_id = ? AND e.exam_date = ? AND e.session = ?"
    );
    $stmt_allocs->bind_param("iss", $room_id, $exam_date, $session);
    
    $stmt_allocs->execute();
    $allocs_result = $stmt_allocs->get_result();
    $allocs_data = $allocs_result->fetch_all(MYSQLI_ASSOC);
    $stmt_allocs->close();

    if ($my_student_id) {
        foreach ($allocs_data as $alloc) {
            if ((int)$alloc['student_id'] === $my_student_id) {
                $my_assigned_seat_no = $alloc['seat_no'];
                break; 
              
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'room' => $room_data, 
        'allocations' => $allocs_data,
        'my_seat_no' => $my_assigned_seat_no,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>