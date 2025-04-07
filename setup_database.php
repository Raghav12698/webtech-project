<?php
require_once 'config/database.php';

echo "Setting up database...<br>";

// Read and execute the SQL file
$sql = file_get_contents('database/student_ms.sql');
if ($conn->multi_query($sql)) {
    echo "Database schema imported successfully<br>";
} else {
    echo "Error importing database schema: " . $conn->error . "<br>";
}

// Wait for all queries to complete
while ($conn->more_results()) {
    $conn->next_result();
}

// Insert test data if tables are empty
$tables = ['users', 'student_profiles', 'courses', 'enrollments', 'attendance', 'marks'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    $count = $result->fetch_assoc()['count'];
    
    if ($count == 0) {
        echo "Table '$table' is empty. Adding test data...<br>";
        
        switch ($table) {
            case 'users':
                $sql = "INSERT INTO users (username, password, email, role) VALUES 
                        ('student', '$2y$10$8KzC5YLDxxpxPGj4B.wOzOxKxsxwq/yZPHEGVzvkxqpNS0.gJqTiG', 'student@example.com', 'student')";
                break;
            case 'student_profiles':
                $sql = "INSERT INTO student_profiles (student_id, first_name, last_name) VALUES 
                        (2, 'Test', 'Student')";
                break;
            case 'courses':
                $sql = "INSERT INTO courses (course_code, course_name, description, credits) VALUES 
                        ('CS101', 'Introduction to Programming', 'Basic programming concepts', 3)";
                break;
            case 'enrollments':
                $sql = "INSERT INTO enrollments (student_id, course_id) VALUES (2, 1)";
                break;
            case 'attendance':
                $sql = "INSERT INTO attendance (student_id, course_id, date, status) VALUES 
                        (2, 1, CURDATE(), 'present')";
                break;
            case 'marks':
                $sql = "INSERT INTO marks (student_id, course_id, assessment_type, marks_obtained, max_marks, assessment_date) VALUES 
                        (2, 1, 'Midterm', 85, 100, CURDATE())";
                break;
        }
        
        if ($conn->query($sql)) {
            echo "Test data added to '$table'<br>";
        } else {
            echo "Error adding test data to '$table': " . $conn->error . "<br>";
        }
    } else {
        echo "Table '$table' already has data<br>";
    }
}

// Add sample courses if none exist
$check_courses = $conn->query("SELECT id FROM courses");
if ($check_courses->num_rows == 0) {
    $sample_courses = [
        [
            'name' => 'Basic programming concepts using Python',
            'description' => 'Learn the fundamentals of programming using Python programming language'
        ],
        [
            'name' => 'Fundamental data structures and algorithms',
            'description' => 'Study essential data structures and algorithms for efficient programming'
        ],
        [
            'name' => 'Introduction to differential calculus',
            'description' => 'Basic concepts of differential calculus and its applications'
        ]
    ];

    $stmt = $conn->prepare("INSERT INTO courses (name, description) VALUES (?, ?)");
    foreach ($sample_courses as $course) {
        $stmt->bind_param("ss", $course['name'], $course['description']);
        $stmt->execute();
    }
    echo "Sample courses added successfully<br>";
}

echo "<br>Setup complete! You can now <a href='index.php'>login</a>.";
?> 