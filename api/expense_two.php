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

// <<<<<<<<<<===================== This is to list expense_two =====================>>>>>>>>>>
if (isset($obj->search_text)) {

    $search_text = $obj->search_text;
    $sql = "SELECT * FROM `expense_two` WHERE `delete_at` = 0 AND `expense_data` LIKE '%$search_text%' ORDER BY `id` DESC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["expense_two"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "expense records not found";
        $output["body"]["expense_two"] = [];
    }
}
// <<<<<<<<<<===================== This is to Create and Edit expense_two =====================>>>>>>>>>>
else if (isset($obj->expense_date) && isset($obj->expense_data)) {

    $expense_date = $obj->expense_date;
    $expense_data = $obj->expense_data;

    if (isset($obj->edit_expense_id)) {
        $edit_id = $obj->edit_expense_id;

        $updateExpense = "UPDATE `expense_two` SET `expense_date`='$expense_date',`expense_data`='$expense_data' WHERE `expense_id`='$edit_id'";

        if ($conn->query($updateExpense)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Successfully expense Details Updated";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to update. Please try again.";
        }
    } else {
        $checkExpense = $conn->query("SELECT `id` FROM `expense_two` WHERE `expense_date`='$expense_date' AND `expense_data`='$expense_data' AND delete_at = 0");
        if ($checkExpense->num_rows == 0) {

            $createExpense = "INSERT INTO `expense_two`(`expense_date`,`expense_data`,`create_at`, `delete_at`) VALUES ('$expense_date','$expense_data','$timestamp','0')";
            if ($conn->query($createExpense)) {
                $id = $conn->insert_id;
                $enId = uniqueID('expense_two', $id);

                $updateExpenseId = "UPDATE `expense_two` SET `expense_id` ='$enId' WHERE `id`='$id'";
                $conn->query($updateExpenseId);

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Successfully expense Created";
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to create. Please try again.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Expense details Already Exist.";
        }
    }
}

// <<<<<<<<<<===================== This is to Delete the expense_two =====================>>>>>>>>>>
else if (isset($obj->delete_expense_id)) {

    $delete_expense_id = $obj->delete_expense_id;

    if (!empty($delete_expense_id)) {

        $deleteExpense = "UPDATE `expense_two` SET `delete_at`= 1  WHERE `expense_id`='$delete_expense_id'";
        if ($conn->query($deleteExpense)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "expense Deleted Successfully.!";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete. Please try again.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
}

else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
