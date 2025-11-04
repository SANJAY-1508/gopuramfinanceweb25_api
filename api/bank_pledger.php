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


// <<<<<<<<<<===================== This is to list bank_pledger =====================>>>>>>>>>>
if (isset($obj->search_text)) {

    $search_text = $obj->search_text;
    $sql = "SELECT * FROM `bank_pledger` WHERE `delete_at` = 0 AND `name` LIKE '%$search_text%' ORDER BY `id` DESC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["bank_pledger"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "bank_pledger records not found";
        $output["body"]["bank_pledger"] = [];
    }
}

// <<<<<<<<<<===================== This is to Create and Edit bank_pledger =====================>>>>>>>>>>
else if (isset($obj->name)) {

    $bank_pledger_details_id = isset($obj->bank_pledger_details_id) ? $obj->bank_pledger_details_id : null;
    $name = $obj->name;
    $mobile_no = isset($obj->mobile_no) ? $obj->mobile_no : null;
    $address = isset($obj->address) ? $obj->address : null;
    $bank_details = isset($obj->bank_details) ? $obj->bank_details : null;
    $pledge_date = isset($obj->pledge_date) ? $obj->pledge_date : null;
    $bank_loan_no = isset($obj->bank_loan_no) ? $obj->bank_loan_no : null;
    $pawn_value = isset($obj->pawn_value) ? $obj->pawn_value : null;
    $interest_rate = isset($obj->interest_rate) ? $obj->interest_rate : null;
    $duration_month = isset($obj->duration_month) ? $obj->duration_month : null;
    $interest_amount = isset($obj->interest_amount) ? $obj->interest_amount : null;
    $pledge_due_date = isset($obj->pledge_due_date) ? $obj->pledge_due_date : null;
    $additional_charges = isset($obj->additional_charges) ? $obj->additional_charges : null;

    if (isset($obj->edit_bank_pledger_id)) {
        $edit_id = $obj->edit_bank_pledger_id;

        $updateBankPledger = "UPDATE `bank_pledger` SET `bank_pledger_details_id`='$bank_pledger_details_id', `name`='$name', `mobile_no`='$mobile_no', `address`='$address', `bank_details`='$bank_details', `pledge_date`='$pledge_date', `bank_loan_no`='$bank_loan_no', `pawn_value`='$pawn_value', `interest_rate`='$interest_rate', `duration_month`='$duration_month', `interest_amount`='$interest_amount', `pledge_due_date`='$pledge_due_date', `additional_charges`='$additional_charges' WHERE `bank_pledge_id`='$edit_id'";

        if ($conn->query($updateBankPledger)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully bank_pledger Details Updated";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to connect. Please try again.";
        }
    } else {
        $bankCheck = $conn->query("SELECT `id` FROM `bank_pledger` WHERE `bank_loan_no`='$bank_loan_no' AND delete_at = 0");
        if ($bankCheck->num_rows == 0) {

            // Validation for creation: Check account_limit and pledge_count_limit
            $validationPassed = false;
            $validationMsg = "";
            if ($bank_pledger_details_id && $bank_details && $pawn_value) {
                // Fetch current bank_pledger_details
                $detailsQuery = "SELECT `bank_details` FROM `bank_pledger_details` WHERE `bank_pledger_details_id`='$bank_pledger_details_id' AND `delete_at` = 0";
                $detailsResult = $conn->query($detailsQuery);
                if ($detailsResult->num_rows > 0) {
                    $detailsRow = $detailsResult->fetch_assoc();
                    $current_bank_details = json_decode($detailsRow['bank_details'], true);
                    
                    $new_bank_details_array = json_decode($bank_details, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($current_bank_details) && is_array($new_bank_details_array) && count($new_bank_details_array) > 0) {
                        // Assuming only one bank is selected (as per React code)
                        $selected_bank_id = $new_bank_details_array[0]['bank_id'];
                        $selected_bank = null;
                        foreach ($current_bank_details as $bank) {
                            if ($bank['bank_id'] === $selected_bank_id) {
                                $selected_bank = $bank;
                                break;
                            }
                        }
                        if ($selected_bank) {
                            $int_pawn_value = (int)$pawn_value;
                            $int_account_limit = (int)$selected_bank['account_limit'];
                            $int_pledge_count = (int)$selected_bank['pledge_count_limit'];
                            if ($int_pawn_value <= $int_account_limit && $int_pledge_count >= 1) {
                                $validationPassed = true;
                            } else {
                                if ($int_pawn_value > $int_account_limit) {
                                    $validationMsg = "Pawn value exceeds account limit. Pawn value: $int_pawn_value, Account limit: $int_account_limit.";
                                } else {
                                    $validationMsg = "Pledge count limit must be at least 1. Current: $int_pledge_count.";
                                }
                            }
                        } else {
                            $validationMsg = "Selected bank not found in pledger details.";
                        }
                    } else {
                        $validationMsg = "Invalid bank details format.";
                    }
                } else {
                    $validationMsg = "Pledger details not found.";
                }
            } else {
                $validationMsg = "Missing required parameters for validation.";
            }

            if (!$validationPassed) {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = $validationMsg ?: "Validation failed.";
            } else {

                $createBankPledger = "INSERT INTO `bank_pledger`(`bank_pledger_details_id`, `name`, `mobile_no`, `address`, `bank_details`, `pledge_date`, `bank_loan_no`, `pawn_value`, `interest_rate`, `duration_month`, `interest_amount`, `pledge_due_date`, `additional_charges`, `create_at`, `delete_at`) VALUES ('$bank_pledger_details_id', '$name', '$mobile_no', '$address', '$bank_details', '$pledge_date', '$bank_loan_no', '$pawn_value', '$interest_rate', '$duration_month', '$interest_amount', '$pledge_due_date', '$additional_charges', '$timestamp', '0')";
                if ($conn->query($createBankPledger)) {
                    $id = $conn->insert_id;
                    $enId = uniqueID('bank_pledger', $id);

                    $updateBankPledgerId = "update `bank_pledger` SET bank_pledge_id ='$enId' WHERE `id`='$id'";
                    $conn->query($updateBankPledgerId);

                    // Update bank_pledger_details: Reduce account_limit by pawn_value and decrement pledge_count_limit by 1 for the selected bank
                    if ($bank_pledger_details_id && $bank_details && $pawn_value) {
                        $bank_details_array = json_decode($bank_details, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($bank_details_array) && count($bank_details_array) > 0) {
                            // Assuming only one bank is selected (as per React code)
                            $selected_bank_id = $bank_details_array[0]['bank_id'];
                            $current_bank_details = json_decode($detailsRow['bank_details'], true); // Reuse from validation
                            $updated_banks = $current_bank_details;
                            foreach ($updated_banks as &$bank) {
                                if ($bank['bank_id'] === $selected_bank_id) {
                                    $bank['account_limit'] = (int)$bank['account_limit'] - (int)$pawn_value;
                                    $bank['pledge_count_limit'] = (int)$bank['pledge_count_limit'] - 1;
                                    break;
                                }
                            }
                            $new_bank_details = json_encode($updated_banks);

                            $updateDetails = "UPDATE `bank_pledger_details` SET `bank_details`='$new_bank_details' WHERE `bank_pledger_details_id`='$bank_pledger_details_id'";
                            $conn->query($updateDetails);
                        }
                    }

                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully bank_pledger Created";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to connect. Please try again.";
                }
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "bank_loan_no Already Exist.";
        }
    }
}


// <<<<<<<<<<===================== This is to Delete the bank_pledgers =====================>>>>>>>>>>
else if (isset($obj->delete_bank_pledger_id)) {

    $delete_bank_pledger_id = $obj->delete_bank_pledger_id;

    if (!empty($delete_bank_pledger_id)) {

        // Fetch the bank_pledger record to get necessary data
        $pledgerQuery = "SELECT `bank_pledger_details_id`, `bank_details`, `pawn_value` FROM `bank_pledger` WHERE `bank_pledge_id`='$delete_bank_pledger_id' AND `delete_at` = 0";
        $pledgerResult = $conn->query($pledgerQuery);
        if ($pledgerResult->num_rows > 0) {
            $pledgerRow = $pledgerResult->fetch_assoc();
            $bank_pledger_details_id = $pledgerRow['bank_pledger_details_id'];
            $bank_details_json = $pledgerRow['bank_details'];
            $pawn_value = $pledgerRow['pawn_value'];

            $new_bank_details_array = json_decode($bank_details_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($new_bank_details_array) && count($new_bank_details_array) > 0) {
                $selected_bank_id = $new_bank_details_array[0]['bank_id'];

                // Fetch current bank_pledger_details
                $detailsQuery = "SELECT `bank_details` FROM `bank_pledger_details` WHERE `bank_pledger_details_id`='$bank_pledger_details_id' AND `delete_at` = 0";
                $detailsResult = $conn->query($detailsQuery);
                if ($detailsResult->num_rows > 0) {
                    $detailsRow = $detailsResult->fetch_assoc();
                    $current_bank_details = json_decode($detailsRow['bank_details'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($current_bank_details)) {
                        $updated_banks = $current_bank_details;
                        foreach ($updated_banks as &$bank) {
                            if ($bank['bank_id'] === $selected_bank_id) {
                                $bank['account_limit'] = (int)$bank['account_limit'] + (int)$pawn_value;
                                $bank['pledge_count_limit'] = (int)$bank['pledge_count_limit'] + 1;
                                break;
                            }
                        }
                        $new_bank_details = json_encode($updated_banks);

                        $updateDetails = "UPDATE `bank_pledger_details` SET `bank_details`='$new_bank_details' WHERE `bank_pledger_details_id`='$bank_pledger_details_id'";
                        if ($conn->query($updateDetails)) {
                            // Now soft delete the bank_pledger
                            $deleteBankPledger = "UPDATE `bank_pledger` SET `delete_at`=1  WHERE `bank_pledge_id`='$delete_bank_pledger_id'";
                            if ($conn->query($deleteBankPledger)) {
                                $output["head"]["code"] = 200;
                                $output["head"]["msg"] = "bank_pledger Deleted Successfully.!";
                            } else {
                                $output["head"]["code"] = 400;
                                $output["head"]["msg"] = "Failed to delete bank_pledger.";
                            }
                        } else {
                            $output["head"]["code"] = 400;
                            $output["head"]["msg"] = "Failed to update bank details.";
                        }
                    } else {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "Invalid bank details format in pledger.";
                    }
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Pledger details not found.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Invalid bank details in pledger.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Bank pledger not found.";
        }

    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
}


echo json_encode($output, JSON_NUMERIC_CHECK);

?>