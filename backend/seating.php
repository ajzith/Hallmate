<?php
// backend/seating.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/db.php';    
require 'utils/auth.php';   
require_role(['admin']);

$method = $_SERVER['REQUEST_METHOD'];

function get_json_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

$input = get_json_input();

if ($method === 'POST' && isset($input['_method'])) {
    $method = strtoupper($input['_method']); 
}

try {
    if ($method === 'GET') {
        $filter_type = $_GET['type'] ?? null;
        $sql = "
            SELECT s.id, s.exam_id, s.room_id, s.student_id, s.seat_no, s.created_at,
                   e.subject, e.exam_type, r.room_no, st.name as student_name
            FROM seating s
            LEFT JOIN exams e ON s.exam_id = e.id
            LEFT JOIN room r ON s.room_id = r.id
            LEFT JOIN students st ON s.student_id = st.id
        ";
        if ($filter_type === 'Theory') {
            $sql .= " WHERE e.exam_type = 'Theory' ";
        } elseif ($filter_type === 'Lab') {
            $sql .= " WHERE e.exam_type = 'Lab' ";
        }
        
       
        // This will sort 'A502-1-L', 'A502-1-R', 'A502-2-L', 'A502-2-R', 'A502-10-L'
        $sql .= " ORDER BY 
                    r.room_no ASC,
                    CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(s.seat_no, '-', -2), '-', 1) AS UNSIGNED) ASC,
                    SUBSTRING_INDEX(s.seat_no, '-', -1) ASC
                ";
       
        
        $res = $mysqli->query($sql);
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'seatings' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST') {
        exit;
    }

    if ($method === 'DELETE') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing seating ID']);
            exit;
        }
        $stmt = $mysqli->prepare("DELETE FROM seating WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'deleted' => $stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }

    if ($method === 'DELETE_ALL_THEORY') {
        $stmt = $mysqli->prepare("
            DELETE s FROM seating s
            JOIN exams e ON s.exam_id = e.id
            WHERE e.exam_type = 'Theory'
        ");
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'deleted' => $stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }
    
    if ($method === 'DELETE_ALL_LAB') {
        $stmt = $mysqli->prepare("
            DELETE s FROM seating s
            JOIN exams e ON s.exam_id = e.id
            WHERE e.exam_type = 'Lab'
        ");
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'deleted' => $stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
?>