<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $error = '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Prepare SQL statement to prevent SQL injection
        $sql = "SELECT id, username, email, password, role, status FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Check account status
                if ($user['status'] === 'pending') {
                    $error = "Your account is pending approval. Please wait for admin confirmation.";
                } else {
                    // Password is correct and account is active, create session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];

                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header('Location: ../admin/dashboard.php');
                    } elseif ($user['role'] === 'teacher') {
                        header('Location: ../teacher/dashboard.php');
                    } else {
                        header('Location: ../student/dashboard.php');
                    }
                    exit();
                }
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or password.";
        }
    }

    if (!empty($error)) {
        header('Location: ../login.php?error=' . urlencode($error));
        exit();
    }
} else {
    header('Location: ../login.php');
    exit();
} 