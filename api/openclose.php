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
if (!isset($obj->action)) {
    echo json_encode(["head" => ["code" => 400, "message" => "Invalid Request"]]);
    exit;
}

$action = $obj->action;

if ($action === "open_balance") {
    $description = "Opening Balance -".$timestamp;
    $amount = $obj->current_balance; 
    $type = "balance";

    // Call the function to insert the transaction
    $result = addTransaction($conn, $description, $amount, $type);

    if ($result) {
        echo json_encode(["head" => ["code" => 200, "message" => "Opening balance recorded successfully"]]);
    } else {
        echo json_encode(["head" => ["code" => 500, "message" => "Failed to record opening balance"]]);
    }
} else if($action === "close_balance"){
    $description = "Closing Balance -".$timestamp;
    $amount = $obj->current_balance; 
    $type = "balance";

    // Call the function to insert the transaction
    $result = addTransaction($conn, $description, $amount, $type);

    if ($result) {
        echo json_encode(["head" => ["code" => 200, "message" => "Opening balance recorded successfully"]]);
    } else {
        echo json_encode(["head" => ["code" => 500, "message" => "Failed to record opening balance"]]);
    }
}else {
    echo json_encode(["head" => ["code" => 400, "message" => "Invalid action"]]);
}
?>