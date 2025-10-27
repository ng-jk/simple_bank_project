<?php
// Handle HTTP status codes and error responses

$status_messages = [
    200 => 'OK',
    201 => 'Created',
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    500 => 'Internal Server Error'
];

$status_code = $status_code ?? 500;
$error = $error ?? 'An error occurred';

http_response_code($status_code);

header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'error' => $error,
    'status_code' => $status_code,
    'status_message' => $status_messages[$status_code] ?? 'Unknown Error'
]);
?>

