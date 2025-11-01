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

// <<<<<<<<<<===================== This is to list bank_pledge_details =====================>>>>>>>>>>
if (isset($obj->search_text)) {

    $search_text = $obj->search_text;
    $sql = "SELECT * FROM `bank_pledge_details` WHERE `delete_at` = 0 AND (`customer_no` LIKE '%$search_text%' OR `receipt_no` LIKE '%$search_text%' OR `bank_loan_no` LIKE '%$search_text%') ORDER BY `id` DESC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["bank_pledge_details"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "bank_pledge_details records not found";
        $output["body"]["bank_pledge_details"] = [];
    }
}
// <<<<<<<<<<===================== This is to Create and Edit bank_pledge_details =====================>>>>>>>>>>
else if (isset($obj->customer_no) && isset($obj->receipt_no) && isset($obj->bank_pledge_date)) {

    $customer_no = $obj->customer_no;
    $receipt_no = $obj->receipt_no;
    $bank_pledge_date = $obj->bank_pledge_date;
    $bank_loan_no = isset($obj->bank_loan_no) ? $obj->bank_loan_no : '';
    $bank_assessor_name = isset($obj->bank_assessor_name) ? $obj->bank_assessor_name : '';
    $bank_name = isset($obj->bank_name) ? $obj->bank_name : '';
    $bank_pawn_value = !empty($obj->bank_pawn_value) ? $obj->bank_pawn_value : 0;
    $bank_interest = !empty($obj->bank_interest) ? $obj->bank_interest : 0;
    $bank_due_date_raw = isset($obj->bank_due_date) ? $obj->bank_due_date : '';
    $closing_date_raw = isset($obj->closing_date) ? $obj->closing_date : '';
    $closing_amount = !empty($obj->closing_amount) ? $obj->closing_amount : 0;

    // Fix: Prepare SQL-safe values for dates (NULL if empty)

    $bank_due_date = !empty($bank_due_date_raw) ? "'$bank_due_date_raw'" : 'NULL';
    $closing_date = !empty($closing_date_raw) ? "'$closing_date_raw'" : 'NULL';

    // Fetch customer_id from customer table using customer_no
    $customerQuery = $conn->query("SELECT `customer_id` as customer_id FROM `customer` WHERE `customer_no` = '$customer_no' AND `delete_at` = 0");
    if ($customerQuery->num_rows == 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Customer not found with given customer_no.";
    } else {
        $customerRow = $customerQuery->fetch_assoc();
        $customer_id = $customerRow['customer_id'];

        // Fetch pawnjewelry_id from pawnjewelry table using receipt_no and customer_no
        $pawnQuery = $conn->query("SELECT `pawnjewelry_id` as pawnjewelry_id FROM `pawnjewelry` WHERE `receipt_no` = '$receipt_no' AND `customer_no` = '$customer_no' AND `delete_at` = 0");
        if ($pawnQuery->num_rows == 0) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Pawn jewelry not found with given receipt_no and customer_no.";
        } else {
            $pawnRow = $pawnQuery->fetch_assoc();
            $pawnjewelry_id = $pawnRow['pawnjewelry_id'];

            if (isset($obj->edit_bank_pledge_id)) {
                $edit_id = $obj->edit_bank_pledge_id;

                // Fix: Use prepared date values in UPDATE
                $updatePledge = "UPDATE `bank_pledge_details` SET `customer_no`='$customer_no',`receipt_no`='$receipt_no',`customer_id`='$customer_id',`pawnjewelry_id`='$pawnjewelry_id',`bank_pledge_date`='$bank_pledge_date',`bank_loan_no`='$bank_loan_no',`bank_assessor_name`='$bank_assessor_name',`bank_name`='$bank_name',`bank_pawn_value`='$bank_pawn_value',`bank_interest`='$bank_interest',`bank_due_date`=$bank_due_date,`closing_date`=$closing_date,`closing_amount`='$closing_amount' WHERE `bank_pledge_details_id`='$edit_id'";

                if ($conn->query($updatePledge)) {
                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully bank pledge details Updated";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to update. Please try again.";
                }
            } else {
                // Check if already exists with same customer_no and receipt_no
                $checkPledge = $conn->query("SELECT `id` FROM `bank_pledge_details` WHERE `customer_no`='$customer_no' AND `receipt_no`='$receipt_no' AND `delete_at` = 0");
                if ($checkPledge->num_rows == 0) {

                    // Fix: Use prepared date values in INSERT (no quotes around NULL)
                    $createPledge = "INSERT INTO `bank_pledge_details`(`customer_no`,`receipt_no`,`customer_id`,`pawnjewelry_id`,`bank_pledge_date`,`bank_loan_no`,`bank_assessor_name`,`bank_name`,`bank_pawn_value`,`bank_interest`,`bank_due_date`,`closing_date`,`closing_amount`,`create_at`, `delete_at`) VALUES ('$customer_no','$receipt_no','$customer_id','$pawnjewelry_id','$bank_pledge_date','$bank_loan_no','$bank_assessor_name','$bank_name','$bank_pawn_value','$bank_interest',$bank_due_date,$closing_date,'$closing_amount','$timestamp','0')";
                    if ($conn->query($createPledge)) {
                        $id = $conn->insert_id;
                        $enId = uniqueID('bank_pledge_details', $id);

                        $updatePledgeId = "UPDATE `bank_pledge_details` SET `bank_pledge_details_id` ='$enId' WHERE `id`='$id'";
                        $conn->query($updatePledgeId);

                        $output["head"]["code"] = 200;
                        $output["head"]["msg"] = "Successfully bank pledge details Created";
                    } else {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "Failed to create. Please try again.";
                    }
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Bank pledge details already exist for this customer_no and receipt_no.";
                }
            }
        }
    }
}

// <<<<<<<<<<===================== This is to Delete the bank_pledge_details =====================>>>>>>>>>>
else if (isset($obj->delete_bank_pledge_id)) {

    $delete_bank_pledge_id = $obj->delete_bank_pledge_id;

    if (!empty($delete_bank_pledge_id)) {

        $deletePledge = "UPDATE `bank_pledge_details` SET `delete_at`= 1  WHERE `bank_pledge_details_id`='$delete_bank_pledge_id'";
        if ($conn->query($deletePledge)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Bank pledge details Deleted Successfully.!";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete. Please try again.";
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
