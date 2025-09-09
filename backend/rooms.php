<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// backend/rooms.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require 'db.php';           // DB connection

// Only admin can manage rooms
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// Get HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Helper: read JSON body
function get_json_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    if ($method === 'GET') {
        $result = $mysqli->query("SELECT * FROM room ORDER BY id DESC");
        $rooms = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'rooms' => $rooms]);
        exit;
    }

    $input = get_json_input();

    if ($method === 'POST') {
        // Add room
        $room_no = trim($input['room_no'] ?? '');
        $capacity = (int)($input['capacity'] ?? 0);
        $location = trim($input['location'] ?? '');

        if ($room_no === '' || $capacity <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Room number and capacity are required']);
            exit;
        }

        $stmt = $mysqli->prepare("INSERT INTO room (room_no, capacity, location) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $room_no, $capacity, $location);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }

    if ($method === 'PUT') {
        // Update room
        $id = (int)($input['id'] ?? 0);
        $room_no = trim($input['room_no'] ?? '');
        $capacity = (int)($input['capacity'] ?? 0);
        $location = trim($input['location'] ?? '');

        if (!$id || $room_no === '' || $capacity <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing fields']);
            exit;
        }

        $stmt = $mysqli->prepare("UPDATE room SET room_no = ?, capacity = ?, location = ? WHERE id = ?");
        $stmt->bind_param("sisi", $room_no, $capacity, $location, $id);

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
            echo json_encode(['success' => false, 'message' => 'Missing room ID']);
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

    // Unsupported method
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
