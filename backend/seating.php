<?php
// backend/seating.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
session_start();

require 'db.php';           // DB connection
require 'utils/auth.php';   // require_role() helper

// Only admin can manage seatings
require_role(['admin']);

$method = $_SERVER['REQUEST_METHOD'];

// Helper to read JSON body
function get_json_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    if ($method === 'GET') {
        $res = $mysqli->query("
            SELECT s.id, s.exam_id, s.room_id, s.student_id, s.seat_no, s.created_at,
                   e.subject, r.room_no, st.name as student_name
            FROM seating s
            LEFT JOIN exams e ON s.exam_id = e.id
            LEFT JOIN room r ON s.room_id = r.id
            LEFT JOIN students st ON s.student_id = st.id
            ORDER BY s.id DESC
        ");
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'seatings' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input = get_json_input();

    if ($method === 'POST') {
        $exam_id = isset($input['exam_id']) ? (int)$input['exam_id'] : 0;
        $room_id = isset($input['room_id']) ? (int)$input['room_id'] : 0;
        $student_id = isset($input['student_id']) ? (int)$input['student_id'] : 0;
        $seat_no = isset($input['seat_no']) ? trim($input['seat_no']) : '';

        if (!$exam_id || !$room_id || !$student_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing exam_id, room_id, or student_id']);
            exit;
        }

        // Check if IDs exist
        $checks = [
            'exam' => $exam_id,
            'room' => $room_id,
            'student' => $student_id
        ];
        foreach ($checks as $table => $id) {
            $stmt = $mysqli->prepare("SELECT id FROM $table".($table=='room'?'':'s')." WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                http_response_code(400);
                echo json_encode(['error' => ucfirst($table)." ID $id does not exist"]);
                exit;
            }
        }

        $stmt = $mysqli->prepare("INSERT INTO seating (exam_id, room_id, student_id, seat_no) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $exam_id, $room_id, $student_id, $seat_no);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Insert failed', 'details' => $stmt->error]);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
