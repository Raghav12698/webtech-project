<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];
$error_message = '';

// Check if course ID is provided
if (!isset($_GET['id'])) {
    header('Location: courses.php');
    exit();
}

$course_id = intval($_GET['id']);

// Verify course belongs to the teacher and get course details
$course_sql = "SELECT id, course_name, course_code, description, credits, teacher_id, created_at, updated_at 
               FROM courses 
               WHERE id = ? AND teacher_id = ?";
$course_stmt = $conn->prepare($course_sql);
if (!$course_stmt) {
    die("Error preparing statement: " . $conn->error);
}
$course_stmt->bind_param("ii", $course_id, $teacher_id);
$course_stmt->execute();
$course = $course_stmt->get_result()->fetch_assoc();

if (!$course) {
    header('Location: courses.php');
    exit();
}

// Get enrolled students
$students_sql = "SELECT u.id, u.username, u.email
                 FROM users u
                 INNER JOIN enrollments e ON u.id = e.student_id
                 WHERE e.course_id = ?
                 ORDER BY u.username";
$students_stmt = $conn->prepare($students_sql);
if (!$students_stmt) {
    die("Error preparing statement: " . $conn->error);
}
$students_stmt->bind_param("i", $course_id);
$students_stmt->execute();
$students_result = $students_stmt->get_result();

// Get assessments
$assessments_sql = "SELECT id, assessment_name, date, total_marks
                    FROM assessments
                    WHERE course_id = ?
                    ORDER BY date DESC";
$assessments_stmt = $conn->prepare($assessments_sql);
if (!$assessments_stmt) {
    die("Error preparing statement: " . $conn->error);
}
$assessments_stmt->bind_param("i", $course_id);
$assessments_stmt->execute();
$assessments_result = $assessments_stmt->get_result();

// Get recent attendance records
$attendance_sql = "SELECT a.date, 
                         COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                         COUNT(*) as total_count
                  FROM attendance a
                  WHERE a.course_id = ?
                  GROUP BY a.date
                  ORDER BY a.date DESC
                  LIMIT 5";
$attendance_stmt = $conn->prepare($attendance_sql);
if (!$attendance_stmt) {
    die("Error preparing statement: " . $conn->error);
}
$attendance_stmt->bind_param("i", $course_id);
$attendance_stmt->execute();
$attendance_result = $attendance_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['course_name']); ?> - Course Details</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root[data-theme="light"] {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --secondary: #64748b;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --background: #f8fafc;
            --surface: #ffffff;
            --text: #0f172a;
            --text-secondary: #64748b;
            --border: #e2e8f0;
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
        }

        :root[data-theme="dark"] {
            --primary: #818cf8;
            --primary-dark: #6366f1;
            --secondary: #94a3b8;
            --success: #4ade80;
            --warning: #fbbf24;
            --danger: #f87171;
            --background: #0f172a;
            --surface: #1e293b;
            --text: #f1f5f9;
            --text-secondary: #94a3b8;
            --border: #334155;
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .navbar {
            background: var(--surface);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
            margin-bottom: 2rem;
        }

        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text);
            text-decoration: none;
        }

        .nav-list {
            display: flex;
            align-items: center;
            gap: 1rem;
            list-style: none;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary);
            background: var(--background);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .course-code {
            font-size: 1rem;
            color: var(--text-secondary);
            font-weight: normal;
        }

        .card {
            background: var(--surface);
            border-radius: 1rem;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
        }

        .btn-secondary:hover {
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--surface);
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .stat-title {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text);
        }

        .stat-icon {
            color: var(--primary);
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .data-table th {
            font-weight: 600;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tbody tr:hover {
            background: var(--background);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-success {
            background: var(--success);
            color: white;
        }

        .badge-warning {
            background: var(--warning);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .empty-state-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 1rem;
            }

            .card {
                padding: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="dashboard.php" class="navbar-brand">
                <i class="fas fa-chalkboard-teacher"></i>
                Teacher Dashboard
            </a>
            <ul class="nav-list">
                <li><a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a></li>
                <li><a href="courses.php" class="nav-link active">
                    <i class="fas fa-book"></i> My Courses
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
                <li>
                    <button class="theme-switch nav-link" onclick="toggleTheme()">
                        <i class="fas fa-moon"></i>
                    </button>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-book"></i>
                <?php echo htmlspecialchars($course['course_name']); ?>
                <span class="course-code">(<?php echo htmlspecialchars($course['course_code']); ?>)</span>
            </h1>
            <div style="display: flex; gap: 1rem;">
                <a href="edit_course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Course
                </a>
                <a href="courses.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Courses
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users stat-icon"></i>
                <div class="stat-title">Enrolled Students</div>
                <div class="stat-value"><?php echo $students_result->num_rows; ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-tasks stat-icon"></i>
                <div class="stat-title">Total Assessments</div>
                <div class="stat-value"><?php echo $assessments_result->num_rows; ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-graduation-cap stat-icon"></i>
                <div class="stat-title">Credits</div>
                <div class="stat-value"><?php echo $course['credits']; ?></div>
            </div>
        </div>

        <div class="card">
            <h2 class="section-title">Course Description</h2>
            <p><?php echo htmlspecialchars($course['description'] ?: 'No description available.'); ?></p>
        </div>

        <div class="card">
            <div class="section-title">
                <span>Enrolled Students</span>
                <a href="add_student.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i>
                    Add Student
                </a>
            </div>
            <?php if ($students_result->num_rows > 0): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $students_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['username']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td>
                                        <a href="view_student.php?course_id=<?php echo $course['id']; ?>&student_id=<?php echo $student['id']; ?>" 
                                           class="btn btn-secondary">
                                            <i class="fas fa-eye"></i>
                                            View Progress
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users empty-state-icon"></i>
                    <h3 class="empty-state-title">No Students Enrolled</h3>
                    <p>Start by adding students to your course.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="section-title">
                <span>Assessments</span>
                <a href="add_assessment.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add Assessment
                </a>
            </div>
            <?php if ($assessments_result->num_rows > 0): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Assessment Name</th>
                                <th>Date</th>
                                <th>Total Marks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($assessment = $assessments_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assessment['assessment_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($assessment['date'])); ?></td>
                                    <td><?php echo $assessment['total_marks']; ?></td>
                                    <td>
                                        <a href="edit_marks.php?course_id=<?php echo $course['id']; ?>&assessment_id=<?php echo $assessment['id']; ?>" 
                                           class="btn btn-secondary">
                                            <i class="fas fa-edit"></i>
                                            Edit Marks
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tasks empty-state-icon"></i>
                    <h3 class="empty-state-title">No Assessments Added</h3>
                    <p>Start by adding assessments to your course.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="section-title">
                <span>Recent Attendance</span>
                <a href="mark_attendance.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-calendar-check"></i>
                    Mark Attendance
                </a>
            </div>
            <?php if ($attendance_result->num_rows > 0): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Present</th>
                                <th>Total</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($attendance = $attendance_result->fetch_assoc()): ?>
                                <?php 
                                $percentage = ($attendance['present_count'] / $attendance['total_count']) * 100;
                                $badge_class = $percentage >= 75 ? 'badge-success' : 'badge-warning';
                                ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($attendance['date'])); ?></td>
                                    <td><?php echo $attendance['present_count']; ?></td>
                                    <td><?php echo $attendance['total_count']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo number_format($percentage, 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-check empty-state-icon"></i>
                    <h3 class="empty-state-title">No Attendance Records</h3>
                    <p>Start by marking attendance for your course.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Theme management
        const getPreferredTheme = () => {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                return savedTheme;
            }
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        };

        const setTheme = (theme) => {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        };

        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);
            
            // Update theme switch icon
            const themeIcon = document.querySelector('.theme-switch i');
            themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Set initial theme
        setTheme(getPreferredTheme());
    </script>
</body>
</html> 