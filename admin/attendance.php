<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Get all courses for the dropdown
$courses_sql = "SELECT id, course_code, course_name FROM courses";
$courses_result = $conn->query($courses_sql);

// Get all students for the dropdown
$students_sql = "SELECT id, username, email FROM users WHERE role = 'student'";
$students_result = $conn->query($students_sql);

// Handle form submission for adding attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $course_id = $_POST['course_id'];
    $date = $_POST['date'];
    $status = $_POST['status'];

    $insert_sql = "INSERT INTO attendance (student_id, course_id, date, status) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    if ($stmt) {
        $stmt->bind_param("iiss", $student_id, $course_id, $date, $status);
        if ($stmt->execute()) {
            $success_message = "Attendance recorded successfully!";
        } else {
            $error_message = "Error recording attendance: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_message = "Error preparing statement: " . $conn->error;
    }
}

// Get attendance records with student and course information
$attendance_sql = "SELECT a.*, 
                         u.username as student_name, 
                         c.course_code,
                         c.course_name
                  FROM attendance a
                  JOIN users u ON a.student_id = u.id
                  JOIN courses c ON a.course_id = c.id
                  ORDER BY a.date DESC, c.course_code";
$attendance_result = $conn->query($attendance_sql);

if (!$attendance_result) {
    $error_message = "Error fetching attendance records: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendance - Admin Dashboard</title>
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
                <h3 class="card-title"><i class="fas fa-calendar-check"></i> Manage Attendance</h3>
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

                <!-- Add Attendance Form -->
                <form method="POST" action="attendance.php" class="mb-4">
                    <div class="row">
                        <div class="col-md-3">
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
                        <div class="col-md-3">
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
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="date">Date:</label>
                                <input type="date" class="form-control" id="date" name="date" required 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="status">Status:</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Attendance
                        </button>
                    </div>
                </form>

                <!-- Attendance Records Table -->
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($attendance_result && $attendance_result->num_rows > 0): ?>
                                <?php while ($attendance = $attendance_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($attendance['date']))); ?></td>
                                        <td><?php echo htmlspecialchars($attendance['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($attendance['course_code'] . ' - ' . $attendance['course_name']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $attendance['status'] === 'present' ? 'success' : ($attendance['status'] === 'late' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst(htmlspecialchars($attendance['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="edit_attendance.php?id=<?php echo $attendance['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete_attendance.php?id=<?php echo $attendance['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Are you sure you want to delete this attendance record?')"
                                               title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No attendance records found.</td>
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