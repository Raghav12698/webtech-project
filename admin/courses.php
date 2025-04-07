<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Get all courses with enrollment count
$sql = "SELECT c.*, COUNT(DISTINCT e.student_id) as enrolled_students 
        FROM courses c 
        LEFT JOIN enrollments e ON c.id = e.course_id 
        GROUP BY c.id, c.course_code, c.course_name, c.description, c.credits";
$result = $conn->query($sql);

if (!$result) {
    $error_message = "Error fetching courses: " . $conn->error;
}

// Handle delete action if requested
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $course_id = $_GET['delete'];
    $delete_sql = "DELETE FROM courses WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    if ($delete_stmt) {
        $delete_stmt->bind_param("i", $course_id);
        if ($delete_stmt->execute()) {
            header('Location: courses.php?success=1');
            exit();
        } else {
            $error_message = "Error deleting course: " . $delete_stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - Admin Dashboard</title>
    <link href="../css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="dashboard.php" class="navbar-brand">
                <i class="fas fa-user-shield"></i> Admin Dashboard
            </a>
            <ul class="nav-list">
                <li><a href="students.php" class="nav-link"><i class="fas fa-users"></i> Students</a></li>
                <li><a href="courses.php" class="nav-link active"><i class="fas fa-book"></i> Courses</a></li>
                <li><a href="attendance.php" class="nav-link"><i class="fas fa-calendar-check"></i> Attendance</a></li>
                <li><a href="marks.php" class="nav-link"><i class="fas fa-chart-line"></i> Marks</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title"><i class="fas fa-book"></i> Manage Courses</h3>
                <a href="add_course.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Course
                </a>
            </div>
            <div class="card-body">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        Course has been successfully deleted.
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>CODE</th>
                                <th>COURSE NAME</th>
                                <th>DESCRIPTION</th>
                                <th>CREDITS</th>
                                <th>ENROLLED STUDENTS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($course = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['id']); ?></td>
                                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($course['description']); ?></td>
                                        <td><?php echo htmlspecialchars($course['credits']); ?></td>
                                        <td><?php echo htmlspecialchars($course['enrolled_students']); ?></td>
                                        <td>
                                            <a href="edit_course.php?id=<?php echo $course['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="courses.php?delete=<?php echo $course['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Are you sure you want to delete this course?')"
                                               title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No courses found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 