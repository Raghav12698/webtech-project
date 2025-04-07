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

// Get marks ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: marks.php');
    exit();
}

$marks_id = $_GET['id'];

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
    $assessment_type = $_POST['assessment_type'];
    $marks_obtained = $_POST['marks_obtained'];
    $max_marks = $_POST['max_marks'];
    $assessment_date = $_POST['assessment_date'];

    // Validate marks
    if ($marks_obtained > $max_marks) {
        $error_message = "Obtained marks cannot be greater than maximum marks.";
    } else {
        $update_sql = "UPDATE marks SET student_id = ?, course_id = ?, assessment_type = ?, 
                       marks_obtained = ?, max_marks = ?, assessment_date = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("iisddsi", $student_id, $course_id, $assessment_type, 
                         $marks_obtained, $max_marks, $assessment_date, $marks_id);
        
        if ($stmt->execute()) {
            $success_message = "Marks updated successfully!";
        } else {
            $error_message = "Error updating marks: " . $stmt->error;
        }
    }
}

// Get marks details
$sql = "SELECT m.*, u.username as student_name, c.course_code, c.course_name 
        FROM marks m
        JOIN users u ON m.student_id = u.id
        JOIN courses c ON m.course_id = c.id
        WHERE m.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $marks_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: marks.php');
    exit();
}

$marks = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Marks - Admin Dashboard</title>
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
                <h3 class="card-title"><i class="fas fa-edit"></i> Edit Marks</h3>
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
                                        <?php echo $student['id'] == $marks['student_id'] ? 'selected' : ''; ?>>
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
                                        <?php echo $course['id'] == $marks['course_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="assessment_type">Assessment Type:</label>
                        <select class="form-control" id="assessment_type" name="assessment_type" required>
                            <?php
                            $assessment_types = ['Quiz', 'Assignment', 'Mid-term', 'Final', 'Project'];
                            foreach ($assessment_types as $type):
                            ?>
                                <option value="<?php echo $type; ?>"
                                        <?php echo $type == $marks['assessment_type'] ? 'selected' : ''; ?>>
                                    <?php echo $type; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="marks_obtained">Marks Obtained:</label>
                        <input type="number" class="form-control" id="marks_obtained" name="marks_obtained" 
                               value="<?php echo htmlspecialchars($marks['marks_obtained']); ?>"
                               step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label for="max_marks">Maximum Marks:</label>
                        <input type="number" class="form-control" id="max_marks" name="max_marks" 
                               value="<?php echo htmlspecialchars($marks['max_marks']); ?>"
                               step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label for="assessment_date">Assessment Date:</label>
                        <input type="date" class="form-control" id="assessment_date" name="assessment_date" 
                               value="<?php echo htmlspecialchars($marks['assessment_date']); ?>" required>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Marks
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
</body>
</html> 