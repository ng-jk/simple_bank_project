<?php
require_once __DIR__ . '/../model/transaction_model.php';
require_once __DIR__ . '/../model/account_model.php';
require_once __DIR__ . '/../model/user_model.php';
require_once __DIR__ . '/../service/pdf_service.php';

class statement_controller {
    private $mysqli;
    private $transaction_model;
    private $account_model;
    private $user_model;
    private $pdf_service;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
        $this->transaction_model = new transaction_model($mysqli);
        $this->account_model = new account_model($mysqli);
        $this->user_model = new user_model($mysqli);
        $this->pdf_service = new PDFService();
    }

    private function verify_account_ownership($account_id, $user_id) {
        $result = $this->account_model->get_account_by_id($account_id);

        if (!$result['success']) {
            return false;
        }

        return $result['account']['user_id'] == $user_id;
    }

    public function generate_statement($status, $account_id) {
        // Check authentication
        if (!$status->is_login) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        // Verify account ownership
        if (!$this->verify_account_ownership($account_id, $status->user_info['user_id']) && $status->permission != 'admin') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }

        // Get month and year from query parameters
        $month = $_GET['month'] ?? null;
        $year = $_GET['year'] ?? null;

        if (!$month || !$year) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Month and year are required']);
            return;
        }

        // Validate month and year
        if (!is_numeric($month) || !is_numeric($year) || $month < 1 || $month > 12) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid month or year']);
            return;
        }

        // Calculate date range (first day to last day of the month)
        $start_date = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $last_day = date('t', strtotime($start_date)); // Get number of days in month
        $end_date = sprintf('%04d-%02d-%02d 23:59:59', $year, $month, $last_day);

        // Get account data
        $account_result = $this->account_model->get_account_by_id($account_id);
        if (!$account_result['success']) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Account not found']);
            return;
        }
        $account_data = $account_result['account'];

        // Get user data
        $user_result = $this->user_model->get_user_by_id($account_data['user_id']);
        if (!$user_result['success']) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        $user_data = $user_result['user'];

        // Get transactions for the period
        $transactions_result = $this->transaction_model->get_account_transactions_by_date_range(
            $account_id,
            $start_date,
            $end_date
        );

        if (!$transactions_result['success']) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to retrieve transactions']);
            return;
        }

        $transactions = $transactions_result['transactions'];

        // Get beginning balance (balance at start of month)
        $beginning_balance_result = $this->transaction_model->get_balance_at_date($account_id, $start_date);
        $beginning_balance = $beginning_balance_result['balance'];

        // Calculate running balance for each transaction
        $running_balance = $beginning_balance;
        foreach ($transactions as &$transaction) {
            $running_balance += floatval($transaction['amount']);
            $transaction['balance_after'] = $running_balance;
        }
        unset($transaction); // Break reference

        // Calculate ending balance
        $ending_balance = $running_balance;

        // Generate PDF
        try {
            $pdf = $this->pdf_service->generate_bank_statement(
                $account_data,
                $user_data,
                $transactions,
                $start_date,
                $end_date,
                $beginning_balance,
                $ending_balance
            );

            // Generate filename
            $filename = sprintf(
                'statement_%s_%04d_%02d.pdf',
                $account_data['account_number'],
                $year,
                $month
            );

            // Get PDF content as string
            $pdf_content = $this->pdf_service->output_pdf($pdf, $filename);

            // Set headers for PDF download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdf_content));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            // Output PDF content
            echo $pdf_content;

            // Since we're outputting directly, we don't return JSON
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to generate PDF: ' . $e->getMessage()]);
            return;
        }
    }

    public function get_available_statement_months($status, $account_id) {
        // Check authentication
        if (!$status->is_login) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }

        // Verify account ownership
        if (!$this->verify_account_ownership($account_id, $status->user_info['user_id']) && $status->permission != 'admin') {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Get the earliest and latest transaction dates for this account
        $stmt = $this->mysqli->prepare("
            SELECT
                MIN(DATE_FORMAT(created_at, '%Y-%m-01')) as earliest_month,
                MAX(DATE_FORMAT(created_at, '%Y-%m-01')) as latest_month
            FROM bank_transaction
            WHERE account_id = ? OR related_account_id = ?
        ");
        $stmt->bind_param('ii', $account_id, $account_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if (!$row['earliest_month']) {
            // No transactions yet
            return ['success' => true, 'months' => []];
        }

        // Generate list of months from earliest to latest
        $months = [];
        $current_month = new DateTime($row['earliest_month']);
        $end_month = new DateTime($row['latest_month']);

        while ($current_month <= $end_month) {
            $months[] = [
                'year' => (int)$current_month->format('Y'),
                'month' => (int)$current_month->format('m'),
                'label' => $current_month->format('F Y')
            ];
            $current_month->modify('+1 month');
        }

        // Reverse to show most recent first
        $months = array_reverse($months);

        return ['success' => true, 'months' => $months];
    }
}
?>
