<?php
// backend/students.php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/db.php';
require 'utils/auth.php'; 
require_once __DIR__ . '/utils/send_email.php'; 
require_role(['admin']);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST' && isset($_GET['_method'])) {
    $method = strtoupper($_GET['_method']); 
}

function get_json_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    if ($method === 'GET') {
        $stmt = $mysqli->prepare("SELECT id, reg_no, name, email, course_name, batch, department, semester, is_approved FROM students ORDER BY is_approved ASC, id DESC");
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'students' => $rows]);
        exit;
    }

    if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'approve_bulk') {
        $input = get_json_input();
        $students_to_approve = $input['students'] ?? [];
        if (empty($students_to_approve)) { http_response_code(400); echo json_encode(['error'=>'No students selected']); exit; }
        
        $mysqli->begin_transaction();
        try {
            $stmt_approve = $mysqli->prepare("UPDATE students SET is_approved = 1 WHERE id = ?");
            $stmt_activate = $mysqli->prepare("UPDATE login SET is_active = 1 WHERE username = ?");
            $errors = [];
            foreach ($students_to_approve as $student) { 
                $student_id = (int)$student['id']; 
                $reg_no = $student['reg_no'];
                $email = $student['email'];
                $name = $student['name'];
                $stmt_approve->bind_param("i", $student_id);
                if (!$stmt_approve->execute() || $stmt_approve->affected_rows === 0) { $errors[] = "Failed approve ID {$student_id}"; }
                $stmt_activate->bind_param("s", $reg_no);
                if (!$stmt_activate->execute() || $stmt_activate->affected_rows === 0) { $errors[] = "Failed activate {$reg_no}"; }
                sendApprovalEmail($name, $email, $reg_no);
            }
            if (!empty($errors)) { throw new Exception(implode("; ", $errors)); }
            $mysqli->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) { 
            $mysqli->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Transaction failed', 'details' => $e->getMessage()]);
        }
        exit;
    }
    
    // 💡 NEW "PROMOTE ALL" LOGIC 💡
    if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'promote_all') {
        // We set a max of 10, so 9th sem becomes 10th, but 10th stays 10th.
        $stmt = $mysqli->prepare("UPDATE students SET semester = semester + 1 WHERE is_approved = 1 AND semester < 10");
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'affected' => $stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        exit;
    }
    // 💡 END OF NEW LOGIC 💡
    
    $input = get_json_input();

    if ($method === 'POST') {
        $reg_no = strtolower(trim($input['reg_no'] ?? ''));
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $course_name = trim($input['course_name'] ?? ''); 
        $batch = trim($input['batch'] ?? '');
        $department = trim($input['department'] ?? '');
        $semester = (int)($input['semester'] ?? 0);
        if (empty($reg_no) || empty($name) || empty($email) || empty($course_name) || empty($batch) || empty($department) || $semester <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'All fields are required.']);
            exit;
        }
        $defaultPassword = $reg_no;
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
        $mysqli->begin_transaction();
        try {
            $stmt_student = $mysqli->prepare("INSERT INTO students (reg_no, name, email, course_name, batch, department, semester, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt_student->bind_param("ssssssi", $reg_no, $name, $email, $course_name, $batch, $department, $semester);
            $stmt_student->execute();
            $new_student_id = $stmt_student->insert_id;
            $stmt_student->close();
            $role = 'student';
            $stmt_login = $mysqli->prepare("INSERT INTO login (username, password_hash, role, is_active, student_id) VALUES (?, ?, ?, 1, ?)");
            $stmt_login->bind_param("sssi", $reg_no, $hashedPassword, $role, $new_student_id);
            $stmt_login->execute();
            $stmt_login->close();
            $mysqli->commit();
            echo json_encode(['success' => true, 'id' => $new_student_id, 'default_password' => $defaultPassword]);
        } catch (mysqli_sql_exception $e) {
            $mysqli->rollback();
            if ($e->getCode() == 1062) {
                http_response_code(409);
                echo json_encode(['error' => 'Register Number (username) or Email already exists.']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database error.', 'details' => $e->getMessage()]);
            }
        } catch (Exception $e) {
            $mysqli->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'An unexpected error occurred.', 'details' => $e->getMessage()]);
        }
        exit;
    }

    if ($method === 'PUT') {
        $id = (int)($input['id'] ?? 0);
        $reg_no = strtolower(trim($input['reg_no'] ?? ''));
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $course_name = trim($input['course_name'] ?? '');
        $batch = trim($input['batch'] ?? '');
        $department = trim($input['department'] ?? '');
        $semester = (int)($input['semester'] ?? 0);
        if (!$id || empty($reg_no) || empty($name) || empty($email) || empty($course_name) || empty($batch) || empty($department) || $semester <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'All fields are required for an update.']);
            exit;
        }
        $stmt = $mysqli->prepare("UPDATE students SET reg_no = ?, name = ?, email = ?, course_name = ?, batch = ?, department = ?, semester = ? WHERE id = ?");
        $stmt->bind_param("ssssssii", $reg_no, $name, $email, $course_name, $batch, $department, $semester, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'affected_rows' => $stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Update failed', 'details' => $stmt->error]);
        }
        exit;
    }

    if ($method === 'DELETE') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) { 
            http_response_code(400); 
            echo json_encode(['error' => 'Missing student id in request body']); 
            exit; 
        }
        $stmt_get = $mysqli->prepare("SELECT reg_no FROM students WHERE id = ?");
        $stmt_get->bind_param("i", $id); $stmt_get->execute(); $result = $stmt_get->get_result(); $student = $result->fetch_assoc();
        $username_to_delete = $student ? $student['reg_no'] : null; $stmt_get->close();
        $mysqli->begin_transaction();
        try {
            $stmt_del_student = $mysqli->prepare("DELETE FROM students WHERE id = ?");
            $stmt_del_student->bind_param("i", $id); $stmt_del_student->execute(); $affected_students = $stmt_del_student->affected_rows; $stmt_del_student->close();
            if ($username_to_delete) {
                $stmt_del_login = $mysqli->prepare("DELETE FROM login WHERE username = ?");
                $stmt_del_login->bind_param("s", $username_to_delete); $stmt_del_login->execute(); $stmt_del_login->close();
            }
            if ($affected_students > 0) {
                 $mysqli->commit();
                 echo json_encode(['success' => true]);
            } else {
                 $mysqli->rollback(); 
                 http_response_code(404); 
                 echo json_encode(['error' => 'Student with the specified ID not found.']);
            }
        } catch (Exception $e) { 
            $mysqli->rollback(); 
            error_log("Delete Error: " . $e->getMessage()); 
            http_response_code(500); 
            echo json_encode(['error' => 'Delete transaction failed.', 'details' => $e->getMessage()]); 
        }
        exit;
    }

    http_response_code(405); echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) { 
    http_response_code(500); 
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]); 
}
?>