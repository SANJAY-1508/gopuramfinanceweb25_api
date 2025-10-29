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
    $sql = "SELECT * FROM `category` WHERE `delete_at` = 0 AND `Category_type` LIKE '%$search_text%' ORDER BY `id` DESC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["category"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "group records not found";
        $output["body"]["category"] = [];
    }
}
// <<<<<<<<<<===================== This is to Create and Edit unit =====================>>>>>>>>>>
else if (isset($obj->group_id) && isset($obj->Category_type)) {

    $group_id = $obj->group_id;
    $Category_type = $obj->Category_type;

    // if (!empty($group_id) && !empty($Category_type)) {

        if (isset($obj->edit_Category_id)) {
            $edit_id = $obj->edit_Category_id;

            $Group_type = getGroupName($group_id);

            $updateUnit = "UPDATE `category` SET `Group_type`='$Group_type',`Category_type`='$Category_type' WHERE `category_id`='$edit_id'";

            if ($conn->query($updateUnit)) {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully category Details Updated";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to connect. Please try again.";
            }
        } else {
            $unitCheck = $conn->query("SELECT `id` FROM `category` WHERE `Category_type`='$Category_type' AND delete_at = 0");
            if ($unitCheck->num_rows == 0) {

                $Group_type = getGroupName($group_id);

                $createUnit = "INSERT INTO `category`(`Group_type`,`Category_type`,`create_at`, `delete_at`) VALUES ('$Group_type','$Category_type','$timestamp','0')";
                if ($conn->query($createUnit)) {
                    $id = $conn->insert_id;
                    $enId = uniqueID('category', $id);

                    $updateUserId = "update `category` SET `category_id` ='$enId' WHERE `id`='$id'";
                    $conn->query($updateUserId);

                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully category Created";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to connect. Please try again.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "category Name Already Exist.";
            }
        }
    // } else {
    //     $output["head"]["code"] = 400;
    //     $output["head"]["msg"] = "Please provide all the required details.";
    // }
}

// <<<<<<<<<<===================== This is to Delete the users =====================>>>>>>>>>>
else if (isset($obj->delete_category_id)) {

    $delete_category_id = $obj->delete_category_id;


    if (!empty($delete_category_id)) {

        $deleteUnit = "UPDATE `category` SET `delete_at`= 1  WHERE `category_id`='$delete_category_id'";
        if ($conn->query($deleteUnit)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "category Deleted Successfully.!";
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
