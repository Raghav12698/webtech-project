<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$error_message = '';
$success_message = '';

// Get attendance ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: attendance.php');
    exit();
}

$attendance_id = $_GET['id'];

// Get all courses for the dropdown
$courses_sql = "SELECT id, course_code, course_name FROM courses";
$courses_result = $conn->query($courses_sql);

// Get all students for the dropdown
$students_sql = "SELECT id, username, email FROM users WHERE role = 'student'";
$students_result = $conn->query($students_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $course_id = $_POST['course_id'];
    $date = $_POST['date'];
    $status = $_POST['status'];

    $update_sql = "UPDATE attendance SET student_id = ?, course_id = ?, date = ?, status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("iissi", $student_id, $course_id, $date, $status, $attendance_id);
    
    if ($stmt->execute()) {
        $success_message = "Attendance record updated successfully!";
    } else {
        $error_message = "Error updating attendance: " . $stmt->error;
    }
}

// Get attendance details
$sql = "SELECT a.*, u.username as student_name, c.course_code, c.course_name 
        FROM attendance a
        JOIN users u ON a.student_id = u.id
        JOIN courses c ON a.course_id = c.id
        WHERE a.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $attendance_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: attendance.php');
    exit();
}

$attendance = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Attendance - Admin Dashboard</title>
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
                <li><a href="courses.php" class="nav-link"><i class="fas fa-book"></i> Courses</a></li>
                <li><a href="attendance.php" class="nav-link active"><i class="fas fa-calendar-check"></i> Attendance</a></li>
                <li><a href="marks.php" class="nav-link"><i class="fas fa-chart-line"></i> Marks</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit"></i> Edit Attendance</h3>
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
                        <label for="student_id">Student:</label>
                        <select class="form-control" id="student_id" name="student_id" required>
                            <?php while ($student = $students_result->fetch_assoc()): ?>
                                <option value="<?php echo $student['id']; ?>" 
                                        <?php echo $student['id'] == $attendance['student_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['username']); ?> 
                                    (<?php echo htmlspecialchars($student['email']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="course_id">Course:</label>
                        <select class="form-control" id="course_id" name="course_id" required>
                            <?php while ($course = $courses_result->fetch_assoc()): ?>
                                <option value="<?php echo $course['id']; ?>"
                                        <?php echo $course['id'] == $attendance['course_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date">Date:</label>
                        <input type="date" class="form-control" id="date" name="date" 
                               value="<?php echo htmlspecialchars($attendance['date']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="present" <?php echo $attendance['status'] == 'present' ? 'selected' : ''; ?>>Present</option>
                            <option value="absent" <?php echo $attendance['status'] == 'absent' ? 'selected' : ''; ?>>Absent</option>
                            <option value="late" <?php echo $attendance['status'] == 'late' ? 'selected' : ''; ?>>Late</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Attendance
                        </button>
                        <a href="attendance.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Attendance
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 