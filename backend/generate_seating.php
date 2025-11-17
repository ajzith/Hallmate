<?php
// FILE: ../backend/generate_seating.php
// (Fully updated to use grid layout, save row/col, and handle single/double seating_type)

ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/db.php';
require 'utils/auth.php'; 
require_role(['admin']); 

function bad($msg, $code = 400) { 
    http_response_code($code); 
    echo json_encode(['success' => false, 'error' => $msg]); 
    exit; 
}
function ok($data = []) { 
    echo json_encode(array_merge(['success' => true], $data)); 
    exit; 
}

$input = json_decode(file_get_contents('php://input'), true);
$primary_exam_id = isset($input['exam_id']) ? (int)$input['exam_id'] : 0;
if (!$primary_exam_id) bad('Missing exam_id');

$mysqli->begin_transaction();
try {
    
    // 1. GET EXAM DETAILS (Date and Session only)
    $stmt_primary_exam = $mysqli->prepare("SELECT exam_date, session FROM exams WHERE id = ? AND exam_type = 'Theory'");
    $stmt_primary_exam->bind_param("i", $primary_exam_id);
    $stmt_primary_exam->execute();
    $exam_details = $stmt_primary_exam->get_result()->fetch_assoc();
    $stmt_primary_exam->close();
    if (!$exam_details) bad('Theory exam not found or specified.', 404);

    $exam_date = $exam_details['exam_date'];
    $session = $exam_details['session'];
    // We NO LONGER fetch the primary exam's semester, as it's misleading.

    
    // 2. GET ALL EXAMS (matching date and session ONLY)
    $stmt_all_exams = $mysqli->prepare("SELECT id, batch, semester FROM exams WHERE exam_date = ? AND session = ? AND exam_type = 'Theory'");
    $stmt_all_exams->bind_param("ss", $exam_date, $session); // Removed semester filter
    $stmt_all_exams->execute();
    $all_exams_details = $stmt_all_exams->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_all_exams->close();
    
    if (empty($all_exams_details)) bad('No exams found for this time slot.');

    // Build a lookup map: [batch_name][semester] => exam_id
    // And a simple list of all exam IDs for deletion
    $exam_lookup_map = [];
    $all_exam_ids = [];
    foreach ($all_exams_details as $exam) {
        if (!isset($exam_lookup_map[$exam['batch']])) {
            $exam_lookup_map[$exam['batch']] = [];
        }
        $exam_lookup_map[$exam['batch']][$exam['semester']] = (int)$exam['id'];
        $all_exam_ids[] = (int)$exam['id'];
    }
    
    if (empty($all_exam_ids)) bad('No valid exams found for this exam slot.');

    
    // 3. GET ROOMS (Unchanged)
    $rooms_res = $mysqli->query("SELECT id, room_no, total_rows, total_cols, seating_type FROM room 
        WHERE total_rows > 0 AND total_cols > 0 
        AND (room_no NOT LIKE 'CS Lab %' AND room_no NOT LIKE 'VM %' AND room_no != 'DA LAB') 
        ORDER BY room_no ASC");
    $rooms = $rooms_res->fetch_all(MYSQLI_ASSOC);
    if (empty($rooms)) bad('No "Theory" rooms with a grid (rows > 0, cols > 0) are available or seating_type not set.');

    
    // 4. GET ALL STUDENTS (matching specific batch/semester PAIRS)
    $all_students_list = [];
    
    $where_conditions = [];
    $params = [];
    $types = "";
    
    // Build dynamic WHERE clause: (course_name = ? AND semester = ?) OR (course_name = ? AND semester = ?) ...
    foreach ($all_exams_details as $exam) {
        $where_conditions[] = "(course_name = ? AND semester = ?)";
        $params[] = $exam['batch'];
        $params[] = $exam['semester'];
        $types .= "si";
    }
    
    if (empty($where_conditions)) {
         bad("No exam criteria to search for students.");
    }
    
    $where_clause = implode(" OR ", $where_conditions);

    // We MUST select student's semester to map them back to the correct exam_id
    $stmt_list = $mysqli->prepare("SELECT id, reg_no, name, course_name, semester FROM students 
        WHERE ($where_clause) AND is_approved = 1");
    
    $stmt_list->bind_param($types, ...$params);
    $stmt_list->execute();
    $students_raw = $stmt_list->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_list->close();
    
    // Map students to their correct exam_id using the lookup map
    foreach ($students_raw as $student) {
        $batch = $student['course_name'];
        $sem = $student['semester'];
        
        // Find the exam_id for this student
        if (isset($exam_lookup_map[$batch][$sem])) {
            $student['exam_id'] = $exam_lookup_map[$batch][$sem]; 
            $all_students_list[] = $student;
        }
        // else: A student was found (e.g., approved=1) but their matching exam
        // was deleted or doesn't exist. They are safely ignored.
    }

    if (empty($all_students_list)) bad("No approved students found for any exam in this time slot.");

    shuffle($all_students_list); // Randomize student order

    
    // 5. DELETE OLD SEATING (Now uses the correct list of all exam IDs)
    $placeholders = implode(',', array_fill(0, count($all_exam_ids), '?'));
    $stmt_delete = $mysqli->prepare("DELETE FROM seating WHERE exam_id IN ($placeholders)");
    $stmt_delete->bind_param(str_repeat('i', count($all_exam_ids)), ...$all_exam_ids);
    $stmt_delete->execute();
    $stmt_delete->close();
    
    // 6. PREPARE STUDENT LISTS (A/B splitting) - (Unchanged)
    $seating_plan = [];
    $room_summary = [];
    $total_assigned = 0;
    
    $list_A = []; // Students for odd columns / Left side
    $list_B = []; // Students for even columns / Right side
    foreach ($all_students_list as $i => $student) {
        if ($i % 2 == 0) {
            $list_A[] = $student;
        } else {
            $list_B[] = $student;
        }
    }

    // 7. NEW GRID-BASED SEATING ALGORITHM - (Unchanged)
    foreach ($rooms as $room) {
        $students_in_this_room = 0;
        $total_room_capacity = $room['total_rows'] * $room['total_cols'] * ($room['seating_type'] === 'double' ? 2 : 1);

        for ($r = 1; $r <= $room['total_rows']; $r++) {
            for ($c = 1; $c <= $room['total_cols']; $c++) {
                
                if ($room['seating_type'] === 'single') {
                    // --- SINGLE DESK LOGIC ---
                    $student = null;
                    if ($c % 2 == 1) { // Odd column
                        if (!empty($list_A)) $student = array_pop($list_A);
                    } else { // Even column
                        if (!empty($list_B)) $student = array_pop($list_B);
                    }

                    // If preferred list is empty, take from the other list
                    if ($student === null) {
                        if (!empty($list_A)) $student = array_pop($list_A);
                        elseif (!empty($list_B)) $student = array_pop($list_B);
                        else break 3; // Break all room/row/col loops (all students assigned)
                    }

                    if ($student === null) break 3; 

                    $seating_plan[] = [
                        'exam_id' => $student['exam_id'],
                        'room_id' => $room['id'],
                        'student_id' => $student['id'],
                        'seat_no' => $room['room_no'] . '-' . (($r - 1) * $room['total_cols'] + $c), // A501-1, A501-2...
                        'seat_row' => $r,
                        'seat_col' => $c // For single, this column is the actual desk column
                    ];
                    $students_in_this_room++;

                } elseif ($room['seating_type'] === 'double') {
                    // --- DOUBLE BENCH LOGIC ---
                    
                    // Student for Left side of the bench
                    $student_L = null;
                    if (!empty($list_A)) $student_L = array_pop($list_A);
                    elseif (!empty($list_B)) $student_L = array_pop($list_B); // If A is exhausted, take from B
                    
                    if ($student_L) {
                        $seating_plan[] = [
                            'exam_id' => $student_L['exam_id'],
                            'room_id' => $room['id'],
                            'student_id' => $student_L['id'],
                            'seat_no' => $room['room_no'] . '-' . (($r - 1) * $room['total_cols'] + $c) . '-L', // A503-1-L
                            'seat_row' => $r,
                            'seat_col' => $c // For double, this column is the bench column
                        ];
                        $students_in_this_room++;
                    } else {
                        // If no more students for L, there won't be for R either
                        break 3; 
                    }

                    // Student for Right side of the bench
                    $student_R = null;
                    if (!empty($list_B)) $student_R = array_pop($list_B);
                    elseif (!empty($list_A)) $student_R = array_pop($list_A); // If B is exhausted, take from A

                    if ($student_R) {
                        $seating_plan[] = [
                            'exam_id' => $student_R['exam_id'],
                            'room_id' => $room['id'],
                            'student_id' => $student_R['id'],
                            'seat_no' => $room['room_no'] . '-' . (($r - 1) * $room['total_cols'] + $c) . '-R', // A503-1-R
                            'seat_row' => $r,
                            'seat_col' => $c // For double, this column is the bench column
                        ];
                        $students_in_this_room++;
                    } else {
                        // If no more students for R, break all loops
                        break 3; 
                    }
                }
            }
        }
        
        if ($students_in_this_room > 0) {
            $room_summary[] = [
                'room_name' => $room['room_no'],
                'assigned' => $students_in_this_room,
                'capacity' => $total_room_capacity,
            ];
            $total_assigned += $students_in_this_room;
        }
    }
    
    if (empty($seating_plan)) {
        throw new Exception("No students were assigned. (Total students found: " . count($all_students_list) . ")");
    }
    
    // 8. INSERT NEW SEATING PLAN - (Unchanged)
    $stmt_insert = $mysqli->prepare("INSERT INTO seating (exam_id, room_id, student_id, seat_no, seat_row, seat_col) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($seating_plan as $seat) {
        $stmt_insert->bind_param("iiisii", 
            $seat['exam_id'], 
            $seat['room_id'], 
            $seat['student_id'], 
            $seat['seat_no'],
            $seat['seat_row'],
            $seat['seat_col']
        );
        $stmt_insert->execute();
    }
    $stmt_insert->close();
    
    $mysqli->commit();
    $remaining = count($list_A) + count($list_B);
    ok([
        'message' => "Seating generated successfully! Assigned $total_assigned students.",
        'summary' => $room_summary,
        'remaining_students' => $remaining
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Seating Algorithm Error: " . $e->getMessage());
    bad("Error: " . $e->getMessage(), 500);
}
?>