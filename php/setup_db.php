<?php
// Run this file once to setup the MySQL database table
$host = getenv('MYSQL_HOST') ?: 'localhost';
$user = getenv('MYSQL_USER') ?: 'root';
$pass = getenv('MYSQL_PASSWORD') ?: '';
$port = getenv('MYSQL_PORT') ?: 3306;
$dbname = getenv('MYSQL_DATABASE') ?: 'internship_db';

try {
    $conn = new mysqli($host, $user, $pass, '', (int)$port);
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create Database
$sql = "CREATE DATABASE IF NOT EXISTS `$dbname`";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

$conn->select_db($dbname);

// Create Users Table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table users created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Attempt to cleanup 'name' column if it exists (Strict Mode: Name belongs in MongoDB)
try {
    // Check if column exists first to avoid error? Or just try drop.
    // We will try simple DROP, ignore if fails.
    $conn->query("ALTER TABLE users DROP COLUMN name");
    echo "Column 'name' dropped from users (Strict Mode)<br>";
} catch (Exception $e) {
    // Ignore error if column doesn't exist
}

$conn->close();
?>
