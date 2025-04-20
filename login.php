<?php
session_start();
require_once 'config/database.php';

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'teacher') {
        header('Location: teacher/dashboard.php');
    } else if ($_SESSION['role'] === 'student') {
        header('Location: student/dashboard.php');
    } else if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    }
    exit();
}

// Google OAuth Configuration
$google_client_id = "951201584827-2s6m3fe4o32mnv0k77u5pbu2brvbel10.apps.googleusercontent.com";
$google_redirect_uri = "http://localhost/webtech-project/auth/google-callback.php";

// Microsoft OAuth Configuration
$microsoft_client_id = "YOUR_MICROSOFT_CLIENT_ID";
$microsoft_redirect_uri = "http://localhost/webtech-project/auth/microsoft-callback.php";
$microsoft_tenant = "common";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }

        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }

        .logo {
            width: 48px;
            height: 48px;
            margin: 0 auto 1rem;
            display: block;
            color: #6366f1;
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            text-align: center;
            color: #6b7280;
            margin-bottom: 2rem;
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: border-color 0.15s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .password-field {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            cursor: pointer;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            font-size: 0.875rem;
        }

        .forgot-password {
            color: #6366f1;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .sign-in-button {
            width: 100%;
            padding: 0.75rem;
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.15s ease;
            margin-bottom: 1.5rem;
        }

        .sign-in-button:hover {
            background: #4f46e5;
        }

        .signup-link {
            text-align: center;
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .signup-link a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 500;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e5e7eb;
        }

        .divider span {
            margin: 0 1rem;
        }

        .oauth-buttons {
            display: flex;
            gap: 1rem;
        }

        .oauth-button {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            color: #374151;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.15s ease;
            text-decoration: none;
        }

        .oauth-button:hover {
            background: #f9fafb;
        }

        .oauth-button img {
            width: 20px;
            height: 20px;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <svg class="logo" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <h1 class="login-title">Welcome back!</h1>
        <p class="login-subtitle">Please sign in to continue</p>

        <form action="auth/login.php" method="POST">
            <div class="form-group">
                <label class="form-label" for="email">Email address</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="password-field">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                    <span class="password-toggle" onclick="togglePassword()">
                        <i class="far fa-eye"></i>
                    </span>
                </div>
            </div>

            <div class="remember-forgot">
                <label class="remember-me">
                    <input type="checkbox" name="remember">
                    Remember me
                </label>
                <a href="forgot-password.php" class="forgot-password">Forgot password?</a>
            </div>

            <button type="submit" class="sign-in-button">Sign In</button>
        </form>

        <div class="signup-link">
            Don't have an account? <a href="register.php">Sign up</a>
        </div>

        <div class="divider">
            <span>or continue with</span>
        </div>

        <div class="oauth-buttons">
            <a href="<?php echo 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => $google_client_id,
                'redirect_uri' => $google_redirect_uri,
                'response_type' => 'code',
                'scope' => 'email profile',
                'access_type' => 'online',
                'prompt' => 'select_account'
            ]); ?>" class="oauth-button">
                <img src="https://www.google.com/favicon.ico" alt="Google">
                Google
            </a>

            <a href="<?php echo 'https://login.microsoftonline.com/' . $microsoft_tenant . '/oauth2/v2.0/authorize?' . http_build_query([
                'client_id' => $microsoft_client_id,
                'redirect_uri' => $microsoft_redirect_uri,
                'response_type' => 'code',
                'scope' => 'User.Read',
                'prompt' => 'select_account'
            ]); ?>" class="oauth-button">
                <img src="https://www.microsoft.com/favicon.ico" alt="Microsoft">
                Microsoft
            </a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html> 