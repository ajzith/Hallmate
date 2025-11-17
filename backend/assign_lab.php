<?php
// backend/assign_lab.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/db.php';
require 'utils/auth.php'; 
require_role(['admin']); 

function bad($msg, $code = 400) { 
    http_response_code($code); 
    echo json_encode(['success' => false, 'error' => $msg]); 
    exit; 
}
function ok($data = []) { 
    echo json_encode(array_merge(['success' => true], $data)); 
    exit; 
}

$input = json_decode(file_get_contents('php://input'), true);
$exam_id = isset($input['exam_id']) ? (int)$input['exam_id'] : 0;
$room_id = isset($input['room_id']) ? (int)$input['room_id'] : 0;

if (!$exam_id || !$room_id) bad('Missing exam_id or room_id');

$mysqli->begin_transaction();
try {
    // 💡 1. Get Exam Details (including semester)
    $stmt_exam = $mysqli->prepare("SELECT batch, semester FROM exams WHERE id = ?"); // Removed 'Lab' type check
    $stmt_exam->bind_param("i", $exam_id);
    $stmt_exam->execute();
    $exam_details = $stmt_exam->get_result()->fetch_assoc();
    $stmt_exam->close();
    if (!$exam_details) bad('Exam not found.', 404);
    $batch_name = $exam_details['batch'];
    $semester = $exam_details['semester']; // 💡 GET THE SEMESTER

    // --- 2. Get Room Details ---
    $stmt_room = $mysqli->prepare("SELECT room_no, capacity FROM room WHERE id = ?");
    $stmt_room->bind_param("i", $room_id);
    $stmt_room->execute();
    $room_details = $stmt_room->get_result()->fetch_assoc();
    $stmt_room->close();
    if (!$room_details) bad('Room not found.', 404);
    $room_no = $room_details['room_no'];
    $capacity = (int)$room_details['capacity'];
    if ($capacity <= 0) bad('Selected room has no capacity.');

    // 💡 3. Get All Approved Students (matching batch AND semester)
    $stmt_students = $mysqli->prepare("SELECT id, name FROM students 
        WHERE course_name = ? AND semester = ? AND is_approved = 1 
        ORDER BY reg_no ASC");
    $stmt_students->bind_param("si", $batch_name, $semester);
    $stmt_students->execute();
    $students = $stmt_students->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_students->close();
    if (empty($students)) bad("No approved students found for this batch in this semester.");

    $student_count = count($students);
    if ($student_count > $capacity) {
        bad("Error: {$student_count} students found, but room capacity is only {$capacity}.");
    }

    // --- 4. Clear Old Seating for this exam ---
    $stmt_delete = $mysqli->prepare("DELETE FROM seating WHERE exam_id = ?");
    $stmt_delete->bind_param("i", $exam_id);
    $stmt_delete->execute();
    $stmt_delete->close();

    // --- 5. Assign Students to Seats ---
    $seating_plan = [];
    $seat_number = 1;
    foreach ($students as $student) {
        $seating_plan[] = [
            'exam_id' => $exam_id,
            'room_id' => $room_id,
            'student_id' => $student['id'],
            'seat_no' => $room_no . '-' . $seat_number
        ];
        $seat_number++;
    }

    // --- 6. Save to Database ---
    $stmt_insert = $mysqli->prepare("INSERT INTO seating (exam_id, room_id, student_id, seat_no) VALUES (?, ?, ?, ?)");
    foreach ($seating_plan as $seat) {
        $stmt_insert->bind_param("iiis", $seat['exam_id'], $seat['room_id'], $seat['student_id'], $seat['seat_no']);
        $stmt_insert->execute();
    }
    $stmt_insert->close();
    
    // --- 7. Success! ---
    $mysqli->commit();
    ok(['message' => "Success! Assigned {$student_count} students to {$room_no}."]);

} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Lab Assign Error: " . $e->getMessage());
    bad("Error: " . $e->getMessage(), 500);
}
?>