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

// Check if course_id is provided
if (!isset($_GET['course_id'])) {
    header('Location: marks.php');
    exit();
}

$course_id = $_GET['course_id'];

// Verify the course belongs to the teacher
try {
    $course_check_sql = "SELECT course_name, course_code FROM courses WHERE id = ? AND teacher_id = ?";
    $course_check_stmt = $conn->prepare($course_check_sql);
    $course_check_stmt->bind_param("ii", $course_id, $teacher_id);
    $course_check_stmt->execute();
    $course_result = $course_check_stmt->get_result();

    if ($course_result->num_rows === 0) {
        header('Location: marks.php');
        exit();
    }

    $course = $course_result->fetch_assoc();

    // Get enrolled students
    $students_sql = "SELECT s.id, s.name, s.email 
                    FROM students s 
                    JOIN enrollments e ON s.id = e.student_id 
                    WHERE e.course_id = ? 
                    ORDER BY s.name";
    $students_stmt = $conn->prepare($students_sql);
    $students_stmt->bind_param("i", $course_id);
    $students_stmt->execute();
    $students_result = $students_stmt->get_result();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $assessment_name = trim($_POST['assessment_name']);
        $date = $_POST['date'];
        $marks = $_POST['marks'];

        // Validate input
        if (empty($assessment_name) || empty($date)) {
            $error_message = "Assessment name and date are required.";
        } else {
            // Begin transaction
            $conn->begin_transaction();

            try {
                // Insert assessment marks for each student
                $insert_sql = "INSERT INTO marks (course_id, student_id, assessment_name, marks, date) VALUES (?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);

                foreach ($marks as $student_id => $mark) {
                    if (is_numeric($mark)) {
                        $insert_stmt->bind_param("iisds", $course_id, $student_id, $assessment_name, $mark, $date);
                        $insert_stmt->execute();
                    }
                }

                $conn->commit();
                $success_message = "Assessment marks have been successfully recorded.";
                
                // Clear form data after successful submission
                $_POST = array();
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error recording marks: " . $e->getMessage();
            }
        }
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Assessment - Teacher Dashboard</title>
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

        .card {
            background: var(--surface);
            border-radius: 0.75rem;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            background: var(--surface);
            color: var(--text);
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-dark);
        }

        .marks-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .marks-table th,
        .marks-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .marks-table th {
            font-weight: 600;
            color: var(--text-secondary);
        }

        .marks-table tr:last-child td {
            border-bottom: none;
        }

        .marks-table input[type="number"] {
            width: 100px;
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 0.375rem;
            background: var(--surface);
            color: var(--text);
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
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
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

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 1rem;
            }

            .marks-table {
                display: block;
                overflow-x: auto;
            }

            .nav-list {
                display: none;
            }

            .nav-list.active {
                display: flex;
                flex-direction: column;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--surface);
                padding: 1rem;
                box-shadow: var(--shadow);
            }

            .mobile-nav-toggle {
                display: block;
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
                <li><a href="courses.php" class="nav-link">
                    <i class="fas fa-book"></i> My Courses
                </a></li>
                <li><a href="attendance.php" class="nav-link">
                    <i class="fas fa-calendar-check"></i> Attendance
                </a></li>
                <li><a href="marks.php" class="nav-link active">
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
                <i class="fas fa-plus-circle"></i>
                Add Assessment
            </h1>
            <a href="marks.php?course_id=<?php echo $course_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Back to Marks
            </a>
        </div>

        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">
                <?php echo htmlspecialchars($course['course_name']); ?> 
                <span style="color: var(--text-secondary); font-size: 0.875rem;">
                    (<?php echo htmlspecialchars($course['course_code']); ?>)
                </span>
            </h2>

            <?php if ($students_result->num_rows > 0): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="assessment_name">Assessment Name</label>
                        <input type="text" id="assessment_name" name="assessment_name" class="form-control" 
                               required placeholder="e.g., Midterm Exam, Quiz 1" 
                               value="<?php echo isset($_POST['assessment_name']) ? htmlspecialchars($_POST['assessment_name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="date">Date</label>
                        <input type="date" id="date" name="date" class="form-control" required
                               value="<?php echo isset($_POST['date']) ? htmlspecialchars($_POST['date']) : date('Y-m-d'); ?>">
                    </div>

                    <table class="marks-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $students_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td>
                                        <input type="number" name="marks[<?php echo $student['id']; ?>]" 
                                               min="0" max="100" step="0.1" required
                                               value="<?php echo isset($_POST['marks'][$student['id']]) ? htmlspecialchars($_POST['marks'][$student['id']]) : ''; ?>"
                                               class="form-control">
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <div style="margin-top: 2rem; text-align: right;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Assessment
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                    <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h2>No Students Enrolled</h2>
                    <p>There are no students enrolled in this course yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleMobileNav() {
            const navList = document.getElementById('nav-list');
            navList.classList.toggle('active');
            const icon = document.querySelector('.mobile-nav-toggle i');
            icon.className = navList.classList.contains('active') ? 'fas fa-times' : 'fas fa-bars';
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