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

// Get teacher's statistics
$stats_sql = "SELECT 
    (SELECT COUNT(DISTINCT e.student_id) 
     FROM enrollments e 
     JOIN courses c ON e.course_id = c.id 
     WHERE c.teacher_id = ?) as total_students,
    (SELECT COUNT(*) 
     FROM courses 
     WHERE teacher_id = ?) as total_courses,
    (SELECT COUNT(DISTINCT a.student_id) 
     FROM attendance a 
     JOIN courses c ON a.course_id = c.id 
     WHERE c.teacher_id = ? AND DATE(a.date) = CURDATE()) as today_attendance,
    (SELECT COUNT(*) 
     FROM marks m 
     JOIN courses c ON m.course_id = c.id 
     WHERE c.teacher_id = ?) as total_assessments";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("iiii", $teacher_id, $teacher_id, $teacher_id, $teacher_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get all teacher's courses with additional info
$courses_sql = "SELECT 
    c.id, 
    c.course_name, 
    c.course_code,
    c.credits,
    (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e WHERE e.course_id = c.id) as enrolled_students,
    (SELECT COUNT(DISTINCT a.date) FROM attendance a WHERE a.course_id = c.id) as attendance_days,
    (SELECT COUNT(DISTINCT m.assessment_name) FROM marks m WHERE m.course_id = c.id) as assessments
FROM courses c 
WHERE c.teacher_id = ? 
ORDER BY c.created_at DESC";

$courses_stmt = $conn->prepare($courses_sql);
$courses_stmt->bind_param("i", $teacher_id);
$courses_stmt->execute();
$courses = $courses_stmt->get_result();

// Get recent activities
$activities_sql = "
    (SELECT 'attendance' as type, 
            CONCAT(u.username, ' - ', c.course_name) as name, 
            a.date as date
     FROM attendance a
     JOIN users u ON a.student_id = u.id
     JOIN courses c ON a.course_id = c.id
     WHERE c.teacher_id = ?
     ORDER BY a.date DESC
     LIMIT 3)
    UNION ALL
    (SELECT 'mark' as type,
            CONCAT(u.username, ' - ', c.course_name) as name,
            m.created_at as date
     FROM marks m
     JOIN users u ON m.student_id = u.id
     JOIN courses c ON m.course_id = c.id
     WHERE c.teacher_id = ?
     ORDER BY m.created_at DESC
     LIMIT 3)
    ORDER BY date DESC
    LIMIT 5";

$activities_stmt = $conn->prepare($activities_sql);
$activities_stmt->bind_param("ii", $teacher_id, $teacher_id);
$activities_stmt->execute();
$activities = $activities_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
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
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
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
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.3);
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

        .navbar {
            background: var(--surface);
            box-shadow: var(--shadow);
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
        }

        .nav-list {
            display: flex;
            gap: 1.5rem;
            list-style: none;
        }

        .nav-link {
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }

        .nav-link:hover {
            color: var(--primary);
            background: var(--background);
        }

        .nav-link.active {
            color: var(--primary);
            background: var(--background);
        }

        .dashboard {
            padding: 2rem 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--surface);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .students .stat-icon { background: linear-gradient(135deg, #4f46e5, #6366f1); }
        .courses .stat-icon { background: linear-gradient(135deg, #22c55e, #4ade80); }
        .attendance .stat-icon { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
        .assessments .stat-icon { background: linear-gradient(135deg, #ef4444, #f87171); }

        .stat-title {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 600;
            color: var(--text);
            line-height: 1;
        }

        .stat-desc {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        .card {
            background: var(--surface);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            color: var(--text);
            font-size: 1.25rem;
            font-weight: 600;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border);
        }

        .activity-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .activity-icon.attendance { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
        .activity-icon.mark { background: linear-gradient(135deg, #ef4444, #f87171); }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 500;
            color: var(--text);
            margin-bottom: 0.25rem;
        }

        .activity-time {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .course-list {
            list-style: none;
        }

        .course-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 0.75rem;
            background: var(--background);
            margin-bottom: 0.75rem;
            transition: all 0.2s;
        }

        .course-item:last-child {
            margin-bottom: 0;
        }

        .course-item:hover {
            transform: translateY(-1px);
            background: var(--primary);
            color: white;
        }

        .course-icon {
            font-size: 1.25rem;
        }

        .course-info {
            flex: 1;
        }

        .course-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .course-code {
            font-size: 0.875rem;
            opacity: 0.8;
        }

        .quick-actions {
            display: grid;
            gap: 1rem;
            margin-top: 2rem;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--background);
            border: none;
            border-radius: 0.75rem;
            color: var(--text);
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.2s;
        }

        .action-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-1px);
        }

        .theme-switch {
            background: none;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            border-radius: 0.5rem;
            transition: all 0.2s;
        }

        .theme-switch:hover {
            background: var(--background);
            color: var(--primary);
        }

        .theme-switch i {
            font-size: 1.25rem;
        }

        .mobile-nav-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
        }

        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .course-card {
            background: var(--surface);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }

        .course-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
        }

        .course-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .course-header {
            margin-bottom: 1rem;
        }

        .course-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.25rem;
        }

        .course-code {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .course-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin: 1rem 0;
        }

        .course-stat {
            text-align: center;
            padding: 0.75rem;
            background: var(--background);
            border-radius: 0.5rem;
        }

        .course-stat-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text);
        }

        .course-stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .course-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .course-action-btn {
            flex: 1;
            padding: 0.5rem;
            border: none;
            border-radius: 0.5rem;
            background: var(--background);
            color: var(--text);
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            text-align: center;
        }

        .course-action-btn:hover {
            background: var(--primary);
            color: white;
        }

        .add-course-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 56px;
            height: 56px;
            border-radius: 28px;
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: var(--shadow-lg);
            transition: transform 0.2s;
        }

        .add-course-btn:hover {
            transform: scale(1.1);
        }

        @media (max-width: 768px) {
            .mobile-nav-toggle {
                display: block;
            }

            .nav-list {
                display: none;
                position: fixed;
                top: 72px;
                left: 0;
                right: 0;
                background: var(--surface);
                padding: 1rem;
                flex-direction: column;
                gap: 0.5rem;
            }

            .nav-list.active {
                display: flex;
            }

            .course-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
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
            <button class="mobile-nav-toggle" onclick="toggleMobileNav()">
                <i class="fas fa-bars"></i>
            </button>
            <ul class="nav-list" id="nav-list">
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
                <li><a href="profile.php" class="nav-link">
                    <i class="fas fa-user"></i> Profile
                </a></li>
                <li><a href="../logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a></li>
                <li>
                    <button class="theme-switch" onclick="toggleTheme()" title="Toggle dark/light mode">
                        <i class="fas fa-moon"></i>
                    </button>
                </li>
            </ul>
        </div>
    </nav>

    <div class="dashboard">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card students">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-title">Total Students</div>
                    <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                    <div class="stat-desc">Enrolled in your courses</div>
                </div>

                <div class="stat-card courses">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-title">Your Courses</div>
                    <div class="stat-value"><?php echo $stats['total_courses']; ?></div>
                    <div class="stat-desc">Active courses</div>
                </div>

                <div class="stat-card attendance">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-title">Today's Attendance</div>
                    <div class="stat-value"><?php echo $stats['today_attendance']; ?></div>
                    <div class="stat-desc">Students present today</div>
                </div>

                <div class="stat-card assessments">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-title">Total Assessments</div>
                    <div class="stat-value"><?php echo $stats['total_assessments']; ?></div>
                    <div class="stat-desc">Marks recorded</div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clock"></i>
                        Recent Activity
                    </div>
                    <ul class="activity-list">
                        <?php while ($activity = $activities->fetch_assoc()): ?>
                            <li class="activity-item">
                                <div class="activity-icon <?php echo $activity['type']; ?>">
                                    <i class="fas fa-<?php echo $activity['type'] === 'attendance' ? 'calendar-check' : 'chart-line'; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <?php echo $activity['type'] === 'attendance' ? 'Attendance Marked' : 'Assessment Added'; ?>: 
                                        <?php echo htmlspecialchars($activity['name']); ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php 
                                            $date = new DateTime($activity['date']);
                                            echo $date->format('M j, Y g:i A');
                                        ?>
                                    </div>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bolt"></i>
                        Quick Actions
                    </div>
                    <div class="quick-actions">
                        <a href="attendance.php" class="action-btn">
                            <i class="fas fa-calendar-plus"></i>
                            Mark Attendance
                        </a>
                        <a href="marks.php" class="action-btn">
                            <i class="fas fa-plus-circle"></i>
                            Add Assessment
                        </a>
                        <a href="students.php" class="action-btn">
                            <i class="fas fa-users"></i>
                            View Students
                        </a>
                    </div>
                </div>
            </div>

            <div class="course-grid">
                <?php while ($course = $courses->fetch_assoc()): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                            <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?> • <?php echo $course['credits']; ?> Credits</div>
                        </div>
                        <div class="course-stats">
                            <div class="course-stat">
                                <div class="course-stat-value"><?php echo $course['enrolled_students']; ?></div>
                                <div class="course-stat-label">Students</div>
                            </div>
                            <div class="course-stat">
                                <div class="course-stat-value"><?php echo $course['attendance_days']; ?></div>
                                <div class="course-stat-label">Classes</div>
                            </div>
                            <div class="course-stat">
                                <div class="course-stat-value"><?php echo $course['assessments']; ?></div>
                                <div class="course-stat-label">Assessments</div>
                            </div>
                            <div class="course-stat">
                                <div class="course-stat-value"><?php echo number_format(($course['attendance_days'] / 15) * 100); ?>%</div>
                                <div class="course-stat-label">Progress</div>
                            </div>
                        </div>
                        <div class="course-actions">
                            <a href="view_course.php?id=<?php echo $course['id']; ?>" class="course-action-btn">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="mark_attendance.php?course_id=<?php echo $course['id']; ?>" class="course-action-btn">
                                <i class="fas fa-calendar-check"></i> Attendance
                            </a>
                            <a href="add_assessment.php?course_id=<?php echo $course['id']; ?>" class="course-action-btn">
                                <i class="fas fa-plus"></i> Assessment
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <a href="add_course.php" class="add-course-btn" title="Add New Course">
                <i class="fas fa-plus"></i>
            </a>
        </div>
    </div>

    <script>
        // Check for saved theme preference, otherwise use system preference
        const getPreferredTheme = () => {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                return savedTheme;
            }
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        };

        // Function to set theme
        const setTheme = (theme) => {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            
            // Update button icon
            const themeIcon = document.querySelector('.theme-switch i');
            themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        };

        // Function to toggle theme
        const toggleTheme = () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);
        };

        // Set initial theme
        setTheme(getPreferredTheme());

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!localStorage.getItem('theme')) {
                setTheme(e.matches ? 'dark' : 'light');
            }
        });

        // Mobile navigation toggle
        function toggleMobileNav() {
            const navList = document.getElementById('nav-list');
            navList.classList.toggle('active');
            const icon = document.querySelector('.mobile-nav-toggle i');
            icon.className = navList.classList.contains('active') ? 'fas fa-times' : 'fas fa-bars';
        }
    </script>
</body>
</html> 