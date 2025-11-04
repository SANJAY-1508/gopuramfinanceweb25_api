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


// <<<<<<<<<<===================== This is to list bank_pledger_details =====================>>>>>>>>>>
if (isset($obj->search_text)) {

    $search_text = $obj->search_text;
    $sql = "SELECT * FROM `bank_pledger_details` WHERE `delete_at` = 0 AND `name` LIKE '%$search_text%' ORDER BY `id` DESC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["pledger"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "pledger records not found";
        $output["body"]["pledger"] = [];
    }
}

// <<<<<<<<<<===================== This is to Create and Edit bank_pledger_details =====================>>>>>>>>>>
else if (isset($obj->name)) {

    $name = $obj->name;
    $mobile_no = isset($obj->mobile_no) ? $obj->mobile_no : null;
    $address = isset($obj->address) ? $obj->address : null;
    $bank_details = isset($obj->bank_details) ? $obj->bank_details : null;

    if (isset($obj->edit_name)) {
        $edit_id = $obj->edit_name;

        $updatePledger = "UPDATE `bank_pledger_details` SET `name`='$name', `mobile_no`='$mobile_no', `address`='$address', `bank_details`='$bank_details' WHERE `bank_pledger_details_id`='$edit_id'";

        if ($conn->query($updatePledger)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully Pledger Details Updated";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to connect. Please try again.";
        }
    } else {
        $pledgerCheck = $conn->query("SELECT `id` FROM `bank_pledger_details` WHERE `name`='$name' AND delete_at = 0");
        if ($pledgerCheck->num_rows == 0) {

            $createPledger = "INSERT INTO `bank_pledger_details`(`name`, `mobile_no`, `address`, `bank_details`, `create_at`, `delete_at`) VALUES ('$name', '$mobile_no', '$address', '$bank_details', '$timestamp', '0')";
            if ($conn->query($createPledger)) {
                $id = $conn->insert_id;
                $enId = uniqueID('bank_pledger_details', $id);

                $updatePledgerId = "update `bank_pledger_details` SET bank_pledger_details_id ='$enId' WHERE `id`='$id'";
                $conn->query($updatePledgerId);

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully Pledger Created";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to connect. Please try again.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Pledger Name Already Exist.";
        }
    }
}


// <<<<<<<<<<===================== This is to Delete the bank_pledger_details =====================>>>>>>>>>>
else if (isset($obj->delete_bank_pledger_details_id)) {

    $delete_bank_pledger_details_id = $obj->delete_bank_pledger_details_id;


    if (!empty($delete_bank_pledger_details_id)) {

        $deletePledger = "UPDATE `bank_pledger_details` SET `delete_at`=1  WHERE `bank_pledger_details_id`='$delete_bank_pledger_details_id'";
        if ($conn->query($deletePledger)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Pledger Deleted Successfully.!";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to connect. Please try again.";
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