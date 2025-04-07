<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Get student details
$student_id = $_SESSION['user_id'];
$student_sql = "SELECT * FROM users WHERE id = ? AND role = 'student'";
$stmt = $conn->prepare($student_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Get enrolled courses
$courses_sql = "SELECT c.*, e.enrollment_date 
        FROM courses c 
        JOIN enrollments e ON c.id = e.course_id 
        WHERE e.student_id = ?";
$courses_stmt = $conn->prepare($courses_sql);
$courses_stmt->bind_param("i", $student_id);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();

// Get attendance records
$attendance_sql = "SELECT a.*, c.course_code, c.course_name 
        FROM attendance a 
        JOIN courses c ON a.course_id = c.id 
        WHERE a.student_id = ? 
                  ORDER BY a.date DESC LIMIT 5";
$attendance_stmt = $conn->prepare($attendance_sql);
$attendance_stmt->bind_param("i", $student_id);
$attendance_stmt->execute();
$attendance_result = $attendance_stmt->get_result();

// Get marks records
$marks_sql = "SELECT m.*, c.course_code, c.course_name 
        FROM marks m 
        JOIN courses c ON m.course_id = c.id 
             WHERE m.student_id = ?
             ORDER BY m.assessment_date DESC LIMIT 5";
$marks_stmt = $conn->prepare($marks_sql);
$marks_stmt->bind_param("i", $student_id);
$marks_stmt->execute();
$marks_result = $marks_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link href="../css/dashboard.css" rel="stylesheet">
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
                <li><a href="profile.php" class="nav-link">
                    <i class="fas fa-user"></i> Profile
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
                <div class="card">
                    <div class="card-header">
                    <h3><i class="fas fa-user"></i> Welcome, <?php echo htmlspecialchars($student['username']); ?></h3>
                    </div>
                    <div class="card-body">
                    <div class="student-info">
                        <p><i class="fas fa-envelope"></i> Email: <?php echo htmlspecialchars($student['email']); ?></p>
                        <?php if (isset($student['phone'])): ?>
                            <p><i class="fas fa-phone"></i> Phone: <?php echo htmlspecialchars($student['phone']); ?></p>
                        <?php endif; ?>
                        <?php if (isset($student['address'])): ?>
                            <p><i class="fas fa-map-marker-alt"></i> Address: <?php echo htmlspecialchars($student['address']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

                <div class="card">
                    <div class="card-header">
                    <h3><i class="fas fa-book"></i> Enrolled Courses</h3>
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
                                    </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No courses enrolled yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                </div>
            </div>
        </div>

        <div class="row">
                <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                            <h3><i class="fas fa-calendar-check"></i> Recent Attendance</h3>
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

                <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Recent Marks</h3>
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
                                </tr>
                            </thead>
                            <tbody>
                                        <?php if ($marks_result->num_rows > 0): ?>
                                            <?php while ($marks = $marks_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($marks['assessment_date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($marks['course_code']); ?></td>
                                                    <td><?php echo htmlspecialchars($marks['assessment_type']); ?></td>
                                                    <td>
                                                        <?php 
                                                            $percentage = ($marks['marks_obtained'] / $marks['max_marks']) * 100;
                                                            $badge_class = $percentage >= 80 ? 'success' : ($percentage >= 60 ? 'warning' : 'danger');
                                                        ?>
                                                        <span class="badge badge-<?php echo $badge_class; ?>">
                                                            <?php echo $marks['marks_obtained']; ?>/<?php echo $marks['max_marks']; ?>
                                                            (<?php echo number_format($percentage, 1); ?>%)
                                                        </span>
                                                    </td>
                                        </tr>
                                    <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No marks records found.</td>
                                            </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 