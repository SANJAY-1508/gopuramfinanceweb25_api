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



// <<<<<<<<<<===================== List Customers =====================>>>>>>>>>>
if (isset($obj['search_text'])) {
    if (!$conn || !($conn instanceof mysqli)) {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database connection not established";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }
    $search_text = $conn->real_escape_string(trim($obj['search_text']));
    $sql = "SELECT *
            FROM `customer` 
            WHERE `delete_at` = 0 
            AND (`name` LIKE ? OR `mobile_number` LIKE ? OR `customer_no` LIKE ?) 
            ORDER BY `id` ASC";

    $stmt = $conn->prepare($sql);
    $search_param = "%$search_text%";
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Success";
        $output["body"]["customer"] = [];
        while ($row = $result->fetch_assoc()) {
            $row['proof'] = json_decode($row['proof'], true) ?? [];
            $row['proof_base64code'] =  [];
            // json_decode($row['proof_base64code'], true) ??
            $row['aadharproof'] = json_decode($row['aadharproof'], true) ?? ["welcome"];
            $row['aadharproof_base64code'] = [];
            //json_decode($row['aadharproof_base64code'], true) ??
            $row['customer_no'] = (string)$row['customer_no'];
            $full_proof_urls = [];
            foreach ($row['proof'] as $proof_path) {
                // Normalize and remove leading "../"
                $cleaned_path = ltrim($proof_path, '../');
                $full_url = $base_url . '/' . $cleaned_path;
                $full_proof_urls[] = $full_url;
            }
            $row['proof'] = $full_proof_urls;
            $full_proof_urls1 = [];
            foreach ($row['aadharproof'] as $proof_path) {
                // Normalize and remove leading "../"
                $cleaned_path = ltrim($proof_path, '../');
                $full_url = $base_url . '/' . $cleaned_path;
                $full_proof_urls1[] = $full_url;
            }
            $row['aadharproof'] = $full_proof_urls1;
            $output["body"]["customer"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No customers found";
        $output["body"]["customer"] = [];
    }
    $stmt->close();
}

// <<<<<<<<<<===================== Create or Update Customer =====================>>>>>>>>>>
elseif (isset($obj['edit_customer_id'])) {
    // Update Customer
    if (!$conn || !($conn instanceof mysqli)) {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database connection not established";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }
    if (!isset($obj['login_id']) || empty(trim($obj['login_id']))) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Login ID is required";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }
    $login_id = $conn->real_escape_string(trim($obj['login_id']));
    $edit_id = $conn->real_escape_string(trim($obj['edit_customer_id']));
    $name = $conn->real_escape_string(trim($obj['name'] ?? ''));
    $mobile_number = $conn->real_escape_string(trim($obj['mobile_number'] ?? ''));
    $customer_no = $conn->real_escape_string(trim($obj['customer_no'] ?? ''));
    $raw_address = $obj['customer_details'];
    $customer_details = $conn->real_escape_string(trim($raw_address ?? ''));
    $place = $conn->real_escape_string(trim($obj['place'] ?? ''));
    $pincode = $conn->real_escape_string(trim($obj['pincode'] ?? ''));
    $proof = isset($obj['proof']) ? $obj['proof'] : [];
    $aadharproof = isset($obj['aadharproof']) ? $obj['aadharproof'] : [];
    $dateofbirth = isset($obj['dateofbirth']) ? $obj['dateofbirth'] : null;
    $proof_number = isset($obj['proof_number']) ? $obj['proof_number'] : "";
    $upload_type = isset($obj['upload_type']) ? $obj['upload_type'] : "";
    $addtionsonal_mobile_number = $conn->real_escape_string(trim($obj['additional_number'] ?? ''));
    $reference = $conn->real_escape_string(trim($obj['reference'] ?? ''));
    $account_holder_name = $conn->real_escape_string(trim($obj['account_holder_name'] ?? ''));
    $bank_name = $conn->real_escape_string(trim($obj['bank_name'] ?? ''));
    $account_number = $conn->real_escape_string(trim($obj['account_number'] ?? ''));
    $ifsc_code = $conn->real_escape_string(trim($obj['ifsc_code'] ?? ''));
    $branch_name = $conn->real_escape_string(trim($obj['branch_name'] ?? ''));
    $by_name = isset($obj['user_name']) ? $conn->real_escape_string(trim($obj['user_name'])) : '';

    // Fetch old row for history
    $old_json = null;
    $sql_old = "SELECT * FROM `customer` WHERE `customer_id` = ? AND `delete_at` = 0";
    $stmt_old = $conn->prepare($sql_old);
    $stmt_old->bind_param("s", $edit_id);
    $stmt_old->execute();
    $old_result = $stmt_old->get_result();
    if ($old_row = $old_result->fetch_assoc()) {
        $old_json = json_encode($old_row);
    }
    $stmt_old->close();



    $proofPaths = [];
    $proofBase64Codes = [];
    $aadharProofPaths = [];
    $aadharProofBase64Codes = [];

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
                $output["head"]["msg"] = "Invalid file format for proof. Expected Base64 encoded string.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $proofBase64Codes[] = $base64File['data'];
            $fileData = $base64File['data'];
            $fileName = uniqid("file_");
            $filePath = "";

            if (preg_match('/^data:application\/pdf;base64,/', $fileData)) {
                $fileName .= ".pdf";
                $filePath = "../Uploads/pdfs/" . $fileName;
            } elseif (preg_match('/^data:image\/(\w+);base64,/', $fileData, $type)) {
                $fileName .= "." . strtolower($type[1]);
                $filePath = "../Uploads/images/" . $fileName;
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Unsupported file type for proof.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $fileData = preg_replace('/^data:.*;base64,/', '', $fileData);
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

            $publicPath = str_replace("../", "", $filePath);
            $networkPath = $base_url . "/" . $publicPath;
            $proofPaths[] = $filePath;
        }
    }

    // Process aadharproof files
    if (!empty($aadharproof)) {
        if (is_string($aadharproof)) {
            $base64File = ['data' => $aadharproof];
            $aadharProofArray = [$base64File];
        } elseif (is_array($aadharproof)) {
            $aadharProofArray = $aadharproof;
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Aadhar proof must be a Base64 string or an array of Base64 strings.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        foreach ($aadharProofArray as $base64File) {
            if (!isset($base64File['data']) || !is_string($base64File['data'])) {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Invalid file format for aadhar proof. Expected Base64 encoded string.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $aadharProofBase64Codes[] = $base64File['data'];
            $fileData = $base64File['data'];
            $fileName = uniqid("aadhar_file_");
            $filePath = "";

            if (preg_match('/^data:application\/pdf;base64,/', $fileData)) {
                $fileName .= ".pdf";
                $filePath = "../Uploads/aadhar_pdfs/" . $fileName;
            } elseif (preg_match('/^data:image\/(\w+);base64,/', $fileData, $type)) {
                $fileName .= "." . strtolower($type[1]);
                $filePath = "../Uploads/aadhar_images/" . $fileName;
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Unsupported file type for aadhar proof.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $fileData = preg_replace('/^data:.*;base64,/', '', $fileData);
            $decodedFile = base64_decode($fileData);
            if ($decodedFile === false) {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Base64 decoding failed for aadhar proof.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $directory = dirname($filePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            if (file_put_contents($filePath, $decodedFile) === false) {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to save the aadhar proof file.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $publicPath = str_replace("../", "", $filePath);
            $networkPath = $base_url . "/" . $publicPath;
            $aadharProofPaths[] = $filePath;
        }
    }

    $proofJson = json_encode($proofPaths, JSON_UNESCAPED_SLASHES);
    $proofBase64CodeJson = json_encode($proofBase64Codes, JSON_UNESCAPED_SLASHES);
    $aadharProofJson = json_encode($aadharProofPaths, JSON_UNESCAPED_SLASHES);
    $aadharProofBase64CodeJson = json_encode($aadharProofBase64Codes, JSON_UNESCAPED_SLASHES);

    if (empty($name) || empty($mobile_number) || empty($customer_no)) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Name, mobile number, and customer number are required";
    } else {
        $sql = "UPDATE `customer` 
                SET `name`=?, `mobile_number`=?, `customer_no`=?, `customer_details`=?, `place`=?, `proof`=?, `proof_base64code`=?, `aadharproof`=?, `aadharproof_base64code`=?,`dateofbirth`=?,`proof_number`=?,`upload_type`=?,`pincode`=?, `addtionsonal_mobile_number`=?,`reference`=?, `account_holder_name`=?, `bank_name`=?, `account_number`=?, `ifsc_code`=?, `branch_name`=?, `updated_by_id`=?
                WHERE `customer_id`=? AND `delete_at`=0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssssssssssssssss", $name, $mobile_number, $customer_no, $customer_details, $place, $proofJson, $proofBase64CodeJson, $aadharProofJson, $aadharProofBase64CodeJson, $dateofbirth, $proof_number, $upload_type, $pincode, $addtionsonal_mobile_number, $reference, $account_holder_name, $bank_name, $account_number, $ifsc_code, $branch_name, $login_id, $edit_id);

        if ($stmt->execute()) {
            // Fetch new row after update
            $new_json = null;
            $sql_new = "SELECT * FROM `customer` WHERE `customer_id` = ? AND `delete_at` = 0";
            $stmt_new = $conn->prepare($sql_new);
            $stmt_new->bind_param("s", $edit_id);
            $stmt_new->execute();
            $new_result = $stmt_new->get_result();
            if ($new_row = $new_result->fetch_assoc()) {
                $new_json = json_encode($new_row);
            }
            $stmt_new->close();

            // Log to history
            $remarks = "Customer updated";
            logCustomerHistory($conn, $edit_id, $customer_no, "update", $old_json, $new_json, $remarks, $login_id, $by_name);

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Customer updated successfully";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to update customer. Please try again.";
        }
        $stmt->close();
    }
} elseif (isset($obj['name']) && isset($obj['mobile_number']) && isset($obj['customer_no'])) {
    // Create Customer
    if (!$conn || !($conn instanceof mysqli)) {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database connection not established";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }
    if (!isset($obj['login_id']) || empty(trim($obj['login_id']))) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Login ID is required";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }
    $login_id = $conn->real_escape_string(trim($obj['login_id']));
    $name = $conn->real_escape_string(trim($obj['name']));
    $mobile_number = $conn->real_escape_string(trim($obj['mobile_number']));
    $addtionsonal_mobile_number = $conn->real_escape_string(trim($obj['additional_number'] ?? ''));
    $customer_no = $conn->real_escape_string(trim($obj['customer_no']));
    $customer_details = $conn->real_escape_string(trim($obj['customer_details'] ?? ''));
    $place = $conn->real_escape_string(trim($obj['place'] ?? ''));
    $pincode = $conn->real_escape_string(trim($obj['pincode'] ?? ''));
    $proof = isset($obj['proof']) ? $obj['proof'] : [];
    $aadharproof = isset($obj['aadharproof']) ? $obj['aadharproof'] : [];
    $dateofbirth = isset($obj['dateofbirth']) ? $obj['dateofbirth'] : null;
    $proof_number = isset($obj['proof_number']) ? $obj['proof_number'] : "";
    $upload_type = isset($obj['upload_type']) ? $obj['upload_type'] : "";
    $reference = $conn->real_escape_string(trim($obj['reference'] ?? ''));
    $account_holder_name = $conn->real_escape_string(trim($obj['account_holder_name'] ?? ''));
    $bank_name = $conn->real_escape_string(trim($obj['bank_name'] ?? ''));
    $account_number = $conn->real_escape_string(trim($obj['account_number'] ?? ''));
    $ifsc_code = $conn->real_escape_string(trim($obj['ifsc_code'] ?? ''));
    $branch_name = $conn->real_escape_string(trim($obj['branch_name'] ?? ''));
    $by_name = isset($obj['user_name']) ? $conn->real_escape_string(trim($obj['user_name'])) : '';


    $proofPaths = [];
    $proofBase64Codes = [];
    $aadharProofPaths = [];
    $aadharProofBase64Codes = [];

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
                $output["head"]["msg"] = "Invalid file format for proof. Expected Base64 encoded string.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $proofBase64Codes[] = $base64File['data'];
            $fileData = $base64File['data'];
            $fileName = uniqid("file_");
            $filePath = "";

            if (preg_match('/^data:application\/pdf;base64,/', $fileData)) {
                $fileName .= ".pdf";
                $filePath = "../Uploads/pdfs/" . $fileName;
            } elseif (preg_match('/^data:image\/(\w+);base64,/', $fileData, $type)) {
                $fileName .= "." . strtolower($type[1]);
                $filePath = "../Uploads/images/" . $fileName;
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Unsupported file type for proof.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $fileData = preg_replace('/^data:.*;base64,/', '', $fileData);
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

            $publicPath = str_replace("../", "", $filePath);
            $networkPath = $base_url . "/" . $publicPath;
            $proofPaths[] = $filePath;
        }
    }

    // Process aadharproof files
    if (!empty($aadharproof)) {
        if (is_string($aadharproof)) {
            $base64File = ['data' => $aadharproof];
            $aadharProofArray = [$base64File];
        } elseif (is_array($aadharproof)) {
            $aadharProofArray = $aadharproof;
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Aadhar proof must be a Base64 string or an array of Base64 strings.";
            echo json_encode($output, JSON_NUMERIC_CHECK);
            exit();
        }

        foreach ($aadharProofArray as $base64File) {
            if (!isset($base64File['data']) || !is_string($base64File['data'])) {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Invalid file format for aadhar proof. Expected Base64 encoded string.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $aadharProofBase64Codes[] = $base64File['data'];
            $fileData = $base64File['data'];
            $fileName = uniqid("aadhar_file_");
            $filePath = "";

            if (preg_match('/^data:application\/pdf;base64,/', $fileData)) {
                $fileName .= ".pdf";
                $filePath = "../Uploads/aadhar_pdfs/" . $fileName;
            } elseif (preg_match('/^data:image\/(\w+);base64,/', $fileData, $type)) {
                $fileName .= "." . strtolower($type[1]);
                $filePath = "../Uploads/aadhar_images/" . $fileName;
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Unsupported file type for aadhar proof.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $fileData = preg_replace('/^data:.*;base64,/', '', $fileData);
            $decodedFile = base64_decode($fileData);
            if ($decodedFile === false) {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Base64 decoding failed for aadhar proof.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $directory = dirname($filePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            if (file_put_contents($filePath, $decodedFile) === false) {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Failed to save the aadhar proof file.";
                echo json_encode($output, JSON_NUMERIC_CHECK);
                exit();
            }

            $publicPath = str_replace("../", "", $filePath);
            $networkPath = $base_url . "/" . $publicPath;
            $aadharProofPaths[] = $filePath;
        }
    }

    $proofJson = json_encode($proofPaths, JSON_UNESCAPED_SLASHES);
    $proofBase64CodeJson = json_encode($proofBase64Codes, JSON_UNESCAPED_SLASHES);
    $aadharProofJson = json_encode($aadharProofPaths, JSON_UNESCAPED_SLASHES);
    $aadharProofBase64CodeJson = json_encode($aadharProofBase64Codes, JSON_UNESCAPED_SLASHES);

    if (empty($name) || empty($mobile_number) || empty($customer_no)) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Name, mobile number, and customer number are required";
    } else {
        // Check if customer already exists by mobile_number
        $sql = "SELECT `id` FROM `customer` WHERE `mobile_number`=? AND `delete_at`=0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $mobile_number);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            // Check if customer_no is unique
            $sql = "SELECT `id` FROM `customer` WHERE `customer_no`=? AND `delete_at`=0";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $customer_no);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                $delete_at = '0';
                $sql = "INSERT INTO `customer` (`name`, `mobile_number`, `customer_no`, `customer_details`, `place`, `proof`, `proof_base64code`, `aadharproof`, `aadharproof_base64code`,`dateofbirth`,`proof_number`,`upload_type`, `create_at`, `delete_at`,`pincode`,`addtionsonal_mobile_number`,`reference`, `account_holder_name`, `bank_name`, `account_number`, `ifsc_code`, `branch_name`, `created_by_id`) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssssssssssssssssss", $name, $mobile_number, $customer_no, $customer_details, $place, $proofJson, $proofBase64CodeJson, $aadharProofJson, $aadharProofBase64CodeJson, $dateofbirth, $proof_number, $upload_type, $timestamp, $delete_at, $pincode, $addtionsonal_mobile_number, $reference, $account_holder_name, $bank_name, $account_number, $ifsc_code, $branch_name, $login_id);

                if ($stmt->execute()) {
                    $id = $conn->insert_id;
                    $uniqueCustomerID = uniqueID('CUST', $id);

                    $sql = "UPDATE `customer` SET `customer_id`=? WHERE `id`=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $uniqueCustomerID, $id);
                    $stmt->execute();

                    // Fetch the new row after insert and set customer_id
                    $new_json = null;
                    $sql_new = "SELECT * FROM `customer` WHERE `customer_id` = ? AND `delete_at` = 0";
                    $stmt_new = $conn->prepare($sql_new);
                    $stmt_new->bind_param("s", $uniqueCustomerID);
                    $stmt_new->execute();
                    $new_result = $stmt_new->get_result();
                    if ($new_row = $new_result->fetch_assoc()) {
                        $new_json = json_encode($new_row);
                    }
                    $stmt_new->close();

                    // Log to history
                    $remarks = "Customer created";
                    logCustomerHistory($conn, $uniqueCustomerID, $customer_no, "create", null, $new_json, $remarks, $login_id, $by_name);

                    $output["head"]["code"] = 200;
                    $output["head"]["msg"] = "Customer created successfully";
                } else {
                    $output["head"]["code"] = 400;
                    $output["head"]["msg"] = "Failed to create customer. Please try again.";
                }
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Customer number already exists.";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Customer with this mobile number already exists.";
        }
        $stmt->close();
    }
}

// <<<<<<<<<<===================== Delete Customer =====================>>>>>>>>>>
elseif (isset($obj['delete_customer_id'])) {
    if (!$conn || !($conn instanceof mysqli)) {
        $output["head"]["code"] = 500;
        $output["head"]["msg"] = "Database connection not established";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }
    if (!isset($obj['login_id']) || empty(trim($obj['login_id']))) {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Login ID is required";
        echo json_encode($output, JSON_NUMERIC_CHECK);
        exit;
    }
    $login_id = $conn->real_escape_string(trim($obj['login_id']));
    $by_name = isset($obj['user_name']) ? $conn->real_escape_string(trim($obj['user_name'])) : '';
    $delete_customer_id = $conn->real_escape_string(trim($obj['delete_customer_id']));

    // Fetch old row for history
    $old_json = null;
    $customer_no = '';
    $sql_old = "SELECT * FROM `customer` WHERE `customer_id` = ? AND `delete_at` = 0";
    $stmt_old = $conn->prepare($sql_old);
    $stmt_old->bind_param("s", $delete_customer_id);
    $stmt_old->execute();
    $old_result = $stmt_old->get_result();
    if ($old_row = $old_result->fetch_assoc()) {
        $old_json = json_encode($old_row);
        $customer_no = $old_row['customer_no'];
    }
    $stmt_old->close();



    if (!empty($delete_customer_id)) {
        $sql = "UPDATE `customer` SET `delete_at`=1, `deleted_by_id`=? WHERE `customer_id`=? AND `delete_at`=0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $login_id, $delete_customer_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            // Log to history
            $remarks = "Customer deleted";
            logCustomerHistory($conn, $delete_customer_id, $customer_no, "delete", $old_json, null, $remarks, $login_id, $by_name);

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Customer deleted successfully";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete customer. Customer may not exist.";
        }
        $stmt->close();
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Customer ID is required";
    }
} 
else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Invalid or missing parameters";
}

echo json_encode($output);
$conn->close();
