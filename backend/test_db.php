<?php
require 'db.php';
$result = $mysqli->query("SELECT COUNT(*) AS c FROM students");
$row = $result->fetch_assoc();
echo "Students in DB: " . $row['c'];
