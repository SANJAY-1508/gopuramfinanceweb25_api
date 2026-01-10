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
$obj = json_decode($json, true);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');



if (isset($obj['list_history'])) {

    $customer_no = isset($obj['customer_no']) ? $obj['customer_no'] : null;
    $fromdate = isset($obj['fromdate']) ? $obj['fromdate'] : null;
    $todate = isset($obj['todate']) ? $obj['todate'] : null;

    $sql = "SELECT 
                `id`, 
                `customer_id`, 
                `customer_no`, 
                `action_type`, 
                `old_value`, 
                `new_value`, 
                `remarks`, 
                `create_by_name`, 
                `create_by_id`, 
                `created_at` 
            FROM `customer_history` 
            WHERE 1";
    
    $params = [];
    $types = "";

    // ✅ Filter by customer_no (if provided)
    if (!empty($customer_no)) {
        $sql .= " AND `customer_no` = ?";
        $params[] = $customer_no;
        $types .= "s";
    }

    // ✅ Filter by date range (if provided)
    if (!empty($fromdate) && !empty($todate)) {
        $sql .= " AND DATE(`created_at`) BETWEEN ? AND ?";
        $params[] = $fromdate;
        $params[] = $todate;
        $types .= "ss";
    } elseif (!empty($fromdate)) {
        $sql .= " AND DATE(`created_at`) >= ?";
        $params[] = $fromdate;
        $types .= "s";
    } elseif (!empty($todate)) {
        $sql .= " AND DATE(`created_at`) <= ?";
        $params[] = $todate;
        $types .= "s";
    }

    $sql .= " ORDER BY `created_at` DESC";

    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $history = $result->num_rows > 0 ? $result->fetch_all(MYSQLI_ASSOC) : [];

    // ✅ Decode old/new values safely
    foreach ($history as &$record) {
        try {
            $record['old_value'] = $record['old_value'] ? json_decode($record['old_value'], true) : null;
            $record['new_value'] = $record['new_value'] ? json_decode($record['new_value'], true) : null;
        } catch (Exception $e) {
            $record['old_value'] = null;
            $record['new_value'] = null;
        }
    }

    $output["body"]["history"] = $history;
    $output["head"]["code"] = 200;
    $output["head"]["msg"] = $result->num_rows > 0 ? "Success" : "No History Found";

    $stmt->close();
}
else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Invalid or missing parameters";
}

echo json_encode($output);
$conn->close();
