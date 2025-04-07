<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Get marks records
$marks_sql = "SELECT m.*, c.course_code, c.course_name 
              FROM marks m
              JOIN courses c ON m.course_id = c.id
              WHERE m.student_id = ?
              ORDER BY m.assessment_date DESC";
$stmt = $conn->prepare($marks_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$marks_result = $stmt->get_result();

// Calculate marks statistics
$stats_sql = "SELECT 
                c.course_code,
                c.course_name,
                COUNT(*) as total_assessments,
                AVG(m.marks_obtained/m.max_marks * 100) as average_percentage,
                MIN(m.marks_obtained/m.max_marks * 100) as min_percentage,
                MAX(m.marks_obtained/m.max_marks * 100) as max_percentage
              FROM marks m
              JOIN courses c ON m.course_id = c.id
              WHERE m.student_id = ?
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
    <title>My Marks - Student Dashboard</title>
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
                <li><a href="attendance.php" class="nav-link">
                    <i class="fas fa-calendar-check"></i> Attendance
                </a></li>
                <li><a href="marks.php" class="nav-link active">
                    <i class="fas fa-chart-line"></i> Marks
                </a></li>
                <li><a href="../logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <!-- Marks Statistics -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Performance Summary</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Assessments</th>
                                <th>Average</th>
                                <th>Lowest</th>
                                <th>Highest</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($stats_result->num_rows > 0): ?>
                                <?php while ($stats = $stats_result->fetch_assoc()): ?>
                                    <?php 
                                        $badge_class = $stats['average_percentage'] >= 80 ? 'success' : 
                                                     ($stats['average_percentage'] >= 60 ? 'warning' : 'danger');
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($stats['course_code']); ?></td>
                                        <td><?php echo $stats['total_assessments']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $badge_class; ?>">
                                                <?php echo number_format($stats['average_percentage'], 1); ?>%
                                            </span>
                                        </td>
                                        <td><?php echo number_format($stats['min_percentage'], 1); ?>%</td>
                                        <td><?php echo number_format($stats['max_percentage'], 1); ?>%</td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No marks records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Marks Records -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Assessment Records</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Course</th>
                                <th>Assessment</th>
                                <th>Marks</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($marks_result->num_rows > 0): ?>
                                <?php while ($marks = $marks_result->fetch_assoc()): ?>
                                    <?php 
                                        $percentage = ($marks['marks_obtained'] / $marks['max_marks']) * 100;
                                        $badge_class = $percentage >= 80 ? 'success' : 
                                                     ($percentage >= 60 ? 'warning' : 'danger');
                                    ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($marks['assessment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($marks['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($marks['assessment_type']); ?></td>
                                        <td><?php echo $marks['marks_obtained']; ?>/<?php echo $marks['max_marks']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $badge_class; ?>">
                                                <?php echo number_format($percentage, 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No marks records found.</td>
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