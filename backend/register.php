<?php
// backend/register.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/db.php';

// 💡 UPDATED: Server-side validation rules
$batchRules = [
    // Computer Science Batches
    "Int MCA 2021 Batch A" => ['pattern' => '/^kh\.sc\.i5mca21\d{3}$/i'],
    "Int MCA 2021 Batch B" => ['pattern' => '/^kh\.sc\.i5mca21\d{3}$/i'],
    "Int MCA 2021 Batch C" => ['pattern' => '/^kh\.sc\.i5mca21\d{3}$/i'],
    "Int MCA 2022 Batch A" => ['pattern' => '/^kh\.en\.i5mca22\d{3}$/i'],
    "Int MCA 2022 Batch B" => ['pattern' => '/^kh\.en\.i5mca22\d{3}$/i'],
    "2023 BCA Data Science" => ['pattern' => '/^kh\.sc\.u3cds23\d{3}$/i', 'min' => 1, 'max' => 67],
    "2023 Int MCA A Batch" => ['pattern' => '/^kh\.sc\.i5mca23\d{3}$/i', 'min' => 1, 'max' => 56],
    "2023 BCA A" => ['pattern' => '/^kh\.sc\.u3bca23\d{3}$/i', 'min' => 1, 'max' => 48],
    "2023 BCA B" => ['pattern' => '/^kh\.sc\.u3bca23\d{3}$/i', 'min' => 49, 'max' => 95],
    "2023 Int MCA B Batch" => ['pattern' => '/^kh\.sc\.i5mca23\d{3}$/i', 'min' => 57, 'max' => 114],
    "2024 BCA A (H)" => ['pattern' => '/^kh\.en\.u4bca24\d{3}$/i', 'min' => 1, 'max' => 58],
    "2024 BCA B (H)" => ['pattern' => '/^kh\.en\.u4bca24\d{3}$/i', 'min' => 101, 'max' => 158],
    "2024 BCA C (H)" => ['pattern' => '/^kh\.en\.u4bca24\d{3}$/i', 'min' => 201, 'max' => 259],
    "2024 MCA III Semester Batch A" => ['pattern' => '/^kh\.en\.p2mca24\d{3}$/i', 'min' => 1, 'max' => 60],
    "2024 MCA III Semester Batch B" => ['pattern' => '/^kh\.en\.p2mca24\d{3}$/i', 'min' => 101, 'max' => 160],
    "MCA 2025 Regular" => ['pattern' => '/^kh\.en\.p2mca25\d{3}$/i', 'min' => 1, 'max' => 56],
    "MCA 2025 AI&DS" => ['pattern' => '/^kh\.en\.p2mca25\d{3}$/i', 'min' => 101, 'max' => 162],
    "MCA 2025 Cybersecurity" => ['pattern' => '/^kh\.en\.p2mca25\d{3}$/i', 'min' => 201, 'max' => 260],
    
    // Mathematics Batches
    "2021 Int M.Sc Mathematics IX Semester" => ['pattern' => '/^kh\.sc\.i5mat21\d{3}$/i'],
    "2022 Int M.Sc Mathematics VII Semester" => ['pattern' => '/^kh\.ps\.i5mat22\d{3}$/i'],
    "2024 M.Sc Applied Statistics and Data Analytics III Semester" => ['pattern' => '/^kh\.ps\.p2asd24\d{3}$/i'],
    "2024 M.Sc Data Science with Logistics and Supply Chain Management III Semester" => ['pattern' => '/^kh\.ps\.p2dls24\d{3}$/i'],
    "2025 M.Sc Applied Statistics and Data Analytics I Sem" => ['pattern' => '/^kh\.ps\.p2asd25\d{3}$/i'],

    // English Batches
    "2023 Int. MA English Language and Literature V Semester" => ['pattern' => '/^kh\.ah\.i5eng23\d{3}$/i'],
    "2021 Int. MA English Language and Literature IX Semester" => ['pattern' => '/^kh\.ar\.i5eng21\d{3}$/i'],
    "2024 MA English Language and Literature III Semester" => ['pattern' => '/^kh\.ah\.p2ell24\d{3}$/i'],
    
    // Physics Batches
    "2021 Int. M.Sc. Physics IX Semester" => ['pattern' => '/^kh\.sc\.i5phy21\d{3}$/i'],
    "2022 Int. M.Sc. Physics VII Semester" => ['pattern' => '/^kh\.ps\.i5phy22\d{3}$/i'],
    "2023 Int. M.Sc. Physics V Semester" => ['pattern' => '/^kh\.ps\.i5phy23\d{3}$/i'],
    "2024 B.Sc. (H) Applied Physics III Semester" => ['pattern' => '/^kh\.ps\.u4phy24\d{3}$/i'],

    // Visual Media Batches
    "2024 B.Des. III Semester" => ['pattern' => '/^kh\.ah\.u4des24\d{3}$/i', 'min' => 1, 'max' => 21],
    "2024 B.Sc. VM III Semester" => ['pattern' => '/^kh\.ah\.u4vmc24\d{3}$/i', 'min' => 1, 'max' => 33],
    "2023 B.Des. V Semester" => ['pattern' => '/^kh\.ah\.u4des23\d{3}$/i', 'min' => 1, 'max' => 26],
    "2023 B.Sc. VM V Semester" => ['pattern' => '/^kh\.ah\.u3vmc23\d{3}$/i', 'min' => 1, 'max' => 11],
    "2022 B.Des. VII semester" => ['pattern' => '/^kh\.ah\.u4des22\d{3}$/i', 'min' => 1, 'max' => 22],
    "2025 MA JMC I Semester" => ['pattern' => '/^kh\.ah\.p2jmc25\d{3}$/i', 'min' => 1, 'max' => 9],
    "2025 MA (VMC) I Semester" => ['pattern' => '/^kh\.ah\.p2vmc25\d{3}$/i', 'min' => 1, 'max' => 11],
    "2025 MFA AAA I Semester" => ['pattern' => '/^kh\.ah\.p2aaa25\d{3}$/i', 'min' => 1, 'max' => 10],
    "2024 MA JMC III Semester" => ['pattern' => '/^kh\.ah\.p2jmc24\d{3}$/i', 'min' => 1, 'max' => 13],
    "2024 MA VMC III Semester" => ['pattern' => '/^kh\.ah\.p2vmc24\d{3}$/i', 'min' => 1, 'max' => 6],
    "2024 MFA AAA" => ['pattern' => '/^kh\.ah\.p2aaa24\d{3}$/i'],
    "2024 MFA AVFX" => ['pattern' => '/^kh\.ah\.p2avx24\d{3}$/i', 'min' => 1, 'max' => 2],

    // Commerce & BBA
    "2024 M.Com. Finance and Systems III Semester" => ['pattern' => '/^kh\.ah\.p2com24\d{3}$/i', 'min' => 1, 'max' => 12],
    "2025 M.Com. Finance and Systems I Semester" => ['pattern' => '/^kh\.ah\.p2com25\d{3}$/i', 'min' => 1, 'max' => 11],
    "2022 B.Com. (FINTECH) VII Semester" => ['pattern' => '/^kh\.ah\.u4com22\d{3}$/i', 'min' => 1, 'max' => 25],
    "2023 B.Com. (FINTECH) V Semester" => ['pattern' => '/^kh\.ah\.u4com23\d{3}$/i', 'min' => 1, 'max' => 40],
    "2023 B.Com. (TAXATION & FINANCE) V Semester" => ['pattern' => '/^kh\.ah\.u3com23\d{3}$/i', 'min' => 1, 'max' => 40],
    "2023 BBA V Semester" => ['pattern' => '/^kh\.ah\.u3bba23\d{3}$/i', 'min' => 1, 'max' => 28],
    "2024 B.Com. (FINTECH) III Semester" => ['pattern' => '/^kh\.ah\.u4fin24\d{3}$/i', 'min' => 1, 'max' => 32],
    "2024 B.Com. (TAXATION & FINANCE) III Semester" => ['pattern' => '/^kh\.ah\.u4tax24\d{3}$/i', 'min' => 1, 'max' => 28],
    "2024 BBA III Semester" => ['pattern' => '/^kh\.ah\.u4bba24\d{3}$/i', 'min' => 1, 'max' => 32],
    
    // 💡 REMOVED 5 generic 2025 BCom/BBA/BDes/BSc batches

    // 💡 NEW: 2025 B.Com/BBA batches with languages
    "2025 B.Com.(Honours) with Research in FINTECH I Semester (Hindi)" => ['pattern' => '/^kh\.ah\.u4fin25\d{3}$/i', 'min' => 1, 'max' => 57],
    "2025 B.Com.(Honours) with Research in FINTECH I Semester (Sanskrit)" => ['pattern' => '/^kh\.ah\.u4fin25\d{3}$/i', 'min' => 1, 'max' => 57],
    "2025 B.Com.(Honours) with Research in FINTECH I Semester (Malayalam)" => ['pattern' => '/^kh\.ah\.u4fin25\d{3}$/i', 'min' => 1, 'max' => 57],
    "2025 B.Com with ACCA I Semester (Hindi)" => ['pattern' => '/^kh\.ah\.u4com25\d{3}$/i', 'min' => 1, 'max' => 44],
    "2025 B.Com with ACCA I Semester (Sanskrit)" => ['pattern' => '/^kh\.ah\.u4com25\d{3}$/i', 'min' => 1, 'max' => 44],
    "2025 B.Com with ACCA I Semester (Malayalam)" => ['pattern' => '/^kh\.ah\.u4com25\d{3}$/i', 'min' => 1, 'max' => 44],
    "2025 BBA (Honours) I Semester (Hindi)" => ['pattern' => '/^kh\.ah\.u4bba25\d{3}$/i', 'min' => 1, 'max' => 25],
    "2025 BBA (Honours) I Semester (Sanskrit)" => ['pattern' => '/^kh\.ah\.u4bba25\d{3}$/i', 'min' => 1, 'max' => 25],
    "2025 BBA (Honours) I Semester (Malayalam)" => ['pattern' => '/^kh\.ah\.u4bba25\d{3}$/i', 'min' => 1, 'max' => 25],

    // 💡 NEW: 2025 BCA batches with languages
    "2025 BCA Honours A (Hindi)" => ['pattern' => '/^kh\.en\.u4bca25\d{3}$/i', 'min' => 1, 'max' => 59],
    "2025 BCA Honours A (Sanskrit)" => ['pattern' => '/^kh\.en\.u4bca25\d{3}$/i', 'min' => 1, 'max' => 59],
    "2025 BCA Honours A (Malayalam)" => ['pattern' => '/^kh\.en\.u4bca25\d{3}$/i', 'min' => 1, 'max' => 59],
    "2025 BCA Honours B (Hindi)" => ['pattern' => '/^kh\.en\.u4bca25\d{3}$/i', 'min' => 101, 'max' => 160],
    "2025 BCA Honours B (Sanskrit)" => ['pattern' => '/^kh\.en\.u4bca25\d{3}$/i', 'min' => 101, 'max' => 160],
    "2025 BCA Honours B (Malayalam)" => ['pattern' => '/^kh\.en\.u4bca25\d{3}$/i', 'min' => 101, 'max' => 160],
    "2025 BCA Honours C (Hindi)" => ['pattern' => '/^kh\.en\.u4bca25\d{3}$/i', 'min' => 201, 'max' => 260],
    "2025 BCA Honours C (Sanskrit)" => ['pattern' => '/^kh\.en\.u4bca25\d{3}$/i', 'min' => 201, 'max' => 260],
    "2025 BCA Honours C (Malayalam)" => ['pattern' => '/^kh\.en\.u4bca25\d{3}$/i', 'min' => 201, 'max' => 260],
    "2025 BCA Honours D (Hindi)" => ['pattern' => '/^kh\.en\.u4bca25\d{3}$/i', 'min' => 301, 'max' => 360],
    "2025 BCA Honours D (Sanskrit)" => ['pattern' => '/^kh\.en\.u4bca25\d{3}$/i', 'min' => 301, 'max' => 360],
    "2025 BCA Honours D (Malayalam)" => ['pattern' => '/^kh\.en\.u4bca25\d{3}$/i', 'min' => 301, 'max' => 360],

    // 💡 NEW: 2025 Visual Media batches with languages
    "2025 B.Des. (Honours) I Semester (Hindi)" => ['pattern' => '/^kh\.ah\.u4des25\d{3}$/i'],
    "2025 B.Des. (Honours) I Semester (Sanskrit)" => ['pattern' => '/^kh\.ah\.u4des25\d{3}$/i'],
    "2025 B.Des. (Honours) I Semester (Malayalam)" => ['pattern' => '/^kh\.ah\.u4des25\d{3}$/i'],
    "2025 B.Sc. VM I Semester (Hindi)" => ['pattern' => '/^kh\.ah\.u4vmc25\d{3}$/i', 'min' => 1, 'max' => 37],
    "2025 B.Sc. VM I Semester (Sanskrit)" => ['pattern' => '/^kh\.ah\.u4vmc25\d{3}$/i', 'min' => 1, 'max' => 37],
    "2025 B.Sc. VM I Semester (Malayalam)" => ['pattern' => '/^kh\.ah\.u4vmc25\d{3}$/i', 'min' => 1, 'max' => 37]
];


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $role = $_POST['role'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $email = isset($_POST['email']) ? strtolower(trim($_POST['email'])) : null;
    $department = trim($_POST['department'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $course_name = trim($_POST['course_name'] ?? ''); 
    $batch = trim($_POST['batch'] ?? '');       
    $semester = isset($_POST['semester']) ? (int)$_POST['semester'] : null;

    if (!$role || !$name || !$username || !$department || !$password || !$email) {
        echo "<script>alert('All required fields are not filled!'); window.history.back();</script>"; exit;
    }
    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match!'); window.history.back();</script>"; exit;
    }
    
    if ($role === 'student') {
        if (empty($course_name) || empty($batch) || $semester === null) {
             echo "<script>alert('Course, Batch, and Semester are required for students!'); window.history.back();</script>"; exit;
        }

        if (!isset($batchRules[$course_name])) {
            echo "<script>alert('Invalid course selected!'); window.history.back();</script>"; exit;
        }
        
        $rule = $batchRules[$course_name];
        
        if (!preg_match($rule['pattern'], $username)) {
            echo "<script>alert('Invalid Register Number format for your selected course.'); window.history.back();</script>"; exit;
        }

        if (isset($rule['min']) && isset($rule['max'])) {
            if (preg_match('/\d{3}$/', $username, $matches)) {
                $numeric_part = (int)$matches[0];
                if ($numeric_part < $rule['min'] || $numeric_part > $rule['max']) {
                    $alert_msg = "Register Number is outside the valid range for {$course_name} (Range: {$rule['min']}-{$rule['max']}).";
                    echo "<script>alert('".addslashes($alert_msg)."'); window.history.back();</script>"; exit;
                }
            } else {
                 echo "<script>alert('Could not validate Register Number. Must end in 3 digits.'); window.history.back();</script>"; exit;
            }
        }

    } else if ($role === 'faculty') {
        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/i', $username)) {
            echo "<script>alert('Invalid Faculty Username format. Must be 3-50 letters, numbers, or underscores.'); window.history.back();</script>"; exit;
        }
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt_check_user = $mysqli->prepare("SELECT id FROM login WHERE username = ?");
        $stmt_check_user->bind_param("s", $username); $stmt_check_user->execute(); $stmt_check_user->store_result();
        if ($stmt_check_user->num_rows > 0) { echo "<script>alert('Username already exists!'); window.history.back();</script>"; $stmt_check_user->close(); exit; }
        $stmt_check_user->close();

        $emailTable = ($role === 'student') ? 'students' : 'faculty';
        $stmt_email_check = $mysqli->prepare("SELECT id FROM $emailTable WHERE email = ?");
        $stmt_email_check->bind_param("s", $email); $stmt_email_check->execute(); $stmt_email_check->store_result();
        if ($stmt_email_check->num_rows > 0) { echo "<script>alert('Email address already registered!'); window.history.back();</script>"; $stmt_email_check->close(); exit; }
        $stmt_email_check->close();

    } catch (mysqli_sql_exception $e) {
        error_log("Pre-check Error: " . $e->getMessage());
        echo "<script>alert('Database error during pre-check. Check logs.'); window.history.back();</script>";
        exit;
    }

    $mysqli->begin_transaction();
    try {
        if ($role === 'student') {
            $stmt_student = $mysqli->prepare("INSERT INTO students (reg_no, name, email, course_name, batch, department, semester) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_student->bind_param("ssssssi", $username, $name, $email, $course_name, $batch, $department, $semester);
            if (!$stmt_student->execute()) { throw new mysqli_sql_exception("Execute failed (insert student): " . $stmt_student->error, $stmt_student->errno); }
            $new_student_id = $stmt_student->insert_id;
            $stmt_student->close();

            $stmt_login = $mysqli->prepare("INSERT INTO login (username, password_hash, role, student_id) VALUES (?, ?, ?, ?)");
            $stmt_login->bind_param("sssi", $username, $hashedPassword, $role, $new_student_id);
            if (!$stmt_login->execute()) { throw new mysqli_sql_exception("Execute failed (insert login for student): " . $stmt_login->error, $stmt_login->errno); }
            $stmt_login->close();

        } else { 
            $stmt_faculty = $mysqli->prepare("INSERT INTO faculty (name, username, email, department) VALUES (?, ?, ?, ?)");
            $stmt_faculty->bind_param("ssss", $name, $username, $email, $department);
            if (!$stmt_faculty->execute()) { throw new mysqli_sql_exception("Execute failed (insert faculty): " . $stmt_faculty->error, $stmt_faculty->errno); }
            $new_faculty_id = $stmt_faculty->insert_id;
            $stmt_faculty->close();

            $stmt_login = $mysqli->prepare("INSERT INTO login (username, password_hash, role, is_active, faculty_id) VALUES (?, ?, ?, 0, ?)"); // Default inactive
            $stmt_login->bind_param("sssi", $username, $hashedPassword, $role, $new_faculty_id);
            if (!$stmt_login->execute()) { throw new mysqli_sql_exception("Execute failed (insert login for faculty): " . $stmt_login->error, $stmt_login->errno); }
            $stmt_login->close();
        }

        $mysqli->commit(); 
        $message = 'Registration successful! Please wait for admin approval to log in.';
        echo "<script>alert('".$message."'); window.location.href='../frontend/login.html';</script>";

    } catch (mysqli_sql_exception $e) { 
        $mysqli->rollback();
        error_log("Registration SQL Error Code " . $e->getCode() . ": " . $e->getMessage());
        if ($e->getCode() == 1062) { 
             echo "<script>alert('Error: Registration failed - Duplicate entry detected (Username or Email likely exists).'); window.history.back();</script>";
        } else {
             echo "<script>alert('Error: Registration failed due to a database error (Code: ".$e->getCode()."). Check logs.'); window.history.back();</script>";
        }
    } catch (Exception $e) { 
        $mysqli->rollback();
        error_log("Registration General Error: " . $e->getMessage());
        echo "<script>alert('Error: An unexpected error occurred during registration. Check logs.'); window.history.back();</script>";
    } finally {
        if (isset($mysqli) && $mysqli->ping()) { $mysqli->close(); }
    }
} else {
     echo "<script>alert('Invalid request method.'); window.history.back();</script>";
}
?>