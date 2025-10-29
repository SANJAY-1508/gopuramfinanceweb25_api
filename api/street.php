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

// <<<<<<<<<<===================== This is to List Streets =====================>>>>>>>>>>
if (isset($obj->search_text)) {
    $search_text = $conn->real_escape_string($obj->search_text); // Sanitize input
    $sql = "SELECT * FROM `street` 
            WHERE `delete_at` = 0 
            AND (`street_eng` LIKE '%$search_text%' OR `street_tam` LIKE '%$search_text%') 
            ORDER BY `id` ASC";

    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["street"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No streets found";
        $output["body"]["street"] = [];
    }
}


// <<<<<<<<<<===================== This is to Create or Update Street =====================>>>>>>>>>>
elseif (isset($obj->edit_street_id)) {
    $edit_id = $obj->edit_street_id;
    $street_eng = $obj->street_eng;
    $street_tam = $obj->street_tam;

    $updateStreet = "UPDATE `street` SET `street_eng`='$street_eng', `street_tam`='$street_tam' WHERE `street_id`='$edit_id'";
    if ($conn->query($updateStreet)) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Street updated successfully";
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to update street. Please try again.";
    }
} elseif (isset($obj->street_eng) && isset($obj->street_tam)) {
    // Create Street
    $street_eng = $obj->street_eng;
    $street_tam = $obj->street_tam;

    // Check if the street already exists
    $streetCheck = $conn->query("SELECT `id` FROM `street` WHERE `street_eng`='$street_eng' AND delete_at = 0");
    if ($streetCheck->num_rows == 0) {
        // Insert new street
        $createStreet = "INSERT INTO `street`(`street_eng`, `street_tam`, `create_at`, `delete_at`) VALUES ('$street_eng','$street_tam','$timestamp','0')";
        if ($conn->query($createStreet)) {
            // Get the auto-increment ID of the newly inserted row
            $id = $conn->insert_id;

            // Generate a unique ID for the street
            $uniqueStreetID = uniqueID('street', $id);

            // Update the `street_id` field with the unique ID
            $updateStreetId = "UPDATE `street` SET `street_id`='$uniqueStreetID' WHERE `id`='$id'";
            $conn->query($updateStreetId);

            // Respond with success
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Street created successfully";
        } else {
            // Handle query failure
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to connect. Please try again.";
        }
    } else {
        // If the street already exists
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Street already exists.";
    }
}

// <<<<<<<<<<===================== This is to Delete Street =====================>>>>>>>>>>
else if (isset($obj->delete_street_id)) {
    $delete_street_id = $obj->delete_street_id;

    if (!empty($delete_street_id)) {
        $deleteStreet = "UPDATE `street` SET `delete_at`=1 WHERE `street_id`='$delete_street_id'";
        if ($conn->query($deleteStreet)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Street deleted successfully";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete street. Please try again.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
