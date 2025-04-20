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

// Get teacher's courses
$courses_sql = "SELECT id, course_name, course_code 
                FROM courses 
                WHERE teacher_id = ? 
                ORDER BY course_name";
$courses_stmt = $conn->prepare($courses_sql);
$courses_stmt->bind_param("i", $teacher_id);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();

// Get selected course and its assessments
$selected_course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;
$assessments = [];
$students = [];

if ($selected_course_id) {
    // Verify course belongs to teacher
    $verify_sql = "SELECT id FROM courses WHERE id = ? AND teacher_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $selected_course_id, $teacher_id);
    $verify_stmt->execute();
    
    if ($verify_stmt->get_result()->fetch_assoc()) {
        // Get assessments for the course
        $assessments_sql = "SELECT id, assessment_name, date, total_marks 
                           FROM assessments 
                           WHERE course_id = ? 
                           ORDER BY date DESC";
        $assessments_stmt = $conn->prepare($assessments_sql);
        $assessments_stmt->bind_param("i", $selected_course_id);
        $assessments_stmt->execute();
        $assessments = $assessments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Get enrolled students
        $students_sql = "SELECT s.id, s.username, s.email 
                        FROM users s
                        JOIN enrollments e ON s.id = e.student_id
                        WHERE e.course_id = ?
                        ORDER BY s.username";
        $students_stmt = $conn->prepare($students_sql);
        $students_stmt->bind_param("i", $selected_course_id);
        $students_stmt->execute();
        $students = $students_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Handle form submission for adding marks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_marks'])) {
    $course_id = intval($_POST['course_id']);
    $assessment_id = intval($_POST['assessment_id']);
    $marks = $_POST['marks'] ?? [];

    try {
        // Verify course belongs to teacher
        $verify_sql = "SELECT id FROM courses WHERE id = ? AND teacher_id = ?";
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param("ii", $course_id, $teacher_id);
        $verify_stmt->execute();
        
        if (!$verify_stmt->get_result()->fetch_assoc()) {
            throw new Exception('Invalid course selected.');
        }

        // Start transaction
        $conn->begin_transaction();

        // Delete existing marks for this assessment
        $delete_sql = "DELETE FROM marks WHERE assessment_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $assessment_id);
        $delete_stmt->execute();

        // Insert new marks
        $insert_sql = "INSERT INTO marks (assessment_id, student_id, marks) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);

        foreach ($marks as $student_id => $mark) {
            if (!empty($mark)) {
                $insert_stmt->bind_param("iid", $assessment_id, $student_id, $mark);
                $insert_stmt->execute();
            }
        }

        $conn->commit();
        $success_message = 'Marks recorded successfully!';
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks - Teacher Dashboard</title>
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

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border);
        }

        .table-container {
            overflow-x: auto;
            margin-top: 1.5rem;
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

        .marks-input {
            width: 80px;
            padding: 0.5rem;
            border: 2px solid var(--border);
            border-radius: 0.5rem;
            background: var(--surface);
            color: var(--text);
            font-size: 1rem;
            transition: all 0.2s;
        }

        .marks-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
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

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
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

            .data-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
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
                <i class="fas fa-chart-line"></i>
                Marks Management
            </h1>
        </div>

        <div class="card">
            <h2 class="section-title">Select Course</h2>
            <form method="GET" action="" class="form-group">
                <select name="course_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Select a course</option>
                    <?php while ($course = $courses_result->fetch_assoc()): ?>
                        <option value="<?php echo $course['id']; ?>" 
                                <?php echo $selected_course_id == $course['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['course_name']); ?> 
                            (<?php echo htmlspecialchars($course['course_code']); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>

            <?php if ($selected_course_id): ?>
                <h2 class="section-title">Add Marks</h2>
                <form method="POST" action="">
                    <input type="hidden" name="add_marks" value="1">
                    <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="assessment_id">Select Assessment<span>*</span></label>
                        <select name="assessment_id" id="assessment_id" class="form-control" required>
                            <option value="">Select an assessment</option>
                            <?php foreach ($assessments as $assessment): ?>
                                <option value="<?php echo $assessment['id']; ?>">
                                    <?php echo htmlspecialchars($assessment['assessment_name']); ?> 
                                    (<?php echo date('F j, Y', strtotime($assessment['date'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Marks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['username']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td>
                                            <input type="number" 
                                                   name="marks[<?php echo $student['id']; ?>]" 
                                                   class="marks-input" 
                                                   min="0" 
                                                   step="0.01"
                                                   placeholder="0.00">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Marks
                    </button>
                </form>

                <h2 class="section-title">Assessment Records</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Assessment</th>
                                <th>Date</th>
                                <th>Total Students</th>
                                <th>Average Marks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assessments as $assessment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assessment['assessment_name']); ?></td>
                                    <td><?php echo date('F j, Y', strtotime($assessment['date'])); ?></td>
                                    <td><?php echo count($students); ?></td>
                                    <td>
                                        <?php
                                        // Calculate average marks for this assessment
                                        $avg_sql = "SELECT AVG(marks) as avg_marks 
                                                   FROM marks 
                                                   WHERE assessment_id = ?";
                                        $avg_stmt = $conn->prepare($avg_sql);
                                        $avg_stmt->bind_param("i", $assessment['id']);
                                        $avg_stmt->execute();
                                        $avg_result = $avg_stmt->get_result()->fetch_assoc();
                                        echo number_format($avg_result['avg_marks'] ?? 0, 2);
                                        ?>
                                    </td>
                                    <td>
                                        <a href="edit_marks.php?course_id=<?php echo $selected_course_id; ?>&assessment_id=<?php echo $assessment['id']; ?>" 
                                           class="btn btn-secondary">
                                            <i class="fas fa-edit"></i>
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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