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


// <<<<<<<<<<===================== This is to list bank =====================>>>>>>>>>>
if (isset($obj->search_text)) {

    $search_text = $obj->search_text;
    $sql = "SELECT * FROM `bank` WHERE `delete_at` = 0 AND `bank_name` LIKE '%$search_text%' ORDER BY `id` DESC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["bank"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "bank records not found";
        $output["body"]["bank"] = [];
    }
}

// <<<<<<<<<<===================== This is to Create and Edit bank =====================>>>>>>>>>>
else if (isset($obj->bank_name)) {

    $bank_name = $obj->bank_name;
    $account_limit = isset($obj->account_limit) ? $obj->account_limit : null;
    $pledge_count_limit = isset($obj->pledge_count_limit) ? $obj->pledge_count_limit : null;

    if (isset($obj->edit_bank_id)) {
        $edit_id = $obj->edit_bank_id;

        $updateBank = "UPDATE `bank` SET `bank_name`='$bank_name', `account_limit`='$account_limit', `pledge_count_limit`='$pledge_count_limit' WHERE `bank_id`='$edit_id'";

        if ($conn->query($updateBank)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully bank Details Updated";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to connect. Please try again.";
        }
    } else {
        $bankCheck = $conn->query("SELECT `id` FROM `bank` WHERE `bank_name`='$bank_name' AND delete_at = 0");
        if ($bankCheck->num_rows == 0) {

            $createBank = "INSERT INTO `bank`(`bank_name`, `account_limit`, `pledge_count_limit`, `create_at`, `delete_at`) VALUES ('$bank_name', '$account_limit', '$pledge_count_limit', '$timestamp', '0')";
            if ($conn->query($createBank)) {
                $id = $conn->insert_id;
                $enId = uniqueID('bank', $id);

                $updateBankId = "update `bank` SET bank_id ='$enId' WHERE `id`='$id'";
                $conn->query($updateBankId);

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully bank Created";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to connect. Please try again.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "bank Name Already Exist.";
        }
    }
}


// <<<<<<<<<<===================== This is to Delete the banks =====================>>>>>>>>>>
else if (isset($obj->delete_bank_id)) {

    $delete_bank_id = $obj->delete_bank_id;


    if (!empty($delete_bank_id)) {

        $deleteBank = "UPDATE `bank` SET `delete_at`=1  WHERE `bank_id`='$delete_bank_id'";
        if ($conn->query($deleteBank)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "bank Deleted Successfully.!";
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