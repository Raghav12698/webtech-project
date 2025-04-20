<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get all courses for the dropdown
$courses_sql = "SELECT id, course_code, course_name FROM courses";
$courses_result = $conn->query($courses_sql);

// Get all students for the dropdown
$students_sql = "SELECT id, username, email FROM users WHERE role = 'student'";
$students_result = $conn->query($students_sql);

// Handle form submission for adding marks
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
        $insert_sql = "INSERT INTO marks (student_id, course_id, assessment_type, marks_obtained, max_marks, assessment_date) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        if ($stmt) {
            $stmt->bind_param("iisdds", $student_id, $course_id, $assessment_type, $marks_obtained, $max_marks, $assessment_date);
            if ($stmt->execute()) {
                $success_message = "Marks recorded successfully!";
            } else {
                $error_message = "Error recording marks: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Error preparing statement: " . $conn->error;
        }
    }
}

// Get marks records with student and course information
$marks_sql = "SELECT m.*, 
                     u.username as student_name, 
                     c.course_code,
                     c.course_name
              FROM marks m
              JOIN users u ON m.student_id = u.id
              JOIN courses c ON m.course_id = c.id
              ORDER BY m.assessment_date DESC, c.course_code";
$marks_result = $conn->query($marks_sql);

if (!$marks_result) {
    $error_message = "Error fetching marks records: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Marks - Admin Dashboard</title>
    <link href="../css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .percentage-cell {
            font-weight: bold;
        }
        .percentage-high {
            color: #28a745;
        }
        .percentage-medium {
            color: #ffc107;
        }
        .percentage-low {
            color: #dc3545;
        }
    </style>
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
                <h3 class="card-title"><i class="fas fa-chart-line"></i> Manage Marks</h3>
            </div>
            <div class="card-body">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Add Marks Form -->
                <form method="POST" action="marks.php" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="student_id">Student:</label>
                                <select class="form-control" id="student_id" name="student_id" required>
                                    <option value="">Select Student</option>
                                    <?php while ($student = $students_result->fetch_assoc()): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['username']); ?> (<?php echo htmlspecialchars($student['email']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="course_id">Course:</label>
                                <select class="form-control" id="course_id" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php while ($course = $courses_result->fetch_assoc()): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="assessment_type">Assessment Type:</label>
                                <select class="form-control" id="assessment_type" name="assessment_type" required>
                                    <option value="Quiz">Quiz</option>
                                    <option value="Assignment">Assignment</option>
                                    <option value="Mid-term">Mid-term</option>
                                    <option value="Final">Final</option>
                                    <option value="Project">Project</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="marks_obtained">Marks Obtained:</label>
                                <input type="number" class="form-control" id="marks_obtained" name="marks_obtained" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="max_marks">Maximum Marks:</label>
                                <input type="number" class="form-control" id="max_marks" name="max_marks" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="assessment_date">Assessment Date:</label>
                                <input type="date" class="form-control" id="assessment_date" name="assessment_date" 
                                       required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-group mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Marks
                        </button>
                    </div>
                </form>

                <!-- Marks Records Table -->
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Assessment</th>
                                <th>Marks</th>
                                <th>Percentage</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($marks_result && $marks_result->num_rows > 0): ?>
                                <?php while ($mark = $marks_result->fetch_assoc()): ?>
                                    <?php 
                                        $percentage = ($mark['marks_obtained'] / $mark['max_marks']) * 100;
                                        $percentage_class = $percentage >= 80 ? 'percentage-high' : 
                                                         ($percentage >= 60 ? 'percentage-medium' : 'percentage-low');
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($mark['assessment_date']))); ?></td>
                                        <td><?php echo htmlspecialchars($mark['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($mark['course_code'] . ' - ' . $mark['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($mark['assessment_type']); ?></td>
                                        <td><?php echo htmlspecialchars($mark['marks_obtained'] . ' / ' . $mark['max_marks']); ?></td>
                                        <td class="percentage-cell <?php echo $percentage_class; ?>">
                                            <?php echo number_format($percentage, 2); ?>%
                                        </td>
                                        <td>
                                            <a href="edit_marks.php?id=<?php echo $mark['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete_marks.php?id=<?php echo $mark['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Are you sure you want to delete this marks record?')"
                                               title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No marks records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 