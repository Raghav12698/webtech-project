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
$success_message = '';

// Get teacher's courses with student count and average marks
$courses_sql = "SELECT c.*, 
                COUNT(DISTINCT e.student_id) as student_count,
                COUNT(DISTINCT a.id) as assessment_count
                FROM courses c
                LEFT JOIN enrollments e ON c.id = e.course_id
                LEFT JOIN assessments a ON c.id = a.course_id
                WHERE c.teacher_id = ?
                GROUP BY c.id
                ORDER BY c.course_name";

$courses_stmt = $conn->prepare($courses_sql);
if (!$courses_stmt) {
    $error_message = "Failed to prepare the courses query: " . $conn->error;
} else {
    $courses_stmt->bind_param("i", $teacher_id);
    $courses_stmt->execute();
    $courses_result = $courses_stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Teacher Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Theme variables */
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

        /* Navigation Styles */
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
            max-width: 1200px;
            margin: 0 auto;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text);
            text-decoration: none;
            transition: color 0.2s;
        }

        .navbar-brand:hover {
            color: var(--primary);
        }

        .nav-list {
            display: flex;
            align-items: center;
            gap: 1rem;
            list-style: none;
            margin: 0;
            padding: 0;
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

        .nav-link:hover {
            color: var(--primary);
            background: var(--background);
        }

        .nav-link.active {
            color: var(--primary);
            background: var(--background);
            font-weight: 500;
        }

        /* Rest of your existing styles */

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
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
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .alert-error {
            background: var(--danger);
            color: white;
        }

        .alert-success {
            background: var(--success);
            color: white;
        }

        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .course-card {
            background: var(--surface);
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }

        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .course-header {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            padding: 1.5rem;
            color: white;
        }

        .course-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .course-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .course-content {
            padding: 1.5rem;
        }

        .course-description {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .course-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            background: var(--background);
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .course-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .action-btn {
            padding: 0.75rem;
            border: none;
            border-radius: 0.5rem;
            background: var(--background);
            color: var(--text);
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .action-btn:hover {
            background: var(--primary);
            color: white;
        }

        .action-btn.danger {
            color: var(--danger);
        }

        .action-btn.danger:hover {
            background: var(--danger);
            color: white;
        }

        .add-course-btn {
            padding: 0.75rem 1.5rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .add-course-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--background);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            transition: width 0.3s ease;
        }

        @media (max-width: 768px) {
            .course-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
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
                    <button class="theme-switch nav-link" onclick="toggleTheme()" title="Toggle dark/light mode">
                        <i class="fas fa-moon"></i>
                    </button>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-book"></i>
                My Courses
            </h1>
            <a href="add_course.php" class="add-course-btn">
                <i class="fas fa-plus"></i>
                Add New Course
            </a>
        </div>

        <?php if ($courses_result && $courses_result->num_rows > 0): ?>
            <div class="course-grid">
                <?php while ($course = $courses_result->fetch_assoc()): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                            <div class="course-meta">
                                <span><i class="fas fa-code"></i> <?php echo htmlspecialchars($course['course_code']); ?></span>
                                <span><i class="fas fa-star"></i> <?php echo $course['credits']; ?> Credits</span>
                            </div>
                        </div>
                        <div class="course-content">
                            <p class="course-description">
                                <?php echo $course['description'] ? htmlspecialchars($course['description']) : 'No description available.'; ?>
                            </p>
                            <div class="course-stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $course['student_count']; ?></div>
                                    <div class="stat-label">Students</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $course['assessment_count']; ?></div>
                                    <div class="stat-label">Assessments</div>
                                </div>
                            </div>

                            <?php 
                            $progress = min(100, ($course['student_count'] / 15) * 100);
                            ?>
                            <div class="stat-label">Course Progress</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                            </div>

                            <div class="course-actions">
                                <a href="edit_course.php?id=<?php echo $course['id']; ?>" class="action-btn">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="view_course.php?id=<?php echo $course['id']; ?>" class="action-btn">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                <i class="fas fa-book-open" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <h2>No Courses Found</h2>
                <p>You haven't created any courses yet. Click the "Add New Course" button to get started.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleMobileNav() {
            const navList = document.getElementById('nav-list');
            navList.classList.toggle('active');
            const icon = document.querySelector('.mobile-nav-toggle i');
            icon.className = navList.classList.contains('active') ? 'fas fa-times' : 'fas fa-bars';
        }

        function deleteCourse(courseId) {
            if (confirm('Are you sure you want to delete this course? This action cannot be undone.')) {
                window.location.href = `delete_course.php?id=${courseId}`;
            }
        }

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