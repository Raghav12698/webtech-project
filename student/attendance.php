<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Get attendance records
$attendance_sql = "SELECT a.*, c.course_code, c.course_name 
                  FROM attendance a
                  JOIN courses c ON a.course_id = c.id
                  WHERE a.student_id = ?
                  ORDER BY a.date DESC";
$stmt = $conn->prepare($attendance_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$attendance_result = $stmt->get_result();

// Calculate attendance statistics
$stats_sql = "SELECT 
                c.course_code,
                c.course_name,
                COUNT(*) as total_classes,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count
              FROM attendance a
              JOIN courses c ON a.course_id = c.id
              WHERE a.student_id = ?
              GROUP BY c.id";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $student_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - Student Dashboard</title>
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
                <li><a href="courses.php" class="nav-link">
                    <i class="fas fa-book"></i> Courses
                </a></li>
                <li><a href="attendance.php" class="nav-link active">
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
        <!-- Attendance Statistics -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie"></i> Attendance Statistics</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Total Classes</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                                <th>Attendance %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($stats_result->num_rows > 0): ?>
                                <?php while ($stats = $stats_result->fetch_assoc()): ?>
                                    <?php 
                                        $attendance_percentage = ($stats['present_count'] + ($stats['late_count'] * 0.5)) / $stats['total_classes'] * 100;
                                        $badge_class = $attendance_percentage >= 75 ? 'success' : ($attendance_percentage >= 60 ? 'warning' : 'danger');
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($stats['course_code']); ?></td>
                                        <td><?php echo $stats['total_classes']; ?></td>
                                        <td><?php echo $stats['present_count']; ?></td>
                                        <td><?php echo $stats['absent_count']; ?></td>
                                        <td><?php echo $stats['late_count']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $badge_class; ?>">
                                                <?php echo number_format($attendance_percentage, 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No attendance records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Attendance Records -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-check"></i> Attendance Records</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Course</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($attendance_result->num_rows > 0): ?>
                                <?php while ($attendance = $attendance_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($attendance['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($attendance['course_code']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $attendance['status'] === 'present' ? 'success' : ($attendance['status'] === 'late' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst(htmlspecialchars($attendance['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No attendance records found.</td>
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