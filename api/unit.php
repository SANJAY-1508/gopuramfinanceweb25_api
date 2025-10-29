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


// <<<<<<<<<<===================== This is to list unit =====================>>>>>>>>>>
if (isset($obj->search_text)) {

    $search_text = $obj->search_text;
    $sql = "SELECT * FROM `units` WHERE `delete_at` = 0 AND `unit_type` LIKE '%$search_text%' ORDER BY `id` DESC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["unit"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "unit records not found";
        $output["body"]["unit"] = [];
    }
}

// <<<<<<<<<<===================== This is to Create and Edit unit =====================>>>>>>>>>>
else if (isset($obj->unit_type)) {

    $unit_type = $obj->unit_type;

    // if (!empty($unit_type)) {

        if (isset($obj->edit_unit_type)) {
            $edit_id = $obj->edit_unit_type;

            $updateUnit = "UPDATE `units` SET `unit_type`='$unit_type' WHERE `unit_id`='$edit_id'";

            if ($conn->query($updateUnit)) {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully Unit Details Updated";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to connect. Please try again.";
            }
        } else {
            $unitCheck = $conn->query("SELECT `id` FROM `units` WHERE `unit_type`='$unit_type' AND delete_at = 0");
            if ($unitCheck->num_rows == 0) {

                $createUnit = "INSERT INTO `units`(`unit_type`, `create_at`, `delete_at`) VALUES ('$unit_type','$timestamp','0')";
                if ($conn->query($createUnit)) {
                    $id = $conn->insert_id;
                    $enId = uniqueID('unit', $id);

                    $updateUserId = "update units set unit_id ='$enId' where `id`='$id'";
                    $conn->query($updateUserId);

                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully Unit Created";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to connect. Please try again.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Unit Name Already Exist.";
            }
        }
    // } else {
    //     $output["head"]["code"] = 400;
    //     $output["head"]["msg"] = "Please provide all the required details.";
    // }
}

// <<<<<<<<<<===================== This is to Delete the users =====================>>>>>>>>>>
else if (isset($obj->delete_unit_id)) {

    $delete_unit_id = $obj->delete_unit_id;


    if (!empty($delete_unit_id)) {

        $deleteUnit = "UPDATE `units` SET `delete_at`=1  WHERE `unit_id`='$delete_unit_id'";
        if ($conn->query($deleteUnit)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Unit Deleted Successfully.!";
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
