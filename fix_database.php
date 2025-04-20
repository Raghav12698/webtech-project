<?php
require_once 'config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>\n";
echo "Starting database update...\n\n";

// Read and execute the SQL file
$sql_file = 'sql/fix_users_table.sql';
if (!file_exists($sql_file)) {
    die("Error: SQL file not found at $sql_file\n");
}

$sql = file_get_contents($sql_file);
if ($sql === false) {
    die("Error: Could not read SQL file\n");
}

// Split SQL file into individual queries
$queries = array_filter(array_map('trim', explode(';', $sql)), 'strlen');

// Execute each query
$success = true;
$query_count = 0;
foreach ($queries as $query) {
    $query_count++;
    echo "Executing query $query_count...\n";
    
    if (!$conn->query($query)) {
        echo "Error executing query: " . $conn->error . "\n";
        echo "Query was: " . $query . "\n\n";
        $success = false;
        break;
    } else {
        echo "Query executed successfully.\n\n";
    }
}

if ($success) {
    echo "\nDatabase structure updated successfully!\n";
    echo "Total queries executed: $query_count\n";
    
    // Verify the users table structure
    $result = $conn->query("DESCRIBE users");
    if ($result) {
        echo "\nCurrent users table structure:\n";
        while ($row = $result->fetch_assoc()) {
            echo "{$row['Field']} - {$row['Type']} - {$row['Null']} - {$row['Key']} - {$row['Default']}\n";
        }
    }
    
    // Check if admin user exists
    $result = $conn->query("SELECT id, username, email, role, status FROM users WHERE role = 'admin' LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        echo "\nAdmin user exists:\n";
        echo "Username: {$admin['username']}\n";
        echo "Email: {$admin['email']}\n";
        echo "Status: {$admin['status']}\n";
    } else {
        echo "\nWarning: No admin user found!\n";
    }
} else {
    echo "\nError updating database structure.\n";
}

$conn->close();
echo "</pre>"; 