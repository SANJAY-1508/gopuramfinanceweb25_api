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
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $domain;

if (isset($obj['customer_no']) && !empty(trim($obj['customer_no']))) {
    $customer_no = $conn->real_escape_string(trim($obj['customer_no']));

    if ($conn && ($conn instanceof mysqli)) {
        // Fetch customer details
        $sql_customer = "SELECT * FROM `customer` WHERE `customer_no` = ? AND `delete_at` = 0";
        $stmt_customer = $conn->prepare($sql_customer);
        $stmt_customer->bind_param("s", $customer_no);
        $stmt_customer->execute();
        $result_customer = $stmt_customer->get_result();

        if ($result_customer->num_rows > 0) {
            $customer_row = $result_customer->fetch_assoc();
            $customer_row['proof'] = json_decode($customer_row['proof'], true) ?? [];
            $customer_row['proof_base64code'] = [];
            $customer_row['aadharproof'] = json_decode($customer_row['aadharproof'], true) ?? [];
            $customer_row['aadharproof_base64code'] = [];

            // Construct full URLs for proofs
            $full_proof_urls = [];
            foreach ($customer_row['proof'] as $proof_path) {
                $cleaned_path = ltrim($proof_path, '../');
                $full_url = $base_url . '/' . $cleaned_path;
                $full_proof_urls[] = $full_url;
            }
            $customer_row['proof'] = $full_proof_urls;

            $full_aadhar_urls = [];
            foreach ($customer_row['aadharproof'] as $proof_path) {
                $cleaned_path = ltrim($proof_path, '../');
                $full_url = $base_url . '/' . $cleaned_path;
                $full_aadhar_urls[] = $full_url;
            }
            $customer_row['aadharproof'] = $full_aadhar_urls;

            $stmt_customer->close();

            // Fetch pawnjewelry records
            $sql_pledges = "SELECT * FROM `pawnjewelry` WHERE `customer_no` = ? AND `delete_at` = 0 ORDER BY `id` ASC";
            $stmt_pledges = $conn->prepare($sql_pledges);
            $stmt_pledges->bind_param("s", $customer_no);
            $stmt_pledges->execute();
            $result_pledges = $stmt_pledges->get_result();

            $pledge_details = [];
            $total_original_amount = 0;
            $total_pledges = 0;
            $receipt_nos = []; // For joining with interest and recovery

            while ($row = $result_pledges->fetch_assoc()) {
                $total_original_amount += floatval($row['original_amount']);
                $total_pledges++;
                $receipt_nos[] = $row['receipt_no'];

                // Process proofs similar to list
                $row['proof'] = json_decode($row['proof'], true) ?? [];
                $row['proof_base64code'] = [];
                $row['aadharproof'] = json_decode($row['aadharproof'], true) ?? [];
                $row['aadharprood_base64code'] = [];

                $full_proof_urls = [];
                foreach ($row['proof'] as $proof_path) {
                    $cleaned_path = ltrim($proof_path, '../');
                    $full_url = $base_url . '/' . $cleaned_path;
                    $full_proof_urls[] = $full_url;
                }
                $row['proof'] = $full_proof_urls;

                $full_aadhar_urls = [];
                foreach ($row['aadharproof'] as $proof_path) {
                    $cleaned_path = ltrim($proof_path, '../');
                    $full_url = $base_url . '/' . $cleaned_path;
                    $full_aadhar_urls[] = $full_url;
                }
                $row['aadharproof'] = $full_aadhar_urls;

                $pledge_details[] = $row;
            }
            $stmt_pledges->close();

            // Fetch interest records
            $interest_details = [];
            $total_interest_paid = 0;
            if (!empty($receipt_nos)) {
                $receipt_nos_placeholder = str_repeat('?,', count($receipt_nos) - 1) . '?';
                $sql_interest = "SELECT * FROM `interest` WHERE `receipt_no` IN ($receipt_nos_placeholder) AND `delete_at` = 0 ORDER BY `id` ASC";
                $stmt_interest = $conn->prepare($sql_interest);
                $stmt_interest->bind_param(str_repeat('s', count($receipt_nos)), ...$receipt_nos);
                $stmt_interest->execute();
                $result_interest = $stmt_interest->get_result();

                while ($row = $result_interest->fetch_assoc()) {
                    $total_interest_paid += floatval($row['interest_income']);
                    $interest_details[] = $row;
                }
                $stmt_interest->close();
            }

            // Calculate total interest due: sum(interest_payment_amount) from pledges minus total_paid
            $total_interest_due = 0;
            foreach ($pledge_details as $pledge) {
                $total_interest_due += floatval($pledge['interest_payment_amount']);
            }
            $total_interest_due = max(0, $total_interest_due - $total_interest_paid);

            // Fetch recovery records
            $recovery_details = [];
            $total_recoveries = 0;
            if (!empty($receipt_nos)) {
                $receipt_nos_placeholder = str_repeat('?,', count($receipt_nos) - 1) . '?';
                $sql_recovery = "SELECT r.*, p.customer_no AS customer_no FROM `pawnjewelry_recovery` r 
                                 LEFT JOIN `pawnjewelry` p ON p.receipt_no = r.receipt_no 
                                 WHERE r.receipt_no IN ($receipt_nos_placeholder) AND r.`delete_at` = 0 ORDER BY r.`id` ASC";
                $stmt_recovery = $conn->prepare($sql_recovery);
                $stmt_recovery->bind_param(str_repeat('s', count($receipt_nos)), ...$receipt_nos);
                $stmt_recovery->execute();
                $result_recovery = $stmt_recovery->get_result();

                while ($row = $result_recovery->fetch_assoc()) {
                    $total_recoveries++;
                    $recovery_details[] = $row;
                }
                $stmt_recovery->close();
            }

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Customer details fetched successfully";
            $output["body"] = [
                "customer_info" => $customer_row,
                "pledges" => [
                    "total_original_amount" => $total_original_amount,
                    "total_pledges" => $total_pledges,
                    "pledge_details" => $pledge_details
                ],
                "interests" => [
                    "total_paid" => $total_interest_paid,
                    "total_due" => $total_interest_due,
                    "interest_details" => $interest_details
                ],
                "recoveries" => [
                    "total_recoveries" => $total_recoveries,
                    "recovery_details" => $recovery_details
                ]
            ];
        } else {
            $output["head"]["code"] = 404;
            $output["head"]["msg"] = "Customer not found";
        }
    } else {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database connection not established";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Customer number is required";
}

echo json_encode($output, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
$conn->close();
?>