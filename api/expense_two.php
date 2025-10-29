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
// <<<<<<<<<<===================== This is to Generate Expense Report =====================>>>>>>>>>>
else if (isset($obj->report)) {

    $from_date = isset($obj->from_date) && !empty($obj->from_date) ? $obj->from_date : null;
    $to_date = isset($obj->to_date) && !empty($obj->to_date) ? $obj->to_date : null;
    $category_filter = isset($obj->category) && !empty($obj->category) ? $obj->category : null;

    // Base query
    $sql = "SELECT * FROM `expense_two` WHERE `delete_at` = 0";

    // Apply date filter only if both dates provided
    if ($from_date && $to_date) {
        $sql .= " AND `expense_date` BETWEEN '$from_date' AND '$to_date'";
    }

    $sql .= " ORDER BY `expense_date` DESC";

    $result = $conn->query($sql);
    $report_items = [];

    // Fetch all categories for lookup
    $cat_query = "SELECT `category_id`, `category_name` FROM `category_two` WHERE `delete_at` = 0";
    $cat_result = $conn->query($cat_query);
    $category_map = [];
    if ($cat_result->num_rows > 0) {
        while ($cat_row = $cat_result->fetch_assoc()) {
            $category_map[$cat_row['category_id']] = $cat_row['category_name'];
        }
    }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $expense_items = json_decode($row['expense_data'], true);
            if (is_array($expense_items)) {
                foreach ($expense_items as $item) {
                    $category_name = isset($category_map[$item['category_name']]) ? $category_map[$item['category_name']] : $item['category_name'];
                    // Apply category filter
                    if ($category_filter && $category_name !== $category_filter) {
                        continue;
                    }
                    $report_items[] = [
                        'date' => $row['expense_date'],
                        'category_name' => $category_name,
                        'description' => $item['description'],
                        'amount' => $item['amount']
                    ];
                }
            }
        }
    }

    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";
    $output["body"]["expense_report"] = $report_items;
}
// <<<<<<<<<<===================== Fetch Categories =====================>>>>>>>>>>
else if (isset($obj->get_categories)) {

    $sql = "SELECT `category_id`, `category_name` FROM `category_two` WHERE `delete_at` = 0 ORDER BY `category_name` ASC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["body"]["categories"][] = $row;
        }
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No categories found";
        $output["body"]["categories"] = [];
    }
}else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
