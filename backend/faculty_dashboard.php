<?php
// backend/faculty_dashboard.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/db.php';
require 'utils/auth.php'; 
require_role(['faculty']);

$action = $_GET['action'] ?? '';

$faculty_id = (int)$_SESSION['user_id']; 

try {
    
    if ($action === 'getSchedule') {
        $stmt = $mysqli->prepare("
            SELECT DISTINCT
                i.exam_id, i.room_id,
                e.subject, e.course_code, e.exam_date, e.session,
                r.room_no
            FROM invigilation i
            JOIN exams e ON i.exam_id = e.id
            JOIN room r ON i.room_id = r.id
            WHERE i.faculty_id = ?
            ORDER BY e.exam_date ASC, e.session ASC
        ");
        $stmt->bind_param("i", $faculty_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $schedule = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'schedule' => $schedule]);
        exit;
    }

    
    if ($action === 'getStudentsInHall') {
        $exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
        $room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;

        if (!$exam_id || !$room_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing exam or room ID.']);
            exit;
        }
        
        
        $stmt_check = $mysqli->prepare("SELECT e.exam_date, e.session, e.exam_type FROM invigilation i
                                        JOIN exams e ON i.exam_id = e.id
                                        WHERE i.faculty_id = ? AND i.exam_id = ? AND i.room_id = ?");
        $stmt_check->bind_param("iii", $faculty_id, $exam_id, $room_id);
        $stmt_check->execute();
        $invigilation_details = $stmt_check->get_result()->fetch_assoc();
        
        if (!$invigilation_details) {
            http_response_code(403);
            echo json_encode(['error' => 'You are not assigned to this hall.']);
            exit;
        }
        $stmt_check->close();

        $exam_date = $invigilation_details['exam_date'];
        $session = $invigilation_details['session'];
        $exam_type = $invigilation_details['exam_type'];

        
        $stmt = $mysqli->prepare("
            SELECT 
                s.reg_no, s.name as student_name,
                st.seat_no,
                e.subject, e.course_code
            FROM seating st
            JOIN students s ON st.student_id = s.id
            JOIN exams e ON st.exam_id = e.id
            WHERE 
                st.room_id = ? 
                AND e.exam_date = ?
                AND e.session = ?
                AND e.exam_type = ?
            
            ORDER BY 
                SUBSTRING_INDEX(st.seat_no, '-', 1) ASC, 
                CAST(SUBSTRING_INDEX(st.seat_no, '-', -1) AS UNSIGNED) ASC
        ");
        $stmt->bind_param("isss", $room_id, $exam_date, $session, $exam_type);

        $stmt->execute();
        $result = $stmt->get_result();
        $students = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'students' => $students]);
        exit;
    }

    
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'A server error occurred.', 'details' => $e->getMessage()]);
}
?>