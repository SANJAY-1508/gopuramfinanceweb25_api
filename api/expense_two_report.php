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

if (isset($obj->report)) {

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
else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
