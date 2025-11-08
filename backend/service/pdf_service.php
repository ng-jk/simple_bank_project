<?php

require_once __DIR__ . '/../../vendor/autoload.php';

class PDFService {

    public function generate_bank_statement($account_data, $user_data, $transactions, $start_date, $end_date, $beginning_balance, $ending_balance) {
        // Create new PDF document
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Simple Bank System');
        $pdf->SetAuthor('Simple Bank');
        $pdf->SetTitle('Bank Statement');
        $pdf->SetSubject('Bank Statement');

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 10);

        // Educational Disclaimer Banner
        $pdf->SetFillColor(255, 243, 205); // Light yellow background
        $pdf->SetTextColor(139, 69, 19); // Brown text
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(0, 6, 'EDUCATIONAL USE ONLY - NOT FOR OFFICIAL VERIFICATION', 0, 1, 'C', true);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(0, 5, 'INTI International University - IBM4202E Web Programming Course', 0, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0); // Reset to black
        $pdf->Ln(3);

        // Bank header
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'Simple Bank', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, 'Simple Bank Berhad', 0, 1, 'C');
        $pdf->Cell(0, 5, '1, Jalan Banking, 50000 Kuala Lumpur', 0, 1, 'C');
        $pdf->Ln(5);

        // Statement date and account info (right aligned)
        $statement_date = date('d/m/y', strtotime($end_date));
        $pdf->SetFont('helvetica', '', 8);

        $pdf->Ln(3);

        // Statement info (right side)
        $y_position = $pdf->GetY();
        $pdf->SetXY(120, $y_position);
        $pdf->Cell(40, 4, 'STATEMENT DATE:', 0, 0, 'L');
        $pdf->Cell(25, 4, $statement_date, 0, 1, 'R');

        $pdf->SetXY(120, $y_position + 5);
        $pdf->Cell(40, 4, 'ACCOUNT NUMBER:', 0, 0, 'L');
        $pdf->Cell(25, 4, $account_data['account_number'], 0, 1, 'R');

        $pdf->Ln(5);

        // Protection notice
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 5, 'PROTECTED BY PIDM UP TO RM250,000 FOR EACH DEPOSITOR', 0, 1, 'C');
        $pdf->Ln(2);

        // Divider line
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(3);

        // Account type
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, strtoupper($account_data['account_type']) . ' ACCOUNT', 0, 1, 'C');

        // Divider line
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(3);

        // Transaction header
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(0, 5, 'ACCOUNT TRANSACTIONS', 0, 1, 'L');

        // Divider line
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(2);

        // Column headers
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell(20, 4, 'ENTRY DATE', 0, 0, 'L');
        $pdf->Cell(100, 4, 'TRANSACTION DESCRIPTION', 0, 0, 'L');
        $pdf->Cell(30, 4, 'AMOUNT', 0, 0, 'R');
        $pdf->Cell(30, 4, 'BALANCE', 0, 1, 'R');

        // Divider line
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(2);

        // Beginning balance
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(20, 5, date('d/m/y', strtotime($start_date)), 0, 0, 'L');
        $pdf->Cell(100, 5, 'BEGINNING BALANCE', 0, 0, 'L');
        $pdf->Cell(30, 5, '', 0, 0, 'R');
        $pdf->Cell(30, 5, number_format($beginning_balance, 2), 0, 1, 'R');

        // Calculate totals
        $total_credit = 0;
        $total_debit = 0;

        // Transaction details
        $pdf->SetFont('helvetica', '', 7);
        foreach ($transactions as $transaction) {
            // Check if we need a new page
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                $pdf->SetY(20);

                // Repeat column headers
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->Cell(20, 4, 'ENTRY DATE', 0, 0, 'L');
                $pdf->Cell(100, 4, 'TRANSACTION DESCRIPTION', 0, 0, 'L');
                $pdf->Cell(30, 4, 'AMOUNT', 0, 0, 'R');
                $pdf->Cell(30, 4, 'BALANCE', 0, 1, 'R');
                $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
                $pdf->Ln(2);
                $pdf->SetFont('helvetica', '', 7);
            }

            $entry_date = date('d/m/y', strtotime($transaction['created_at']));

            // Format description
            $description = $this->format_transaction_description($transaction);

            // Calculate amount display
            $amount = floatval($transaction['amount']);
            if ($amount > 0) {
                $total_credit += $amount;
                $amount_display = number_format($amount, 2);
            } else {
                $total_debit += abs($amount);
                $amount_display = '-' . number_format(abs($amount), 2);
            }

            $balance_display = number_format($transaction['balance_after'], 2);

            // Print transaction
            $y_before = $pdf->GetY();
            $pdf->Cell(20, 5, $entry_date, 0, 0, 'L');

            // Multi-line description
            $x = $pdf->GetX();
            $pdf->MultiCell(100, 4, $description, 0, 'L');
            $y_after = $pdf->GetY();

            // Amount and balance on the same line as the first line of description
            $pdf->SetXY($x + 100, $y_before);
            $pdf->Cell(30, 5, $amount_display, 0, 0, 'R');
            $pdf->Cell(30, 5, $balance_display, 0, 1, 'R');

            // Move to after the multiline description if it was taller
            if ($y_after > $pdf->GetY()) {
                $pdf->SetY($y_after);
            }

            $pdf->Ln(1);
        }

        // Divider line
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(3);

        // Summary
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(120, 5, 'ENDING BALANCE:', 0, 0, 'L');
        $pdf->Cell(60, 5, number_format($ending_balance, 2), 0, 1, 'R');

        $pdf->Cell(120, 5, 'TOTAL CREDIT:', 0, 0, 'L');
        $pdf->Cell(60, 5, number_format($total_credit, 2), 0, 1, 'R');

        $pdf->Cell(120, 5, 'TOTAL DEBIT:', 0, 0, 'L');
        $pdf->Cell(60, 5, number_format($total_debit, 2), 0, 1, 'R');

        // Divider line
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(5);

        // Notice
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell(0, 4, 'Notice', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 7);
        $notice_text = "(1) All items and balances shown will be considered correct unless the Bank is notified in writing of any discrepancies within 21 days.\n\n";
        $notice_text .= "(2) Please notify us of any change of address in writing.";
        $pdf->MultiCell(0, 4, $notice_text, 0, 'L');

        // Divider line
        $pdf->Line(15, $pdf->GetY() + 2, 195, $pdf->GetY() + 2);

        $pdf->Ln(3);

        // Educational Disclaimer Footer
        $pdf->SetFillColor(255, 243, 205); // Light yellow background
        $pdf->SetTextColor(139, 69, 19); // Brown text
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell(0, 5, 'DISCLAIMER: FOR EDUCATIONAL PURPOSES ONLY', 0, 1, 'C', true);
        $pdf->SetFont('helvetica', '', 6);
        $disclaimer_text = "This bank statement is generated for INTI International University course IBM4202E Web Programming.\n";
        $disclaimer_text .= "All information contained in this document is simulated and NOT valid for any official verification,\n";
        $disclaimer_text .= "financial transactions, loan applications, visa applications, or any legal purposes.";
        $pdf->MultiCell(0, 3, $disclaimer_text, 0, 'C', true);
        $pdf->SetTextColor(0, 0, 0); // Reset to black

        return $pdf;
    }

    private function format_transaction_description($transaction) {
        $type = strtoupper($transaction['transaction_type']);
        $description = $transaction['description'];
        $reference = $transaction['reference_number'];

        $formatted = $type . "\n";
        if (!empty($description)) {
            $formatted .= wordwrap($description, 50, "\n") . "\n";
        }
        $formatted .= "REF: " . $reference;

        return $formatted;
    }

    public function output_pdf($pdf, $filename = 'statement.pdf') {
        // Output PDF string (let controller handle headers)
        return $pdf->Output($filename, 'S');
    }

    public function save_pdf($pdf, $filepath) {
        // Save PDF to file
        $pdf->Output($filepath, 'F');
    }
}
?>
