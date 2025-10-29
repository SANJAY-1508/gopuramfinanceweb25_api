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

// <<<<<<<<<<===================== This is to list category_two =====================>>>>>>>>>>
if (isset($obj->search_text)) {

    $search_text = $obj->search_text;
    $sql = "SELECT * FROM `category_two` WHERE `delete_at` = 0 AND `category_name` LIKE '%$search_text%' ORDER BY `id` DESC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["category_two"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "category records not found";
        $output["body"]["category_two"] = [];
    }
}
// <<<<<<<<<<===================== This is to Create and Edit category_two =====================>>>>>>>>>>
else if (isset($obj->category_name)) {

    $category_name = $obj->category_name;

    if (isset($obj->edit_category_id)) {
        $edit_id = $obj->edit_category_id;

        $updateCategory = "UPDATE `category_two` SET `category_name`='$category_name' WHERE `category_id`='$edit_id'";

        if ($conn->query($updateCategory)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully category Details Updated";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to update. Please try again.";
        }
    } else {
        $checkCategory = $conn->query("SELECT `id` FROM `category_two` WHERE `category_name`='$category_name' AND delete_at = 0");
        if ($checkCategory->num_rows == 0) {

            $createCategory = "INSERT INTO `category_two`(`category_name`,`create_at`, `delete_at`) VALUES ('$category_name','$timestamp','0')";
            if ($conn->query($createCategory)) {
                $id = $conn->insert_id;
                $enId = uniqueID('category_two', $id);

                $updateCategoryId = "UPDATE `category_two` SET `category_id` ='$enId' WHERE `id`='$id'";
                $conn->query($updateCategoryId);

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully category Created";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to create. Please try again.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "category_name Already Exist.";
        }
    }
}

// <<<<<<<<<<===================== This is to Delete the category_two =====================>>>>>>>>>>
else if (isset($obj->delete_category_id)) {

    $delete_category_id = $obj->delete_category_id;

    if (!empty($delete_category_id)) {

        $deleteCategory = "UPDATE `category_two` SET `delete_at`= 1  WHERE `category_id`='$delete_category_id'";
        if ($conn->query($deleteCategory)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "category Deleted Successfully.!";
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
