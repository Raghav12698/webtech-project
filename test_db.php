<?php
require_once 'config/database.php';

echo "Testing database connection...<br>";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Database connection successful!<br><br>";

// Test tables
$tables = ['users', 'student_profiles', 'courses', 'enrollments', 'attendance', 'marks'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "Table '$table' exists<br>";
        
        // Count records
        $count = $conn->query("SELECT COUNT(*) as count FROM $table")->fetch_assoc()['count'];
        echo "Number of records in '$table': $count<br>";
    } else {
        echo "Table '$table' does not exist<br>";
    }
    echo "<br>";
}
?> 