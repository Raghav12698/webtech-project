<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $error = '';

    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!in_array($role, ['student', 'teacher'])) {
        $error = "Invalid role selected.";
    } else {
        try {
            // Check if email already exists
            $check_sql = "SELECT id FROM users WHERE email = ?";
            $check_stmt = $conn->prepare($check_sql);
            
            if ($check_stmt === false) {
                throw new Exception("Error preparing statement: " . $conn->error);
            }
            
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $error = "Email address already exists.";
            } else {
                // Hash password and insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // First, check if the status column exists
                $check_column_sql = "SHOW COLUMNS FROM users LIKE 'status'";
                $column_result = $conn->query($check_column_sql);
                
                if ($column_result->num_rows === 0) {
                    // Add status column if it doesn't exist
                    $alter_sql = "ALTER TABLE users ADD COLUMN status ENUM('active', 'pending', 'suspended') NOT NULL DEFAULT 'active'";
                    if (!$conn->query($alter_sql)) {
                        throw new Exception("Error adding status column: " . $conn->error);
                    }
                }
                
                // Set status based on role
                $status = ($role === 'teacher') ? 'pending' : 'active';
                
                // Insert new user
                $sql = "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                
                if ($stmt === false) {
                    throw new Exception("Error preparing statement: " . $conn->error);
                }
                
                $stmt->bind_param("sssss", $username, $email, $hashed_password, $role, $status);
                
                if ($stmt->execute()) {
                    // Registration successful
                    if ($role === 'teacher') {
                        $success = "Registration successful! Please wait for admin approval to access your account.";
                    } else {
                        $success = "Registration successful! You can now login with your credentials.";
                    }
                    header('Location: ../login.php?success=' . urlencode($success));
                    exit();
                } else {
                    throw new Exception("Error executing statement: " . $stmt->error);
                }
            }
        } catch (Exception $e) {
            $error = "Registration failed: " . $e->getMessage();
            // Log the error for debugging
            error_log("Registration error: " . $e->getMessage());
        }
    }

    if (!empty($error)) {
        header('Location: ../register.php?error=' . urlencode($error));
        exit();
    }
} else {
    header('Location: ../register.php');
    exit();
} 