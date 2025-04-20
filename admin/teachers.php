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
        
        // Set success message
        $success = ($action === 'approve') ? "Teacher approved successfully!" : "Teacher rejected successfully!";
        header('Location: teachers.php?success=' . urlencode($success));
        exit();
    }
}

// Get all teachers
$teachers_sql = "SELECT id, username, email, status, created_at FROM users WHERE role = 'teacher' ORDER BY created_at DESC";
$teachers_result = $conn->query($teachers_sql);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_teachers,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_teachers,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_teachers,
    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_teachers
FROM users WHERE role = 'teacher'";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers - Admin Dashboard</title>
    <link href="../css/dashboard.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="../js/theme.js"></script>
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
                <li><a href="teachers.php" class="nav-link active">
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
            </ul>
            <div class="theme-toggle-wrapper">
                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle dark/light mode">
                    <div class="toggle-thumb"></div>
                </button>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="container">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3><i class="fas fa-users"></i> Total Teachers</h3>
                    <div class="number"><?php echo $stats['total_teachers']; ?></div>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-user-clock"></i> Pending Approval</h3>
                    <div class="number"><?php echo $stats['pending_teachers']; ?></div>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-user-check"></i> Active Teachers</h3>
                    <div class="number"><?php echo $stats['active_teachers']; ?></div>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-user-slash"></i> Suspended</h3>
                    <div class="number"><?php echo $stats['suspended_teachers']; ?></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Registration Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($teachers_result->num_rows > 0): ?>
                                    <?php while ($teacher = $teachers_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                            <td>
                                                <?php
                                                    $status_class = '';
                                                    switch ($teacher['status']) {
                                                        case 'active':
                                                            $status_class = 'success';
                                                            break;
                                                        case 'pending':
                                                            $status_class = 'warning';
                                                            break;
                                                        case 'suspended':
                                                            $status_class = 'danger';
                                                            break;
                                                    }
                                                ?>
                                                <span class="badge badge-<?php echo $status_class; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($teacher['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($teacher['created_at'])); ?></td>
                                            <td>
                                                <?php if ($teacher['status'] === 'pending'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                <?php elseif ($teacher['status'] === 'suspended'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                                            <i class="fas fa-user-check"></i> Reactivate
                                                        </button>
                                                    </form>
                                                <?php elseif ($teacher['status'] === 'active'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-user-slash"></i> Suspend
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No teachers found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 