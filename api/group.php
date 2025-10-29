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
    $sql = "SELECT * FROM `groups` WHERE `delete_at` = 0 AND `Group_type` LIKE '%$search_text%' ORDER BY `id` DESC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["group"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "group records not found";
        $output["body"]["group"] = [];
    }
}

// <<<<<<<<<<===================== This is to Create and Edit unit =====================>>>>>>>>>>
else if (isset($obj->Group_type)) {

    $Group_type = $obj->Group_type;
    $interest = isset($obj->interest) ? $obj->interest : null;

    if (isset($obj->edit_Group_type)) {
        $edit_id = $obj->edit_Group_type;

        $updateUnit = "UPDATE `groups` SET `Group_type`='$Group_type', `interest`='$interest' WHERE `Group_id`='$edit_id'";

        if ($conn->query($updateUnit)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully group Details Updated";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to connect. Please try again.";
        }
    } else {
        $unitCheck = $conn->query("SELECT `id` FROM `groups` WHERE `Group_type`='$Group_type' AND delete_at = 0");
        if ($unitCheck->num_rows == 0) {

            $createUnit = "INSERT INTO `groups`(`Group_type`, `interest`, `create_at`, `delete_at`) VALUES ('$Group_type', '$interest', '$timestamp', '0')";
            if ($conn->query($createUnit)) {
                $id = $conn->insert_id;
                $enId = uniqueID('unit', $id);

                $updateUserId = "update `groups` SET Group_id ='$enId' WHERE `id`='$id'";
                $conn->query($updateUserId);

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully group Created";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to connect. Please try again.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "group Name Already Exist.";
        }
    }
}


// <<<<<<<<<<===================== This is to Delete the users =====================>>>>>>>>>>
else if (isset($obj->delete_Group_id)) {

    $delete_Group_id = $obj->delete_Group_id;


    if (!empty($delete_Group_id)) {

        $deleteUnit = "UPDATE `groups` SET `delete_at`=1  WHERE `Group_id`='$delete_Group_id'";
        if ($conn->query($deleteUnit)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "group Deleted Successfully.!";
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
