<?php
// Router - handles all API (in case you need it) and page requests

// Load controllers
require_once __DIR__ . '/controller/auth_controller.php';
require_once __DIR__ . '/controller/account_controller.php';
require_once __DIR__ . '/controller/transaction_controller.php';
require_once __DIR__ . '/controller/statement_controller.php';
require_once __DIR__ . '/controller/admin_controller.php';

// Initialize controllers
$auth_controller = new auth_controller($mysqli, $config->JWT_DAILY_REFRESH_KEY);
$account_controller = new account_controller($mysqli);
$transaction_controller = new transaction_controller($mysqli);
$statement_controller = new statement_controller($mysqli);
$admin_controller = new admin_controller($mysqli, $config->JWT_DAILY_REFRESH_KEY);

// API routes
if (strpos($request_uri, '/api/') !== false) {
    // Don't set JSON header for PDF download endpoint - it sets its own headers
    if (!preg_match('/^\/api\/accounts\/\d+\/statements\/download$/', $request_uri)) {
        header('Content-Type: application/json');
    }

    // Authentication routes
    // OTP-based registration (new flow)
    if ($request_uri == '/api/auth/register/request-otp' && $request_method == 'POST') {
        $result = $auth_controller->request_registration_otp();
        echo json_encode($result);
        exit;
    }
    
    if ($request_uri == '/api/auth/register/verify-otp' && $request_method == 'POST') {
        $result = $auth_controller->verify_registration_otp();
        echo json_encode($result);
        exit;
    }
    
    if ($request_uri == '/api/auth/resend-otp' && $request_method == 'POST') {
        $result = $auth_controller->resend_otp();
        echo json_encode($result);
        exit;
    }
    
    // Authentication routes
    if ($request_uri == '/api/auth/register' && $request_method == 'POST') {
        $result = $auth_controller->register();
        echo json_encode($result);
        exit;
    }
    
    if ($request_uri == '/api/auth/login' && $request_method == 'POST') {
        $result = $auth_controller->login();
        echo json_encode($result);
        exit;
    }
    
    if ($request_uri == '/api/auth/logout' && $request_method == 'POST') {
        $result = $auth_controller->logout();
        echo json_encode($result);
        exit;
    }
    
    if ($request_uri == '/api/auth/me' && $request_method == 'GET') {
        $result = $auth_controller->get_current_user($status);
        echo json_encode($result);
        exit;
    }

    // Admin authentication routes
    if ($request_uri == '/api/admin/auth/login' && $request_method == 'POST') {
        $result = $admin_controller->login();
        echo json_encode($result);
        exit;
    }

    if ($request_uri == '/api/admin/auth/logout' && $request_method == 'POST') {
        $result = $admin_controller->logout();
        echo json_encode($result);
        exit;
    }

    if ($request_uri == '/api/admin/auth/me' && $request_method == 'GET') {
        $result = $admin_controller->get_current_user($status);
        echo json_encode($result);
        exit;
    }

    // Account routes
    if ($request_uri == '/api/accounts' && $request_method == 'GET') {
        $result = $account_controller->get_my_accounts($status);
        echo json_encode($result);
        exit;
    }
    
    if ($request_uri == '/api/accounts' && $request_method == 'POST') {
        $result = $account_controller->create_account($status);
        echo json_encode($result);
        exit;
    }
    
    if (preg_match('/^\/api\/accounts\/(\d+)$/', $request_uri, $matches) && $request_method == 'GET') {
        $account_id = $matches[1];
        $result = $account_controller->get_account_details($status, $account_id);
        echo json_encode($result);
        exit;
    }
    
    if (preg_match('/^\/api\/accounts\/(\d+)\/close$/', $request_uri, $matches) && $request_method == 'POST') {
        $account_id = $matches[1];
        $result = $account_controller->close_account($status, $account_id);
        echo json_encode($result);
        exit;
    }
    
    if ($request_uri == '/api/admin/accounts' && $request_method == 'GET') {
        $result = $account_controller->get_all_accounts($status);
        echo json_encode($result);
        exit;
    }
    
    // Transaction routes
    if ($request_uri == '/api/transactions/deposit' && $request_method == 'POST') {
        $result = $transaction_controller->deposit($status);
        echo json_encode($result);
        exit;
    }
    
    if ($request_uri == '/api/transactions/withdraw' && $request_method == 'POST') {
        $result = $transaction_controller->withdraw($status);
        echo json_encode($result);
        exit;
    }
    
    if ($request_uri == '/api/transactions/transfer' && $request_method == 'POST') {
        $result = $transaction_controller->transfer($status);
        echo json_encode($result);
        exit;
    }

    if ($request_uri == '/api/transactions/paybill' && $request_method == 'POST') {
        $result = $transaction_controller->pay_bill($status);
        echo json_encode($result);
        exit;
    }

    if ($request_uri == '/api/payees' && $request_method == 'GET') {
        $result = $transaction_controller->get_payees($status);
        echo json_encode($result);
        exit;
    }

    if (preg_match('/^\/api\/accounts\/(\d+)\/transactions$/', $request_uri, $matches) && $request_method == 'GET') {
        $account_id = $matches[1];
        $result = $transaction_controller->get_account_transactions($status, $account_id);
        echo json_encode($result);
        exit;
    }
    
    if (preg_match('/^\/api\/transactions\/reference\/(.+)$/', $request_uri, $matches) && $request_method == 'GET') {
        $reference = $matches[1];
        $result = $transaction_controller->get_transaction_by_reference($status, $reference);
        echo json_encode($result);
        exit;
    }

    // Statement routes
    if (preg_match('/^\/api\/accounts\/(\d+)\/statements\/months$/', $request_uri, $matches) && $request_method == 'GET') {
        $account_id = $matches[1];
        $result = $statement_controller->get_available_statement_months($status, $account_id);
        echo json_encode($result);
        exit;
    }

    if (preg_match('/^\/api\/accounts\/(\d+)\/statements\/download$/', $request_uri, $matches) && $request_method == 'GET') {
        $account_id = $matches[1];
        // This endpoint outputs PDF directly, no JSON response
        $statement_controller->generate_statement($status, $account_id);
        exit;
    }

    // API route not found
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => "API endpoint not found"]);
    exit;
}

// Admin redirect - /admin redirects to /admin/login
if ($request_uri == '/admin') {
    header('Location: /admin/login');
    exit;
}

// Frontend page routes
$page_routes = [
    '/' => 'frontend/index.html',
    '/login' => 'frontend/login.html',
    '/register_otp' => 'frontend/register_otp.html',
    '/dashboard' => 'frontend/dashboard.html',
    '/accounts' => 'frontend/accounts.html',
    '/transactions' => 'frontend/transactions.html',
    '/transfer' => 'frontend/transfer.html',
    '/admin/login' => 'frontend/admin/login.html',
    '/admin/dashboard' => 'frontend/admin/dashboard.html'
];

// Define admin-only and user-only routes
$admin_routes = ['/admin/dashboard'];
$user_routes = ['/dashboard', '/accounts', '/transactions', '/transfer'];

// Serve frontend pages with server data injection
foreach ($page_routes as $route => $file) {
    if ($request_uri == $route) {

        $file_path = __DIR__ . '/../' . $file;

        if (file_exists($file_path)) {
            // Role-based access control
            $is_admin_route = in_array($route, $admin_routes);
            $is_user_route = in_array($route, $user_routes);

            // Check if user is logged in and has correct role
            if ($status->is_login) {
                $user_role = $status->permission;

                // Admin trying to access user-only pages - redirect to admin dashboard
                if ($user_role === 'admin' && $is_user_route) {
                    header('Location: /admin/dashboard');
                    exit;
                }

                // Regular user trying to access admin pages - redirect to user dashboard
                if ($user_role === 'user' && $is_admin_route) {
                    header('Location: /dashboard');
                    exit;
                }
            }

            // Prepare server data for frontend
            $server_data = [
                'metadata' => [
                    'date_time' => time(),
                    'login_status' => $status->is_login ? 'login' : 'not_login',
                    'permission' => $status->permission ?? 'not_login',
                    'current_uri' => $request_uri
                ]
            ];

            // Add user data if logged in
            if ($status->is_login) {
                $server_data['user'] = $status->user_info;
            }

            // Inject server data before HTML
            echo '<script>';
            echo 'window.serverData = ' . json_encode($server_data) . ';';
            echo '</script>';

            // Include the HTML file
            include $file_path;
            exit;
        }
    }
}

// 404 - Page not found
http_response_code(404);
echo '<h1>404 - Page Not Found</h1>';
exit;
?>