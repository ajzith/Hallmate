<?php
// backend/request_reset.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


require __DIR__ . '/src/Exception.php';
require __DIR__ . '/src/PHPMailer.php';
require __DIR__ . '/src/SMTP.php';

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$emailConfig = [
    'host'       => 'smtp.office365.com', // Outlook/Office 365 SMTP server
    'port'       => 587,                  // TLS port
    'username'   => 'kh.sc.i5mca23019@kh.students.amrita.edu',
    'password'   => 'Amma@1234', 
    'from_email' => 'kh.sc.i5mca23019@kh.students.amrita.edu', 
    'from_name'  => 'Hallmate Password Reset',
    'reset_url_base' => 'https://hallmate.42web.io/frontend/reset_password.html' 
];

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');

if (empty($username)) {
    http_response_code(400); echo json_encode(['error' => 'Username required.']); exit;
}

$responseMessage = ['success' => true];

try {
    $stmt_find = $mysqli->prepare("SELECT id, role, student_id, faculty_id FROM login WHERE username = ?");
    $stmt_find->bind_param("s", $username);
    $stmt_find->execute();
    $result = $stmt_find->get_result();
    $user = $result->fetch_assoc();
    $stmt_find->close();

    if ($user) {
        $user_email = null;
        if ($user['role'] === 'student' && !empty($user['student_id'])) {
            $stmt_email = $mysqli->prepare("SELECT email FROM students WHERE id = ?");
            $stmt_email->bind_param("i", $user['student_id']);
            $stmt_email->execute();
            $email_result = $stmt_email->get_result()->fetch_assoc();
            $user_email = $email_result['email'] ?? null;
            $stmt_email->close();
        } elseif ($user['role'] === 'faculty' && !empty($user['faculty_id'])) {
            $stmt_email = $mysqli->prepare("SELECT email FROM faculty WHERE id = ?");
            $stmt_email->bind_param("i", $user['faculty_id']);
            $stmt_email->execute();
            $email_result = $stmt_email->get_result()->fetch_assoc();
            $user_email = $email_result['email'] ?? null; 
            $stmt_email->close();
        }

        if ($user_email) {
            $token = bin2hex(random_bytes(32)); 
            $hashed_token = password_hash($token, PASSWORD_DEFAULT); 

            $expires_dt = new DateTime('now', new DateTimeZone('UTC'));
            $expires_dt->modify('+1 hour');
            $expires = $expires_dt->format('Y-m-d H:i:s');
 

            $stmt_update = $mysqli->prepare("UPDATE login SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
            if (!$stmt_update) throw new Exception("DB Prepare Error (store token): " . $mysqli->error);
            $stmt_update->bind_param("ssi", $hashed_token, $expires, $user['id']);
            if (!$stmt_update->execute()) {
                 throw new Exception("Failed to store reset token: " . $stmt_update->error);
            }
            $stmt_update->close();

            $reset_link = $emailConfig['reset_url_base'] . '?token=' . $token;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = $emailConfig['host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $emailConfig['username'];
                $mail->Password   = $emailConfig['password'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
                $mail->Port       = $emailConfig['port'];

                $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
                $mail->addAddress($user_email); // Add recipient

                $mail->isHTML(true);
                $mail->Subject = 'Hallmate Password Reset Request';
                $mail->Body    = "You requested a password reset for Hallmate.<br>Click the link below to reset your password (link expires in 1 hour):<br><a href='{$reset_link}'>{$reset_link}</a><br><br>If you did not request this, please ignore this email.";
                $mail->AltBody = "You requested a password reset for Hallmate.\nGo to the following link to reset your password (link expires in 1 hour):\n{$reset_link}\n\nIf you did not request this, please ignore this email.";

                $mail->send();
       

            } catch (Exception $e) {
                error_log("PHPMailer Error for user {$username}: {$mail->ErrorInfo}");
            }
        } else {
             error_log("Forgot Password: No email found for user {$username}, role {$user['role']}");
        }
    } else {
        error_log("Forgot Password: User not found - {$username}");
    }

} catch (Exception $e) {
    error_log("request_reset.php Error: " . $e->getMessage());
} finally {
     if (isset($mysqli) && $mysqli->ping()) { $mysqli->close(); }
     echo json_encode($responseMessage);
}
?>