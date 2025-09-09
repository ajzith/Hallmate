<?php
// backend/exams.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require 'db.php';
require 'utils/auth.php'; // ensure you have this file, like in students.php

// Only admin can access
require_role(['admin']);

$method = $_SERVER['REQUEST_METHOD'];

function get_json_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    if ($method === 'GET') {
        $stmt = $mysqli->prepare("SELECT * FROM exams ORDER BY exam_date ASC");
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success'=>true,'exams'=>$rows]);
        exit;
    }

    $input = get_json_input();

    if ($method === 'POST') {
        $subject = trim($input['subject'] ?? '');
        $course_code = trim($input['course_code'] ?? '');
        $batch = trim($input['batch'] ?? '');
        $exam_date = trim($input['exam_date'] ?? '');
        $session = trim($input['session'] ?? '');
        $expected_students = (int)($input['expected_students'] ?? 0);

        if (!$subject || !$course_code || !$batch || !$exam_date || !$session) {
            http_response_code(400);
            echo json_encode(['error'=>'Missing required fields']);
            exit;
        }

        $stmt = $mysqli->prepare("INSERT INTO exams (subject, course_code, batch, exam_date, session, expected_students) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $subject, $course_code, $batch, $exam_date, $session, $expected_students);

        if ($stmt->execute()) {
            echo json_encode(['success'=>true,'id'=>$stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error'=>'Insert failed', 'details'=>$stmt->error]);
        }
        exit;
    }

    if ($method === 'PUT') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error'=>'Missing exam ID']); exit; }

        $subject = trim($input['subject'] ?? '');
        $course_code = trim($input['course_code'] ?? '');
        $batch = trim($input['batch'] ?? '');
        $exam_date = trim($input['exam_date'] ?? '');
        $session = trim($input['session'] ?? '');
        $expected_students = (int)($input['expected_students'] ?? 0);

        $stmt = $mysqli->prepare("UPDATE exams SET subject=?, course_code=?, batch=?, exam_date=?, session=?, expected_students=? WHERE id=?");
        $stmt->bind_param("ssssiii", $subject, $course_code, $batch, $exam_date, $session, $expected_students, $id);

        if ($stmt->execute()) {
            echo json_encode(['success'=>true,'affected'=>$stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['error'=>'Update failed', 'details'=>$stmt->error]);
        }
        exit;
    }

    if ($method === 'DELETE') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error'=>'Missing exam ID']); exit; }

        $stmt = $mysqli->prepare("DELETE FROM exams WHERE id=?");
        $stmt->bind_param("i",$id);
        if ($stmt->execute()) {
            echo json_encode(['success'=>true,'deleted'=>$stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['error'=>'Delete failed', 'details'=>$stmt->error]);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['error'=>'Method not allowed']);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>'Server error','message'=>$e->getMessage()]);
}
?>
