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
    $userid = $obj->search_text;
    $sql = "SELECT * FROM `pawnjewelry_estimate` WHERE `delete_at` = 0 AND (recipt_no LIKE '%$userid%' OR customer_name LIKE '%$userid%') ORDER BY `id` DESC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["pawnjewelry_estimate"][$count] = $row;
            $output["body"]["pawnjewelry_estimate"][$count]["products"] = json_decode($row["jewel_product"]);
            $count++;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "pawnjewelry Estimation Details Not Found";
        $output["body"]["pawnjewelry_estimate"] = [];
    }
} else if (isset($obj->pawnjewelry_estimate_id)) {
    if (isset($obj->jewel_product) && isset($obj->customer_name) && isset($obj->name_of_guardians) && isset($obj->mobile_number) && isset($obj->address) && isset($obj->group_id) && isset($obj->jewel_original_rate) && isset($obj->pawn_rate) && isset($obj->pawn_interest) && isset($obj->pawn_interest_amount) && isset($obj->pawnjewelry_estimate_date) && isset($obj->remark_jewel_pawn)) {

        $pawnjewelry_estimate_id = $obj->pawnjewelry_estimate_id;
        $jewel_product = $obj->jewel_product;
        $customer_name = $obj->customer_name;
        $name_of_guardians = $obj->name_of_guardians;
        $mobile_number = $obj->mobile_number;
        $address = $obj->address;
        $group_id = $obj->group_id;
        $jewel_original_rate = $obj->jewel_original_rate;
        $pawn_rate = $obj->pawn_rate;
        $pawn_interest = $obj->pawn_interest;
        $pawn_interest_amount = $obj->pawn_interest_amount;
        $pawnjewelry_estimate_date = $obj->pawnjewelry_estimate_date;
        $remark_jewel_pawn = $obj->remark_jewel_pawn;
        
        $Group_type = getGroupName($group_id);

        // if (!empty($jewel_product)) {
            $products_json = json_encode($jewel_product,JSON_UNESCAPED_UNICODE);
            if (!empty($pawnjewelry_estimate_id)) {

                $update_pawnjewelry_sql = "UPDATE `pawnjewelry_estimate` SET `customer_name`='$customer_name',`name_of_guardians`='$name_of_guardians',`mobile_number`='$mobile_number',`address`='$address',`group`='$Group_type',`jewel_original_rate`='$jewel_original_rate',`pawn_rate`='$pawn_rate',`pawn_interest`='$pawn_interest',`pawn_interest_amount`='$pawn_interest_amount',`jewel_product`='$products_json',`pawnjewelry_estimate_date`='$pawnjewelry_estimate_date',`remark_jewel_pawn`='$remark_jewel_pawn' WHERE `pawnjewelry_estimate_id`='$pawnjewelry_estimate_id'";
                if ($conn->query($update_pawnjewelry_sql)) {
                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully pawnjewelry Estimation Updated";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed To pawnjewelry Estimation Updated";
                }
            } else {

                $createpawnjewelry= "INSERT INTO `pawnjewelry_estimate`(`customer_name`, `name_of_guardians`, `mobile_number`, `address`, `group`, `jewel_original_rate`, `pawn_rate`, `pawn_interest`, `pawn_interest_amount`, `jewel_product`, `pawnjewelry_estimate_date`,`remark_jewel_pawn`, `delete_at`, `create_at`) VALUES ('$customer_name','$name_of_guardians','$mobile_number','$address','$Group_type','$jewel_original_rate','$pawn_rate','$pawn_interest','$pawn_interest_amount','$products_json','$pawnjewelry_estimate_date','$remark_jewel_pawn','0','$timestamp')";

                if ($conn->query($createpawnjewelry)) {
                    $id = $conn->insert_id;
                    $enId = uniqueID('pawnjewelry estimate', $id);
                    $recipt_no = generateReceiptesitmateNo();

                    $updatepawnjewelry_id = "update pawnjewelry_estimate set pawnjewelry_estimate_id ='$enId',`recipt_no`='$recipt_no' where `id`='$id'";
                    $conn->query($updatepawnjewelry_id);

                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully pawnjewelry Estimation Created";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to connect. Please try again.";
                }
            }

        // } else {
        //     $output["head"]["code"] = 400;
        //     $output["head"]["msg"] = "Please provide jewelProduct Estimation details required.";
        // }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
}

// <<<<<<<<<<===================== This is to Delete the users =====================>>>>>>>>>>
else if (isset($obj->delete_pawnjewelry_estimate_id)) {
    $delete_pawnjewelry_estimate_id = $obj->delete_pawnjewelry_estimate_id;

    if (!empty($delete_pawnjewelry_estimate_id)) {

        $deletepawnjewelry = "UPDATE `pawnjewelry_estimate` SET `delete_at`= 1 where `pawnjewelry_estimate_id`='$delete_pawnjewelry_estimate_id'";
        if ($conn->query($deletepawnjewelry) === true) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "successfully pawnjewelry Estimation deleted!.";
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
