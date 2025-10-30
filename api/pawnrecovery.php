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

// <<<<<<<<<<===================== List Recovery Records =====================>>>>>>>>>>
if (isset($obj->search_text)) {
    $search_text = $conn->real_escape_string($obj->search_text);
    $sql = "SELECT r.id AS id,p.customer_no AS customer_no, r.pawnjewelry_recovery_id AS pawnjewelry_recovery_id, r.receipt_no AS receipt_no, r.pawnjewelry_date AS pawnjewelry_date, r.name AS name, r.customer_details AS customer_details, r.place AS place, r.mobile_number AS mobile_number, r.original_amount AS original_amount, r.interest_rate AS interest_rate, r.jewel_product AS jewel_product, r.interest_income AS interest_income, r.refund_amount AS refund_amount, r.other_amount AS other_amount, r.pawnjewelry_recovery_date AS pawnjewelry_recovery_date, r.status AS status, r.interest_payment_periods AS interest_payment_periods, r.proof_base64code AS proof_base64code,r.interest_paid AS interest_paid FROM  pawnjewelry_recovery AS r
LEFT JOIN 
    pawnjewelry AS p ON p.receipt_no = r.receipt_no

WHERE 
    r.delete_at = 0
    AND (r.receipt_no LIKE '%$search_text%' OR p.customer_details LIKE '%$search_text%')

ORDER BY 
    r.id ASC;
;
";

    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["pawn_recovery"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No records found";
        $output["body"]["pawn_recovery"] = [];
    }
}

// <<<<<<<<<<===================== Create or Update Recovery Record =====================>>>>>>>>>>

elseif (isset($obj->receipt_no)) {
    // --------------------------
    // 🟢 Input Sanitize & Prepare
    // --------------------------
    $edit_id = $conn->real_escape_string($obj->edit_pawnrecovery_id ?? '');
    $receipt_no = $conn->real_escape_string($obj->receipt_no);
    $pawnjewelry_date = $conn->real_escape_string($obj->pawnjewelry_date);
    $name = $conn->real_escape_string($obj->name);
    $raw_address = $obj->customer_details ?? '';
    $cleaned_address = str_replace(['/', '\\n', '\n', "\n", "\r"], ' ', $raw_address);
    $cleaned_address = preg_replace('/\s+/', ' ', $cleaned_address);
    $cleaned_address = trim($cleaned_address);
    $customer_details = $conn->real_escape_string($cleaned_address);
    $place = $conn->real_escape_string($obj->place);
    $mobile_number = $conn->real_escape_string($obj->mobile_number);
    $original_amount = floatval($obj->original_amount);
    $interest_rate = floatval($obj->interest_rate ?? 0);
    $jewel_product = isset($obj->jewel_product) ? $obj->jewel_product : [];
    $products_json = $conn->real_escape_string(json_encode($jewel_product, JSON_UNESCAPED_UNICODE));
    $interest_income = floatval($obj->interest_income);
    $refund_amount = floatval($obj->refund_amount);
    $other_amount = floatval($obj->other_amount);
    $pawnjewelry_recovery_date = $conn->real_escape_string($obj->pawnjewelry_recovery_date);
    $interest_payment_periods = $conn->real_escape_string($obj->interest_payment_periods);
    $interest_paid = floatval($obj->interest_paid);

    // Bank fields
    $bank_pledge_date = $conn->real_escape_string($obj->bank_pledge_date ?? '');
    $bank_assessor_name = $conn->real_escape_string($obj->bank_assessor_name ?? '');
    $bank_name = $conn->real_escape_string($obj->bank_name ?? '');
    $bank_pawn_value = floatval($obj->bank_pawn_value ?? 0);
    $bank_interest = floatval($obj->bank_interest ?? 0);
    $bank_duration = $conn->real_escape_string($obj->bank_duration ?? '');
    $bank_additional_charges = floatval($obj->bank_additional_charges ?? 0);
    $location = $conn->real_escape_string($obj->location ?? '');
    $bank_paid_interest_amount = floatval($obj->bank_paid_interest_amount ?? 0);
    $bank_recovery_pawn_amount = floatval($obj->bank_recovery_pawn_amount ?? 0);

    $type = "varavu";
    $timestamp = date('Y-m-d H:i:s');

    // ------------------------
    // 🟢 Insert (New Record)
    // ------------------------
    if ($edit_id === "") {
        // Validate pawnjewelry record
        $checkInterestStmt = $conn->prepare("SELECT `interest_payment_amount` FROM `pawnjewelry` WHERE `receipt_no` = ? AND `delete_at` = 0");
        $checkInterestStmt->bind_param("s", $receipt_no);
        $checkInterestStmt->execute();
        $resultInterest = $checkInterestStmt->get_result();
        $checkInterestStmt->close();

        if ($resultInterest->num_rows === 0) {
            $output["head"]["code"] = 404;
            $output["head"]["msg"] = "No pawn jewelry record found for this receipt number.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        // Check duplicate recovery
        $checkStmt = $conn->prepare("SELECT `id` FROM `pawnjewelry_recovery` WHERE `receipt_no` = ? AND delete_at = 0");
        $checkStmt->bind_param("s", $receipt_no);
        $checkStmt->execute();
        $recoveryCheck = $checkStmt->get_result();
        $checkStmt->close();

        if ($recoveryCheck->num_rows == 0) {
            // Convert empty dates to NULL
            $bank_pledge_date_sql = ($bank_pledge_date === '') ? 'NULL' : "'$bank_pledge_date'";
            $bank_duration_sql = ($bank_duration === '') ? 'NULL' : "'$bank_duration'";

            $sql = "
                INSERT INTO `pawnjewelry_recovery` (
                    `pawnjewelry_date`, `receipt_no`, `name`, `customer_details`, `place`, `mobile_number`, 
                    `original_amount`, `interest_rate`, `jewel_product`, `interest_income`, `refund_amount`, 
                    `pawnjewelry_recovery_date`, `interest_payment_periods`, `create_at`, `delete_at`, 
                    `other_amount`, `interest_paid`, `bank_pledge_date`, `bank_assessor_name`, `bank_name`, 
                    `bank_pawn_value`, `bank_interest`, `bank_duration`, `bank_additional_charges`, `location`, 
                    `bank_paid_interest_amount`, `bank_recovery_pawn_amount`
                ) VALUES (
                    '$pawnjewelry_date', '$receipt_no', '$name', '$customer_details', '$place', '$mobile_number',
                    $original_amount, $interest_rate, '$products_json', $interest_income, $refund_amount,
                    '$pawnjewelry_recovery_date', '$interest_payment_periods', '$timestamp', 0,
                    $other_amount, $interest_paid, $bank_pledge_date_sql, '$bank_assessor_name', '$bank_name',
                    $bank_pawn_value, $bank_interest, $bank_duration_sql, $bank_additional_charges, '$location',
                    $bank_paid_interest_amount, $bank_recovery_pawn_amount
                )
            ";

            if ($conn->query($sql) === TRUE) {
                $id = $conn->insert_id;
                $uniqueRecoveryID = uniqueID('recovery', $id);

                $updateStmt = $conn->prepare("UPDATE `pawnjewelry_recovery` SET `pawnjewelry_recovery_id` = ? WHERE `id` = ?");
                $updateStmt->bind_param("si", $uniqueRecoveryID, $id);
                $updateStmt->execute();
                $updateStmt->close();

                // Update pawnjewelry status
                $statusStmt = $conn->prepare("UPDATE `pawnjewelry` SET `status` = 'நகை மீட்கபட்டது' WHERE `receipt_no` = ? AND `delete_at` = 0");
                $statusStmt->bind_param("s", $receipt_no);
                if (!$statusStmt->execute()) {
                    error_log("Failed to update pawnjewelry status: " . $statusStmt->error);
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Recovery record added, but failed to update pawn status.";
                    echo json_encode($output, JSON_NUMERIC_CHECK);
                    exit();
                }
                $statusStmt->close();

                // Transactions
                addTransaction($conn, $name, $refund_amount, $type, $pawnjewelry_recovery_date);
                if ($other_amount > 0) addTransaction($conn, $name, $other_amount, $type, $pawnjewelry_recovery_date);
                if ($bank_recovery_pawn_amount > 0) addTransaction($conn, $name, $bank_recovery_pawn_amount, $type, $pawnjewelry_recovery_date);
                if ($bank_paid_interest_amount > 0) addTransaction($conn, $name, $bank_paid_interest_amount, $type, $pawnjewelry_recovery_date);
                if ($bank_additional_charges > 0) addTransaction($conn, $name, $bank_additional_charges, $type, $pawnjewelry_recovery_date);

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Recovery record added successfully and pawn status updated";
            } else {
                error_log("Insert failed: " . $conn->error);
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to add. Error: " . $conn->error;
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Receipt number already Jewel Recovered exists.";
        }
    }
    // ----------------------------
    // 🟢 Update (Edit Existing)
    // ----------------------------
    else {
        $updateStmt = $conn->prepare("UPDATE `pawnjewelry_recovery` SET 
            `pawnjewelry_date` = ?, `receipt_no` = ?, `name` = ?, `customer_details` = ?, `place` = ?, 
            `mobile_number` = ?, `original_amount` = ?, `interest_rate` = ?, `jewel_product` = ?, 
            `interest_income` = ?, `refund_amount` = ?, `other_amount` = ?, `pawnjewelry_recovery_date` = ?, 
            `interest_payment_periods` = ?, `bank_pledge_date` = ?, `bank_assessor_name` = ?, 
            `bank_name` = ?, `bank_pawn_value` = ?, `bank_interest` = ?, `bank_duration` = ?, 
            `bank_additional_charges` = ?, `location` = ?, `bank_paid_interest_amount` = ?, 
            `bank_recovery_pawn_amount` = ?
            WHERE `pawnjewelry_recovery_id` = ?");
        $updateStmt->bind_param(
            "ssssssdssdsssssssssddss",
            $pawnjewelry_date,
            $receipt_no,
            $name,
            $customer_details,
            $place,
            $mobile_number,
            $original_amount,
            $interest_rate,
            $products_json,
            $interest_income,
            $refund_amount,
            $other_amount,
            $pawnjewelry_recovery_date,
            $interest_payment_periods,
            $bank_pledge_date,
            $bank_assessor_name,
            $bank_name,
            $bank_pawn_value,
            $bank_interest,
            $bank_duration,
            $bank_additional_charges,
            $location,
            $bank_paid_interest_amount,
            $bank_recovery_pawn_amount,
            $edit_id
        );

        if ($updateStmt->execute()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Recovery record updated successfully";
        } else {
            error_log("Update failed: " . $updateStmt->error);
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to update. Error: " . $updateStmt->error;
        }
        $updateStmt->close();
    }
}


// <<<<<<<<<<===================== Delete Recovery Record =====================>>>>>>>>>>  
else if (isset($obj->delete_pawn_recovery_id)) {
    $delete_pawn_recovery_id = $obj->delete_pawn_recovery_id;

    if (!empty($delete_pawn_recovery_id)) {
        $deleteRecovery = "UPDATE `pawnjewelry_recovery` SET `delete_at` = 1 WHERE `pawnjewelry_recovery_id` = '$delete_pawn_recovery_id'";
        if ($conn->query($deleteRecovery)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Recovery record deleted successfully";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete. Please try again.";
        }
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
