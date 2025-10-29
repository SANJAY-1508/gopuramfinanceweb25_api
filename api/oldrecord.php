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

// <<<<<<<<<<===================== This is to List Old Records =====================>>>>>>>>>>
if (isset($obj->search_text) || isset($obj->from_date) || isset($obj->to_date)) {
    $search_text = isset($obj->search_text) ? $conn->real_escape_string($obj->search_text) : "";
    $from_date = isset($obj->from_date) ? $conn->real_escape_string($obj->from_date) : null;
    $to_date = isset($obj->to_date) ? $conn->real_escape_string($obj->to_date) : null;

    $conditions = "`delete_at` = 0"; // அடிப்படை நிலைமை

    if (!empty($search_text)) {
        $conditions .= " AND (`bill_no` LIKE '%$search_text%' OR `customer_details` LIKE '%$search_text%')";
    }

    if (!empty($from_date)) {
        $conditions .= " AND DATE(`oldrecord_date`) >= '$from_date'";
    }

    if (!empty($to_date)) {
        $conditions .= " AND DATE(`oldrecord_date`) <= '$to_date'";
    }

    $sql = "SELECT * FROM `oldrecord` WHERE $conditions ORDER BY `id` ASC";

    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["records"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No records found";
        $output["body"]["records"] = [];
    }
}

// <<<<<<<<<<===================== This is to Create Old Record =====================>>>>>>>>>>
elseif ( isset($obj->oldrecord_date) && isset($obj->bill_no) && isset($obj->customer_details)  
    && isset($obj->pawn_amount) && isset($obj->interest_amount) && isset($obj->jewel_details) 
    && isset($obj->count) && isset($obj->weight) && isset($obj->amount) && isset($obj->recovery_date)) {

    $oldrecord_date = date('Y-m-d H:i:s', strtotime($obj->oldrecord_date));
    $bill_no = $obj->bill_no;
    $customer_details = $obj->customer_details;
    $pawn_amount = $obj->pawn_amount;
    $interest_amount = $obj->interest_amount;
    $jewel_details = $obj->jewel_details;
    $count = $obj->count;
    $weight = $obj->weight;
    $amount = $obj->amount;
    $recovery_date = date('Y-m-d H:i:s', strtotime($obj->recovery_date));

    // Insert record into the `oldrecord` table
    $sql = "INSERT INTO `oldrecord` ( `oldrecord_date`, `bill_no`, `customer_details`, `pawn_amount`, 
            `interest_amount`, `jewel_details`, `count`, `weight`, `amount`, `recovery_date`, `create_at`, `delete_at`) 
            VALUES ( '$oldrecord_date', '$bill_no', '$customer_details', '$pawn_amount', '$interest_amount', 
            '$jewel_details', '$count', '$weight', '$amount', '$recovery_date', '$timestamp', '0')";

    if ($conn->query($sql)) {
        // Get the auto-increment ID of the newly inserted row
        $id = $conn->insert_id;

        // Generate a unique ID for the old record
        $uniqueOldRecordID = uniqueID('oldrecord', $id);

        // Update the `oldrecord_id` field with the unique ID
        $updateOldRecordId = "UPDATE `oldrecord` SET `oldrecord_id`='$uniqueOldRecordID' WHERE `id`='$id'";
        if ($conn->query($updateOldRecordId)) {
            // Respond with success
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Old record created successfully";
        } else {
            // Handle update failure
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to update unique ID. Please try again.";
        }
    } else {
        // Handle insert failure
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to create record. Please try again.";
    }
}
 else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter mismatch or missing required fields";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
?>
