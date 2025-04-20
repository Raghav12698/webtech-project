<?php
require_once 'config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Array to store creation status
$creation_status = [];

// Drop existing tables in reverse order of dependencies
$conn->query("DROP TABLE IF EXISTS marks");
$conn->query("DROP TABLE IF EXISTS attendance");
$conn->query("DROP TABLE IF EXISTS enrollments");
$conn->query("DROP TABLE IF EXISTS courses");
$conn->query("DROP TABLE IF EXISTS users");

// Create users table
$users_sql = "CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($users_sql)) {
    $creation_status[] = "Users table created successfully";
} else {
    $creation_status[] = "Error creating users table: " . $conn->error;
}

// Create courses table with credits column
$courses_sql = "CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_name VARCHAR(100) NOT NULL,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    credits INT NOT NULL DEFAULT 3,
    teacher_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($courses_sql)) {
    $creation_status[] = "Courses table created successfully";
} else {
    $creation_status[] = "Error creating courses table: " . $conn->error;
}

// Create enrollments table
$enrollments_sql = "CREATE TABLE enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, course_id)
)";

if ($conn->query($enrollments_sql)) {
    $creation_status[] = "Enrollments table created successfully";
} else {
    $creation_status[] = "Error creating enrollments table: " . $conn->error;
}

// Create attendance table
$attendance_sql = "CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'late') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (student_id, course_id, date)
)";

if ($conn->query($attendance_sql)) {
    $creation_status[] = "Attendance table created successfully";
} else {
    $creation_status[] = "Error creating attendance table: " . $conn->error;
}

// Create marks table
$marks_sql = "CREATE TABLE marks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    assessment_name VARCHAR(100) NOT NULL,
    marks_obtained DECIMAL(5,2) NOT NULL,
    max_marks DECIMAL(5,2) NOT NULL,
    assessment_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
)";

if ($conn->query($marks_sql)) {
    $creation_status[] = "Marks table created successfully";
        } else {
    $creation_status[] = "Error creating marks table: " . $conn->error;
}

// Insert default admin account
$admin_password = password_hash("admin123", PASSWORD_DEFAULT);
$insert_admin = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')");
$admin_username = "admin";
$admin_email = "admin@example.com";
$insert_admin->bind_param("sss", $admin_username, $admin_email, $admin_password);

if ($insert_admin->execute()) {
    $creation_status[] = "Default admin account created successfully";
    } else {
    $creation_status[] = "Error creating default admin account: " . $conn->error;
}

// Insert default teacher account
$teacher_password = password_hash("teacher123", PASSWORD_DEFAULT);
$insert_teacher = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'teacher', 'active')");
$teacher_username = "teacher";
$teacher_email = "teacher@example.com";
$insert_teacher->bind_param("sss", $teacher_username, $teacher_email, $teacher_password);

if ($insert_teacher->execute()) {
    $creation_status[] = "Default teacher account created successfully";
} else {
    $creation_status[] = "Error creating default teacher account: " . $conn->error;
}

// Output status
echo "<html><head><title>Database Setup</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    </style></head><body>";
echo "<h1>Database Setup Results</h1>";
foreach ($creation_status as $status) {
    if (strpos($status, "Error") !== false) {
        echo "<p class='error'>$status</p>";
    } else {
        echo "<p class='success'>$status</p>";
    }
}
echo "<p>Default admin login credentials:<br>";
echo "Email: admin@example.com<br>";
echo "Password: admin123</p>";
echo "<p>Default teacher login credentials:<br>";
echo "Email: teacher@example.com<br>";
echo "Password: teacher123</p>";
echo "</body></html>";
?> 