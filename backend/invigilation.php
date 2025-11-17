<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . "/db.php"; 
// require 'utils/auth.php'; // Uncomment when ready
// require_role(['admin']); // Uncomment when ready

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    $data = [];
}

if ($method === 'POST' && isset($data['_method'])) {
    $method = strtoupper($data['_method']);
}

try {
    switch ($method) {
        case 'GET':
            $res = $mysqli->query("SELECT i.id, i.exam_id, i.room_id, e.subject, e.exam_date, e.session, f.name AS faculty_name, r.room_no
                                    FROM invigilation i
                                    JOIN exams e ON i.exam_id = e.id
                                    JOIN faculty f ON i.faculty_id = f.id
                                    JOIN room r ON i.room_id = r.id 
                                    ORDER BY e.exam_date ASC");
            
            if (!$res) {
                 throw new Exception("Database query failed: " . $mysqli->error);
            }
            
            $assignments = $res->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'assignments' => $assignments]);
            break;

        case 'POST':
            $exam_id = (int)($data['exam_id'] ?? 0);
            $faculty_id = (int)($data['faculty_id'] ?? 0);
            $room_id = (int)($data['room_id'] ?? 0);

            if (!$exam_id || !$faculty_id || !$room_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing exam, faculty, or room ID.']);
                exit;
            }

            $stmt_exam_time = $mysqli->prepare("SELECT exam_date, session FROM exams WHERE id = ?");
            $stmt_exam_time->bind_param("i", $exam_id);
            $stmt_exam_time->execute();
            $exam_time = $stmt_exam_time->get_result()->fetch_assoc();
            $stmt_exam_time->close();
            
            if (!$exam_time) {
                 http_response_code(404);
                 echo json_encode(['success' => false, 'error' => 'Exam not found.']);
                 exit;
            }

            $exam_date = $exam_time['exam_date'];
            $session = $exam_time['session'];

            $stmt_conflict = $mysqli->prepare("
                SELECT r.room_no
                FROM invigilation i
                JOIN exams e ON i.exam_id = e.id
                JOIN room r ON i.room_id = r.id
                WHERE i.faculty_id = ? 
                AND e.exam_date = ? 
                AND e.session = ?
            ");
            $stmt_conflict->bind_param("iss", $faculty_id, $exam_date, $session);
            $stmt_conflict->execute();
            $conflict_room = $stmt_conflict->get_result()->fetch_assoc();
            $stmt_conflict->close();

            if ($conflict_room) {
                http_response_code(409); // Conflict
                echo json_encode(['success' => false, 'error' => "Conflict! Faculty is already assigned to Room {$conflict_room['room_no']} for the same date/session."]);
                exit;
            }
            
            $stmt = $mysqli->prepare("INSERT INTO invigilation (exam_id, faculty_id, room_id) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $exam_id, $faculty_id, $room_id);
            
            if ($stmt->execute()) {
                 echo json_encode(['success' => true]);
            } else {
                 http_response_code(500);
                 echo json_encode(['success' => false, 'error' => 'Database insert failed: ' . $stmt->error]);
            }
            break;

        case 'DELETE':
            $stmt = $mysqli->prepare("DELETE FROM invigilation WHERE id = ?");
            $stmt->bind_param("i", $data['id']);
            $stmt->execute();
            echo json_encode(['success' => true, 'affected' => $stmt->affected_rows]);
            break;
        
        // 💡 NEW "DELETE ALL" LOGIC 💡
        case 'DELETE_ALL':
            // TRUNCATE is faster and resets the ID counter
            $stmt = $mysqli->prepare("TRUNCATE TABLE invigilation");
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'affected' => 'all']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Delete all failed: ' . $stmt->error]);
            }
            break;
        // 💡 END OF NEW LOGIC 💡

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>