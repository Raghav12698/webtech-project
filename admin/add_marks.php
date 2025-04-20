<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Get students
$students_sql = "SELECT u.id, CONCAT(sp.first_name, ' ', sp.last_name) as name 
                FROM users u 
                JOIN student_profiles sp ON u.id = sp.student_id 
                WHERE u.role = 'student'";
$students = $conn->query($students_sql);

// Get courses
$courses_sql = "SELECT id, name, max_marks FROM courses";
$courses = $conn->query($courses_sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $course_id = $_POST['course_id'];
    $marks_obtained = $_POST['marks_obtained'];
    $exam_date = $_POST['exam_date'];
    $remarks = $_POST['remarks'];
    
    // Calculate grade based on marks
    if ($marks_obtained >= 90) {
        $grade = 'A';
    } elseif ($marks_obtained >= 80) {
        $grade = 'B';
    } elseif ($marks_obtained >= 70) {
        $grade = 'C';
    } elseif ($marks_obtained >= 60) {
        $grade = 'D';
    } else {
        $grade = 'F';
    }
    
    // Check if marks already exist
    $check_sql = "SELECT id FROM marks WHERE student_id = ? AND course_id = ? AND exam_date = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iis", $student_id, $course_id, $exam_date);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = "Marks already exist for this student on this date.";
    } else {
        $sql = "INSERT INTO marks (student_id, course_id, marks_obtained, grade, exam_date, remarks) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iidsss", $student_id, $course_id, $marks_obtained, $grade, $exam_date, $remarks);
        
        if ($stmt->execute()) {
            $success_message = "Marks recorded successfully!";
        } else {
            $error_message = "Error recording marks: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Marks - Admin Dashboard</title>
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
                <li><a href="attendance.php" class="nav-link"><i class="fas fa-calendar-check"></i> Attendance</a></li>
                <li><a href="marks.php" class="nav-link active"><i class="fas fa-chart-line"></i> Marks</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-plus-circle"></i> Add Marks</h3>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="student_id">Student</label>
                                <select id="student_id" name="student_id" class="form-control" required>
                                    <option value="">Select Student</option>
                                    <?php while ($student = $students->fetch_assoc()): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="course_id">Course</label>
                                <select id="course_id" name="course_id" class="form-control" required>
                                    <option value="">Select Course</option>
                                    <?php while ($course = $courses->fetch_assoc()): ?>
                                        <option value="<?php echo $course['id']; ?>" 
                                                data-max-marks="<?php echo $course['max_marks']; ?>">
                                            <?php echo htmlspecialchars($course['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="marks_obtained">Marks Obtained</label>
                                <input type="number" id="marks_obtained" name="marks_obtained" 
                                       class="form-control" min="0" max="100" required>
                                <small class="form-text text-muted">Maximum marks: <span id="max-marks">100</span></small>
                            </div>
                            <div class="form-group">
                                <label for="exam_date">Exam Date</label>
                                <input type="date" id="exam_date" name="exam_date" 
                                       class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group text-center">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Marks
                        </button>
                        <a href="marks.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Marks
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script>
        // Update max marks when course is selected
        document.getElementById('course_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const maxMarks = selectedOption.getAttribute('data-max-marks');
            document.getElementById('max-marks').textContent = maxMarks;
            document.getElementById('marks_obtained').max = maxMarks;
        });
    </script>
</body>
</html> 