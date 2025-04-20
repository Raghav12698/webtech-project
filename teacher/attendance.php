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

// Handle form submission for adding attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_attendance'])) {
    $course_id = intval($_POST['course_id']);
    $date = $_POST['date'];
    $attendance = $_POST['attendance'] ?? [];

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

        // Delete existing attendance for this date and course
        $delete_sql = "DELETE FROM attendance WHERE course_id = ? AND date = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("is", $course_id, $date);
        $delete_stmt->execute();

        // Insert new attendance records
        $insert_sql = "INSERT INTO attendance (course_id, student_id, date, status) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);

        foreach ($attendance as $student_id => $status) {
            $insert_stmt->bind_param("iisi", $course_id, $student_id, $date, $status);
            $insert_stmt->execute();
        }

        $conn->commit();
        $success_message = 'Attendance recorded successfully!';
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

// Get attendance records for the selected course
$selected_course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;
$attendance_records = [];

if ($selected_course_id) {
    // Verify course belongs to teacher
    $verify_sql = "SELECT id FROM courses WHERE id = ? AND teacher_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $selected_course_id, $teacher_id);
    $verify_stmt->execute();
    
    if ($verify_stmt->get_result()->fetch_assoc()) {
        $attendance_sql = "SELECT a.date, 
                                 COUNT(*) as total_students,
                                 SUM(CASE WHEN a.status = 1 THEN 1 ELSE 0 END) as present_count,
                                 SUM(CASE WHEN a.status = 0 THEN 1 ELSE 0 END) as absent_count
                          FROM attendance a
                          WHERE a.course_id = ?
                          GROUP BY a.date
                          ORDER BY a.date DESC";
        $attendance_stmt = $conn->prepare($attendance_sql);
        $attendance_stmt->bind_param("i", $selected_course_id);
        $attendance_stmt->execute();
        $attendance_records = $attendance_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Teacher Dashboard</title>
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

        .form-control::placeholder {
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

        .attendance-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border: 2px solid var(--border);
            border-radius: 0.5rem;
            background: var(--surface);
            color: var(--text);
            cursor: pointer;
            transition: all 0.2s;
        }

        .attendance-toggle input[type="radio"] {
            display: none;
        }

        .attendance-toggle.present {
            border-color: var(--success);
            color: var(--success);
        }

        .attendance-toggle.absent {
            border-color: var(--danger);
            color: var(--danger);
        }

        .attendance-toggle.present.active {
            background: var(--success);
            color: white;
        }

        .attendance-toggle.absent.active {
            background: var(--danger);
            color: white;
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
                <li><a href="attendance.php" class="nav-link active">
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
                <i class="fas fa-calendar-check"></i>
                Attendance Management
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
                <h2 class="section-title">Add Attendance</h2>
                <form method="POST" action="">
                    <input type="hidden" name="add_attendance" value="1">
                    <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="date">Date<span>*</span></label>
                        <input type="date" id="date" name="date" class="form-control" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get enrolled students
                                $students_sql = "SELECT s.id, s.username, s.email 
                                                FROM users s
                                                JOIN enrollments e ON s.id = e.student_id
                                                WHERE e.course_id = ?
                                                ORDER BY s.username";
                                $students_stmt = $conn->prepare($students_sql);
                                $students_stmt->bind_param("i", $selected_course_id);
                                $students_stmt->execute();
                                $students_result = $students_stmt->get_result();

                                while ($student = $students_result->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['username']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td>
                                            <label class="attendance-toggle present">
                                                <input type="radio" name="attendance[<?php echo $student['id']; ?>]" 
                                                       value="1" checked>
                                                <i class="fas fa-check"></i> Present
                                            </label>
                                            <label class="attendance-toggle absent">
                                                <input type="radio" name="attendance[<?php echo $student['id']; ?>]" 
                                                       value="0">
                                                <i class="fas fa-times"></i> Absent
                                            </label>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Attendance
                    </button>
                </form>

                <h2 class="section-title">Attendance Records</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total Students</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): ?>
                                <tr>
                                    <td><?php echo date('F j, Y', strtotime($record['date'])); ?></td>
                                    <td><?php echo $record['total_students']; ?></td>
                                    <td><?php echo $record['present_count']; ?></td>
                                    <td><?php echo $record['absent_count']; ?></td>
                                    <td>
                                        <a href="edit_attendance.php?course_id=<?php echo $selected_course_id; ?>&date=<?php echo $record['date']; ?>" 
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

        // Handle attendance toggle buttons
        document.querySelectorAll('.attendance-toggle input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Remove active class from all toggles in this group
                const toggles = this.closest('td').querySelectorAll('.attendance-toggle');
                toggles.forEach(toggle => toggle.classList.remove('active'));
                
                // Add active class to selected toggle
                this.closest('.attendance-toggle').classList.add('active');
            });
        });
    </script>
</body>
</html> 