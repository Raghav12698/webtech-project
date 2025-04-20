<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Get enrolled courses
$courses_sql = "SELECT c.*, e.enrollment_date, 
                (SELECT COUNT(*) FROM attendance a WHERE a.student_id = ? AND a.course_id = c.id) as attendance_count,
                (SELECT COUNT(*) FROM marks m WHERE m.student_id = ? AND m.course_id = c.id) as marks_count
                FROM courses c 
                JOIN enrollments e ON c.id = e.course_id 
                WHERE e.student_id = ?";
$stmt = $conn->prepare($courses_sql);
$stmt->bind_param("iii", $student_id, $student_id, $student_id);
$stmt->execute();
$courses_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Student Dashboard</title>
    <link href="../css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="dashboard.php" class="navbar-brand">
                <i class="fas fa-graduation-cap"></i>
                Student Dashboard
            </a>
            <ul class="nav-list">
                <li><a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a></li>
                <li><a href="courses.php" class="nav-link active">
                    <i class="fas fa-book"></i> Courses
                </a></li>
                <li><a href="attendance.php" class="nav-link">
                    <i class="fas fa-calendar-check"></i> Attendance
                </a></li>
                <li><a href="marks.php" class="nav-link">
                    <i class="fas fa-chart-line"></i> Marks
                </a></li>
                <li><a href="../logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-book"></i> My Courses</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Description</th>
                                <th>Credits</th>
                                <th>Enrolled Date</th>
                                <th>Statistics</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($courses_result->num_rows > 0): ?>
                                <?php while ($course = $courses_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($course['description']); ?></td>
                                        <td><?php echo htmlspecialchars($course['credits']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($course['enrollment_date'])); ?></td>
                                        <td>
                                            <span class="badge badge-primary">
                                                <i class="fas fa-calendar-check"></i> 
                                                <?php echo $course['attendance_count']; ?> Attendance
                                            </span>
                                            <span class="badge badge-success">
                                                <i class="fas fa-chart-line"></i> 
                                                <?php echo $course['marks_count']; ?> Marks
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No courses enrolled yet.</td>
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