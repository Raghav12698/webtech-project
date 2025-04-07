<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Get statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
    (SELECT COUNT(*) FROM courses) as total_courses,
    (SELECT COUNT(*) FROM attendance WHERE date = CURRENT_DATE) as today_attendance,
    (SELECT COUNT(*) FROM marks) as total_marks";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Get recent activities
$recent_students_sql = "SELECT id, username, email, created_at FROM users 
                       WHERE role = 'student' ORDER BY created_at DESC LIMIT 5";
$recent_students = $conn->query($recent_students_sql);

$recent_courses_sql = "SELECT * FROM courses ORDER BY id DESC LIMIT 5";
$recent_courses = $conn->query($recent_courses_sql);

$recent_attendance_sql = "SELECT a.*, u.username, c.course_code 
                         FROM attendance a
                         JOIN users u ON a.student_id = u.id
                         JOIN courses c ON a.course_id = c.id
                         ORDER BY a.date DESC LIMIT 5";
$recent_attendance = $conn->query($recent_attendance_sql);

$recent_marks_sql = "SELECT m.*, u.username, c.course_code 
                     FROM marks m
                     JOIN users u ON m.student_id = u.id
                     JOIN courses c ON m.course_id = c.id
                     ORDER BY m.assessment_date DESC LIMIT 5";
$recent_marks = $conn->query($recent_marks_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Student Management System</title>
    <link href="../css/dashboard.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="dashboard.php" class="navbar-brand">
                <i class="fas fa-graduation-cap"></i>
                Admin Dashboard
            </a>
            <ul class="nav-list">
                <li><a href="students.php" class="nav-link">
                    <i class="fas fa-users"></i> Students
                </a></li>
                <li><a href="courses.php" class="nav-link">
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

    <div class="main-content">
    <div class="container">
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3><i class="fas fa-users"></i> Total Students</h3>
                    <div class="number"><?php echo $stats['total_students']; ?></div>
                    </div>
                <div class="stat-card">
                    <h3><i class="fas fa-book"></i> Total Courses</h3>
                    <div class="number"><?php echo $stats['total_courses']; ?></div>
                                    </div>
                <div class="stat-card">
                    <h3><i class="fas fa-calendar-check"></i> Today's Attendance</h3>
                    <div class="number"><?php echo $stats['today_attendance']; ?></div>
                                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-chart-line"></i> Total Marks Records</h3>
                    <div class="number"><?php echo $stats['total_marks']; ?></div>
            </div>
        </div>

        <div class="row">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Recent Activities</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                        <th>Type</th>
                                        <th>Details</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                    <?php while ($student = $recent_students->fetch_assoc()): ?>
                                        <tr>
                                            <td><span class="badge badge-success">New Student</span></td>
                                            <td><?php echo htmlspecialchars($student['username']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                    
                                    <?php while ($attendance = $recent_attendance->fetch_assoc()): ?>
                                        <tr>
                                            <td><span class="badge badge-warning">Attendance</span></td>
                                            <td>
                                                <?php echo htmlspecialchars($attendance['username']); ?> - 
                                                <?php echo htmlspecialchars($attendance['course_code']); ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($attendance['date'])); ?></td>
                                </tr>
                                    <?php endwhile; ?>

                                    <?php while ($marks = $recent_marks->fetch_assoc()): ?>
                                        <tr>
                                            <td><span class="badge badge-primary">Marks</span></td>
                                            <td>
                                                <?php echo htmlspecialchars($marks['username']); ?> - 
                                                <?php echo htmlspecialchars($marks['course_code']); ?>
                                                (<?php echo $marks['marks_obtained']; ?>/<?php echo $marks['max_marks']; ?>)
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($marks['assessment_date'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 