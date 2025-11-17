<?php
// backend/rooms.php

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

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
        
        $sql = "SELECT * FROM room";
        
        if ($filter_type === 'Lab') {
            $sql .= " WHERE room_no LIKE 'CS Lab %' OR room_no LIKE 'VM %' OR room_no = 'DA LAB' "; 
        }
        
        $sql .= " ORDER BY room_no ASC";
        $result = $mysqli->query($sql);

        $rooms = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'rooms' => $rooms]);
        exit;
    }

    if ($method === 'POST') {
        $room_no = trim($input['room_no'] ?? '');
        $capacity = (int)($input['capacity'] ?? 0);
        $location = trim($input['location'] ?? '');
        $seating_type = trim($input['seating_type'] ?? 'single'); 
        $block = trim($input['block'] ?? '');
        $floor = (int)($input['floor'] ?? 0);

        if ($room_no === '' || $capacity <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Room number and capacity are required']);
            exit;
        }
        $stmt = $mysqli->prepare("INSERT INTO room (room_no, capacity, location, seating_type, block, floor) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisssi", $room_no, $capacity, $location, $seating_type, $block, $floor);
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
        $room_no = trim($input['room_no'] ?? '');
        $capacity = (int)($input['capacity'] ?? 0);
        $location = trim($input['location'] ?? '');
        $seating_type = trim($input['seating_type'] ?? 'single'); 
        $block = trim($input['block'] ?? '');
        $floor = (int)($input['floor'] ?? 0);

        if (!$id || $room_no === '' || $capacity <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing fields: id, room_no, and capacity are required.']);
            exit;
        }
        $stmt = $mysqli->prepare("UPDATE room SET room_no = ?, capacity = ?, location = ?, seating_type = ?, block = ?, floor = ? WHERE id = ?");
        $stmt->bind_param("sisssii", $room_no, $capacity, $location, $seating_type, $block, $floor, $id);
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
            echo json_encode(['success' => false, 'message' => 'Missing room ID in request body']);
            exit;
        }
        $stmt = $mysqli->prepare("DELETE FROM room WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'deleted' => $stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>