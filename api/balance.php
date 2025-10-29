<?php
include 'headers.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? '';
date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');


switch ($action) {
    case "get_balance":
        echo json_encode(["head" => ["code" => 200], "body" => ["balance" => getBalance($conn)]]);
        break;

    case "add_transaction":
        $description = $input['description'] ?? '';
        $amount = floatval($input['amount'] ?? 0);
        $type = $input['type'] ?? ''; // 'varavu' (income) or 'patru' (expense)

        $response = addTransaction($conn, $description, $amount, $type,$timestamp);
        echo json_encode($response);
        break;

    case "list_transactions":
        echo json_encode(["head" => ["code" => 200], "body" => ["transactions" => listTransactions($conn)]]);
        break;

    default:
        echo json_encode(["head" => ["code" => 400, "msg" => "Invalid action!"]]);
}

$conn->close();


?>
