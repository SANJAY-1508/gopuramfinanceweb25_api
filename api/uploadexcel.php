<?php
// Include database connection
include 'headers.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Calcutta');

// Read JSON input
$inputJSON = file_get_contents('php://input');
$obj = json_decode($inputJSON, true);

// Check for JSON errors
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["head" => ["code" => 400, "msg" => "Invalid JSON format"]]);
    exit();
}

// Validate the required pawnjewelry_data
if (isset($obj['pawnjewelry_data']) && is_array($obj['pawnjewelry_data'])) {
    foreach ($obj['pawnjewelry_data'] as $data) {
        // Assign data to variables with validation
        $recipt_no = $data['recipt_no'] ?? null;
        $customer_no = $data['customer_no'] ?? null;
        $customer_name = $data['customer_name'] ?? '';
        $name_of_guardians = $data['name_of_guardians'] ?? '';
        $mobile_number = $data['mobile_number'] ?? '';
        $address = $data['address'] ?? '';
        $group = $data['group'] ?? '';
        $jewel_original_rate = $data['jewel_original_rate'] ?? 0;
        $pawn_rate = $data['pawn_rate'] ?? 0;
        $pawn_interest = $data['pawn_interest'] ?? 0;
        $pawn_interest_amount = $data['pawn_interest_amount'] ?? 0;
        $pawnjewelry_date = $data['pawnjewelry_date'] ?? null;
        $pawnjewelry_recovery_date = $data['pawnjewelry_recovery_date'] ?? null;
        $pawnjewelry_recovery_finshed_date = $data['pawnjewelry_recovery_finshed_date'] ?? null;
        $remark_jewel_pawn = $data['remark_jewel_pawn'] ?? '';
        $createdby = $data['createdby'] ?? '';
        $paidby = $data['paidby'] ?? '';

        $jewel_product_json = isset($data['jewel_product']) ? json_encode($data['jewel_product']) : json_encode([]);
 
        // Check if the receipt number already exists
        $checkQuery = $conn->prepare("SELECT id FROM pawnjewelry WHERE recipt_no = ? AND delete_at = 0");
        $checkQuery->bind_param("s", $recipt_no);
        $checkQuery->execute();
        $result = $checkQuery->get_result();

        if ($result->num_rows == 0) {
            // Insert pawn jewelry details
          

           $insertQuery = $conn->prepare("
        INSERT INTO pawnjewelry (
            recipt_no, customer_no, customer_name, name_of_guardians, mobile_number, address, 
            `group`, jewel_original_rate, pawn_rate, pawn_interest, pawn_interest_amount, 
            pawnjewelry_date, pawnjewelry_recovery_date, pawnjewelry_recovery_finshed_date, 
            remark_jewel_pawn, createdby, paidby, jewel_product, create_at, delete_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)
    ");

    $insertQuery->bind_param(
        "sisssssddddsssssss",
        $recipt_no, $customer_no, $customer_name, $name_of_guardians, $mobile_number, $address, 
        $group, $jewel_original_rate, $pawn_rate, $pawn_interest, $pawn_interest_amount, 
        $pawnjewelry_date, $pawnjewelry_recovery_date, $pawnjewelry_recovery_finshed_date, 
        $remark_jewel_pawn, $createdby, $paidby, json_encode($data['jewel_product']) // Convert jewel product to JSON
    );


            if ($insertQuery->execute()) {
               
                $pawnJewelryId = $conn->insert_id;
                $id = $conn->insert_id;
                $pawnJewelryUniqueID = uniqueID('pawnjewelry', $id);

                // Update the `pawnjewelry_id` field with the unique ID
                $updatePawnJewelryId = $conn->prepare("UPDATE pawnjewelry SET pawnjewelry_id = ? WHERE id = ?");
                $updatePawnJewelryId->bind_param("si", $pawnJewelryUniqueID, $id);
                $updatePawnJewelryId->execute();
                // Insert jewel products if they exist
               

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Pawn jewelry data inserted successfully";
            } else {
                $output["head"]["code"] = 500;
                $output["head"]["msg"] = "Failed to insert pawn jewelry data.";
            }
        } else {
            $output["head"]["code"] = 409;
            $output["head"]["msg"] = "Receipt number already exists.";
        }
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Invalid data format or missing pawnjewelry_data.";
}

echo json_encode($output);
?>
