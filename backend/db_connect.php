<?php
// Database connection configuration
$host = 'localhost';
$db_user = 'root';
$db_password = ''; // Replace with your MySQL password
$db_name = 'unimarket';

// Create connection
$conn = new mysqli($host, $db_user, $db_password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");
?>