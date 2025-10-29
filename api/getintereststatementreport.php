<?php
include 'headers.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}
header('Content-Type: application/json; charset=utf-8');

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();
date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');
$today = new DateTime();

function calculateInterestPeriod_14HalfDays($startDate, $endDate = null) {
    $start = new DateTime($startDate);
    $end = $endDate ? new DateTime($endDate) : new DateTime();
    $diff_days = $start->diff($end)->days;
    $half_count = ceil(($diff_days + 1) / 14);
    return $half_count * 0.5;
}

function getDynamicRateByFullMonth($monthIndex, $pawn_interest, $recoveryPeriod) {
    if ($monthIndex <= $recoveryPeriod) {
        return $pawn_interest;
    }

    $monthAfterRecovery = $monthIndex - $recoveryPeriod;

    if ($monthAfterRecovery <= 3) {
        return $pawn_interest + 1;
    } elseif ($monthAfterRecovery <= 5) {
        return $pawn_interest + 2;
    } else {
        $extraMonths = $monthAfterRecovery - 5;
        return $pawn_interest + 2 + $extraMonths;
    }
}

function getDateRangeForHalfPeriod($startDate, $halfIndex) {
    $start = new DateTime($startDate);
    $fromDate = clone $start;
    $fromDate->add(new DateInterval('P' . (($halfIndex - 1) * 14) . 'D'));
    $toDate = clone $fromDate;
    $toDate->add(new DateInterval('P13D'));  // +13 days for 14-day period (inclusive)
    return [
        'from' => $fromDate->format('d-m-Y'),
        'to' => $toDate->format('d-m-Y')
    ];
}

function generateBreakdownSection($startDate, $principal, $recoveryPeriod, $pawn_interest, $endDate = null, $paidTotalMonths = 0, $isNewCycle = false) {
    $monthsFloat = calculateInterestPeriod_14HalfDays($startDate, $endDate);
    $totalInterest = 0.0;
    $breakdown = [];
    $half_count = (int)ceil($monthsFloat * 2);

    for ($i = 1; $i <= $half_count; $i++) {
        $period = $i * 0.5;
        $monthIndex = (int)ceil($i / 2);
        $rate = getDynamicRateByFullMonth($monthIndex, $pawn_interest, $recoveryPeriod);
        $halfInterest = round(($principal * $rate / 100) * 0.5, 2);
        $isPaid = ($paidTotalMonths >= $period);
        $interestForPeriod = $isPaid ? 0 : $halfInterest;
        $totalInterest += $interestForPeriod;

        $dateRange = getDateRangeForHalfPeriod($startDate, $i);
        $breakdown[] = [
            'period' => round($period, 1),
            'from_to' => $dateRange['from'] . ' to ' . $dateRange['to'],
            'rate' => $rate . '%',
            'interest' => $halfInterest,
            'total' => $totalInterest,
            'balance' => $totalInterest,
            'paid' => $isPaid
        ];
    }

    return [$monthsFloat, $totalInterest, $breakdown];
}

// Main logic: Generate Interest Statement Report
if (isset($obj->receipt_no)) {
    $receipt_no = trim($conn->real_escape_string($obj->receipt_no));

    if (empty($receipt_no)) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Receipt number is required";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    // Fetch pawn data
    $stmt = $conn->prepare("SELECT p.pawnjewelry_date, p.original_amount, p.Jewelry_recovery_agreed_period, p.interest_rate, p.last_interest_settlement_date, r.pawnjewelry_recovery_date 
                            FROM pawnjewelry p 
                            LEFT JOIN pawnjewelry_recovery r ON p.receipt_no = r.receipt_no AND r.delete_at = 0 
                            WHERE p.receipt_no = ? AND p.delete_at = 0");
    $stmt->bind_param("s", $receipt_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Receipt number not found";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    $pawn_data = $result->fetch_assoc();
    $original_start = $pawn_data['pawnjewelry_date'];
    $principal = floatval($pawn_data['original_amount']);
    $recoveryPeriod = intval($pawn_data['Jewelry_recovery_agreed_period'] ?? 0);
    $interest_rate_str = $pawn_data['interest_rate'] ?? '0';
    $pawn_interest = floatval(str_replace('%', '', $interest_rate_str));
    $end_date_str = $pawn_data['pawnjewelry_recovery_date'] ?: null;

    // Fetch ALL interest payments sorted by date
    $paymentStmt = $conn->prepare("SELECT interest_receive_date, interest_income FROM interest WHERE receipt_no = ? AND delete_at = 0 ORDER BY interest_receive_date ASC");
    $paymentStmt->bind_param("s", $receipt_no);
    $paymentStmt->execute();
    $paymentResult = $paymentStmt->get_result();
    $payments = [];
    $total_paid_interest = 0.0;
    while ($row = $paymentResult->fetch_assoc()) {
        $payments[] = [
            'date' => $row['interest_receive_date'],
            'amount' => floatval($row['interest_income'])
        ];
        $total_paid_interest += $payments[count($payments)-1]['amount'];
    }
    $paymentStmt->close();

    $breakdown = [];
    $overall_total_interest = 0.0;
    $overall_total_months = 0.0;
    $paid_months = 0.0;
    $current_start = $original_start;
    $cumulative_period = 0.0;
    $paid_days = 0;
    $unpaid_days = 0;

    foreach ($payments as $payment) {
        $payment_date_full = $payment['date'];
        $payment_date = date('Y-m-d', strtotime($payment_date_full)); // Strip time if any
        $payment_amount = $payment['amount'];

        // Calculate segment from current_start to payment_date
        list($seg_months, $seg_interest, $seg_breakdown) = generateBreakdownSection($current_start, $principal, $recoveryPeriod, $pawn_interest, $payment_date);

        // Calculate inclusive days for this paid segment
        $seg_start_dt = new DateTime($current_start);
        $seg_end_dt = new DateTime($payment_date);
        $seg_diff_days = $seg_start_dt->diff($seg_end_dt)->days;
        $seg_inclusive_days = $seg_diff_days + 1;
        $paid_days += $seg_inclusive_days;

        // Adjust periods to cumulative
        foreach ($seg_breakdown as &$row) {
            $row['period'] = round($cumulative_period + $row['period'], 1);
        }

        // Add ALL unpaid rows (paid=false, local totals)
        $breakdown = array_merge($breakdown, $seg_breakdown);

        // Add paid entry
        $last_period = round($cumulative_period + $seg_months, 1);
        $paid_entry = [
            'period' => $last_period,
            'balance' => 0,
            'paid' => true,
            'paid_date' => date('d-m-Y', strtotime($payment_date)) . ' ',
            'paid_amount' => $payment_amount  // Use actual paid amount from DB
        ];
        $breakdown[] = $paid_entry;

        // Accumulate
        $overall_total_interest += $seg_interest;
        $overall_total_months += $seg_months;
        $paid_months += $seg_months;
        $cumulative_period += $seg_months;

        // Next start: payment +1 day
        $next_start_date = new DateTime($payment_date);
        $next_start_date->add(new DateInterval('P1D'));
        $current_start = $next_start_date->format('Y-m-d');
    }

    // Last unpaid segment from last payment +1 to end_date or today
    $end_for_unpaid = $end_date_str ?: $today->format('Y-m-d');
    if (strtotime($current_start) <= strtotime($end_for_unpaid)) {
        list($last_months, $last_interest, $last_breakdown) = generateBreakdownSection($current_start, $principal, $recoveryPeriod, $pawn_interest, $end_for_unpaid);

        // Calculate inclusive days for unpaid segment
        $last_start_dt = new DateTime($current_start);
        $last_end_dt = new DateTime($end_for_unpaid);
        $last_diff_days = $last_start_dt->diff($last_end_dt)->days;
        $unpaid_days = $last_diff_days + 1;

        foreach ($last_breakdown as &$row) {
            $row['period'] = round($cumulative_period + $row['period'], 1);
            $row['paid'] = false;  // Ensure
        }

        $breakdown = array_merge($breakdown, $last_breakdown);

        $overall_total_interest += $last_interest;
        $overall_total_months += $last_months;
        // paid_months not added for unpaid
        $cumulative_period += $last_months;
    } else {
        $unpaid_days = 0;
    }

    // Calculate total_days (inclusive)
    $overall_start = new DateTime($original_start);
    $overall_end = new DateTime($end_for_unpaid);
    $overall_diff_days = $overall_start->diff($overall_end)->days;
    $total_days = $overall_diff_days + 1;

    $total_due = $principal + $overall_total_interest;
    $unpaid_months = round($overall_total_months - $paid_months, 1);
    $unpaid_interest = round($overall_total_interest - $total_paid_interest, 2);

    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Interest Statement Report Generated for " . $receipt_no;
    $output["body"] = [
        'receipt_no' => $receipt_no,
        'effective_start_date' => $original_start,
        'end_date_used' => $end_for_unpaid,
        'principal' => $principal,
        'base_rate' => $pawn_interest . '%',
        'recovery_period' => $recoveryPeriod . ' months',
        'paid_months' => round($paid_months, 1),
        'total_months' => round($overall_total_months, 1),
        'total_interest' => $overall_total_interest,
        'paid_interest' => $total_paid_interest,
        'total_due' => $total_due,
        'unpaid_months' => $unpaid_months,
        'unpaid_interest' => $unpaid_interest,
        'paid_days' => $paid_days,
        'unpaid_days' => $unpaid_days,
        'total_days' => $total_days,
        'breakdown' => $breakdown
    ];
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter mismatch: receipt_no required";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
?>