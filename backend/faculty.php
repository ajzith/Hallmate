<?php
// backend/faculty.php
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
        $stmt = $mysqli->prepare("SELECT id, name, username, email, department, is_approved FROM faculty ORDER BY is_approved ASC, id DESC");
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'faculty' => $rows]);
        exit;
    }

    
    if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'approve_bulk') {
        $input = get_json_input(); 
        $faculty_to_approve = $input['faculty'] ?? [];
        if (empty($faculty_to_approve)) { http_response_code(400); echo json_encode(['error'=>'No faculty selected']); exit; }
        
        $mysqli->begin_transaction();
        try {
            $stmt_approve = $mysqli->prepare("UPDATE faculty SET is_approved = 1 WHERE id = ?");
            $stmt_activate = $mysqli->prepare("UPDATE login SET is_active = 1 WHERE username = ?"); 
            $errors = [];

            
            foreach ($faculty_to_approve as $fac) {
                $faculty_id = (int)$fac['id'];
                $username = $fac['username'];
                $email = $fac['email']; 
                $name = $fac['name'];   
                
              
                $stmt_approve->bind_param("i", $faculty_id);
                if (!$stmt_approve->execute() || $stmt_approve->affected_rows === 0) { $errors[] = "Failed approve ID {$faculty_id}"; }
                
                
                $stmt_activate->bind_param("s", $username);
                if (!$stmt_activate->execute() || $stmt_activate->affected_rows === 0) { $errors[] = "Failed activate {$username}"; }
                
                
                error_log("Attempting to send approval email to: {$name} ({$email})");
                sendApprovalEmail($name, $email, $username);
                error_log("Finished email attempt for: {$username}");
            }
            
            if (!empty($errors)) { throw new Exception(implode("; ", $errors)); }
            $mysqli->commit(); echo json_encode(['success' => true]);
        } catch (Exception $e) { 
            $mysqli->rollback(); 
            http_response_code(500); 
            echo json_encode(['error' => 'Transaction failed', 'details' => $e->getMessage()]); 
        }
        exit;
    }

   
    $input = get_json_input();

   
    if ($method === 'POST') {
        $name = trim($input['name'] ?? '');
        $username = trim($input['username'] ?? ''); // Get username
        $email = trim($input['email'] ?? '');
        $department = trim($input['department'] ?? '');
        if (empty($name) || empty($username) || empty($email)) { http_response_code(400); echo json_encode(['error'=>'Name, Username, Email required']); exit;}
        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) { http_response_code(400); echo json_encode(['error'=>'Invalid username format']); exit; }

        $defaultPassword = $username; 
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

        $mysqli->begin_transaction();
        try {
            
            $stmt_check_user = $mysqli->prepare("SELECT id FROM login WHERE username = ?");
            $stmt_check_user->bind_param("s", $username); $stmt_check_user->execute(); $stmt_check_user->store_result();
            if($stmt_check_user->num_rows > 0) { throw new mysqli_sql_exception("Username already exists in login table.", 1062); }
            $stmt_check_user->close();
            
            $stmt_check_email = $mysqli->prepare("SELECT id FROM faculty WHERE email = ?");
            $stmt_check_email->bind_param("s", $email); $stmt_check_email->execute(); $stmt_check_email->store_result();
             if($stmt_check_email->num_rows > 0) { throw new mysqli_sql_exception("Email already exists in faculty table.", 1062); }
             $stmt_check_email->close();

            
            $stmt_faculty = $mysqli->prepare("INSERT INTO faculty (name, username, email, department, is_approved) VALUES (?, ?, ?, ?, 1)"); 
            $stmt_faculty->bind_param("ssss", $name, $username, $email, $department);
            $stmt_faculty->execute(); $new_faculty_id = $stmt_faculty->insert_id; $stmt_faculty->close();

            
            $role = 'faculty';
            $stmt_login = $mysqli->prepare("INSERT INTO login (username, password_hash, role, is_active, faculty_id) VALUES (?, ?, ?, 1, ?)"); 
            $stmt_login->bind_param("sssi", $username, $hashedPassword, $role, $new_faculty_id);
            $stmt_login->execute(); $stmt_login->close();

            $mysqli->commit();
            echo json_encode(['success' => true, 'id' => $new_faculty_id, 'default_password' => $defaultPassword]); // Password is username

        } catch (mysqli_sql_exception $e) { 
            $mysqli->rollback();
            if ($e->getCode() == 1062) { http_response_code(409); echo json_encode(['error' => 'Username or Email already exists.']); } 
            else { http_response_code(500); echo json_encode(['error' => 'DB error.', 'details' => $e->getMessage()]); }
        } catch (Exception $e) { 
            $mysqli->rollback(); 
            http_response_code(500); 
            echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]); 
        }
        exit;
    }

    
    if ($method === 'PUT') {
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $username = trim($input['username'] ?? ''); 
        $email = trim($input['email'] ?? '');
        $department = trim($input['department'] ?? '');
        
        if (!$id || !$name || !$username || !$email) { http_response_code(400); echo json_encode(['error'=>'ID, Name, Username, Email required']); exit;}

        $stmt = $mysqli->prepare("UPDATE faculty SET name = ?, username = ?, email = ?, department = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $username, $email, $department, $id);
        if ($stmt->execute()) { echo json_encode(['success' => true, 'affected' => $stmt->affected_rows]); }
        else { http_response_code(500); echo json_encode(['error' => 'Update failed', 'details' => $stmt->error]); }
        exit;
    }

    
    if ($method === 'DELETE') {
        
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        
        if (!$id) { 
            http_response_code(400); 
            echo json_encode(['error' => 'Missing faculty id in request body']); 
            exit; 
        }

        
        $stmt_get = $mysqli->prepare("SELECT username FROM faculty WHERE id = ?"); 
        $stmt_get->bind_param("i", $id); $stmt_get->execute(); $result = $stmt_get->get_result(); $faculty = $result->fetch_assoc();
        $username_to_delete = $faculty ? $faculty['username'] : null; 
        $stmt_get->close();

        $mysqli->begin_transaction();
        try {
            
            $stmt_del_faculty = $mysqli->prepare("DELETE FROM faculty WHERE id = ?");
            $stmt_del_faculty->bind_param("i", $id); $stmt_del_faculty->execute(); $affected_faculty = $stmt_del_faculty->affected_rows; $stmt_del_faculty->close();

            
            if ($username_to_delete) {
                $stmt_del_login = $mysqli->prepare("DELETE FROM login WHERE username = ?");
                $stmt_del_login->bind_param("s", $username_to_delete); $stmt_del_login->execute(); $stmt_del_login->close();
            }

            if ($affected_faculty > 0) { 
                $mysqli->commit(); 
                echo json_encode(['success' => true]); 
            } else { 
                $mysqli->rollback(); 
                http_response_code(404); 
                echo json_encode(['error' => 'Faculty with that ID not found.']); 
            }

        } catch (Exception $e) { 
            $mysqli->rollback(); 
            http_response_code(500); 
            echo json_encode(['error' => 'Delete transaction failed', 'details' => $e->getMessage()]); 
        }
        exit;
    }

    http_response_code(405); echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) { http_response_code(500); echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]); }
?>