<?php
// backend/perform_reset.php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? ''); 
$new_password = trim($input['password'] ?? '');

if (empty($token) || empty($new_password)) {
    http_response_code(400); echo json_encode(['error' => 'Token and new password are required.']); exit;
}
if (strlen($new_password) < 6) {
     http_response_code(400); echo json_encode(['error' => 'Password must be at least 6 characters long.']); exit;
}

try {

    $stmt_find = $mysqli->prepare("SELECT id, password_reset_token, password_reset_expires FROM login WHERE password_reset_expires > UTC_TIMESTAMP() AND password_reset_token IS NOT NULL");

    if (!$stmt_find) throw new Exception("DB Prepare Error (find token): " . $mysqli->error);

    $stmt_find->execute();
    $result = $stmt_find->get_result();
   

    $user_id_to_update = null;
    $found_valid_token = false;

    while ($row = $result->fetch_assoc()) {
        
        if (password_verify($token, $row['password_reset_token'])) {
            $user_id_to_update = $row['id'];
            $found_valid_token = true; 
            break; 
        } else {
            // error_log("FAIL: password_verify did NOT match for login ID: " . $row['id']); 
        }
    }
    $stmt_find->close();


    if ($found_valid_token && $user_id_to_update !== null) {
       
        $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt_update = $mysqli->prepare("UPDATE login SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?");
        if (!$stmt_update) throw new Exception("DB Prepare Error (update password): " . $mysqli->error);

        $stmt_update->bind_param("si", $new_hashed_password, $user_id_to_update);

        if ($stmt_update->execute()) {
             echo json_encode(['success' => true]);
        } else {
            error_log("FAIL: Execute failed (update password) for login ID " . $user_id_to_update . ": " . $stmt_update->error);
            throw new Exception("Failed to update password.");
        }
        $stmt_update->close();

    } else {
        http_response_code(400); 
        echo json_encode(['error' => 'Invalid or expired password reset token.']);
    }

} catch (mysqli_sql_exception $e) { 
    error_log("perform_reset.php SQL Error Code " . $e->getCode() . ": " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'A database error occurred while resetting the password.']);
}
catch (Exception $e) {
    error_log("perform_reset.php General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred while resetting the password.']);
} finally {
    if (isset($mysqli) && $mysqli->ping()) { $mysqli->close(); }
}
?>