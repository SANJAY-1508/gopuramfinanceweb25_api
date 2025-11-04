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
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $domain;

// <<<<<<<<<<===================== This is to list bank_pledge_details =====================>>>>>>>>>>
if (isset($obj['search_text'])) {

    if (!$conn || !($conn instanceof mysqli)) {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database connection not established";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }
    $search_text = $conn->real_escape_string(trim($obj['search_text']));
    $sql = "SELECT * FROM `bank_pledge_details` WHERE `delete_at` = 0 AND (`customer_no` LIKE ? OR `receipt_no` LIKE ? OR `bank_loan_no` LIKE ?) ORDER BY `id` DESC";
    $stmt = $conn->prepare($sql);
    $search_param = "%$search_text%";
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $output["body"]["bank_pledge_details"] = [];
        while ($row = $result->fetch_assoc()) {
            $row['proof'] = json_decode($row['proof'], true) ?? [];
            $row['proof_base64code'] = json_decode($row['proof_base64code'], true) ?? [];
            $full_proof_urls = [];
            foreach ($row['proof'] as $proof_path) {
                // Normalize and remove leading "../"
                $cleaned_path = ltrim($proof_path, '../');
                $full_url = $base_url . '/' . $cleaned_path;
                $full_proof_urls[] = $full_url;
            }
            $row['proof'] = $full_proof_urls;
            $output["body"]["bank_pledge_details"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "bank_pledge_details records not found";
        $output["body"]["bank_pledge_details"] = [];
    }
    $stmt->close();
}
// <<<<<<<<<<===================== This is to Create and Edit bank_pledge_details =====================>>>>>>>>>>
else if (isset($obj['customer_no']) && isset($obj['receipt_no']) && isset($obj['bank_pledge_date'])) {

    if (!isset($obj['login_id']) || empty(trim($obj['login_id']))) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Login ID is required";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }
    $login_id = $conn->real_escape_string(trim($obj['login_id']));
    $by_name = isset($obj['user_name']) ? $conn->real_escape_string(trim($obj['user_name'])) : '';

    $customer_no = $conn->real_escape_string(trim($obj['customer_no']));
    $receipt_no = $conn->real_escape_string(trim($obj['receipt_no']));
    $bank_pledge_date = $conn->real_escape_string(trim($obj['bank_pledge_date']));
    $bank_loan_no = isset($obj['bank_loan_no']) ? $conn->real_escape_string(trim($obj['bank_loan_no'])) : '';
    $bank_assessor_name = isset($obj['bank_assessor_name']) ? $conn->real_escape_string(trim($obj['bank_assessor_name'])) : '';
    $bank_name = isset($obj['bank_name']) ? $conn->real_escape_string(trim($obj['bank_name'])) : '';
    $bank_pawn_value = !empty($obj['bank_pawn_value']) ? $obj['bank_pawn_value'] : 0;  
    $bank_interest = !empty($obj['bank_interest']) ? $obj['bank_interest'] : 0;        
    $bank_due_date_raw = isset($obj['bank_due_date']) ? $obj['bank_due_date'] : '';    
    $closing_date_raw = isset($obj['closing_date']) ? $obj['closing_date'] : '';       
    $closing_amount = !empty($obj['closing_amount']) ? $obj['closing_amount'] : 0;     

    // Fix: Prepare SQL-safe values for dates (NULL if empty)
    
    $bank_due_date = !empty($bank_due_date_raw) ? "'$bank_due_date_raw'" : 'NULL';
    $closing_date = !empty($closing_date_raw) ? "'$closing_date_raw'" : 'NULL';

    $proof = isset($obj['proof']) ? $obj['proof'] : [];
    $proofPaths = [];
    $proofBase64Codes = [];

    // Process proof files
    if (!empty($proof)) {
        if (is_string($proof)) {
            $base64File = ['data' => $proof];
            $proofArray = [$base64File];
        } elseif (is_array($proof)) {
            $proofArray = $proof;
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Proof must be a Base64 string or an array of Base64 strings.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        foreach ($proofArray as $base64File) {
            if (!isset($base64File['data']) || !is_string($base64File['data'])) {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Invalid file format for proof. Expected Base64 encoded string or URL.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $data = $base64File['data'];

            if (strpos($data, 'http') === 0) {
                // Existing file URL
                $full_url = $data;
                $cleaned_path = str_replace($base_url . '/', '', $full_url);
                $relative_path = '../' . $cleaned_path;
                $proofPaths[] = $relative_path;
                // No base64 for existing
            } else {
                // New base64 file
                $proofBase64Codes[] = $data;
                $fileName = uniqid("file_");
                $filePath = "";

                if (preg_match('/^data:application\/pdf;base64,/', $data)) {
                    $fileName .= ".pdf";
                    $filePath = "../Uploads/pdfs/" . $fileName;
                } elseif (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
                    $fileName .= "." . strtolower($type[1]);
                    $filePath = "../Uploads/images/" . $fileName;
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Unsupported file type for proof.";
                    echo json_encode($output, JSON_NUMERIC_CHECK);
                    exit();
                }

                $fileData = preg_replace('/^data:.*;base64,/', '', $data);
                $decodedFile = base64_decode($fileData);
                if ($decodedFile === false) {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Base64 decoding failed for proof.";
                    echo json_encode($output, JSON_NUMERIC_CHECK);
                    exit();
                }

                $directory = dirname($filePath);
                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }

                if (file_put_contents($filePath, $decodedFile) === false) {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to save the proof file.";
                    echo json_encode($output, JSON_NUMERIC_CHECK);
                    exit();
                }

                $proofPaths[] = $filePath;
            }
        }
    }

    $proofJson = json_encode($proofPaths, JSON_UNESCAPED_SLASHES);
    $proofBase64CodeJson = json_encode($proofBase64Codes, JSON_UNESCAPED_SLASHES);

    // Fetch customer_id from customer table using customer_no
    $customerQuery = $conn->query("SELECT `customer_id` as customer_id FROM `customer` WHERE `customer_no` = '$customer_no' AND `delete_at` = 0");
    if ($customerQuery->num_rows == 0) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Customer not found with given customer_no.";
    } else {
        $customerRow = $customerQuery->fetch_assoc();
        $customer_id = $customerRow['customer_id'];

        // Fetch pawnjewelry_id from pawnjewelry table using receipt_no and customer_no
        $pawnQuery = $conn->query("SELECT `pawnjewelry_id` as pawnjewelry_id FROM `pawnjewelry` WHERE `receipt_no` = '$receipt_no' AND `customer_no` = '$customer_no' AND `delete_at` = 0");
        if ($pawnQuery->num_rows == 0) {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Pawn jewelry not found with given receipt_no and customer_no.";
        } else {
            $pawnRow = $pawnQuery->fetch_assoc();
            $pawnjewelry_id = $pawnRow['pawnjewelry_id'];

            if (isset($obj['edit_bank_pledge_id'])) {
                $edit_id = $conn->real_escape_string(trim($obj['edit_bank_pledge_id']));

                // Fix: Use prepared date values in UPDATE
                $updatePledge = "UPDATE `bank_pledge_details` SET `customer_no`='$customer_no',`receipt_no`='$receipt_no',`customer_id`='$customer_id',`pawnjewelry_id`='$pawnjewelry_id',`bank_pledge_date`='$bank_pledge_date',`bank_loan_no`='$bank_loan_no',`bank_assessor_name`='$bank_assessor_name',`bank_name`='$bank_name',`bank_pawn_value`='$bank_pawn_value',`bank_interest`='$bank_interest',`bank_due_date`=$bank_due_date,`closing_date`=$closing_date,`closing_amount`='$closing_amount',`proof`='$proofJson',`proof_base64code`='$proofBase64CodeJson',`updated_by_id`='$login_id' WHERE `bank_pledge_details_id`='$edit_id'";

                if ($conn->query($updatePledge)) {
                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Successfully bank pledge details Updated";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to update. Please try again.";
                }
            } else {
                // Check if already exists with same customer_no and receipt_no
                $checkPledge = $conn->query("SELECT `id` FROM `bank_pledge_details` WHERE `customer_no`='$customer_no' AND `receipt_no`='$receipt_no' AND `delete_at` = 0");
                if ($checkPledge->num_rows == 0) {

                    // Fix: Use prepared date values in INSERT (no quotes around NULL)
                    $createPledge = "INSERT INTO `bank_pledge_details`(`customer_no`,`receipt_no`,`customer_id`,`pawnjewelry_id`,`bank_pledge_date`,`bank_loan_no`,`bank_assessor_name`,`bank_name`,`bank_pawn_value`,`bank_interest`,`bank_due_date`,`closing_date`,`closing_amount`,`proof`,`proof_base64code`,`create_at`, `delete_at`,`created_by_id`) VALUES ('$customer_no','$receipt_no','$customer_id','$pawnjewelry_id','$bank_pledge_date','$bank_loan_no','$bank_assessor_name','$bank_name','$bank_pawn_value','$bank_interest',$bank_due_date,$closing_date,'$closing_amount','$proofJson','$proofBase64CodeJson','$timestamp','0','$login_id')";
                    if ($conn->query($createPledge)) {
                        $id = $conn->insert_id;
                        $enId = uniqueID('bank_pledge_details', $id);

                        $updatePledgeId = "UPDATE `bank_pledge_details` SET `bank_pledge_details_id` ='$enId' WHERE `id`='$id'";
                        $conn->query($updatePledgeId);

                        $output["head"]["code"] = 200;
                        $output["head"]["msg"] = "Successfully bank pledge details Created";
                    } else {
                        $output["head"]["code"] = 400;
                        $output["head"]["msg"] = "Failed to create. Please try again.";
                    }
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Bank pledge details already exist for this customer_no and receipt_no.";
                }
            }
        }
    }
}

// <<<<<<<<<<===================== This is to Delete the bank_pledge_details =====================>>>>>>>>>>
else if (isset($obj['delete_bank_pledge_id'])) {

    $delete_bank_pledge_id = $conn->real_escape_string(trim($obj['delete_bank_pledge_id']));

    if (!empty($delete_bank_pledge_id)) {

        $deletePledge = "UPDATE `bank_pledge_details` SET `delete_at`= 1  WHERE `bank_pledge_details_id`='$delete_bank_pledge_id'";
        if ($conn->query($deletePledge)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Bank pledge details Deleted Successfully.!";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete. Please try again.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
$conn->close();