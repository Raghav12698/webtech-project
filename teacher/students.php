<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];
$error_message = '';

try {
    // Get all students enrolled in teacher's courses
    $students_sql = "SELECT DISTINCT 
                        u.id,
                        u.username,
                        u.email,
                        GROUP_CONCAT(DISTINCT c.course_name) as enrolled_courses,
                        COUNT(DISTINCT c.id) as total_courses,
                        COUNT(DISTINCT a.id) as attendance_count,
                        COUNT(DISTINCT m.id) as assessments_count
                    FROM users u
                    INNER JOIN enrollments e ON u.id = e.student_id
                    INNER JOIN courses c ON e.course_id = c.id
                    LEFT JOIN attendance a ON u.id = a.student_id AND a.course_id = c.id
                    LEFT JOIN marks m ON u.id = m.student_id AND m.course_id = c.id
                    WHERE c.teacher_id = ? AND u.role = 'student'
                    GROUP BY u.id
                    ORDER BY u.username";

    $stmt = $conn->prepare($students_sql);
    if ($stmt === false) {
        throw new Exception("Error preparing query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $students_result = $stmt->get_result();

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - Teacher Dashboard</title>
    <link href="../css/dashboard.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="../js/theme.js"></script>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="navbar-left">
                <a href="dashboard.php" class="navbar-brand">
                    <i class="fas fa-chalkboard-teacher"></i>
                    Teacher Dashboard
                </a>
            </div>
            <ul class="nav-list">
                <li><a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a></li>
                <li><a href="courses.php" class="nav-link">
                    <i class="fas fa-book"></i> My Courses
                </a></li>
                <li><a href="students.php" class="nav-link active">
                    <i class="fas fa-users"></i> Students
                </a></li>
                <li><a href="attendance.php" class="nav-link">
                    <i class="fas fa-calendar-check"></i> Attendance
                </a></li>
                <li><a href="marks.php" class="nav-link">
                    <i class="fas fa-chart-line"></i> Marks
                </a></li>
                <li><a href="profile.php" class="nav-link">
                    <i class="fas fa-user"></i> Profile
                </a></li>
                <li><a href="../logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a></li>
            </ul>
        </div>
    </nav>

    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <h2><i class="fas fa-users"></i> My Students</h2>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <?php if ($students_result && $students_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Email</th>
                                        <th>Enrolled Courses</th>
                                        <th>Total Courses</th>
                                        <th>Attendance Records</th>
                                        <th>Assessments</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($student = $students_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['username']); ?></td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td><?php echo htmlspecialchars($student['enrolled_courses']); ?></td>
                                            <td><?php echo $student['total_courses']; ?></td>
                                            <td><?php echo $student['attendance_count']; ?></td>
                                            <td><?php echo $student['assessments_count']; ?></td>
                                            <td>
                                                <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="student_attendance.php?id=<?php echo $student['id']; ?>" class="btn btn-success btn-sm">
                                                    <i class="fas fa-calendar-check"></i> Attendance
                                                </a>
                                                <a href="student_marks.php?id=<?php echo $student['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-chart-line"></i> Marks
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No students are currently enrolled in your courses.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 