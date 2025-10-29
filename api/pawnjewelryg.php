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
    $sql = "SELECT * FROM `pawnjewelryg` WHERE `delete_at` = 0 AND (recipt_no LIKE '%$userid%' OR customer_name LIKE '%$userid%') ORDER BY `id` DESC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["pawnjewelryg"][$count] = $row;
            $output["body"]["pawnjewelryg"][$count]["products"] = json_decode($row["jewel_product"]);
            $count++;
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "pawnjewelryg Details Not Found";
        $output["body"]["pawnjewelry"] = [];
    }
} else if (isset($obj->pawnjewelryg_id)) {
        if (isset($obj->jewel_product) && isset($obj->customer_name) && isset($obj->receipt_no) && isset($obj->customer_no) && isset($obj->name_of_guardians) && isset($obj->mobile_number) && isset($obj->address) && isset($obj->group_id) && isset($obj->jewel_original_rate) && isset($obj->pawn_rate) && isset($obj->pawn_interest) && isset($obj->pawn_interest_amount) && isset($obj->pawnjewelry_date) && isset($obj->remark_jewel_pawn) && isset($obj->pawnjewelry_recovery_date) && isset($obj->createdby)) {

            $pawnjewelry_id = $obj->pawnjewelryg_id;
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
            $pawnjewelry_date = (new DateTime($obj->pawnjewelry_date))->format('Y-m-d H:i:s');
            $remark_jewel_pawn = $obj->remark_jewel_pawn;
            $customer_no = $obj->customer_no;
            $receipt_no = $obj->receipt_no;
            $pawnjewelry_recovery_date = (new DateTime($obj->pawnjewelry_recovery_date))->format('Y-m-d H:i:s');
            //$pawnjewelry_recovery_finshed_date = (new DateTime($obj->pawnjewelry_recovery_finshed_date))->format('Y-m-d H:i:s');
            //$paidby = $obj->paidby;
            $createdby = $obj->createdby;
            
            $Group_type = getGroupName($group_id);

            $products_json = json_encode($jewel_product, JSON_UNESCAPED_UNICODE);
            if (!empty($pawnjewelry_id)) {
                $update_pawnjewelry_sql = "UPDATE `pawnjewelryg` SET `recipt_no`='$receipt_no', `customer_no`='$customer_no', `customer_name`='$customer_name', `name_of_guardians`='$name_of_guardians', `mobile_number`='$mobile_number', `address`='$address', `group`='$Group_type', `jewel_original_rate`='$jewel_original_rate', `pawn_rate`='$pawn_rate', `pawn_interest`='$pawn_interest', `pawn_interest_amount`='$pawn_interest_amount', `jewel_product`='$products_json', `pawnjewelryg_date`='$pawnjewelry_date', `remark_jewel_pawn`='$remark_jewel_pawn', `pawnjewelryg_recovery_date`='$pawnjewelry_recovery_date', `createdby`='$createdby' WHERE `pawnjewelryg_id`='$pawnjewelryg_id'";
                
                if ($conn->query($update_pawnjewelry_sql)) {
                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully pawnjewelry Updated";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed To pawnjewelry Updated";
                }
            } else {
                $check_recipt_sql = "SELECT * FROM `pawnjewelry` WHERE `recipt_no`='$receipt_no' AND `delete_at` = 0";
                $resultrecipt = $conn->query($check_recipt_sql);
                
                if ($resultrecipt->num_rows == 0) {
                    if (!empty($customer_name)) {
                        $createpawnjewelry = "INSERT INTO `pawnjewelryg`(`recipt_no`, `customer_no`, `customer_name`, `name_of_guardians`, `mobile_number`, `address`, `group`, `jewel_original_rate`, `pawn_rate`, `pawn_interest`, `pawn_interest_amount`, `jewel_product`, `pawnjewelryg_date`, `remark_jewel_pawn`, `delete_at`, `create_at`, `pawnjewelryg_recovery_date`, `createdby`) VALUES ('$receipt_no', '$customer_no', '$customer_name', '$name_of_guardians', '$mobile_number', '$address', '$Group_type', '$jewel_original_rate', '$pawn_rate', '$pawn_interest', '$pawn_interest_amount', '$products_json', '$pawnjewelry_date', '$remark_jewel_pawn', '0', '$timestamp', '$pawnjewelry_recovery_date', '$createdby')";
                        
                        if ($conn->query($createpawnjewelry)) {
                            $id = $conn->insert_id;
                            $enId = uniqueID('pawnjewelryg', $id);

                            $updatepawnjewelry_id = "UPDATE `pawnjewelryg` SET `pawnjewelryg_id`='$enId' WHERE `id`='$id'";
                            $conn->query($updatepawnjewelry_id);

                            $createpawnjewelrycustomer = "INSERT INTO `customer`(`customer_name`, `name_of_guardians`, `mobile_number`, `address`, `delete_at`, `create_at`) VALUES ('$customer_name', '$name_of_guardians', '$mobile_number', '$address', '0', '$timestamp')";

                            if ($conn->query($createpawnjewelrycustomer)) {
                                $customer_id = $conn->insert_id;
                                $enIdcustomer = uniqueID('customer', $customer_id);
                                $updatepawnjewelry_customer_id = "UPDATE `customer` SET `customer_id`='$enIdcustomer', `customer_no`='$customer_no' WHERE `id`='$customer_id'";
                                
                                if ($conn->query($updatepawnjewelry_customer_id)) {
                                    $updatepawnjewelryg_id = "UPDATE `pawnjewelryg` SET `customer_id`='$enIdcustomer' WHERE `pawnjewelryg_id`='$enId'";
                                    $conn->query($updatepawnjewelryg_id);
                                }
                            }

                            $output["head"]["code"] = 200;
                            $output["head"]["msg"] = "Successfully pawnjewelry-G Created";
                        } else {
                            $output["head"]["code"] = 400;
                            $output["head"]["msg"] = "Failed to create pawnjewelry. Please try again.";
                        }
                    } else {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "Customer Name Is Empty.";
                    }
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Receipt number already exists.";
                }
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Please provide all the required details.";
        }
}

// <<<<<<<<<<===================== This is to Delete the users =====================>>>>>>>>>>
else if (isset($obj->delete_pawnjewelry_id)) {
    $delete_pawnjewelry_id = $obj->delete_pawnjewelry_id;

    if (!empty($delete_pawnjewelry_id)) {

        $deletepawnjewelry = "UPDATE `pawnjewelryg` SET `delete_at`= 1 where `pawnjewelryg_id`='$delete_pawnjewelry_id'";
        if ($conn->query($deletepawnjewelry) === true) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "successfully pawnjewelry deleted!.";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "faild to deleted.please try againg.";
        }

    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
}else if($obj->customer_id){
    $customer_id = $obj->customer_id;
    $sql = "SELECT * FROM `pawnjewelryg` WHERE `delete_at` = 0 AND customer_id = '$customer_id' ORDER BY `id` DESC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["pawnjewelryg"][$count] = $row;
            $output["body"]["pawnjewelryg"][$count]["products"] = json_decode($row["jewel_product"]);
            $count++;
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "pawnjewelry Details Not Found";
        $output["body"]["pawnjewelryg"] = [];
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
    $output["head"]["inputs"] = $obj;
}

echo json_encode($output, JSON_NUMERIC_CHECK);
