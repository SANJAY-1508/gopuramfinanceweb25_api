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

// <<<<<<<<<<===================== This is to list Products =====================>>>>>>>>>>
if (isset($obj->search_text)) {
    $search_text = $conn->real_escape_string($obj->search_text); // Sanitize input
    $sql = "SELECT * FROM `product` 
            WHERE `delete_at` = 0 
            AND (`product_eng` LIKE '%$search_text%' OR `product_tam` LIKE '%$search_text%') 
            ORDER BY `id` ASC";
    
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["product"][] = $row;
        }
    } else {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "No products found";
        $output["body"]["product"] = [];
    }
} 

// <<<<<<<<<<===================== This is to Create or Update Product =====================>>>>>>>>>>
elseif (isset($obj->edit_product_id)) {
    $edit_id = $obj->edit_product_id;
    $product_eng = $obj->product_eng;
    $product_tam = $obj->product_tam;
    $group_id = $obj->group_id;
    
    $group_name = getGroupName($group_id);

    $updateProduct = "UPDATE `product` SET `product_eng`='$product_eng', `product_tam`='$product_tam',`group_id` = '$group_id',`group_name` = '$group_name' WHERE `product_id`='$edit_id'";
    if ($conn->query($updateProduct)) {
        $output["head"]["code"] = 200;
        $output["head"]["msg"] = "Product updated successfully";
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Failed to update product. Please try again.";
    }
}
elseif (isset($obj->product_eng) && isset($obj->product_tam)) {
    // Create Product
    $product_eng = $obj->product_eng;
    $product_tam = $obj->product_tam;
    $group_id = $obj->group_id;
    
    $group_name = getGroupName($group_id);

    // Check if the product already exists
    $productCheck = $conn->query("SELECT `id` FROM `product` WHERE `product_eng`='$product_eng' AND delete_at = 0");
    if ($productCheck->num_rows == 0) {
        // Insert new product
        $createProduct = "INSERT INTO `product`(`product_eng`, `product_tam`, `create_at`, `delete_at`,`group_id`,`group_name`) VALUES ('$product_eng','$product_tam','$timestamp','0','$group_id','$group_name')";
        if ($conn->query($createProduct)) {
            // Get the auto-increment ID of the newly inserted row
            $id = $conn->insert_id;

            // Generate a unique ID for the product
            $uniqueProductID = uniqueID('product', $id);

            // Update the `product_id` field with the unique ID
            $updateProductId = "UPDATE `product` SET `product_id`='$uniqueProductID' WHERE `id`='$id'";
            $conn->query($updateProductId);

            // Respond with success
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Product created successfully";
        } else {
            // Handle query failure
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to connect. Please try again.";
        }
    } else {
        // If the product already exists
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Product already exists.";
    }
}



// <<<<<<<<<<===================== This is to Delete Product =====================>>>>>>>>>>
else if (isset($obj->delete_product_id)) {
    $delete_product_id = $obj->delete_product_id;

    if (!empty($delete_product_id)) {
        $deleteProduct = "UPDATE `product` SET `delete_at`=1 WHERE `product_id`='$delete_product_id'";
        if ($conn->query($deleteProduct)) {
            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Product deleted successfull";
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "Failed to delete product. Please try again.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
}
 else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
?>
