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

// <<<<<<<<<<===================== This is to List Actions =====================>>>>>>>>>>
if (isset($obj->search_text)) {
    $search_text = $conn->real_escape_string($obj->search_text); // Sanitize input
    $sql = "SELECT * FROM `action` 
            WHERE `delete_at` = 0 
            AND (`name` LIKE '%$search_text%' 
                 OR `receipt_no` LIKE '%$search_text%' 
                 OR `mobile_number` LIKE '%$search_text%') 
            ORDER BY `id` ASC";

    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["action"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No actions found";
        $output["body"]["action"] = [];
    }
}

// <<<<<<<<<<===================== This is to Create or Update Action =====================>>>>>>>>>>
elseif (isset($obj->edit_action_id)) {
    $edit_id = $conn->real_escape_string($obj->edit_action_id);
    $action_date = $conn->real_escape_string($obj->action_date);
    $receipt_no = $conn->real_escape_string($obj->receipt_no);
    $name = $conn->real_escape_string($obj->name);
    $customer_details = $conn->real_escape_string($obj->customer_details ?? '');
    $place = $conn->real_escape_string($obj->place ?? '');
    $original_amount = $conn->real_escape_string($obj->original_amount ?? '');
    $mobile_number = $conn->real_escape_string($obj->mobile_number ?? '');
    $jewel_product = $conn->real_escape_string($obj->jewel_product ?? '[]');

    $updateAction = "UPDATE `action` SET 
                     `action_date`='$action_date', 
                     `receipt_no`='$receipt_no', 
                     `name`='$name', 
                     `customer_details`='$customer_details', 
                     `place`='$place', 
                      `original_amount`='$original_amount', 
                     `mobile_number`='$mobile_number',
                     `jewel_product`='$jewel_product' 
                     WHERE `action_id`='$edit_id'";
    if ($conn->query($updateAction)) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Action updated successfully";
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to update action. Please try again.";
    }
} elseif (isset($obj->action_date) && isset($obj->receipt_no) && isset($obj->name) && isset($obj->mobile_number)) {
    // Create Action
    $action_date = $conn->real_escape_string($obj->action_date);
    $receipt_no = $conn->real_escape_string($obj->receipt_no);
    $name = $conn->real_escape_string($obj->name);
    $customer_details = $conn->real_escape_string($obj->customer_details ?? '');
    $place = $conn->real_escape_string($obj->place ?? '');
    $original_amount = $conn->real_escape_string($obj->original_amount ?? '');
    $mobile_number = $conn->real_escape_string($obj->mobile_number ?? '');
    $jewel_product = $conn->real_escape_string($obj->jewel_product ?? '[]');

    // Check if the action already exists (using receipt_no as unique identifier)
    $actionCheck = $conn->query("SELECT `id` FROM `action` WHERE `receipt_no`='$receipt_no' AND `delete_at` = 0");
    if ($actionCheck->num_rows == 0) {
        // Insert new action
        $createAction = "INSERT INTO `action`(
            `action_date`, `receipt_no`, `name`, 
            `customer_details`, `place`, `original_amount`, `mobile_number`, 
            `jewel_product`, `create_at`, `delete_at`
        ) VALUES (
            '$action_date', '$receipt_no', '$name',
            '$customer_details', '$place', '$original_amount', '$mobile_number',
            '$jewel_product', '$timestamp', '0'
        )";
        if ($conn->query($createAction)) {
            // Get the auto-increment ID of the newly inserted row
            $id = $conn->insert_id;

            // Generate a unique ID for the action
            $uniqueActionID = uniqueID('action', $id);

            // Update the `action_id` field with the unique ID
            $updateActionId = "UPDATE `action` SET `action_id`='$uniqueActionID' WHERE `id`='$id'";
            $conn->query($updateActionId);

            // Respond with success
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Action created successfully";
        } else {
            // Handle query failure
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to create action. Please try again.";
        }
    } else {
        // If the action already exists
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Action with this receipt number already exists.";
    }
}

// <<<<<<<<<<===================== This is to Delete Action =====================>>>>>>>>>>
else if (isset($obj->delete_action_id)) {
    $delete_action_id = $conn->real_escape_string($obj->delete_action_id);

    if (!empty($delete_action_id)) {
        $deleteAction = "UPDATE `action` SET `delete_at`=1 WHERE `action_id`='$delete_action_id'";
        if ($conn->query($deleteAction)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Action deleted successfully";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete action. Please try again.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide a valid action ID.";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
