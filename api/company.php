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


if (isset($obj->search_text)) {
    $sql = "SELECT * FROM `company`";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        if ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["company"] = $row;
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Company Details Not Found";
    }
} else if (isset($obj->company_name) && isset($obj->mobile_number) && isset($obj->gst) && isset($obj->place) && isset($obj->pincode) && isset($obj->jewel_price_details)) {

    $company_name = $obj->company_name;
    $mobile_number = $obj->mobile_number;
    $gst = $obj->gst;
    $place = $obj->place;
    $pincode = $obj->pincode;
    $jewel_price_details = $obj->jewel_price_details;

    // if (!empty($company_name) && !empty($mobile_number) && !empty($gst) && !empty($place) && !empty($pincode)) {

    if (numericCheck($mobile_number) && strlen($mobile_number) == 10) {

        if (isset($obj->edit_company_id)) {
            $edit_id = $obj->edit_company_id;

            $updateCompany = "UPDATE `company` SET `company_name`='$company_name',`mobile_number`='$mobile_number',`gst`='$gst',`pincode`='$pincode',`place`='$place',`jewel_price_details`='$jewel_price_details' WHERE `user_id`='$edit_id'";

            if ($conn->query($updateCompany)) {
                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully Company Details Updated";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to connect. Please try again." . $conn->error;
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Company not found.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Invalid Phone Number.";
    }

    // } else {
    //     $output["head"]["code"] = 400;
    //     $output["head"]["msg"] = "Please provide all the required details.";
    // }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
}
echo json_encode($output, JSON_NUMERIC_CHECK);
