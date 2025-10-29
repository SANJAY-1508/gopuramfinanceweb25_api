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

if (isset($obj->from_date) && isset($obj->to_date) && isset($obj->search_text)) {
    $from_date = $obj->from_date;
    $to_date = $obj->to_date;
    $search_text = $obj->search_text;

   
    $sql = "SELECT * FROM `pawnjewelry` WHERE `pawnjewelry_date` BETWEEN '$from_date' AND '$to_date' AND (`recipt_no` LIKE '%$search_text%' OR `customer_name` LIKE '%$search_text%' OR `mobile_number` LIKE '%$search_text%') AND `delete_at` = 0";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $row["products"] = json_decode($row["jewel_product"]);
            $data[] = $row;
        }

        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Data fetched successfully";
        $output["body"]["pawnjewelry_report"] = $data;
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No records found";
        $output["body"]["pawnjewelry_report"] = [];
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Invalid input";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
?>
