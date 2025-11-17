<?php
// backend/create_admin.php
require __DIR__ . '/db.php';

$username = 'admin';
$password = 'admin123'; 
$role = 'admin';


$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $mysqli->prepare("INSERT INTO login (username, password_hash, role) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $username, $hash, $role);
if ($stmt->execute()) {
    echo "Admin created. username=$username password=$password\n";
} else {
    echo "Error: " . $stmt->error . "\n";
}
$stmt->close();
$mysqli->close();
