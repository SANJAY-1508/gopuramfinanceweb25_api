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
$obj = json_decode($json, true);
$output = array();

// Database connection check
if (!isset($conn)) {
    $output["head"]["code"] = 500;
    $output["head"]["msg"] = "Database connection not established";
    echo json_encode($output, JSON_NUMERIC_CHECK);
    exit();
}

// Date filtering
$fromDate = isset($obj['fromDate']) ? $obj['fromDate'] : null;
$toDate = isset($obj['toDate']) ? $obj['toDate'] : null;
$dateFilter = "";
$params = array();
$paramTypes = "";

if ($fromDate && $toDate) {
    if ($fromDate === $toDate) {
        $toDate .= " 23:59:59";
    }
    $dateFilter = " AND create_at BETWEEN ? AND ?";
    $params = [$fromDate, $toDate];
    $paramTypes = "ss";
} elseif ($fromDate) {
    $dateFilter = " AND create_at >= ?";
    $params = [$fromDate];
    $paramTypes = "s";
} elseif ($toDate) {
    $dateFilter = " AND create_at <= ?";
    $params = [$toDate];
    $paramTypes = "s";
}

if (isset($obj['action'])) {
    switch ($obj['action']) {
        case 'daily_final_sheet':
            // Gold receipts
            $sql_gold_receipts = "SELECT receipt_no FROM pawnjewelry WHERE delete_at = 0 AND group_type = ? $dateFilter";
            $stmt_gold = $conn->prepare($sql_gold_receipts);
            $bind_params = array_merge(['GOLD'], $params);
            $stmt_gold->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_gold->execute();
            $result_gold = $stmt_gold->get_result();
            $gold_receipts = [];
            while ($row = $result_gold->fetch_assoc()) {
                $gold_receipts[] = $row['receipt_no'];
            }
            $gold_in = !empty($gold_receipts) ? "'" . implode("','", $gold_receipts) . "'" : "''";

            // Silver receipts
            $sql_silver_receipts = "SELECT receipt_no FROM pawnjewelry WHERE delete_at = 0 AND group_type = ? $dateFilter";
            $stmt_silver = $conn->prepare($sql_silver_receipts);
            $bind_params = array_merge(['SLIVER'], $params);
            $stmt_silver->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_silver->execute();
            $result_silver = $stmt_silver->get_result();
            $silver_receipts = [];
            while ($row = $result_silver->fetch_assoc()) {
                $silver_receipts[] = $row['receipt_no'];
            }
            $silver_in = !empty($silver_receipts) ? "'" . implode("','", $silver_receipts) . "'" : "''";

            // Pawn amounts
            $sql_pawn_gold = "SELECT IFNULL(SUM(original_amount), 0) AS gold_c FROM pawnjewelry WHERE delete_at = 0 AND group_type = ? $dateFilter";
            $stmt_pawn_gold = $conn->prepare($sql_pawn_gold);
            $bind_params = array_merge(['GOLD'], $params);
            $stmt_pawn_gold->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_pawn_gold->execute();
            $gold_c = $stmt_pawn_gold->get_result()->fetch_assoc()['gold_c'];

            $sql_pawn_silver = "SELECT IFNULL(SUM(original_amount), 0) AS silver_c FROM pawnjewelry WHERE delete_at = 0 AND group_type = ? $dateFilter";
            $stmt_pawn_silver = $conn->prepare($sql_pawn_silver);
            $bind_params = array_merge(['SLIVER'], $params);
            $stmt_pawn_silver->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_pawn_silver->execute();
            $silver_c = $stmt_pawn_silver->get_result()->fetch_assoc()['silver_c'];

            // Gold recovery & interest
            $gold_d = $gold_i = 0;
            if (!empty($gold_receipts)) {
                $sql_recovery_gold = "SELECT IFNULL(SUM(refund_amount), 0) AS gold_d FROM pawnjewelry_recovery WHERE delete_at = 0 AND receipt_no IN ($gold_in)";
                $stmt_recovery_gold = $conn->prepare($sql_recovery_gold);
                $stmt_recovery_gold->execute();
                $gold_d = $stmt_recovery_gold->get_result()->fetch_assoc()['gold_d'];

                $sql_interest_gold = "SELECT IFNULL(SUM(interest_income), 0) AS gold_i FROM interest WHERE delete_at = 0 AND receipt_no IN ($gold_in)";
                $stmt_interest_gold = $conn->prepare($sql_interest_gold);
                $stmt_interest_gold->execute();
                $gold_i = $stmt_interest_gold->get_result()->fetch_assoc()['gold_i'];
            }

            // Silver recovery & interest
            $silver_d = $silver_i = 0;
            if (!empty($silver_receipts)) {
                $sql_recovery_silver = "SELECT IFNULL(SUM(refund_amount), 0) AS silver_d FROM pawnjewelry_recovery WHERE delete_at = 0 AND receipt_no IN ($silver_in)";
                $stmt_recovery_silver = $conn->prepare($sql_recovery_silver);
                $stmt_recovery_silver->execute();
                $silver_d = $stmt_recovery_silver->get_result()->fetch_assoc()['silver_d'];

                $sql_interest_silver = "SELECT IFNULL(SUM(interest_income), 0) AS silver_i FROM interest WHERE delete_at = 0 AND receipt_no IN ($silver_in)";
                $stmt_interest_silver = $conn->prepare($sql_interest_silver);
                $stmt_interest_silver->execute();
                $silver_i = $stmt_interest_silver->get_result()->fetch_assoc()['silver_i'];
            }

            // RP Gold
            $sql_pawn_rp = "SELECT IFNULL(SUM(bank_pawn_value), 0) AS rp_gold_c FROM pawnjewelry WHERE delete_at = 0 AND group_type = ? $dateFilter";
            $stmt_pawn_rp = $conn->prepare($sql_pawn_rp);
            $bind_params = array_merge(['GOLD'], $params);
            $stmt_pawn_rp->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_pawn_rp->execute();
            $rp_gold_c = $stmt_pawn_rp->get_result()->fetch_assoc()['rp_gold_c'];

            // RP receipts
            $sql_rp_receipts = "SELECT receipt_no FROM pawnjewelry WHERE delete_at = 0 AND group_type = ? AND bank_pawn_value > 0 $dateFilter";
            $stmt_rp = $conn->prepare($sql_rp_receipts);
            $bind_params = array_merge(['GOLD'], $params);
            $stmt_rp->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_rp->execute();
            $result_rp = $stmt_rp->get_result();
            $rp_receipts = [];
            while ($row = $result_rp->fetch_assoc()) {
                $rp_receipts[] = $row['receipt_no'];
            }
            $rp_in = !empty($rp_receipts) ? "'" . implode("','", $rp_receipts) . "'" : "''";

            $rp_gold_d = $rp_gold_i = 0;
            if (!empty($rp_receipts)) {
                $sql_recovery_rp_d = "SELECT IFNULL(SUM(bank_recovery_pawn_amount), 0) AS rp_gold_d FROM pawnjewelry_recovery WHERE delete_at = 0 AND receipt_no IN ($rp_in)";
                $stmt_recovery_rp_d = $conn->prepare($sql_recovery_rp_d);
                $stmt_recovery_rp_d->execute();
                $rp_gold_d = $stmt_recovery_rp_d->get_result()->fetch_assoc()['rp_gold_d'];

                $sql_recovery_rp_i = "SELECT IFNULL(SUM(bank_paid_interest_amount), 0) AS rp_gold_i FROM pawnjewelry_recovery WHERE delete_at = 0 AND receipt_no IN ($rp_in)";
                $stmt_recovery_rp_i = $conn->prepare($sql_recovery_rp_i);
                $stmt_recovery_rp_i->execute();
                $rp_gold_i = $stmt_recovery_rp_i->get_result()->fetch_assoc()['rp_gold_i'];
            }

            // Expenses
            $sql_expense = "SELECT IFNULL(SUM(amount), 0) AS expense FROM expenses WHERE expense_type = ? $dateFilter";
            $stmt_expense = $conn->prepare($sql_expense);
            $bind_params = array_merge(['debit'], $params);
            $stmt_expense->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_expense->execute();
            $expense = $stmt_expense->get_result()->fetch_assoc()['expense'];

            // Cash inflow
            $sql_cash = "SELECT IFNULL(SUM(amount), 0) AS cash FROM expenses WHERE expense_type = ? $dateFilter";
            $stmt_cash = $conn->prepare($sql_cash);
            $bind_params = array_merge(['credit'], $params);
            $stmt_cash->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_cash->execute();
            $cash = $stmt_cash->get_result()->fetch_assoc()['cash'];

            $start_bal = 0;
            $result = ($gold_c + $gold_i + $cash) - ($gold_d + $expense);
            $end_bal = $start_bal + $result;

            $ledger_data = [
                [
                    "date" => date('Y-m-d'),
                    "gold_c" => (float)$gold_c,
                    "gold_d" => (float)$gold_d,
                    "gold_i" => (float)$gold_i,
                    "silver_c" => (float)$silver_c,
                    "silver_d" => (float)$silver_d,
                    "silver_i" => (float)$silver_i,
                    "rp_gold_c" => (float)$rp_gold_c,
                    "rp_gold_d" => (float)$rp_gold_d,
                    "rp_gold_i" => (float)$rp_gold_i,
                    "expense" => (float)$expense,
                    "cash" => (float)$cash,
                    "start_bal" => (float)$start_bal,
                    "end_bal" => (float)$end_bal,
                    "result" => (float)$result
                ]
            ];

            $totals = [
                "gold_c" => (float)$gold_c,
                "gold_d" => (float)$gold_d,
                "gold_i" => (float)$gold_i,
                "silver_c" => (float)$silver_c,
                "silver_d" => (float)$silver_d,
                "silver_i" => (float)$silver_i,
                "rp_gold_c" => (float)$rp_gold_c,
                "rp_gold_d" => (float)$rp_gold_d,
                "rp_gold_i" => (float)$rp_gold_i,
                "expense" => (float)$expense,
                "cash" => (float)$cash,
                "start_bal" => (float)$start_bal,
                "end_bal" => (float)$end_bal,
                "result" => (float)$result
            ];

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Daily final sheet data reconciled successfully";
            $output["body"]["data"] = $ledger_data;
            $output["body"]["totals"] = $totals;
            break;

        case 'final_report':
            $monthMap = [
                'January' => '01', 'February' => '02', 'March' => '03', 'April' => '04',
                'May' => '05', 'June' => '06', 'July' => '07', 'August' => '08',
                'September' => '09', 'October' => '10', 'November' => '11', 'December' => '12'
            ];
            $month = isset($obj['fromDate']) && isset($monthMap[$obj['fromDate']]) ? $monthMap[$obj['fromDate']] : null;
            $year = isset($obj['toDate']) ? $obj['toDate'] : null;

            $dateFilter = "";
            $params = [];
            $paramTypes = "";
            if ($month && $year) {
                $dateFilter = " AND YEAR(pawnjewelry_date) = ? AND MONTH(pawnjewelry_date) = ?";
                $params = [$year, $month];
                $paramTypes = "ss";
            }

            // Gold credit
            $sql_gold_c = "SELECT IFNULL(SUM(original_amount), 0) AS gold_c FROM pawnjewelry WHERE delete_at = 0 AND group_type = ? $dateFilter";
            $stmt_gold_c = $conn->prepare($sql_gold_c);
            $bind_params = array_merge(['GOLD'], $params);
            $stmt_gold_c->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_gold_c->execute();
            $gold_c = $stmt_gold_c->get_result()->fetch_assoc()['gold_c'];

            // Silver credit
            $sql_silver_c = "SELECT IFNULL(SUM(original_amount), 0) AS silver_c FROM pawnjewelry WHERE delete_at = 0 AND group_type = ? $dateFilter";
            $stmt_silver_c = $conn->prepare($sql_silver_c);
            $bind_params = array_merge(['SLIVER'], $params);
            $stmt_silver_c->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_silver_c->execute();
            $silver_c = $stmt_silver_c->get_result()->fetch_assoc()['silver_c'];

            // Fetch receipts
            $getReceipts = function($type) use ($conn, $dateFilter, $params, $paramTypes) {
                $sql = "SELECT receipt_no FROM pawnjewelry WHERE delete_at = 0 AND group_type = ? $dateFilter";
                $stmt = $conn->prepare($sql);
                $bind_params = array_merge([$type], $params);
                $stmt->bind_param('s' . $paramTypes, ...$bind_params);
                $stmt->execute();
                $result = $stmt->get_result();
                $receipts = [];
                while ($row = $result->fetch_assoc()) {
                    $receipts[] = $row['receipt_no'];
                }
                return $receipts;
            };

            $goldReceipts = $getReceipts('GOLD');
            $silverReceipts = $getReceipts('SLIVER');
            $rpReceipts = $getReceipts('GOLD');

            $inClause = function($receipts) {
                return !empty($receipts) ? "'" . implode("','", $receipts) . "'" : "''";
            };

            // Gold debit & interest
            $gold_d = $gold_i = 0;
            if (!empty($goldReceipts)) {
                $goldIn = $inClause($goldReceipts);
                $sql = "SELECT IFNULL(SUM(refund_amount), 0) AS gold_d FROM pawnjewelry_recovery WHERE delete_at = 0 AND receipt_no IN ($goldIn)";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $gold_d = $stmt->get_result()->fetch_assoc()['gold_d'];

                $sql = "SELECT IFNULL(SUM(interest_income), 0) AS gold_i FROM interest WHERE delete_at = 0 AND receipt_no IN ($goldIn)";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $gold_i = $stmt->get_result()->fetch_assoc()['gold_i'];
            }

            // Silver debit & interest
            $silver_d = $silver_i = 0;
            if (!empty($silverReceipts)) {
                $silverIn = $inClause($silverReceipts);
                $sql = "SELECT IFNULL(SUM(refund_amount), 0) AS silver_d FROM pawnjewelry_recovery WHERE delete_at = 0 AND receipt_no IN ($silverIn)";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $silver_d = $stmt->get_result()->fetch_assoc()['silver_d'];

                $sql = "SELECT IFNULL(SUM(interest_income), 0) AS silver_i FROM interest WHERE delete_at = 0 AND receipt_no IN ($silverIn)";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $silver_i = $stmt->get_result()->fetch_assoc()['silver_i'];
            }

            // RP Gold credit
            $sql_rp_c = "SELECT IFNULL(SUM(bank_pawn_value), 0) AS rp_gold_c FROM pawnjewelry WHERE delete_at = 0 AND bank_pawn_value > 0 AND group_type = ? $dateFilter";
            $stmt_rp_c = $conn->prepare($sql_rp_c);
            $bind_params = array_merge(['GOLD'], $params);
            $stmt_rp_c->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_rp_c->execute();
            $rp_gold_c = $stmt_rp_c->get_result()->fetch_assoc()['rp_gold_c'];

            // RP receipts
            $sql_rp_receipts = "SELECT receipt_no FROM pawnjewelry WHERE delete_at = 0 AND group_type = ? AND bank_pawn_value > 0 $dateFilter";
            $stmt_rp = $conn->prepare($sql_rp_receipts);
            $bind_params = array_merge(['GOLD'], $params);
            $stmt_rp->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_rp->execute();
            $result_rp = $stmt_rp->get_result();
            $rpReceipts = [];
            while ($row = $result_rp->fetch_assoc()) {
                $rpReceipts[] = $row['receipt_no'];
            }

            $rp_d = $rp_i = 0;
            if (!empty($rpReceipts)) {
                $rpIn = $inClause($rpReceipts);
                $sql = "SELECT IFNULL(SUM(bank_recovery_pawn_amount), 0) AS rp_d FROM pawnjewelry_recovery WHERE delete_at = 0 AND receipt_no IN ($rpIn)";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $rp_d = $stmt->get_result()->fetch_assoc()['rp_d'];

                $sql = "SELECT IFNULL(SUM(bank_paid_interest_amount), 0) AS rp_i FROM pawnjewelry_recovery WHERE delete_at = 0 AND receipt_no IN ($rpIn)";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $rp_i = $stmt->get_result()->fetch_assoc()['rp_i'];
            }

            // Expense & cash
            $sql_expense = "SELECT IFNULL(SUM(amount), 0) AS expense FROM expenses WHERE expense_type = ? $dateFilter";
            $stmt_expense = $conn->prepare($sql_expense);
            $bind_params = array_merge(['debit'], $params);
            $stmt_expense->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_expense->execute();
            $expense = $stmt_expense->get_result()->fetch_assoc()['expense'];

            $sql_cash = "SELECT IFNULL(SUM(amount), 0) AS cash FROM expenses WHERE expense_type = ? $dateFilter";
            $stmt_cash = $conn->prepare($sql_cash);
            $bind_params = array_merge(['credit'], $params);
            $stmt_cash->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_cash->execute();
            $cash = $stmt_cash->get_result()->fetch_assoc()['cash'];

            $start_bal = 0;
            $total_credit = (float)$gold_c + (float)$gold_i + (float)$cash + (float)$rp_d + (float)$rp_i;
            $total_debit = (float)$gold_d + (float)$silver_d + (float)$silver_i + (float)$rp_gold_c + (float)$expense;

            $result_val = $total_credit - $total_debit;
            $end_bal = $start_bal + $result_val;

            $ledger_data = [[
                "month" => "$month-$year",
                "gold_c" => (float)$gold_c,
                "gold_d" => (float)$gold_d,
                "gold_i" => (float)$gold_i,
                "silver_c" => (float)$silver_c,
                "silver_d" => (float)$silver_d,
                "silver_i" => (float)$silver_i,
                "rp_gold_c" => (float)$rp_gold_c,
                "rp_gold_d" => (float)$rp_d,
                "rp_gold_i" => (float)$rp_i,
                "expense" => (float)$expense,
                "cash" => (float)$cash,
                "start_bal" => (float)$start_bal,
                "end_bal" => (float)$end_bal,
                "result" => (float)$result_val,
                "total_credit" => (float)$total_credit,
                "total_debit" => (float)$total_debit,
            ]];

            $totals = $ledger_data[0];

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Final report data reconciled successfully";
            $output["body"]["data"] = $ledger_data;
            $output["body"]["totals"] = $totals;
            break;

        case 'gold_ledger':
            $sql_pawn = "SELECT id, receipt_no, name, original_amount AS gold_c, pawnjewelry_date, bank_pawn_value 
                         FROM pawnjewelry 
                         WHERE delete_at = 0 AND group_type = ? $dateFilter";
            $stmt_pawn = $conn->prepare($sql_pawn);
            $bind_params = array_merge(['GOLD'], $params);
            $stmt_pawn->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_pawn->execute();
            $result_pawn = $stmt_pawn->get_result();

            $sql_interest = "SELECT receipt_no, interest_income FROM interest WHERE delete_at = 0";
            $stmt_interest = $conn->prepare($sql_interest);
            $stmt_interest->execute();
            $result_interest = $stmt_interest->get_result();

            $interest_map = [];
            while ($row = $result_interest->fetch_assoc()) {
                $receipt_no = $row['receipt_no'];
                if (!isset($interest_map[$receipt_no])) {
                    $interest_map[$receipt_no] = [];
                }
                $interest_map[$receipt_no][] = (float)$row['interest_income'];
            }

            $sql_recovery = "SELECT receipt_no, refund_amount FROM pawnjewelry_recovery WHERE delete_at = 0";
            $stmt_recovery = $conn->prepare($sql_recovery);
            $stmt_recovery->execute();
            $result_recovery = $stmt_recovery->get_result();

            $recovery_map = [];
            while ($row = $result_recovery->fetch_assoc()) {
                $recovery_map[$row['receipt_no']] = (float)$row['refund_amount'];
            }

            $ledger_data = [];
            $totals = [
                'gold_c' => 0, 'gc_interest' => 0, 'gold_d' => 0, 'gd_interest' => 0
            ];

            while ($row = $result_pawn->fetch_assoc()) {
                $receipt_no = $row['receipt_no'];
                $gc_interest = 0;
                $gd_interest = 0;
                $months = 0;

                if (isset($interest_map[$receipt_no])) {
                    $interest_list = $interest_map[$receipt_no];
                    if (count($interest_list) > 0) {
                        $gc_interest = $interest_list[0];
                        if (count($interest_list) > 1) {
                            $gd_interest = array_sum(array_slice($interest_list, 1));
                        }
                    }
                }

                $pawn_date = $row['pawnjewelry_date'];
                $start_date = new DateTime($pawn_date);
                $today = new DateTime();
                $diff_years = $today->format('Y') - $start_date->format('Y');
                $diff_months = $today->format('n') - $start_date->format('n');
                $total_months = ($diff_years * 12) + $diff_months;
                $today_day = (int)$today->format('d');
                if ($today_day > 15) {
                    $total_months += 1;
                }
                $final_months = max($total_months - 1, 0);
                $gold_type = ($row['bank_pawn_value'] > 0) ? 'rp' : '-';
                $gold_d = isset($recovery_map[$receipt_no]) ? $recovery_map[$receipt_no] : 0;

                $ledger_row = [
                    "pledge_no" => $receipt_no,
                    "gold_type" => $gold_type,
                    "gold_c" => (float)$row['gold_c'],
                    "gc_interest" => $gc_interest,
                    "gold_d" => $gold_d,
                    "months" => $final_months,
                    "gd_interest" => $gd_interest,
                    "name" => $row['name']
                ];

                $totals['gold_c'] += (float)$row['gold_c'];
                $totals['gc_interest'] += $gc_interest;
                $totals['gold_d'] += $gold_d;
                $totals['gd_interest'] += $gd_interest;

                $ledger_data[] = $ledger_row;
            }

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Gold ledger data fetched successfully";
            $output["body"]["data"] = $ledger_data;
            $output["body"]["totals"] = $totals;
            break;

        case 'rp_gold_ledger':
            $sql_pawn = "SELECT id, receipt_no, bank_name, location, name, bank_pawn_value AS rp_gold_c, pawnjewelry_date 
                         FROM pawnjewelry 
                         WHERE delete_at = 0 AND group_type = ? $dateFilter";
            $stmt_pawn = $conn->prepare($sql_pawn);
            $bind_params = array_merge(['GOLD'], $params);
            $stmt_pawn->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_pawn->execute();
            $result_pawn = $stmt_pawn->get_result();

            $sql_interest = "SELECT receipt_no, interest_income FROM interest WHERE delete_at = 0";
            $stmt_interest = $conn->prepare($sql_interest);
            $stmt_interest->execute();
            $result_interest = $stmt_interest->get_result();

            $interest_map = [];
            while ($row = $result_interest->fetch_assoc()) {
                $receipt_no = $row['receipt_no'];
                if (!isset($interest_map[$receipt_no])) {
                    $interest_map[$receipt_no] = [];
                }
                $interest_map[$receipt_no][] = (float)$row['interest_income'];
            }

            $sql_recovery = "SELECT receipt_no, bank_recovery_pawn_amount, bank_paid_interest_amount 
                            FROM pawnjewelry_recovery WHERE delete_at = 0";
            $stmt_recovery = $conn->prepare($sql_recovery);
            $stmt_recovery->execute();
            $result_recovery = $stmt_recovery->get_result();

            $recovery_map = [];
            while ($row = $result_recovery->fetch_assoc()) {
                $recovery_map[$row['receipt_no']] = [
                    'rp_gold_d' => (float)$row['bank_recovery_pawn_amount'],
                    'rp_gold_i' => (float)$row['bank_paid_interest_amount']
                ];
            }

            $ledger_data = [];
            $totals = [
                'rp_gold_c' => 0, 'rp_gold_d' => 0, 'rp_gold_i' => 0
            ];

            while ($row = $result_pawn->fetch_assoc()) {
                $receipt_no = $row['receipt_no'];
                $gc_interest = 0;
                $gd_interest = 0;

                if (isset($interest_map[$receipt_no])) {
                    $interest_list = $interest_map[$receipt_no];
                    if (count($interest_list) > 0) {
                        $gc_interest = $interest_list[0];
                        if (count($interest_list) > 1) {
                            $gd_interest = array_sum(array_slice($interest_list, 1));
                        }
                    }
                }

                $rp_gold_d = isset($recovery_map[$receipt_no]['rp_gold_d']) ? $recovery_map[$receipt_no]['rp_gold_d'] : 0;
                $rp_gold_i = isset($recovery_map[$receipt_no]['rp_gold_i']) ? $recovery_map[$receipt_no]['rp_gold_i'] : 0;

                $ledger_row = [
                    "pledge_no" => $receipt_no,
                    "bank_name" => $row['bank_name'],
                    "rp_pledge_no" => $row['location'],
                    "rp_gold_c" => (float)$row['rp_gold_c'],
                    "rp_gold_d" => $rp_gold_d,
                    "rp_gold_i" => $rp_gold_i
                ];

                $totals['rp_gold_c'] += (float)$row['rp_gold_c'];
                $totals['rp_gold_d'] += $rp_gold_d;
                $totals['rp_gold_i'] += $rp_gold_i;

                $ledger_data[] = $ledger_row;
            }

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "RP Gold ledger data fetched successfully";
            $output["body"]["data"] = $ledger_data;
            $output["body"]["totals"] = $totals;
            break;

        case 'sliver_ledger':
            $sql_pawn = "SELECT id, receipt_no, name, original_amount AS silver_c, pawnjewelry_date, bank_pawn_value 
                         FROM pawnjewelry 
                         WHERE delete_at = 0 AND group_type = ? $dateFilter";
            $stmt_pawn = $conn->prepare($sql_pawn);
            $bind_params = array_merge(['SLIVER'], $params);
            $stmt_pawn->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt_pawn->execute();
            $result_pawn = $stmt_pawn->get_result();

            $sql_interest = "SELECT receipt_no, interest_income FROM interest WHERE delete_at = 0";
            $stmt_interest = $conn->prepare($sql_interest);
            $stmt_interest->execute();
            $result_interest = $stmt_interest->get_result();

            $interest_map = [];
            while ($row = $result_interest->fetch_assoc()) {
                $receipt_no = $row['receipt_no'];
                if (!isset($interest_map[$receipt_no])) {
                    $interest_map[$receipt_no] = [];
                }
                $interest_map[$receipt_no][] = (float)$row['interest_income'];
            }

            $sql_recovery = "SELECT receipt_no, refund_amount FROM pawnjewelry_recovery WHERE delete_at = 0";
            $stmt_recovery = $conn->prepare($sql_recovery);
            $stmt_recovery->execute();
            $result_recovery = $stmt_recovery->get_result();

            $recovery_map = [];
            while ($row = $result_recovery->fetch_assoc()) {
                $recovery_map[$row['receipt_no']] = (float)$row['refund_amount'];
            }

            $ledger_data = [];
            $totals = [
                'silver_c' => 0, 'sc_interest' => 0, 'silver_d' => 0, 'sd_interest' => 0
            ];

            while ($row = $result_pawn->fetch_assoc()) {
                $receipt_no = $row['receipt_no'];
                $sc_interest = 0;
                $sd_interest = 0;
                $months = 0;

                if (isset($interest_map[$receipt_no])) {
                    $interest_list = $interest_map[$receipt_no];
                    if (count($interest_list) > 0) {
                        $sc_interest = $interest_list[0];
                        if (count($interest_list) > 1) {
                            $sd_interest = array_sum(array_slice($interest_list, 1));
                        }
                    }
                }

                $pawn_date = $row['pawnjewelry_date'];
                $start_date = new DateTime($pawn_date);
                $today = new DateTime();
                $diff_years = $today->format('Y') - $start_date->format('Y');
                $diff_months = $today->format('n') - $start_date->format('n');
                $total_months = ($diff_years * 12) + $diff_months;
                $today_day = (int)$today->format('d');
                if ($today_day > 15) {
                    $total_months += 1;
                } else {
                    $total_months += 0.5;
                }
                $final_months = max($total_months - 1, 0);

                $silver_d = isset($recovery_map[$receipt_no]) ? $recovery_map[$receipt_no] : 0;

                $ledger_row = [
                    "pledge_no" => $receipt_no,
                    "silver_c" => (float)$row['silver_c'],
                    "sc_interest" => $sc_interest,
                    "silver_d" => $silver_d,
                    "months" => $final_months,
                    "sd_interest" => $sd_interest,
                    "name" => $row['name']
                ];

                $totals['silver_c'] += (float)$row['silver_c'];
                $totals['sc_interest'] += $sc_interest;
                $totals['silver_d'] += $silver_d;
                $totals['sd_interest'] += $sd_interest;

                $ledger_data[] = $ledger_row;
            }

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Silver ledger data fetched successfully";
            $output["body"]["data"] = $ledger_data;
            $output["body"]["totals"] = $totals;
            break;

        case 'expense_ledger':
            $sql = "SELECT expense_name, amount AS expense, date FROM expenses WHERE expense_type = ? $dateFilter";
            $stmt = $conn->prepare($sql);
            $bind_params = array_merge(['debit'], $params);
            $stmt->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt->execute();
            $result = $stmt->get_result();

            $ledger_data = [];
            $totals = ['expense' => 0];

            while ($row = $result->fetch_assoc()) {
                $ledger_data[] = $row;
                $totals['expense'] += (float)($row['expense'] ?? 0);
            }

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Expense ledger data fetched successfully";
            $output["body"]["data"] = $ledger_data;
            $output["body"]["totals"] = $totals;
            break;

        case 'cash_ledger':
            $sql = "SELECT expense_name, amount AS expense, date FROM expenses WHERE expense_type = ? $dateFilter";
            $stmt = $conn->prepare($sql);
            $bind_params = array_merge(['credit'], $params);
            $stmt->bind_param('s' . $paramTypes, ...$bind_params);
            $stmt->execute();
            $result = $stmt->get_result();

            $ledger_data = [];
            $totals = ['expense' => 0];

            while ($row = $result->fetch_assoc()) {
                $ledger_data[] = $row;
                $totals['expense'] += (float)($row['expense'] ?? 0);
            }

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Cash ledger data fetched successfully";
            $output["body"]["data"] = $ledger_data;
            $output["body"]["totals"] = $totals;
            break;

        default:
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Invalid action parameter";
            break;
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Missing action parameter";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
?>