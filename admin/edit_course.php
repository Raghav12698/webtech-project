<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$error_message = '';
$success_message = '';

// Get course ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: courses.php');
    exit();
}

$course_id = $_GET['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_code = trim($_POST['course_code']);
    $course_name = trim($_POST['course_name']);
    $description = trim($_POST['description']);
    $credits = (int)$_POST['credits'];

    if (empty($course_code) || empty($course_name)) {
        $error_message = "Course code and name are required.";
    } else {
        // Check if course code exists for other courses
        $check_sql = "SELECT id FROM courses WHERE course_code = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $course_code, $course_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "Course code already exists.";
        } else {
            $update_sql = "UPDATE courses SET course_code = ?, course_name = ?, description = ?, credits = ? WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("sssii", $course_code, $course_name, $description, $credits, $course_id);
            
            if ($stmt->execute()) {
                $success_message = "Course updated successfully!";
            } else {
                $error_message = "Error updating course: " . $stmt->error;
            }
        }
    }
}

// Get course details
$sql = "SELECT * FROM courses WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: courses.php');
    exit();
}

$course = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Course - Admin Dashboard</title>
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
                <h3 class="card-title"><i class="fas fa-edit"></i> Edit Course</h3>
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

                <form method="POST">
                    <div class="form-group">
                        <label for="course_code">Course Code:</label>
                        <input type="text" class="form-control" id="course_code" name="course_code" 
                               value="<?php echo htmlspecialchars($course['course_code']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="course_name">Course Name:</label>
                        <input type="text" class="form-control" id="course_name" name="course_name" 
                               value="<?php echo htmlspecialchars($course['course_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($course['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="credits">Credits:</label>
                        <input type="number" class="form-control" id="credits" name="credits" 
                               value="<?php echo htmlspecialchars($course['credits']); ?>" min="1" max="6" required>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Course
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