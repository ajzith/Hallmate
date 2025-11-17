<?php
// backend/utils/auth.php
if (session_status() === PHP_SESSION_NONE) session_start();

function require_role(array $roles = []) {
    if (!isset($_SESSION['role'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    if (!empty($roles) && !in_array($_SESSION['role'], $roles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient privileges']);
        exit;
    }
    // ok
}
