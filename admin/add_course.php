<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$error_message = '';
$success_message = '';

// Get all active teachers
$teachers_sql = "SELECT id, username, email FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY username";
$teachers_result = $conn->query($teachers_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_name = trim($_POST['course_name']);
    $course_code = trim($_POST['course_code']);
    $description = trim($_POST['description']);
    $credits = (int)$_POST['credits'];
    $teacher_id = (int)$_POST['teacher_id'];

    // Validate input
    if (empty($course_name) || empty($course_code) || $credits < 1 || $teacher_id < 1) {
        $error_message = "All fields are required. Credits must be positive and a teacher must be selected.";
    } else {
        // Check if course code already exists
        $check_sql = "SELECT id FROM courses WHERE course_code = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $course_code);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = "Course code already exists!";
        } else {
            // Insert new course
            $insert_sql = "INSERT INTO courses (course_name, course_code, description, credits, teacher_id) VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("sssis", $course_name, $course_code, $description, $credits, $teacher_id);
            
            if ($insert_stmt->execute()) {
                $_SESSION['success_message'] = "Course created successfully!";
                header('Location: dashboard.php');
                exit();
            } else {
                $error_message = "Error creating course: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Course - Admin Dashboard</title>
    <link href="../css/dashboard.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        .card {
            background: var(--card-bg, #fff);
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        .card-body {
            padding: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color, #2d3748);
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        .form-control:focus {
            border-color: var(--primary-color, #4a90e2);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
            outline: none;
        }
        .form-text {
            font-size: 0.875rem;
            color: var(--text-muted, #718096);
            margin-top: 0.25rem;
        }
        .required {
            color: var(--danger-color, #e53e3e);
            margin-left: 0.25rem;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color, #e2e8f0);
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background: var(--primary-color, #4a90e2);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark, #357abd);
        }
        .btn-secondary {
            background: var(--secondary-color, #718096);
            color: white;
        }
        .btn-secondary:hover {
            background: var(--secondary-dark, #4a5568);
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem 0;
        }
        .page-header h2 {
            font-size: 1.5rem;
            color: var(--heading-color, #2d3748);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .alert-danger {
            background-color: var(--danger-light, #fff5f5);
            color: var(--danger-color, #e53e3e);
            border: 1px solid var(--danger-border, #feb2b2);
        }
        .alert-success {
            background-color: var(--success-light, #f0fff4);
            color: var(--success-color, #38a169);
            border: 1px solid var(--success-border, #9ae6b4);
        }
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23718096'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1rem;
            padding-right: 2.5rem;
        }
        .form-control:disabled {
            background-color: var(--disabled-bg, #f7fafc);
            cursor: not-allowed;
        }
        @media (max-width: 768px) {
            .form-container {
                padding: 1rem;
            }
            .card-body {
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
            <div class="navbar-left">
                <a href="dashboard.php" class="navbar-brand">
                    <i class="fas fa-graduation-cap"></i>
                    Admin Dashboard
                </a>
            </div>
            <ul class="nav-list">
                <li><a href="students.php" class="nav-link">
                    <i class="fas fa-users"></i> Students
                </a></li>
                <li><a href="teachers.php" class="nav-link">
                    <i class="fas fa-chalkboard-teacher"></i> Teachers
                </a></li>
                <li><a href="courses.php" class="nav-link active">
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
        <div class="form-container">
            <div class="page-header">
                <h2><i class="fas fa-plus-circle"></i> Add New Course</h2>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
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

            <div class="card">
                <div class="card-body">
                    <form method="POST" class="form">
                        <div class="form-group">
                            <label for="course_name">Course Name <span class="required">*</span></label>
                            <input type="text" id="course_name" name="course_name" class="form-control" 
                                   value="<?php echo isset($_POST['course_name']) ? htmlspecialchars($_POST['course_name']) : ''; ?>" 
                                   placeholder="Enter course name"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="course_code">Course Code <span class="required">*</span></label>
                            <input type="text" id="course_code" name="course_code" class="form-control" 
                                   value="<?php echo isset($_POST['course_code']) ? htmlspecialchars($_POST['course_code']) : ''; ?>"
                                   placeholder="Example: CS101, MATH202"
                                   required>
                            <small class="form-text">Must be unique. Example: CS101, MATH202, etc.</small>
                        </div>

                        <div class="form-group">
                            <label for="description">Course Description</label>
                            <textarea id="description" name="description" class="form-control" 
                                    placeholder="Enter course description..."
                                    rows="4"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="credits">Credits <span class="required">*</span></label>
                            <input type="number" id="credits" name="credits" class="form-control" 
                                   value="<?php echo isset($_POST['credits']) ? htmlspecialchars($_POST['credits']) : '3'; ?>"
                                   min="1" max="6" required>
                        </div>

                        <div class="form-group">
                            <label for="teacher_id">Assign Teacher <span class="required">*</span></label>
                            <select name="teacher_id" id="teacher_id" class="form-control" required>
                                <option value="">Select a teacher...</option>
                                <?php while ($teacher = $teachers_result->fetch_assoc()): ?>
                                    <option value="<?php echo $teacher['id']; ?>" 
                                            <?php echo isset($_POST['teacher_id']) && $_POST['teacher_id'] == $teacher['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['username'] . ' (' . $teacher['email'] . ')'); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <?php if ($teachers_result->num_rows === 0): ?>
                                <small class="form-text text-danger">No active teachers available. Please approve some teachers first.</small>
                            <?php endif; ?>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" <?php echo $teachers_result->num_rows === 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-save"></i> Create Course
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 