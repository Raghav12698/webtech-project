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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_name = trim($_POST['course_name'] ?? '');
    $course_code = trim($_POST['course_code'] ?? '');
    $credits = intval($_POST['credits'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    // Validate input
    if (empty($course_name) || empty($course_code) || $credits < 1 || $credits > 6) {
        $error_message = 'Please fill in all required fields correctly. Credits must be between 1 and 6.';
    } else {
        try {
            // Check if course code already exists
            $check_sql = "SELECT id FROM courses WHERE course_code = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $course_code);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $error_message = 'Course code already exists. Please use a different code.';
            } else {
                // Insert new course
                $insert_sql = "INSERT INTO courses (course_name, course_code, credits, description, teacher_id) VALUES (?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("ssisi", $course_name, $course_code, $credits, $description, $teacher_id);

                if ($insert_stmt->execute()) {
                    $success_message = 'Course created successfully!';
                    // Clear form data after successful submission
                    $course_name = $course_code = $description = '';
                    $credits = 0;
                } else {
                    throw new Exception("Failed to create course: " . $conn->error);
                }
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Course - Teacher Dashboard</title>
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

        .container {
            max-width: 800px;
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
            max-width: 1200px;
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
            border-radius: 1rem;
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

        .form-label span {
            color: var(--danger);
            margin-left: 0.25rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 0.5rem;
            background: var(--surface);
            color: var(--text);
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        .help-text {
            margin-top: 0.375rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
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

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-error {
            background: var(--danger);
            color: white;
        }

        .alert-success {
            background: var(--success);
            color: white;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .card {
                padding: 1.5rem;
            }

            .form-actions {
                flex-direction: column;
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
                Add New Course
            </h1>
        </div>

        <div class="card">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="course_name">Course Name<span>*</span></label>
                    <input type="text" id="course_name" name="course_name" class="form-control" 
                           value="<?php echo htmlspecialchars($course_name ?? ''); ?>" 
                           placeholder="Enter course name" required>
                    <div class="help-text">Enter a descriptive name for your course</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="course_code">Course Code<span>*</span></label>
                    <input type="text" id="course_code" name="course_code" class="form-control" 
                           value="<?php echo htmlspecialchars($course_code ?? ''); ?>" 
                           placeholder="e.g., CS101" required>
                    <div class="help-text">Enter a unique identifier for your course</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="credits">Credits<span>*</span></label>
                    <input type="number" id="credits" name="credits" class="form-control" 
                           value="<?php echo htmlspecialchars($credits ?? ''); ?>" 
                           min="1" max="6" required>
                    <div class="help-text">Enter the number of credits (1-6)</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Course Description</label>
                    <textarea id="description" name="description" class="form-control" 
                              rows="4" placeholder="Enter course description"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                    <div class="help-text">Provide a brief description of the course content and objectives</div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Create Course
                    </button>
                    <a href="courses.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Courses
                    </a>
                </div>
            </form>
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