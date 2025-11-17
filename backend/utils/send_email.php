<?php
// backend/utils/send_email.php

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Corrected paths based on your file structure
require_once __DIR__ . '/../src/Exception.php';
require_once __DIR__ . '/../src/PHPMailer.php';
require_once __DIR__ . '/../src/SMTP.php';

function sendApprovalEmail($to_name, $to_email, $username) {
    if (empty($to_email)) {
        // Don't try to send if email is missing
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings from your request_reset.php
        $mail->isSMTP();
        $mail->Host       = 'smtp.office365.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = '';
        $mail->Password   = ''; // Using the password from your file
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('kh.sc.i5mca23019@kh.students.amrita.edu', 'Hallmate Admin'); // Changed From Name
        $mail->addAddress($to_email, $to_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Hallmate Account Has Been Approved!';
        $mail->Body    = "
            <p>Hi {$to_name},</p>
            <p>Your account for Hallmate has been approved by the admin.</p>
            <p>You can now log in using the following credentials:</p>
            <ul>
                <li><strong>Username:</strong> {$username}</li>
                <li><strong>Password:</strong> (The password you set at registration)</li>
            </ul>
            <p>Thank you,<br>The Hallmate Team</p>
        ";
        $mail->AltBody = "Your Hallmate account has been approved. You can now log in. Your username is: {$username}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>
