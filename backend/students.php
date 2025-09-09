<?php
// backend/students.php
header('Content-Type: application/json; charset=utf-8');
session_start();

require 'db.php';           // db connection (creates $mysqli)
require 'utils/auth.php';   // require_role() helper in backend/utils/auth.php

// Require admin role for all operations. If you want GET to be public,
// remove or change this line.
require_role(['admin']);

// Determine HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Helper: read JSON body into array
function get_json_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    if ($method === 'GET') {
        // Optional search query (q) support
        $q = isset($_GET['q']) ? trim($_GET['q']) : null;
        if ($q) {
            $stmt = $mysqli->prepare(
                "SELECT id, reg_no, name, batch, department, semester
                 FROM students
                 WHERE reg_no LIKE CONCAT('%', ?, '%')
                    OR name LIKE CONCAT('%', ?, '%')
                 ORDER BY id DESC"
            );
            $stmt->bind_param("ss", $q, $q);
        } else {
            $stmt = $mysqli->prepare(
                "SELECT id, reg_no, name, batch, department, semester
                 FROM students
                 ORDER BY id DESC"
            );
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'students' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input = get_json_input();

    if ($method === 'POST') {
        // Create new student
        $reg    = isset($input['reg_no']) ? trim($input['reg_no']) : '';
        $name   = isset($input['name']) ? trim($input['name']) : '';
        $batch  = isset($input['batch']) ? trim($input['batch']) : '';
        $dept   = isset($input['department']) ? trim($input['department']) : null;
        $sem    = isset($input['semester']) ? (int)$input['semester'] : null;

        // Basic validation
        if ($reg === '' || $name === '' || $batch === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields: reg_no, name, batch']);
            exit;
        }

        $stmt = $mysqli->prepare("INSERT INTO students (reg_no, name, batch, department, semester) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $reg, $name, $batch, $dept, $sem);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        } else {
            // 1062 = duplicate entry (unique constraint violation)
            if ($stmt->errno === 1062) {
                http_response_code(409);
                echo json_encode(['error' => 'Duplicate entry (reg_no already exists)']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Insert failed', 'details' => $stmt->error]);
            }
        }
        exit;
    }

    if ($method === 'PUT') {
        // Update student
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing student id']);
            exit;
        }

        // Read fields (use existing DB values if desired — this is full update)
        $reg   = isset($input['reg_no']) ? trim($input['reg_no']) : '';
        $name  = isset($input['name']) ? trim($input['name']) : '';
        $batch = isset($input['batch']) ? trim($input['batch']) : '';
        $dept  = isset($input['department']) ? trim($input['department']) : null;
        $sem   = isset($input['semester']) ? (int)$input['semester'] : null;

        if ($reg === '' || $name === '' || $batch === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields for update: reg_no, name, batch']);
            exit;
        }

        $stmt = $mysqli->prepare("UPDATE students SET reg_no = ?, name = ?, batch = ?, department = ?, semester = ? WHERE id = ?");
        $stmt->bind_param("ssssii", $reg, $name, $batch, $dept, $sem, $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'affected' => $stmt->affected_rows]);
        } else {
            if ($stmt->errno === 1062) {
                http_response_code(409);
                echo json_encode(['error' => 'Duplicate reg_no']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Update failed', 'details' => $stmt->error]);
            }
        }
        exit;
    }

    if ($method === 'DELETE') {
        // Delete student
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing student id']);
            exit;
        }

        $stmt = $mysqli->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'deleted' => $stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Delete failed', 'details' => $stmt->error]);
        }
        exit;
    }

    // Unsupported method
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
