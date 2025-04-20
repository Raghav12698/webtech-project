<?php
session_start();
require_once '../config/database.php';



// Check if there's an error
if (isset($_GET['error'])) {
    header('Location: ../login.php?error=' . urlencode('Google authentication failed'));
    exit();
}

// Check if we have an authorization code
if (!isset($_GET['code'])) {
    header('Location: ../login.php');
    exit();
}

// Exchange authorization code for access token
$token_url = 'https://oauth2.googleapis.com/token';
$token_data = [
    'code' => $_GET['code'],
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$token_response = curl_exec($ch);
curl_close($ch);

$token_data = json_decode($token_response, true);

if (!isset($token_data['access_token'])) {
    header('Location: ../login.php?error=' . urlencode('Failed to get access token'));
    exit();
}

// Get user info with access token
$user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init($user_info_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token_data['access_token']]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$user_info_response = curl_exec($ch);
curl_close($ch);

$user_info = json_decode($user_info_response, true);

if (!isset($user_info['email'])) {
    header('Location: ../login.php?error=' . urlencode('Failed to get user information'));
    exit();
}

// Check if user exists in database
$stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
$stmt->bind_param("s", $user_info['email']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    // User exists, log them in
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['email'] = $user_info['email'];
    $_SESSION['name'] = $user_info['name'] ?? '';

    // Redirect based on role
    switch ($user['role']) {
        case 'teacher':
            header('Location: ../teacher/dashboard.php');
            break;
        case 'student':
            header('Location: ../student/dashboard.php');
            break;
        case 'admin':
            header('Location: ../admin/dashboard.php');
            break;
        default:
            header('Location: ../login.php?error=' . urlencode('Invalid user role'));
    }
} else {
    // New user, store in session and redirect to role selection
    $_SESSION['oauth_email'] = $user_info['email'];
    $_SESSION['oauth_name'] = $user_info['name'] ?? '';
    $_SESSION['oauth_provider'] = 'google';
    header('Location: ../register.php?oauth=google');
}
exit(); 