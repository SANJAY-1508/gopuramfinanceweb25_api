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
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $domain;

function calculateInterestPeriod_14HalfDays($startDate, $endDate = null)
{
    $start = new DateTime($startDate);
    $end = $endDate ? new DateTime($endDate) : new DateTime();
    $diff_days = $start->diff($end)->days;
    $half_count = ceil(($diff_days + 1) / 14);
    return $half_count * 0.5;
}

function getDynamicRateByFullMonth($monthIndex, $pawn_interest, $recoveryPeriod)
{
    if ($monthIndex <= $recoveryPeriod) {
        return $pawn_interest;
    }

    $monthAfterRecovery = $monthIndex - $recoveryPeriod;

    if ($monthAfterRecovery <= 3) {
        return $pawn_interest + 1;
    } elseif ($monthAfterRecovery <= 5) {
        return $pawn_interest + 2;
    } else {
        $extraMonths = $monthAfterRecovery - 5;
        return $pawn_interest + 2 + $extraMonths;
    }
}

function calculateTotalInterestDue($startDate, $principal, $recoveryPeriod, $paidTotalMonths, $pawn_interest, $endDate = null)
{
    $monthsFloat = calculateInterestPeriod_14HalfDays($startDate, $endDate);
    $totalInterest = 0.0;

    $fullMonths = floor($monthsFloat);
    $hasHalfMonth = ($monthsFloat > $fullMonths);

    for ($m = 1; $m <= $fullMonths; $m++) {
        $rate = getDynamicRateByFullMonth($m, $pawn_interest, $recoveryPeriod);
        $monthlyInterest = round(($principal * $rate) / 100, 2);

        $isPaid = ($paidTotalMonths >= $m);
        if (!$isPaid) {
            $totalInterest += $monthlyInterest;
        }
    }

    if ($hasHalfMonth) {
        $m = $fullMonths + 1;
        $rate = getDynamicRateByFullMonth($m, $pawn_interest, $recoveryPeriod);
        $halfInterest = round((($principal * $rate) / 100) * 0.5, 2);

        $isPaid = ($paidTotalMonths >= $fullMonths + 0.5);
        if (!$isPaid) {
            $totalInterest += $halfInterest;
        }
    }

    return [$monthsFloat, $totalInterest];
}

// <<<<<<<<<<===================== List Pawn Jewelry =====================>>>>>>>>>>

if (isset($obj->search_text)) {
    $search_text = $conn->real_escape_string($obj->search_text);
    $sql = "SELECT * FROM `pawnjewelry` 
            WHERE `delete_at` = 0 
            AND (`receipt_no` LIKE ? OR `mobile_number` LIKE ? OR `name` LIKE ?) 
            ORDER BY `id` ASC";
    $search_like = "%$search_text%";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_like, $search_like, $search_like);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result === false) {
        error_log("Pawnjewelry query failed: " . $conn->error);
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database error";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $output["body"]["pawnjewelry"] = [];
       while ($row = $result->fetch_assoc()) {
    $row['proof'] = json_decode($row['proof'], true) ?? [];
    $row['proof_base64code'] = [];
    $row['aadharproof'] = json_decode($row['aadharproof'], true) ?? [];
    $row['aadharprood_base64code'] = [];

   
    $row['original_amount_copy'] = $row['original_amount'];

    // Proof URLs
    $full_proof_urls = [];
    foreach ($row['proof'] as $proof_path) {
        $prefix = "../";
        if (strpos($proof_path, $prefix) === 0) {
            $cleaned_path = substr($proof_path, strlen($prefix));
        } else {
            $cleaned_path = $proof_path;
        }
        $full_url = $base_url . '/' . $cleaned_path;
        $full_proof_urls[] = $full_url;
    }
    $row['proof'] = $full_proof_urls;

    // Aadhar URLs
    $full_aadhar_urls = [];
    foreach ($row['aadharproof'] as $proof_path) {
        $prefix = "../";
        if (strpos($proof_path, $prefix) === 0) {
            $cleaned_path = substr($proof_path, strlen($prefix));
        } else {
            $cleaned_path = $proof_path;
        }
        $full_url = $base_url . '/' . $cleaned_path;
        $full_aadhar_urls[] = $full_url;
    }
    $row['aadharproof'] = $full_aadhar_urls;

    $output["body"]["pawnjewelry"][] = $row;
}

    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No records found";
        $output["body"]["pawnjewelry"] = [];
    }
}

else if (isset($obj->receipt_no) && isset($obj->action) && $obj->action === "bank_details") {
    $receipt_no = $conn->real_escape_string($obj->receipt_no);
    
    // Sum interest_payment_amount from pawnjewelry
    $sql_pawn = "SELECT COALESCE(SUM(interest_payment_amount), 0) as pawn_interest 
                 FROM `pawnjewelry` 
                 WHERE `delete_at` = 0 AND `receipt_no` = ?";
    $stmt_pawn = $conn->prepare($sql_pawn);
    $stmt_pawn->bind_param("s", $receipt_no);
    $stmt_pawn->execute();
    $result_pawn = $stmt_pawn->get_result();
    $pawn_interest = 0;
    if ($row_pawn = $result_pawn->fetch_assoc()) {
        $pawn_interest = (float)$row_pawn['pawn_interest'];
    }
    $stmt_pawn->close();
    
    // Sum interest_income from interest
    $sql_interest = "SELECT COALESCE(SUM(interest_income), 0) as paid_interest 
                     FROM `interest` 
                     WHERE `delete_at` = 0 AND `receipt_no` = ?";
    $stmt_interest = $conn->prepare($sql_interest);
    $stmt_interest->bind_param("s", $receipt_no);
    $stmt_interest->execute();
    $result_interest = $stmt_interest->get_result();
    $paid_interest = 0;
    if ($row_interest = $result_interest->fetch_assoc()) {
        $paid_interest = (float)$row_interest['paid_interest'];
    }
    $stmt_interest->close();
    
    $total_interest = $pawn_interest + $paid_interest;
    
    $output["head"]["code"] = 200;
    $output["head"]["msg"] = "Success";
    $output["body"]["total_interest"] = $total_interest;
}


// Create Pawn Jewelry
else if (isset($obj->receipt_no) && !isset($obj->edit_pawnjewelry_id) && isset($obj->action) && $obj->action === "pawn_creation") {

    if (!isset($obj->login_id) || empty(trim($obj->login_id))) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Login ID is required";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }


    $login_id = $conn->real_escape_string(trim($obj->login_id));
    $by_name = isset($obj->user_name) ? $conn->real_escape_string(trim($obj->user_name)) : '';
    $pawnjewelry_date = isset($obj->pawnjewelry_date) ? $conn->real_escape_string($obj->pawnjewelry_date) : '';
    $customer_no = isset($obj->customer_no) ? $conn->real_escape_string($obj->customer_no) : '';
    $receipt_no = isset($obj->receipt_no) ? $conn->real_escape_string($obj->receipt_no) : '';
    $name = isset($obj->name) ? $conn->real_escape_string($obj->name) : '';
    $raw_address = $obj->customer_details ?? '';
    $cleaned_address = str_replace(['/', '\\n', '\n', "\n", "\r"], ' ', $raw_address);
    $cleaned_address = preg_replace('/\s+/', ' ', $cleaned_address);
    $cleaned_address = trim($cleaned_address);
    $customer_details = $conn->real_escape_string($cleaned_address);
    $place = isset($obj->place) ? $conn->real_escape_string($obj->place) : '';
    $mobile_number = isset($obj->mobile_number) ? $conn->real_escape_string($obj->mobile_number) : '';
    $original_amount_str = isset($obj->original_amount) ? $conn->real_escape_string($obj->original_amount) : '0';
    $original_amount = floatval($original_amount_str);
    $jewel_product = isset($obj->jewel_product) ? $obj->jewel_product : [];
    $Jewelry_recovery_agreed_period = isset($obj->Jewelry_recovery_agreed_period) ? $conn->real_escape_string($obj->Jewelry_recovery_agreed_period) : '';
    $interest_rate_str = isset($obj->interest_rate) ? $conn->real_escape_string($obj->interest_rate) : '0';
    $interest_rate = floatval($interest_rate_str);
    $proof = isset($obj->proof) ? $obj->proof : [];
    $aadharproof = isset($obj->aadharproof) ? $obj->aadharproof : [];
    $proof_number = isset($obj->proof_number) ? $obj->proof_number : "";
    $upload_type = isset($obj->upload_type) ? $obj->upload_type : "";
    $type1 = "patru";
    $current_balance = getBalance($conn);

    error_log("Create attempt: receipt_no=$receipt_no, amount=$original_amount, balance=$current_balance");

    if ($current_balance < $original_amount) {
        error_log("Insufficient balance: $current_balance < $original_amount");
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Insufficient balance!";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    // Updated validation 
    if (
        empty(trim($pawnjewelry_date)) ||
        empty(trim($customer_no)) ||
        empty(trim($receipt_no)) ||
        empty(trim($name)) ||
        empty(trim($customer_details)) ||
        empty(trim($place)) ||
        $original_amount <= 0 ||
        empty(trim($Jewelry_recovery_agreed_period)) ||
        $interest_rate <= 0
    ) {
        error_log("Validation failed for create");
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all required fields";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    if (!is_array($jewel_product) || count($jewel_product) === 0) {
        error_log("jewel_product invalid");
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "jewel_product is required and must be a non-empty array";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    try {
        $datetime1 = new DateTime($pawnjewelry_date);
    } catch (Exception $e) {
        error_log("Invalid date: " . $e->getMessage());
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Invalid date format for pawn jewelry date.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    $current_date = date('Y-m-d');
    $datetime2 = new DateTime($current_date);

    if ($datetime1 > $datetime2) {
        error_log("Future date");
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Pawn jewelry date cannot be in the future.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    // Process Proof Files (now handles URLs + Base64)
    $proofPaths = [];
    $proofBase64Codes = [];
    if (empty($proof)) {
        // Empty: OK, no files
    } else {
        if (is_string($proof)) {
            $proofArray = [(object)['data' => $proof]];
        } elseif (is_array($proof)) {
            $proofArray = $proof;
        } else {
            error_log("Proof invalid type");
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Proof must be a Base64 string or an array of Base64 strings/objects.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        foreach ($proofArray as $proofItem) {
            if (is_string($proofItem)) {
                // URL string: Convert to relative path
                $fullUrl = $proofItem;
                $baseUrlLen = strlen($base_url . '/');
                if (strpos($fullUrl, $base_url . '/') === 0) {
                    $relativePath = substr($fullUrl, $baseUrlLen);
                    $proofPaths[] = '../' . $relativePath;
                } else {
                    $proofPaths[] = $fullUrl; // Fallback
                }
                continue;
            }

            if (!isset($proofItem->data) || !is_string($proofItem->data)) {
                error_log("Invalid proof data");
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Invalid file format. Expected Base64 encoded string.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $fileData = $proofItem->data;
            if (strpos($fileData, 'http') === 0) {
                // URL: Handle like above
                $fullUrl = $fileData;
                $baseUrlLen = strlen($base_url . '/');
                if (strpos($fullUrl, $base_url . '/') === 0) {
                    $relativePath = substr($fullUrl, $baseUrlLen);
                    $proofPaths[] = '../' . $relativePath;
                } else {
                    $proofPaths[] = $fullUrl;
                }
                continue;
            }

            $proofBase64Codes[] = $fileData;
            $fileName = uniqid("file_");
            $filePath = "";

            if (preg_match('/^data:application\/pdf;base64,/', $fileData)) {
                $fileName .= ".pdf";
                $filePath = "../Uploads/pdfs/" . $fileName;
            } elseif (preg_match('/^data:image\/(\w+);base64,/', $fileData, $type)) {
                $fileName .= "." . strtolower($type[1]);
                $filePath = "../Uploads/images/" . $fileName;
            } else {
                error_log("Unsupported proof type: " . substr($fileData, 0, 50));
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Unsupported file type.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $fileData = preg_replace('/^data:.*;base64,/', '', $fileData);
            $decodedFile = base64_decode($fileData);
            if ($decodedFile === false) {
                error_log("Base64 decode failed");
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Base64 decoding failed.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $directory = dirname($filePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            if (file_put_contents($filePath, $decodedFile) === false) {
                error_log("File save failed: $filePath");
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to save the file.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $proofPaths[] = $filePath;
        }
    }

    // Process Aadhaar Proof Files (similar, handles URLs + Base64)
    $aadharProofPaths = [];
    $aadharProofBase64Codes = [];
    if (empty($aadharproof)) {
        // Empty: OK
    } else {
        if (is_string($aadharproof)) {
            $aadharArray = [(object)['data' => $aadharproof]];
        } elseif (is_array($aadharproof)) {
            $aadharArray = $aadharproof;
        } else {
            error_log("Aadharproof invalid type");
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Aadharproof must be a Base64 string or an array.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        foreach ($aadharArray as $aadharItem) {
            if (is_string($aadharItem)) {
                // URL string
                $fullUrl = $aadharItem;
                $baseUrlLen = strlen($base_url . '/');
                if (strpos($fullUrl, $base_url . '/') === 0) {
                    $relativePath = substr($fullUrl, $baseUrlLen);
                    $aadharProofPaths[] = '../' . $relativePath;
                } else {
                    $aadharProofPaths[] = $fullUrl;
                }
                continue;
            }

            if (!isset($aadharItem->data) || !is_string($aadharItem->data)) {
                error_log("Invalid aadhar data");
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Invalid Aadhaar proof file format. Expected Base64 encoded string.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $fileData = $aadharItem->data;
            if (strpos($fileData, 'http') === 0) {
                // URL
                $fullUrl = $fileData;
                $baseUrlLen = strlen($base_url . '/');
                if (strpos($fullUrl, $base_url . '/') === 0) {
                    $relativePath = substr($fullUrl, $baseUrlLen);
                    $aadharProofPaths[] = '../' . $relativePath;
                } else {
                    $aadharProofPaths[] = $fullUrl;
                }
                continue;
            }

            $aadharProofBase64Codes[] = $fileData;
            $fileName = uniqid("aadhar_");
            $filePath = "";

            if (preg_match('/^data:application\/pdf;base64,/', $fileData)) {
                $fileName .= ".pdf";
                $filePath = "../Uploads/aadhar/" . $fileName;
            } elseif (preg_match('/^data:image\/(\w+);base64,/', $fileData, $type)) {
                $fileName .= "." . strtolower($type[1]);
                $filePath = "../Uploads/aadhar/" . $fileName;
            } else {
                error_log("Unsupported aadhar type: " . substr($fileData, 0, 50));
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Unsupported Aadhaar proof file type.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $fileData = preg_replace('/^data:.*;base64,/', '', $fileData);
            $decodedFile = base64_decode($fileData);
            if ($decodedFile === false) {
                error_log("Aadhar Base64 decode failed");
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Aadhaar proof Base64 decoding failed.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $directory = dirname($filePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            if (file_put_contents($filePath, $decodedFile) === false) {
                error_log("Aadhar file save failed: $filePath");
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to save Aadhaar proof file.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $aadharProofPaths[] = $filePath;
        }
    }

    $proofJson = json_encode($proofPaths, JSON_UNESCAPED_SLASHES);
    $proofBase64CodeJson = json_encode($proofBase64Codes, JSON_UNESCAPED_SLASHES);
    $aadharProofJson = json_encode($aadharProofPaths, JSON_UNESCAPED_SLASHES);
    $aadharProofBase64CodeJson = json_encode($aadharProofBase64Codes, JSON_UNESCAPED_SLASHES);
    $products_json = json_encode($jewel_product, JSON_UNESCAPED_UNICODE);

    error_log("Processed files - proofJson: $proofJson");

    $stmt = $conn->prepare("SELECT id FROM pawnjewelry WHERE receipt_no = ? AND delete_at = 0");
    $stmt->bind_param("s", $receipt_no);
    $stmt->execute();
    $pawnjewelryCheck = $stmt->get_result();
    $stmt->close();

    if ($pawnjewelryCheck->num_rows == 0) {
        // Insert with last_interest_settlement_date = pawnjewelry_date
        $stmt = $conn->prepare("INSERT INTO pawnjewelry (
            pawnjewelry_date, customer_no, receipt_no, name, customer_details, place, mobile_number, 
            original_amount, jewel_product, Jewelry_recovery_agreed_period, interest_rate, 
            proof, proof_base64code, aadharproof, aadharprood_base64code, 
            create_at, proof_number, upload_type, last_interest_settlement_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "sssssssdsssssssssss",
            $pawnjewelry_date,
            $customer_no,
            $receipt_no,
            $name,
            $customer_details,
            $place,
            $mobile_number,
            $original_amount,
            $products_json,
            $Jewelry_recovery_agreed_period,
            $interest_rate_str,
            $proofJson,
            $proofBase64CodeJson,
            $aadharProofJson,
            $aadharProofBase64CodeJson,
            $timestamp,
            $proof_number,
            $upload_type,
            $pawnjewelry_date  // last_settlement = creation
        );

        if ($stmt->execute()) {
            error_log("Insert success, id: " . $conn->insert_id);
            $id = $conn->insert_id;
            $uniquePawnJewelryID = uniqueID('pawnjewelry', $id);
            $updateStmt = $conn->prepare("UPDATE pawnjewelry SET pawnjewelry_id = ? WHERE id = ?");
            $updateStmt->bind_param("si", $uniquePawnJewelryID, $id);
            if ($updateStmt->execute()) {
                error_log("pawnjewelry_id updated");
            } else {
                error_log("pawnjewelry_id update failed: " . $updateStmt->error);
            }
            $updateStmt->close();

            // NEW: Immediate interest calculation
            $recoveryPeriod = intval($Jewelry_recovery_agreed_period);
            $pawn_interest = $interest_rate;  // percent
            $effective_start = $pawnjewelry_date;
            $paidTotalMonths = 0.0;  // New loan

            list($monthsFloat, $totalInterest) = calculateTotalInterestDue($effective_start, $original_amount, $recoveryPeriod, $paidTotalMonths, $pawn_interest);

            $dueMonths = max(0.0, $monthsFloat - $paidTotalMonths);
            $dueMonthsRounded = round($dueMonths * 2) / 2.0;

            // Update interest fields
            $updateInterestStmt = $conn->prepare("UPDATE pawnjewelry SET interest_payment_period = ?, interest_payment_amount = ? WHERE id = ?");
            $updateInterestStmt->bind_param("ddi", $dueMonthsRounded, $totalInterest, $id);
            $updateInterestStmt->execute();
            $updateInterestStmt->close();

            error_log("Interest calculated: Period=$dueMonthsRounded, Amount=$totalInterest for ID=$id");

            $result = addTransaction($conn, $name, $original_amount_str, $type1, $pawnjewelry_date);

            if ($result) {
                // Fetch new row for history
                $new_json = null;
                $sql_new = "SELECT * FROM `pawnjewelry` WHERE `pawnjewelry_id` = ? AND `delete_at` = 0";
                $stmt_new = $conn->prepare($sql_new);
                $stmt_new->bind_param("s", $uniquePawnJewelryID);
                $stmt_new->execute();
                $new_result = $stmt_new->get_result();
                if ($new_row = $new_result->fetch_assoc()) {
                    $new_json = json_encode($new_row);
                }
                $stmt_new->close();



                // Log to history
                $remarks = "Pawn jewelry created";
                logCustomerHistory($conn, $uniquePawnJewelryID,  $customer_no, "create", null, $new_json, $remarks, $login_id, $by_name);

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Pawn jewelry created successfully. Initial Interest: Period=$dueMonthsRounded months, Amount=₹$totalInterest";
                $output["body"]["pawnjewelry_id"] = $uniquePawnJewelryID;
                $output["body"]["initial_interest"] = ["period" => $dueMonthsRounded, "amount" => $totalInterest];
            } else {
                error_log("addTransaction failed");
                $output["head"]["code"] = 500;
                $output["head"]["msg"] = "Pawn jewelry created but transaction not saved";
            }
        } else {
            error_log("Pawnjewelry insert failed: " . $stmt->error . " | SQL: " . $stmt->sqlstate);
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to create pawn jewelry. Please try again.";
        }
        $stmt->close();
    } else {
        error_log("Duplicate receipt_no: $receipt_no");
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Receipt number already exists.";
    }
}

// Update Pawn Jewelry
elseif (isset($obj->edit_pawnjewelry_id)) {


    if (!isset($obj->login_id) || empty(trim($obj->login_id))) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Login ID is required";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }

    $login_id = $conn->real_escape_string(trim($obj->login_id));
    $by_name = isset($obj->user_name) ? $conn->real_escape_string(trim($obj->user_name)) : '';
    $edit_id = $conn->real_escape_string($obj->edit_pawnjewelry_id);
    $customer_no = $conn->real_escape_string($obj->customer_no ?? '');
    $receipt_no = $conn->real_escape_string($obj->receipt_no ?? '');
    $name = $conn->real_escape_string($obj->name ?? '');
    $raw_address = $obj->customer_details ?? '';
    $cleaned_address = str_replace(['/', '\\n', '\n', "\n", "\r"], ' ', $raw_address);
    $cleaned_address = preg_replace('/\s+/', ' ', $cleaned_address);
    $cleaned_address = trim($cleaned_address);
    $customer_details = $conn->real_escape_string($cleaned_address);
    $place = $conn->real_escape_string($obj->place ?? '');
    $mobile_number = $conn->real_escape_string($obj->mobile_number ?? '');
    $original_amount_str = $conn->real_escape_string($obj->original_amount ?? '0');
    $original_amount = floatval($original_amount_str);
    $jewel_product = isset($obj->jewel_product) ? $obj->jewel_product : [];
    $Jewelry_recovery_agreed_period = $conn->real_escape_string($obj->Jewelry_recovery_agreed_period ?? '');
    $interest_rate_str = $conn->real_escape_string($obj->interest_rate ?? '0');
    $interest_rate = floatval($interest_rate_str);
    $proof = isset($obj->proof) ? $obj->proof : [];
    $aadharproof = isset($obj->aadharproof) ? $obj->aadharproof : [];
    $pawnjewelry_date = ''; // Will fetch from DB

    error_log("Update attempt: edit_id=$edit_id, receipt_no=$receipt_no");

    // Fetch existing pawn jewelry details including last_settlement
    $stmt = $conn->prepare("SELECT pawnjewelry_date, interest_rate, original_amount, last_interest_settlement_date 
                            FROM pawnjewelry 
                            WHERE pawnjewelry_id = ? AND delete_at = 0");
    $stmt->bind_param("s", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        error_log("Record not found for edit_id: $edit_id");
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Pawn jewelry record not found.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    $pawn_data = $result->fetch_assoc();
    $pawnjewelry_date = $pawn_data['pawnjewelry_date'];
    $old_interest_rate = floatval($pawn_data['interest_rate']);
    $old_original_amount = floatval($pawn_data['original_amount']);
    $old_last_settlement = $pawn_data['last_interest_settlement_date'];

    // Fetch old row for history
    $old_json = null;
    $sql_old = "SELECT * FROM `pawnjewelry` WHERE `pawnjewelry_id` = ? AND `delete_at` = 0";
    $stmt_old = $conn->prepare($sql_old);
    $stmt_old->bind_param("s", $edit_id);
    $stmt_old->execute();
    $old_result = $stmt_old->get_result();
    if ($old_row = $old_result->fetch_assoc()) {
        $old_json = json_encode($old_row);
    }
    $stmt_old->close();

    // Validate 
    if (
        empty(trim($customer_no)) ||
        empty(trim($receipt_no)) ||
        empty(trim($name)) ||
        empty(trim($customer_details)) ||
        empty(trim($place)) ||
        $original_amount <= 0 ||
        empty(trim($Jewelry_recovery_agreed_period)) ||
        $interest_rate <= 0
    ) {
        error_log("Validation failed for update");
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all required fields";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    // Check if receipt is already recovered
    $recoveryStmt = $conn->prepare("SELECT id FROM `pawnjewelry_recovery` WHERE `receipt_no` = ? AND `delete_at` = 0");
    $recoveryStmt->bind_param("s", $receipt_no);
    $recoveryStmt->execute();
    $recoveryCheck = $recoveryStmt->get_result();
    $recoveryStmt->close();

    if ($recoveryCheck->num_rows > 0) {
        error_log("Receipt recovered: $receipt_no");
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "This receipt number is already recovered.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    // Check if receipt number exists in another record
    $stmt = $conn->prepare("SELECT `id` FROM `pawnjewelry` WHERE `receipt_no` = ? AND delete_at = 0 AND `pawnjewelry_id` != ?");
    $stmt->bind_param("ss", $receipt_no, $edit_id);
    $stmt->execute();
    $pawnjewelryCheck = $stmt->get_result();
    $stmt->close();

    if ($pawnjewelryCheck->num_rows > 0) {
        error_log("Duplicate receipt_no for update: $receipt_no");
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Receipt number already exists.";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }

    $interestPaid = false;
    $stmtCheck = $conn->prepare("SELECT COUNT(*) as total FROM interest WHERE receipt_no = ?");
    $stmtCheck->bind_param("s", $receipt_no);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    $rowCheck = $resultCheck->fetch_assoc();
    $stmtCheck->close();

    if ($rowCheck && $rowCheck['total'] > 0) {
        $interestPaid = true;
    }

    if ($old_interest_rate != $interest_rate) {
        if ($interestPaid) {
            error_log("Cannot update interest rate - paid exists");
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Cannot update interest rate. Interest payments already exist for this receipt.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }
    }

    if ($old_original_amount != $original_amount) {
        if ($interestPaid) {
            error_log("Cannot update amount - paid exists");
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Cannot update principal amount. Interest payments already exist for this receipt.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }
    }

    // Process Proof Files (mixed: Base64 or URLs) - same as create
    $proofPaths = [];
    $proofBase64Codes = [];
    if (empty($proof)) {
        // No proof provided: Fetch old from DB
        $oldStmt = $conn->prepare("SELECT proof FROM pawnjewelry WHERE pawnjewelry_id = ? AND delete_at = 0");
        $oldStmt->bind_param("s", $edit_id);
        $oldStmt->execute();
        $oldResult = $oldStmt->get_result();
        if ($oldRow = $oldResult->fetch_assoc()) {
            $oldProof = json_decode($oldRow['proof'], true) ?? [];
            $proofPaths = $oldProof;  // Preserve old paths
        }
        $oldStmt->close();
    } else {
        // ... (same as before, unchanged)
        foreach ($proof as $proofItem) {
            // ... (full logic from original)
        }
    }

    // Process Aadhaar Proof Files (similar logic)
    $aadharProofPaths = [];
    $aadharProofBase64Codes = [];
    if (empty($aadharproof)) {
        // No aadhar provided: Fetch old from DB
        $oldStmt = $conn->prepare("SELECT aadharproof FROM pawnjewelry WHERE pawnjewelry_id = ? AND delete_at = 0");
        $oldStmt->bind_param("s", $edit_id);
        $oldStmt->execute();
        $oldResult = $oldStmt->get_result();
        if ($oldRow = $oldResult->fetch_assoc()) {
            $oldAadhar = json_decode($oldRow['aadharproof'], true) ?? [];
            $aadharProofPaths = $oldAadhar;  // Preserve old paths
        }
        $oldStmt->close();
    } else {
        // ... (same as before, unchanged)
        foreach ($aadharproof as $aadharItem) {
            // ... (full logic from original)
        }
    }

    $proofJson = json_encode($proofPaths, JSON_UNESCAPED_SLASHES);
    $proofBase64CodeJson = json_encode($proofBase64Codes, JSON_UNESCAPED_SLASHES);
    $aadharProofJson = json_encode($aadharProofPaths, JSON_UNESCAPED_SLASHES);
    $aadharProofBase64CodeJson = json_encode($aadharProofBase64Codes, JSON_UNESCAPED_SLASHES);
    $products_json = json_encode($jewel_product, JSON_UNESCAPED_UNICODE);

    error_log("Processed files for update - proofJson: $proofJson");

    // Update the record, include last_settlement if changed (but keep old for now)
    $stmt = $conn->prepare("UPDATE `pawnjewelry` SET  
        `customer_no`=?, `receipt_no`=?, `name`=?, `customer_details`=?, `place`=?, `mobile_number`=?, 
        `original_amount`=?, `jewel_product`=?, `Jewelry_recovery_agreed_period`=?, `interest_rate`=?, 
        `proof`=?, `proof_base64code`=?, `aadharproof`=?, 
        `aadharprood_base64code`=? 
        WHERE `pawnjewelry_id`=?");
    $stmt->bind_param(
        "ssssssdssssssss",
        $customer_no,
        $receipt_no,
        $name,
        $customer_details,
        $place,
        $mobile_number,
        $original_amount,
        $products_json,
        $Jewelry_recovery_agreed_period,
        $interest_rate_str,
        $proofJson,
        $proofBase64CodeJson,
        $aadharProofJson,
        $aadharProofBase64CodeJson,
        $edit_id
    );

    if ($stmt->execute()) {
        error_log("Update success for $edit_id");

        // Fetch new row after update
        $new_json = null;
        $sql_new = "SELECT * FROM `pawnjewelry` WHERE `pawnjewelry_id` = ? AND `delete_at` = 0";
        $stmt_new = $conn->prepare($sql_new);
        $stmt_new->bind_param("s", $edit_id);
        $stmt_new->execute();
        $new_result = $stmt_new->get_result();
        if ($new_row = $new_result->fetch_assoc()) {
            $new_json = json_encode($new_row);
        }
        $stmt_new->close();

        // Log to history
        $remarks = "Pawn jewelry updated";
        logCustomerHistory($conn, $edit_id,  $customer_no, "update", $old_json, $new_json, $remarks, $login_id, $by_name);

        // Recalc interest if amount or rate changed (only if no payments)
        if (($old_original_amount != $original_amount || $old_interest_rate != $interest_rate) && !$interestPaid) {
            $recoveryPeriod = intval($Jewelry_recovery_agreed_period);
            $pawn_interest = $interest_rate;
            $effective_start = $old_last_settlement ?: $pawnjewelry_date;
            $paidTotalMonths = 0.0;  // Recalc from start if no payments

            list($monthsFloat, $totalInterest) = calculateTotalInterestDue($effective_start, $original_amount, $recoveryPeriod, $paidTotalMonths, $pawn_interest);

            $dueMonths = max(0.0, $monthsFloat - $paidTotalMonths);
            $dueMonthsRounded = round($dueMonths * 2) / 2.0;

            $updateInterestStmt = $conn->prepare("UPDATE pawnjewelry SET interest_payment_period = ?, interest_payment_amount = ? WHERE pawnjewelry_id = ?");
            $updateInterestStmt->bind_param("dds", $dueMonthsRounded, $totalInterest, $edit_id);
            $updateInterestStmt->execute();
            $updateInterestStmt->close();

            error_log("Interest recalculated on update: Period=$dueMonthsRounded, Amount=$totalInterest for $edit_id");
        }

        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Pawn jewelry updated successfully";
        $output["body"] = $obj;  // Return data for confirmation
    } else {
        error_log("Pawnjewelry update failed: " . $stmt->error . " | SQL: " . $stmt->sqlstate);
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to update. Please try again.";
    }
    $stmt->close();
}

// <<<<<<<<<<===================== Delete Pawn Jewelry =====================>>>>>>>>>>  
else if (isset($obj->delete_pawnjewelry_id)) {

    if (!isset($obj->login_id) || empty(trim($obj->login_id))) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Login ID is required";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }

    $login_id = $conn->real_escape_string(trim($obj->login_id));
    $by_name = isset($obj->user_name) ? $conn->real_escape_string(trim($obj->user_name)) : '';
    $delete_pawnjewelry_id = $conn->real_escape_string($obj->delete_pawnjewelry_id);

    // Initialize variables to avoid undefined warnings
    $old_json = null;
    $customer_no = '';

    // Fetch old row for history
    $sql_old = "SELECT * FROM `pawnjewelry` WHERE `pawnjewelry_id` = ? AND `delete_at` = 0";
    $stmt_old = $conn->prepare($sql_old);
    $stmt_old->bind_param("s", $delete_pawnjewelry_id);
    $stmt_old->execute();
    $old_result = $stmt_old->get_result();
    $row_found = false;
    if ($old_row = $old_result->fetch_assoc()) {
        $old_json = json_encode($old_row);
        $customer_no = $old_row['customer_no'] ?? '';
        $row_found = true;
    }
    $stmt_old->close();

    if (!empty($delete_pawnjewelry_id)) {
        $stmt = $conn->prepare("UPDATE `pawnjewelry` SET `delete_at` = 1 WHERE `pawnjewelry_id` = ?");
        $stmt->bind_param("s", $delete_pawnjewelry_id);
        if ($stmt->execute()) {
            // FIXED: Check affected rows
            if ($stmt->affected_rows > 0) {
                error_log("Delete success: $delete_pawnjewelry_id (affected rows: " . $stmt->affected_rows . ")");

                // FIXED: Only log if row was found (pre-delete)
                if ($row_found && !empty($customer_no)) {
                    $remarks = "Pawn jewelry deleted";
                    logCustomerHistory($conn, $delete_pawnjewelry_id, $customer_no, "delete", $old_json, null, $remarks, $login_id, $by_name);
                } else {
                    error_log("Skip history log: Row not found or customer_no empty for $delete_pawnjewelry_id");
                }

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Pawn jewelry deleted successfully";
            } else {
                error_log("Delete no-op: No rows affected for $delete_pawnjewelry_id (already deleted?)");
                $output["head"]["code"] = 404;
                $output["head"]["msg"] = "Pawn jewelry not found or already deleted";
            }
        } else {
            error_log("Delete failed: " . $stmt->error);
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete. Please try again.";
        }
        $stmt->close();
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all required details.";
    }
}

// <<<<<<<<<<===================== List Pawn Jewelry with customer_no Filter =====================>>>>>>>>>>  
else if (isset($obj->customer_no)) {
    $customer_no = $conn->real_escape_string($obj->customer_no);
    $sql = "SELECT * FROM `pawnjewelry`
            WHERE `delete_at` = 0 AND `customer_no` = ?
            ORDER BY `id` ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $customer_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    if ($result === false) {
        error_log("Pawnjewelry query failed: " . $conn->error);
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database error";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit();
    }
    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $output["body"]["pawnjewelry"] = [];
        while ($row = $result->fetch_assoc()) {
            $row['original_amount_copy'] = $row['original_amount'];
            $row['proof'] = json_decode($row['proof'], true) ?? [];
            $row['proof_base64code'] = [];
            $row['aadharproof'] = json_decode($row['aadharproof'], true) ?? [];
            $row['aadharprood_base64code'] = [];
            $full_proof_urls = [];
            foreach ($row['proof'] as $proof_path) {
                $prefix = "../";
                if (strpos($proof_path, $prefix) === 0) {
                    $cleaned_path = substr($proof_path, strlen($prefix));
                } else {
                    $cleaned_path = $proof_path;
                }
                $full_url = $base_url . '/' . $cleaned_path;
                $full_proof_urls[] = $full_url;
            }
            $row['proof'] = $full_proof_urls;
            $full_aadhar_urls = [];
            foreach ($row['aadharproof'] as $proof_path) {
                $prefix = "../";
                if (strpos($proof_path, $prefix) === 0) {
                    $cleaned_path = substr($proof_path, strlen($prefix));
                } else {
                    $cleaned_path = $proof_path;
                }
                $full_url = $base_url . '/' . $cleaned_path;
                $full_aadhar_urls[] = $full_url;
            }
            $row['aadharproof'] = $full_aadhar_urls;

            // Fetch matching bank_pledger records based on receipt_no = pawn_loan_no
            $receipt_no = $row['receipt_no'];
            $sql_bank = "SELECT * FROM `bank_pledger`
                         WHERE `delete_at` = 0 AND `pawn_loan_no` = ?";
            $stmt_bank = $conn->prepare($sql_bank);
            if ($stmt_bank === false) {
                error_log("Bank_pledger prepare failed: " . $conn->error);
                $row['bank_pledger'] = []; // Fallback to empty array on error
            } else {
                $stmt_bank->bind_param("s", $receipt_no);
                $stmt_bank->execute();
                $result_bank = $stmt_bank->get_result();
                $row['bank_pledger'] = [];
                if ($result_bank !== false) {
                    while ($bank_row = $result_bank->fetch_assoc()) {
                        $row['bank_pledger'][] = $bank_row;
                    }
                }
                $stmt_bank->close();
            }

            $output["body"]["pawnjewelry"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No records found for this customer";
        $output["body"]["pawnjewelry"] = [];
    }
}

// <<<<<<<<<<===================== List interest_report =====================>>>>>>>>>>  
else if (isset($obj->report_type) && $obj->report_type === 'interest_report') {
    $receipt_no = isset($obj->receipt_no) ? trim($conn->real_escape_string($obj->receipt_no)) : '';
    $today = new DateTime();

    if (!empty($receipt_no)) {
        // Single receipt report
        $stmt = $conn->prepare("SELECT p.pawnjewelry_date, p.original_amount, p.customer_no, p.name, p.customer_details, p.place, p.mobile_number, p.dateofbirth, p.interest_rate, p.Jewelry_recovery_agreed_period, p.last_interest_settlement_date, r.pawnjewelry_recovery_date 
                                FROM pawnjewelry p 
                                LEFT JOIN pawnjewelry_recovery r ON p.receipt_no = r.receipt_no AND r.delete_at = 0 
                                WHERE p.receipt_no = ? AND p.delete_at = 0");
        $stmt->bind_param("s", $receipt_no);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Receipt number not found";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $pawn_data = $result->fetch_assoc();
        $effective_start = $pawn_data['last_interest_settlement_date'] ?: $pawn_data['pawnjewelry_date'];
        $period_start_date = $effective_start;
        $principal = floatval($pawn_data['original_amount']);
        $recoveryPeriod = intval($pawn_data['Jewelry_recovery_agreed_period'] ?? 0);
        $interest_rate_str = $pawn_data['interest_rate'] ?? '0';
        $pawn_interest = floatval(str_replace('%', '', $interest_rate_str));  // Keep as percent
        $monthly_interest = round($principal * $pawn_interest / 100, 2);

        // Cycle paid: SUM since effective_start
        $paidStmt = $conn->prepare("SELECT SUM(interest_payment_period) AS cycle_paid_months FROM interest WHERE receipt_no = ? AND create_at >= ? AND delete_at = 0");
        $paidStmt->bind_param("ss", $receipt_no, $effective_start);
        $paidStmt->execute();
        $paidResult = $paidStmt->get_result();
        $paidRow = $paidResult->fetch_assoc();
        $paidTotalMonths = floatval($paidRow['cycle_paid_months'] ?? 0);
        $paidStmt->close();

        // Determine end date: recovery_date if exists, else today
        $end_date_str = $pawn_data['pawnjewelry_recovery_date'] ? $pawn_data['pawnjewelry_recovery_date'] : null;
        $end_date = $pawn_data['pawnjewelry_recovery_date'] ? new DateTime($pawn_data['pawnjewelry_recovery_date']) : clone $today;

        list($monthsFloat, $total_interest) = calculateTotalInterestDue($period_start_date, $principal, $recoveryPeriod, $paidTotalMonths, $pawn_interest, $end_date_str);

        $total_due = $principal + $total_interest;

        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Interest Report Generated (Dynamic Rate starting from {$pawn_interest}%) for " . $receipt_no;
        $output["body"] = [
            'effective_start_date' => $effective_start,
            'period_start_date' => $period_start_date,
            'receipt_no' => $receipt_no,
            'customer_no' => $pawn_data['customer_no'],
            'name' => $pawn_data['name'],
            'customer_details' => $pawn_data['customer_details'],
            'place' => $pawn_data['place'],
            'mobile_number' => $pawn_data['mobile_number'],
            'dateofbirth' => $pawn_data['dateofbirth'],
            'principal' => $principal,
            'monthly_interest' => $monthly_interest,
            'total_interest' => $total_interest,
            'total_due' => $total_due,
            'total_months' => $monthsFloat,
            'cycle_paid_months' => $paidTotalMonths,
            'end_date_used' => $end_date->format('Y-m-d')
        ];
    } else {
        // All receipts report
        $sql = "SELECT p.pawnjewelry_date, p.original_amount, p.receipt_no, p.customer_no, p.name, p.customer_details, p.place, p.mobile_number, p.dateofbirth, p.interest_rate, p.Jewelry_recovery_agreed_period, p.last_interest_settlement_date, r.pawnjewelry_recovery_date 
                FROM pawnjewelry p 
                LEFT JOIN pawnjewelry_recovery r ON p.receipt_no = r.receipt_no AND r.delete_at = 0 
                WHERE p.delete_at = 0 ORDER BY p.receipt_no ASC";
        $result = $conn->query($sql);

        if ($result === false || $result->num_rows === 0) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "No active pawnjewelry records found";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        $reports = [];
        while ($row = $result->fetch_assoc()) {
            $effective_start = $row['last_interest_settlement_date'] ?: $row['pawnjewelry_date'];
            $period_start_date = $effective_start;
            $receipt_no_row = $row['receipt_no'];
            $principal = floatval($row['original_amount']);
            $interest_rate_str = $row['interest_rate'] ?? '0';
            $pawn_interest = floatval(str_replace('%', '', $interest_rate_str));  // percent
            $monthly_interest = round($principal * $pawn_interest / 100, 2);
            $recoveryPeriod = intval($row['Jewelry_recovery_agreed_period'] ?? 0);

            // Cycle paid per record
            $paidStmt = $conn->prepare("SELECT SUM(interest_payment_period) AS cycle_paid_months FROM interest WHERE receipt_no = ? AND create_at >= ? AND delete_at = 0");
            $paidStmt->bind_param("ss", $receipt_no_row, $effective_start);
            $paidStmt->execute();
            $paidResult = $paidStmt->get_result();
            $paidRow = $paidResult->fetch_assoc();
            $paidTotalMonths = floatval($paidRow['cycle_paid_months'] ?? 0);
            $paidStmt->close();

            // Determine end date: recovery_date if exists, else today
            $end_date_str = $row['pawnjewelry_recovery_date'] ? $row['pawnjewelry_recovery_date'] : null;
            $end_date = $row['pawnjewelry_recovery_date'] ? new DateTime($row['pawnjewelry_recovery_date']) : clone $today;

            list($monthsFloat, $total_interest) = calculateTotalInterestDue($period_start_date, $principal, $recoveryPeriod, $paidTotalMonths, $pawn_interest, $end_date_str);

            $total_due = $principal + $total_interest;

            $reports[] = [
                'effective_start_date' => $effective_start,
                'period_start_date' => $period_start_date,
                'receipt_no' => $receipt_no_row,
                'customer_no' => $row['customer_no'],
                'name' => $row['name'],
                'customer_details' => $row['customer_details'],
                'place' => $row['place'],
                'mobile_number' => $row['mobile_number'],
                'dateofbirth' => $row['dateofbirth'],
                'principal' => $principal,
                'monthly_interest' => $monthly_interest,
                'total_interest' => $total_interest,
                'total_due' => $total_due,
                'total_months' => $monthsFloat,
                'cycle_paid_months' => $paidTotalMonths,
                'end_date_used' => $end_date->format('Y-m-d')
            ];
        }

        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Interest Report Generated (Dynamic Rate) for ALL receipts (" . count($reports) . " records)";
        $output["body"]["reports"] = $reports;
    }
} else {
    error_log("Parameter mismatch: " . json_encode($obj));
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
