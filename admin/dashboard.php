<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle teacher approval/rejection
if (isset($_POST['action']) && isset($_POST['teacher_id'])) {
    $teacher_id = (int)$_POST['teacher_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $update_sql = "UPDATE users SET status = 'active' WHERE id = ? AND role = 'teacher'";
    } elseif ($action === 'reject') {
        $update_sql = "UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'teacher'";
    }
    
    if (isset($update_sql)) {
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
    }
    
    // Redirect to remove POST data
    header('Location: dashboard.php');
    exit();
}

// Get statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
    (SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'pending') as pending_teachers,
    (SELECT COUNT(*) FROM courses) as total_courses,
    (SELECT COUNT(DISTINCT student_id) FROM attendance WHERE DATE(date) = CURDATE()) as today_attendance";
$stats = $conn->query($stats_sql)->fetch_assoc();

// Get recent activities
$activities_sql = "
    (SELECT 'student' as type, username as name, created_at as date
     FROM users 
     WHERE role = 'student'
     ORDER BY created_at DESC LIMIT 3)
    UNION ALL
    (SELECT 'course' as type, course_name as name, created_at as date
     FROM courses 
     ORDER BY created_at DESC LIMIT 3)
    ORDER BY date DESC LIMIT 5";
$activities = $conn->query($activities_sql);
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
        .teachers .stat-icon { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
        .courses .stat-icon { background: linear-gradient(135deg, #22c55e, #4ade80); }
        .attendance .stat-icon { background: linear-gradient(135deg, #ef4444, #f87171); }

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

        .activity-icon.student { background: linear-gradient(135deg, #4f46e5, #6366f1); }
        .activity-icon.course { background: linear-gradient(135deg, #22c55e, #4ade80); }

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

        .quick-actions {
            display: grid;
            gap: 1rem;
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

        @media (max-width: 768px) {
            .nav-list {
                display: none;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .theme-switch {
                margin-left: auto;
            }
        }
    </style>
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
                <li><a href="teachers.php" class="nav-link">
                    <i class="fas fa-chalkboard-teacher"></i> Teachers
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
                    <div class="stat-desc">Enrolled students</div>
                </div>

                <div class="stat-card teachers">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-title">Pending Teachers</div>
                    <div class="stat-value"><?php echo $stats['pending_teachers']; ?></div>
                    <div class="stat-desc">Awaiting approval</div>
                </div>

                <div class="stat-card courses">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-title">Total Courses</div>
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
                                    <i class="fas fa-<?php echo $activity['type'] === 'student' ? 'user-graduate' : 'book'; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        New <?php echo ucfirst($activity['type']); ?>: <?php echo htmlspecialchars($activity['name']); ?>
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
                        <a href="add_student.php" class="action-btn">
                            <i class="fas fa-user-plus"></i>
                            Add New Student
                        </a>
                        <a href="add_teacher.php" class="action-btn">
                            <i class="fas fa-chalkboard-teacher"></i>
                            Add New Teacher
                        </a>
                        <a href="add_course.php" class="action-btn">
                            <i class="fas fa-plus-circle"></i>
                            Add New Course
                        </a>
                        <a href="attendance.php" class="action-btn">
                            <i class="fas fa-calendar-plus"></i>
                            Mark Attendance
                        </a>
                    </div>
                </div>
            </div>
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
    </script>
</body>
</html> 