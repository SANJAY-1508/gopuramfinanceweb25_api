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

$response = ["head" => ["code" => 400, "msg" => "Invalid request"]];

switch ($action) {
    case "list":
        $searchText = $input['search_text'] ?? '';
        $stmt = $conn->prepare("SELECT * FROM expenses WHERE expense_name LIKE ?");
        $searchParam = "%$searchText%";
        $stmt->bind_param("s", $searchParam);
        $stmt->execute();
        $result = $stmt->get_result();
        $expenses = $result->fetch_all(MYSQLI_ASSOC);

        $response = ["head" => ["code" => 200], "body" => ["expenses" => $expenses]];
        break;

    case "create":
        if (
    empty($input['expense_name']) ||
    empty($input['expense_type']) ||
    !isset($input['amount']) ||  // amount can be 0
    empty($input['date'])
) {
    $response = ["head" => ["code" => 400, "msg" => "Missing or invalid required fields"]];
    break;
}

        $expense_type = $input["expense_type"];
        $type ="";
        if($expense_type === "debit"){
            $type ="patru";
        }else{
           $type ="varavu"; 
        }
        
        $current_balance = getBalance($conn);

        // Check if balance is sufficient for expense
        if ($type === 'patru' && $current_balance < $input['amount']) {
            $response = ["head" => ["code" => 400, "msg" => "Insufficient balance!\nYour Balance is $current_balance"]];
            break; 
        }else{

        $stmt = $conn->prepare("INSERT INTO expenses (expense_name,expense_type, amount, date) VALUES (?, ?, ?,?)");
        $stmt->bind_param("ssds", $input['expense_name'],$input['expense_type'], $input['amount'], $input['date']);
        $stmt->execute();

        addTransaction($conn, $input['expense_name'], $input['amount'], $type,$input['date']);

        $response = ["head" => ["code" => 200, "msg" => "Transaction added successfully!"]];
        break;
       }

    case "update":
        if (!isset($input['expense_id'], $input['expense_name'],$input['expense_type'], $input['amount'], $input['date'])) {
            $response = ["head" => ["code" => 400, "msg" => "Missing required fields"]];
            break;
        }

        $stmt = $conn->prepare("UPDATE expenses SET expense_name = ?,expense_type = ?, amount = ?, date = ? WHERE expense_id = ?");
        $stmt->bind_param("ssdsi", $input['expense_name'],$input['expense_type'], $input['amount'], $input['date'], $input['expense_id']);
        $stmt->execute();

        $response = ["head" => ["code" => 200, "msg" => "Transaction updated successfully!"]];
        break;

    case "delete":
        if (!isset($input['expense_id'])) {
            $response = ["head" => ["code" => 400, "msg" => "Missing expense ID"]];
            break;
        }

        $stmt = $conn->prepare("DELETE FROM expenses WHERE expense_id = ?");
        $stmt->bind_param("i", $input['expense_id']);
        $stmt->execute();

        $response = ["head" => ["code" => 200, "msg" => "Expense deleted successfully!"]];
        break;
}

$conn->close();
echo json_encode($response);
?>
