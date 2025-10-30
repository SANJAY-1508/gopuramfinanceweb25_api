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
    $sql = "SELECT `id`, `pawnjewelry_recovery_id`, `receipt_no`, `pawnjewelry_date`, `name`, `customer_details`, `place`, `mobile_number`, `original_amount`, `interest_rate`, `jewel_product`, `interest_income`, `refund_amount`, `other_amount`, `interest_paid`, `pawnjewelry_recovery_date`, `status`, `interest_payment_periods`, `proof_base64code`, `delete_at`, `create_at` FROM `pawnjewelry_recovery`
WHERE 
    `delete_at` = 0
    AND (`receipt_no` LIKE '%$search_text%' OR `customer_details` LIKE '%$search_text%')

ORDER BY 
    `id` ASC
";

    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        while ($row = $result->fetch_assoc()) {
            $output["body"]["pawnrecovery"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No records found";
        $output["body"]["pawnrecovery"] = [];
    }
}

// <<<<<<<<<<===================== Create or Update Recovery Record =====================>>>>>>>>>>

elseif (isset($obj->receipt_no)) {

    if (!isset($obj->login_id) || empty(trim($obj->login_id))) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Login ID is required";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }

    $login_id = $conn->real_escape_string(trim($obj->login_id));
    $by_name = isset($obj->user_name) ? $conn->real_escape_string(trim($obj->user_name)) : '';
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
    $interest_income = floatval($obj->interest_income ?? 0);
    $refund_amount = floatval($obj->refund_amount);
    $other_amount = floatval($obj->other_amount);
    $pawnjewelry_recovery_date = $conn->real_escape_string($obj->pawnjewelry_recovery_date);
    $interest_payment_periods = $conn->real_escape_string($obj->interest_payment_periods);
    $interest_paid = floatval($obj->interest_paid);

    $type = "varavu";
    $timestamp = date('Y-m-d H:i:s');

    // Fetch customer_no from pawnjewelry
    $customer_no_stmt = $conn->prepare("SELECT customer_no FROM pawnjewelry WHERE receipt_no = ? AND delete_at = 0");
    $customer_no_stmt->bind_param("s", $receipt_no);
    $customer_no_stmt->execute();
    $customer_no_result = $customer_no_stmt->get_result();
    $customer_no = '';
    if ($customer_no_row = $customer_no_result->fetch_assoc()) {
        $customer_no = $customer_no_row['customer_no'];
    }
    $customer_no_stmt->close();


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
            $sql = "
                INSERT INTO `pawnjewelry_recovery` (
                    `pawnjewelry_date`, `receipt_no`, `name`, `customer_details`, `place`, `mobile_number`, 
                    `original_amount`, `interest_rate`, `jewel_product`, `interest_income`, `refund_amount`, 
                    `pawnjewelry_recovery_date`, `interest_payment_periods`, `create_at`, `delete_at`, 
                    `other_amount`, `interest_paid`, `status`, `proof_base64code`
                ) VALUES (
                    '$pawnjewelry_date', '$receipt_no', '$name', '$customer_details', '$place', '$mobile_number',
                    $original_amount, $interest_rate, '$products_json', $interest_income, $refund_amount,
                    '$pawnjewelry_recovery_date', '$interest_payment_periods', '$timestamp', 0,
                    $other_amount, $interest_paid, 'நகை மீட்கபட்டது', ''
                )
            ";

            if ($conn->query($sql) === TRUE) {
                $id = $conn->insert_id;
                $uniqueRecoveryID = uniqueID('recovery', $id);

                $updateStmt = $conn->prepare("UPDATE `pawnjewelry_recovery` SET `pawnjewelry_recovery_id` = ? WHERE `id` = ?");
                $updateStmt->bind_param("si", $uniqueRecoveryID, $id);
                $updateStmt->execute();
                $updateStmt->close();

                // Fetch the new row after insert and set pawnjewelry_recovery_id
                $new_json = null;
                $sql_new = "SELECT * FROM `pawnjewelry_recovery` WHERE `pawnjewelry_recovery_id` = ? AND `delete_at` = 0";
                $stmt_new = $conn->prepare($sql_new);
                $stmt_new->bind_param("s", $uniqueRecoveryID);
                $stmt_new->execute();
                $new_result = $stmt_new->get_result();
                if ($new_row = $new_result->fetch_assoc()) {
                    $new_json = json_encode($new_row);
                }
                $stmt_new->close();

                // Log to history
                $remarks = "Recovery created";
                logCustomerHistory($conn, $uniqueRecoveryID, $customer_no, "create", null, $new_json, $remarks, $login_id, $by_name);

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
        // Fetch customer_no from pawnjewelry
        $customer_no_stmt = $conn->prepare("SELECT customer_no FROM pawnjewelry WHERE receipt_no = ? AND delete_at = 0");
        $customer_no_stmt->bind_param("s", $receipt_no);
        $customer_no_stmt->execute();
        $customer_no_result = $customer_no_stmt->get_result();
        $customer_no = '';
        if ($customer_no_row = $customer_no_result->fetch_assoc()) {
            $customer_no = $customer_no_row['customer_no'];
        }
        $customer_no_stmt->close();

        // Fetch old row for history
        $old_json = null;
        $sql_old = "SELECT * FROM `pawnjewelry_recovery` WHERE `pawnjewelry_recovery_id` = ? AND `delete_at` = 0";
        $stmt_old = $conn->prepare($sql_old);
        $stmt_old->bind_param("s", $edit_id);
        $stmt_old->execute();
        $old_result = $stmt_old->get_result();
        if ($old_row = $old_result->fetch_assoc()) {
            $old_json = json_encode($old_row);
        }
        $stmt_old->close();

        $updateStmt = $conn->prepare("UPDATE `pawnjewelry_recovery` SET 
            `pawnjewelry_date` = ?, `receipt_no` = ?, `name` = ?, `customer_details` = ?, `place` = ?, 
            `mobile_number` = ?, `original_amount` = ?, `interest_rate` = ?, `jewel_product` = ?, 
            `interest_income` = ?, `refund_amount` = ?, `other_amount` = ?, `pawnjewelry_recovery_date` = ?, 
            `interest_payment_periods` = ?, `interest_paid` = ?
            WHERE `pawnjewelry_recovery_id` = ?");
        $updateStmt->bind_param(
            "ssssssdssdddssds",
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
            $interest_paid,
            $edit_id
        );

        if ($updateStmt->execute()) {
            // Fetch new row after update
            $new_json = null;
            $sql_new = "SELECT * FROM `pawnjewelry_recovery` WHERE `pawnjewelry_recovery_id` = ? AND `delete_at` = 0";
            $stmt_new = $conn->prepare($sql_new);
            $stmt_new->bind_param("s", $edit_id);
            $stmt_new->execute();
            $new_result = $stmt_new->get_result();
            if ($new_row = $new_result->fetch_assoc()) {
                $new_json = json_encode($new_row);
            }
            $stmt_new->close();

            // Log to history
            $remarks = "Recovery updated";
            logCustomerHistory($conn, $edit_id, $customer_no, "update", $old_json, $new_json, $remarks, $login_id, $by_name);

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
    if (!isset($obj->login_id) || empty(trim($obj->login_id))) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Login ID is required";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }
    $login_id = $conn->real_escape_string(trim($obj->login_id));
    $by_name = isset($obj->user_name) ? $conn->real_escape_string(trim($obj->user_name)) : '';

    $delete_pawn_recovery_id = $obj->delete_pawn_recovery_id;

    if (!empty($delete_pawn_recovery_id)) {
        // Get the receipt_no from the recovery record
        $getReceiptStmt = $conn->prepare("SELECT `receipt_no` FROM `pawnjewelry_recovery` WHERE `pawnjewelry_recovery_id` = ? AND `delete_at` = 0");
        $getReceiptStmt->bind_param("s", $delete_pawn_recovery_id);
        $getReceiptStmt->execute();
        $result = $getReceiptStmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $receipt_no = $row['receipt_no'];

            // Fetch customer_no from pawnjewelry
            $customer_no_stmt = $conn->prepare("SELECT customer_no FROM pawnjewelry WHERE receipt_no = ? AND delete_at = 0");
            $customer_no_stmt->bind_param("s", $receipt_no);
            $customer_no_stmt->execute();
            $customer_no_result = $customer_no_stmt->get_result();
            $customer_no = '';
            if ($customer_no_row = $customer_no_result->fetch_assoc()) {
                $customer_no = $customer_no_row['customer_no'];
            }
            $customer_no_stmt->close();

            // Fetch old row for history
            $old_json = null;
            $sql_old = "SELECT * FROM `pawnjewelry_recovery` WHERE `pawnjewelry_recovery_id` = ? AND `delete_at` = 0";
            $stmt_old = $conn->prepare($sql_old);
            $stmt_old->bind_param("s", $delete_pawn_recovery_id);
            $stmt_old->execute();
            $old_result = $stmt_old->get_result();
            if ($old_row = $old_result->fetch_assoc()) {
                $old_json = json_encode($old_row);
            }
            $stmt_old->close();

            // Soft delete the recovery
            $deleteRecovery = "UPDATE `pawnjewelry_recovery` SET `delete_at` = 1 WHERE `pawnjewelry_recovery_id` = '$delete_pawn_recovery_id'";
            if ($conn->query($deleteRecovery)) {
                // Log to history
                $remarks = "Recovery deleted";
                logCustomerHistory($conn, $delete_pawn_recovery_id, $customer_no, "delete", $old_json, null, $remarks, $login_id, $by_name);

                // Update pawnjewelry status back to 'நகை மீட்கபடவில்லை'
                $statusUpdateStmt = $conn->prepare("UPDATE `pawnjewelry` SET `status` = 'நகை மீட்கபடவில்லை' WHERE `receipt_no` = ? AND `delete_at` = 0");
                $statusUpdateStmt->bind_param("s", $receipt_no);
                if ($statusUpdateStmt->execute()) {
                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Recovery record deleted successfully and pawn status updated";
                } else {
                    error_log("Failed to update pawn status after deletion: " . $statusUpdateStmt->error);
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Recovery deleted, but failed to update pawn status.";
                }
                $statusUpdateStmt->close();
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to delete recovery record. Please try again.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Recovery record not found.";
        }
        $getReceiptStmt->close();
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
