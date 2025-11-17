<?php
// backend/exams.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/db.php'; 
require 'utils/auth.php';
require_role(['admin']); 


function get_json_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = get_json_input(); 


if ($method === 'POST' && isset($input['_method'])) {
    $method = strtoupper($input['_method']);
}

try {
    if ($method === 'GET') {
        $filter_type = $_GET['type'] ?? null;
        
        $sql = "SELECT id, subject, course_code, batch, semester, exam_type, exam_date, session FROM exams";
        
        if ($filter_type === 'Theory') {
            $sql .= " WHERE exam_type = 'Theory'";
        } elseif ($filter_type === 'Lab') {
            $sql .= " WHERE exam_type = 'Lab'";
        }
        
        $sql .= " ORDER BY exam_date DESC";
        
        $result = $mysqli->query($sql);
        $exams = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'exams' => $exams]);
        exit;
    }

    if ($method === 'POST') {
        $subject = trim($input['subject'] ?? '');
        $course_code = trim($input['course_code'] ?? '');
        $batch = trim($input['batch'] ?? '');
        $semester = (int)($input['semester'] ?? 0);
        $exam_date = trim($input['exam_date'] ?? '');
        $session = trim($input['session'] ?? 'Morning');
        $exam_type = trim($input['exam_type'] ?? 'Theory');
        if (!in_array($exam_type, ['Theory', 'Lab'])) {
            $exam_type = 'Theory'; 
        }

        if ($subject === '' || $batch === '' || $exam_date === '' || $semester <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Subject, Batch, Semester, and Exam Date are required']);
            exit;
        }

        $stmt = $mysqli->prepare("INSERT INTO exams (subject, course_code, batch, semester, exam_type, exam_date, session) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssisis", $subject, $course_code, $batch, $semester, $exam_type, $exam_date, $session);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }
    
    
    if ($method === 'PUT') {
        $id = (int)($input['id'] ?? 0);
        $subject = trim($input['subject'] ?? '');
        $course_code = trim($input['course_code'] ?? '');
        $batch = trim($input['batch'] ?? '');
        $semester = (int)($input['semester'] ?? 0);
        $exam_date = trim($input['exam_date'] ?? '');
        $session = trim($input['session'] ?? '');
        $exam_type = trim($input['exam_type'] ?? '');
        
        if (!$id || empty($subject) || empty($batch) || empty($exam_date) || $semester <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing ID, Subject, Batch, Semester, or Date.']);
            exit;
        }

        $stmt = $mysqli->prepare("UPDATE exams SET subject = ?, course_code = ?, batch = ?, semester = ?, exam_type = ?, exam_date = ?, session = ? WHERE id = ?");
        $stmt->bind_param("sssisssi", $subject, $course_code, $batch, $semester, $exam_type, $exam_date, $session, $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'affected' => $stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }

    
    if ($method === 'DELETE') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing exam ID']);
            exit;
        }

        $stmt = $mysqli->prepare("DELETE FROM exams WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'affected' => $stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }
    
    
    if ($method === 'BULK_UPDATE') {
        $old_date = trim($input['old_date'] ?? '');
        $old_session = trim($input['old_session'] ?? '');
        $new_date = trim($input['new_date'] ?? '');
        $reset_session = (bool)($input['reset_session'] ?? false);

        if (empty($new_date)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New exam date is required.']);
            exit;
        }

        $sql = "UPDATE exams SET exam_date = ?";
        $params = "s";
        $values = [$new_date];

        if ($reset_session) {
            $sql .= ", session = 'Morning'"; 
        }

        $where = [];
        
        if (!empty($old_date)) {
            $where[] = "exam_date = ?";
            $params .= "s";
            $values[] = $old_date;
        }
        if (!empty($old_session)) {
            $where[] = "session = ?";
            $params .= "s";
            $values[] = $old_session;
        }

        if (empty($where)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Cannot perform bulk update without an Old Date or Old Session filter.']);
            exit;
        }
        
        $sql .= " WHERE " . implode(' AND ', $where);
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($params, ...$values);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'updated' => $stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }

    // 💡💡💡 NEW BULK DELETE LOGIC 💡💡💡
    if ($method === 'BULK_DELETE') {
        $delete_date = trim($input['delete_date'] ?? '');
        $delete_session = trim($input['delete_session'] ?? '');

        if (empty($delete_date) && empty($delete_session)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please provide at least a date or session to filter for deletion.']);
            exit;
        }

        $sql = "DELETE FROM exams";
        $params = "";
        $values = [];
        $where = [];

        if (!empty($delete_date)) {
            // Deletes all exams ON OR BEFORE this date
            $where[] = "exam_date <= ?";
            $params .= "s";
            $values[] = $delete_date;
        }
        if (!empty($delete_session)) {
            $where[] = "session = ?";
            $params .= "s";
            $values[] = $delete_session;
        }

        $sql .= " WHERE " . implode(' AND ', $where);

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($params, ...$values);

        if ($stmt->execute()) {
            // Note: affected_rows is correct for DELETE
            echo json_encode(['success' => true, 'deleted' => $stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }
    // 💡💡💡 END OF NEW LOGIC 💡💡💡
    
    // Unsupported method
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>