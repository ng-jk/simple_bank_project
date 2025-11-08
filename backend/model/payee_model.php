<?php

class payee_model {
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    public function get_all_active_payees() {
        $stmt = $this->mysqli->prepare("SELECT * FROM bill_payee WHERE status = 'active' ORDER BY payee_name ASC");
        $stmt->execute();
        $result = $stmt->get_result();

        $payees = [];
        while ($row = $result->fetch_assoc()) {
            $payees[] = $row;
        }

        return ['success' => true, 'payees' => $payees];
    }

    public function get_payee_by_id($payee_id) {
        $stmt = $this->mysqli->prepare("SELECT * FROM bill_payee WHERE payee_id = ?");
        $stmt->bind_param('i', $payee_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return ['success' => true, 'payee' => $row];
        }

        return ['success' => false, 'error' => 'Payee not found'];
    }

    public function get_payee_by_code($payee_code) {
        $stmt = $this->mysqli->prepare("SELECT * FROM bill_payee WHERE payee_code = ?");
        $stmt->bind_param('s', $payee_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return ['success' => true, 'payee' => $row];
        }

        return ['success' => false, 'error' => 'Payee not found'];
    }

    public function get_payees_by_category($category) {
        $stmt = $this->mysqli->prepare("SELECT * FROM bill_payee WHERE payee_category = ? AND status = 'active' ORDER BY payee_name ASC");
        $stmt->bind_param('s', $category);
        $stmt->execute();
        $result = $stmt->get_result();

        $payees = [];
        while ($row = $result->fetch_assoc()) {
            $payees[] = $row;
        }

        return ['success' => true, 'payees' => $payees];
    }
}
?>
