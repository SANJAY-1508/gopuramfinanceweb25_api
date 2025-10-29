<?php
include 'db/config.php';
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


// <<<<<<<<<<===================== API Data Handling Starts Here =====================>>>>>>>>>>
if (isset($obj->user_name) && isset($obj->password)) {

    $user_name = $obj->user_name;
    $password = $obj->password;



    if (!empty($user_name) && !empty($password)) {

        // <<<<<<<<<<===================== Checking the user table =====================>>>>>>>>>>
        $result = $conn->query("SELECT `id`,`user_id`,`Name`,`Mobile_Number`,`RoleSelection`,`User_Name`,`Password` FROM `users` WHERE `User_Name`='$user_name' AND `deleted_at`=0");
        if ($result->num_rows > 0) {
            if ($row = $result->fetch_assoc()) {

                if ($row['Password'] == $password) {

                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Success";
                    $output["body"]["user"]["id"] = $row['user_id'];
                    $output["body"]["user"]["user_name"] = $row['Name'];                  
                    $output["body"]["user"]["phone_no"] = $row['Mobile_Number'];
                    $output["body"]["user"]["role"] = $row['RoleSelection'];

                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Invalid Credentials";
                }
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "User Not Found.";
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
