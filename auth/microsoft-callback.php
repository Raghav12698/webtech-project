<?php
session_start();
require_once '../config/database.php';

// Microsoft OAuth Configuration
$client_id = 'YOUR_MICROSOFT_CLIENT_ID';
$client_secret = 'YOUR_MICROSOFT_CLIENT_SECRET';
$redirect_uri = 'http://localhost/webtech-project/auth/microsoft-callback.php';
$tenant = 'common';

// Check if there's an error
if (isset($_GET['error'])) {
    header('Location: ../login.php?error=' . urlencode('Microsoft authentication failed: ' . $_GET['error_description']));
    exit();
}

// Check if we have an authorization code
if (!isset($_GET['code'])) {
    header('Location: ../login.php');
    exit();
}

// Exchange authorization code for access token
$token_url = "https://login.microsoftonline.com/$tenant/oauth2/v2.0/token";
$token_data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'code' => $_GET['code'],
    'redirect_uri' => $redirect_uri,
    'grant_type' => 'authorization_code',
    'scope' => 'User.Read'
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
$user_info_url = 'https://graph.microsoft.com/v1.0/me';
$ch = curl_init($user_info_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token_data['access_token']]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$user_info_response = curl_exec($ch);
curl_close($ch);

$user_info = json_decode($user_info_response, true);

if (!isset($user_info['mail']) && !isset($user_info['userPrincipalName'])) {
    header('Location: ../login.php?error=' . urlencode('Failed to get user information'));
    exit();
}

// Use mail if available, otherwise use userPrincipalName
$email = $user_info['mail'] ?? $user_info['userPrincipalName'];

// Check if user exists in database
$stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    // User exists, log them in
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['email'] = $email;
    $_SESSION['name'] = $user_info['displayName'] ?? '';

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
    $_SESSION['oauth_email'] = $email;
    $_SESSION['oauth_name'] = $user_info['displayName'] ?? '';
    $_SESSION['oauth_provider'] = 'microsoft';
    header('Location: ../register.php?oauth=microsoft');
}
exit(); 