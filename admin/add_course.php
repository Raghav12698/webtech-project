<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_code = trim($_POST['course_code']);
    $course_name = trim($_POST['course_name']);
    $description = trim($_POST['description']);
    $credits = (int)$_POST['credits'];

    // Validate input
    if (empty($course_code) || empty($course_name)) {
        $error_message = "Course code and name are required.";
    } else {
        // Check if course already exists
        $check_sql = "SELECT id FROM courses WHERE course_code = ?";
        $check_stmt = $conn->prepare($check_sql);
        if ($check_stmt) {
            $check_stmt->bind_param("s", $course_code);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "A course with this course code already exists.";
            } else {
                // Insert new course
                $sql = "INSERT INTO courses (course_code, course_name, description, credits) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssi", $course_code, $course_name, $description, $credits);
                    
                    if ($stmt->execute()) {
                        $success_message = "Course added successfully!";
                        // Clear form data
                        $course_code = $course_name = $description = '';
                        $credits = 3;
                    } else {
                        $error_message = "Error adding course: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = "Error preparing statement: " . $conn->error;
                }
            }
            $check_stmt->close();
        } else {
            $error_message = "Error checking course existence: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Course - Admin Dashboard</title>
    <link href="../css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="dashboard.php" class="navbar-brand">
                <i class="fas fa-user-shield"></i> Admin Dashboard
            </a>
            <ul class="nav-list">
                <li><a href="students.php" class="nav-link"><i class="fas fa-users"></i> Students</a></li>
                <li><a href="courses.php" class="nav-link active"><i class="fas fa-book"></i> Courses</a></li>
                <li><a href="attendance.php" class="nav-link"><i class="fas fa-calendar-check"></i> Attendance</a></li>
                <li><a href="marks.php" class="nav-link"><i class="fas fa-chart-line"></i> Marks</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-plus"></i> Add New Course</h3>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="add_course.php">
                    <div class="form-group">
                        <label for="course_code">Course Code:</label>
                        <input type="text" class="form-control" id="course_code" name="course_code" 
                               value="<?php echo isset($course_code) ? htmlspecialchars($course_code) : ''; ?>" 
                               placeholder="e.g., CS101" required>
                    </div>

                    <div class="form-group">
                        <label for="course_name">Course Name:</label>
                        <input type="text" class="form-control" id="course_name" name="course_name" 
                               value="<?php echo isset($course_name) ? htmlspecialchars($course_name) : ''; ?>" 
                               placeholder="e.g., Introduction to Programming" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea class="form-control" id="description" name="description" rows="4" 
                                  placeholder="Course description..."><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="credits">Credits:</label>
                        <input type="number" class="form-control" id="credits" name="credits" 
                               value="<?php echo isset($credits) ? htmlspecialchars($credits) : '3'; ?>" 
                               min="1" max="6">
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Course
                        </button>
                        <a href="courses.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Courses
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 