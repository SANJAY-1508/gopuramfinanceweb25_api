<?php
include 'db/config.php';

if (!$conn) {
    file_put_contents('/var/log/pawnjewelry_update.log', "DB connection failed: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    exit();
}

date_default_timezone_set('Asia/Calcutta');

function calculateInterestPeriod_14HalfDays($startDate, $endDate = null) {
    $start = new DateTime($startDate);
    $end = $endDate ? new DateTime($endDate) : new DateTime();
    $diff_days = $start->diff($end)->days;
    if ($diff_days == 0) {
        return 0.0;  // FIXED: No interest on same day for cron
    }
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

function calculateTotalInterestDue($startDate, $principal, $recoveryPeriod, $paidTotalMonths, $pawn_interest, $endDate = null) {
    $monthsFloat = calculateInterestPeriod_14HalfDays($startDate, $endDate);
    $totalInterest = 0.0;

    $fullMonths = floor($monthsFloat);
    $hasHalfMonth = ($monthsFloat > $fullMonths);

    for ($m = 1; $m <= $fullMonths; $m++) {
        $rate = getDynamicRateByFullMonth($m, $pawn_interest, $recoveryPeriod);
        $monthlyInterest = round(($principal * $rate) / 100, 2);

        $isPaid = ($paidTotalMonths >= $m);
        if (!$isPaid) {
            $totalInterest += $monthlyInterest;
        }
    }

    if ($hasHalfMonth) {
        $m = $fullMonths + 1;
        $rate = getDynamicRateByFullMonth($m, $pawn_interest, $recoveryPeriod);
        $halfInterest = round((($principal * $rate) / 100) * 0.5, 2);

        $isPaid = ($paidTotalMonths >= $fullMonths + 0.5);
        if (!$isPaid) {
            $totalInterest += $halfInterest;
        }
    }

    return [$monthsFloat, $totalInterest];
}

/* -----------------------
   Get pawn records with last_settlement
   ----------------------- */
$pawnQuery = "SELECT id, receipt_no, pawnjewelry_date, original_amount, Jewelry_recovery_agreed_period, interest_rate, last_interest_settlement_date FROM pawnjewelry WHERE delete_at = 0";
$result = $conn->query($pawnQuery);

if ($result === false) {
    file_put_contents('/var/log/pawnjewelry_update.log', "Pawn query failed: " . $conn->error . "\n", FILE_APPEND);
    exit();
}

/* -----------------------
   Process and update each pawn record
   ----------------------- */
while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $receipt_no = $row['receipt_no'];
    $effective_start = $row['last_interest_settlement_date'] ?: $row['pawnjewelry_date'];
    $pawn_date = $effective_start;
    $principal = floatval($row['original_amount']);
    $interest_rate_str = $row['interest_rate'] ?? '0';
    $pawn_interest = floatval(str_replace('%', '', $interest_rate_str));  // percent
    $recoveryPeriod = intval($row['Jewelry_recovery_agreed_period'] ?? 0);

    // FIXED: Cycle paid: SUM(outstanding_period) WHERE DATE(create_at) > DATE(effective_start) to exclude same-day payments
    $paidStmt = $conn->prepare("SELECT SUM(outstanding_period) AS cycle_paid_months FROM interest WHERE receipt_no = ? AND DATE(create_at) > DATE(?) AND delete_at = 0");
    $paidStmt->bind_param("ss", $receipt_no, $effective_start);
    $paidStmt->execute();
    $paidResult = $paidStmt->get_result();
    $paidRow = $paidResult->fetch_assoc();
    $paidTotalMonths = floatval($paidRow['cycle_paid_months'] ?? 0);
    $paidStmt->close();
    
    // Fetch recovery date to skip or set end_date
    $recoveryStmt = $conn->prepare("SELECT pawnjewelry_recovery_date FROM pawnjewelry_recovery WHERE receipt_no = ? AND delete_at = 0");
    $recoveryStmt->bind_param("s", $receipt_no);
    $recoveryStmt->execute();
    $recResult = $recoveryStmt->get_result();
    $recRow = $recResult->fetch_assoc();
    $recoveryDate = $recRow ? $recRow['pawnjewelry_recovery_date'] : null;
    $recoveryStmt->close();

    // ============= FIXED: Handle recovery - Skip if recovered =============
    if ($recoveryDate) {
        // Log and skip update for recovered loans
        file_put_contents('/var/log/pawnjewelry_update.log', "Skipped recovered receipt $receipt_no (recovered on $recoveryDate)\n", FILE_APPEND);
        continue;  // Skip calc & update
    }
    // ============= END FIX =============

    // Calculate (endDate null = today)
    list($totalMonthsFloat, $totalUnpaidInterest) = calculateTotalInterestDue($pawn_date, $principal, $recoveryPeriod, $paidTotalMonths, $pawn_interest);

    // Compute due months
    $dueMonths = max(0.0, $totalMonthsFloat - $paidTotalMonths);
    $dueMonthsRounded = round($dueMonths * 2) / 2.0;

    // Update pawnjewelry table
    $stmt = $conn->prepare("UPDATE pawnjewelry SET interest_payment_period = ?, interest_payment_amount = ? WHERE id = ? AND delete_at = 0");
    $stmt->bind_param("ddi", $dueMonthsRounded, $totalUnpaidInterest, $id);
    $stmt->execute();
    $stmt->close();

    // Debug log
    file_put_contents('/var/log/pawnjewelry_update.log', "Receipt $receipt_no | Effective Start: $effective_start | Due Months: $dueMonthsRounded | Due Interest: $totalUnpaidInterest | Total Months: $totalMonthsFloat | Cycle Paid Months: $paidTotalMonths\n", FILE_APPEND);
}

file_put_contents('/var/log/pawnjewelry_update.log', "Pawnjewelry dynamic interest update completed successfully at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
echo "Pawnjewelry dynamic interest update completed successfully.";
?>