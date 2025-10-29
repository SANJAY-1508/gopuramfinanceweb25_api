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


// <<<<<<<<<<===================== This is to list users =====================>>>>>>>>>>
if (isset($obj->search_text)) {
    $search_text = $obj->search_text;

    $sql = "SELECT * FROM `users` WHERE `deleted_at` = 0 AND (`Name` LIKE '%$search_text%' OR Mobile_Number LIKE '%$search_text%' OR RoleSelection LIKE '%$search_text%') ORDER BY `id` ASC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["user"][$count] = $row;
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "User Details Not Found";
        $output["body"]["user"] = [];
    }
}


// <<<<<<<<<<===================== This is to Create and Edit users =====================>>>>>>>>>>
else if (isset($obj->Name) && isset($obj->Mobile_Number) && isset($obj->RoleSelection) && isset($obj->Password) && isset($obj->nickname) && isset($obj->User_Name)) {

    $Name = $obj->Name;
    $Mobile_Number = $obj->Mobile_Number;
    $RoleSelection = $obj->RoleSelection;
    $User_Name = $obj->User_Name;
    $Password = $obj->Password;
    $nickname = $obj->nickname;

    // if (!empty($Name) && !empty($Mobile_Number) && !empty($RoleSelection) && !empty($User_Name) && !empty($Password)) {

        if (numericCheck($Mobile_Number) && strlen($Mobile_Number) == 10) {

            if (isset($obj->edit_user_id)) {
                $edit_id = $obj->edit_user_id;
                if (userExist($edit_id)) {

                    $updateUser = "UPDATE `users` SET `Name`='$Name', `Mobile_Number`='$Mobile_Number',`RoleSelection`='$RoleSelection',`User_Name`='$User_Name', `Password`='$Password',nickname='$nickname' WHERE `user_id`='$edit_id'";

                    if ($conn->query($updateUser)) {
                        $output["head"]["code"] = 200;
                        $output["head"]["msg"] = "Successfully User Details Updated";
                    } else {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "Failed to connect. Please try again." . $conn->error;
                    }
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "User not found.";
                }
            } else {

                $userCheck = $conn->query("SELECT `user_id` FROM `users` WHERE `User_Name`='$User_Name' AND deleted_at = 0");
                if ($userCheck->num_rows == 0) {

                    $createUser = "INSERT INTO `users`(`Name`, `Mobile_Number`, `RoleSelection`, `User_Name`, `Password`, `created_at_datetime`, `deleted_at`,`nickname`) VALUES ('$Name', '$Mobile_Number', '$RoleSelection','$User_Name', '$Password','$timestamp','0','$nickname') ";

                    if ($conn->query($createUser)) {
                        $id = $conn->insert_id;
                        $enId = uniqueID('users', $id);

                        $updateUserId = "update users set user_id ='$enId' where `id`='$id'";
                        $conn->query($updateUserId);

                        $output["head"]["code"] = 200;
                        $output["head"]["msg"] = "Successfully User Created";
                    } else {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "Failed to connect. Please try again.";
                    }
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "UserName Already Exist.";
                }
            }

        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Invalid Phone Number.";
        }
    // } else {
    //     $output["head"]["code"] = 400;
    //     $output["head"]["msg"] = "Please provide all the required details.";
    // }
}

// <<<<<<<<<<===================== This is to Delete the users =====================>>>>>>>>>>
else if (isset($obj->delete_user_id)) {
    $delete_user_id = $obj->delete_user_id;

    if (!empty($delete_user_id)) {

        $deleteuser = "UPDATE `users` SET `deleted_at`= 1 where `user_id`='$delete_user_id'";
        if ($conn->query($deleteuser) === true) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "successfully user deleted!.";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "faild to deleted.please try againg.";
        }

    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
    $output["head"]["inputs"] = $obj;
}

echo json_encode($output, JSON_NUMERIC_CHECK);
