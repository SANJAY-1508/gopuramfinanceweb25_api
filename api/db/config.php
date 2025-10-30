<?php
$name = "localhost";
$username = "root";
$password = "";
$database = "gopuramfinanceweb";

$conn = new mysqli($name, $username, $password, $database);

if ($conn->connect_error) {
    $output = array();
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "DB Connection Lost...";

    echo json_encode($output, JSON_NUMERIC_CHECK);
}


// <<<<<<<<<<===================== Function For Check Numbers Only =====================>>>>>>>>>>

function numericCheck($data)
{
    if (!preg_match('/[^0-9]+/', $data)) {
        return true;
    } else {
        return false;
    }
}

// <<<<<<<<<<===================== Function For Check Alphabets Only =====================>>>>>>>>>>

function alphaCheck($data)
{
    if (!preg_match('/[^a-zA-Z]+/', $data)) {
        return true;
    } else {
        return false;
    }
}

// <<<<<<<<<<===================== Function For Check Alphabets and Numbers Only =====================>>>>>>>>>>

function alphaNumericCheck($data)
{
    if (!preg_match('/[^a-zA-Z0-9]+/', $data)) {
        return true;
    } else {
        return false;
    }
}

// <<<<<<<<<<===================== Function for checking user exist or not =====================>>>>>>>>>>
function userExist($user)
{
    global $conn;

    $checkUser = $conn->query("SELECT `Name` FROM `users` WHERE `user_id`='$user'");
    if ($checkUser->num_rows > 0) {
        return true;
    } else {
        return false;
    }
}

function staffExist($staff)
{
    global $conn;

    $checkUser = $conn->query("SELECT `staff_name` FROM `staff` WHERE `id`='$staff'");
    if ($checkUser->num_rows > 0) {
        return true;
    } else {
        return false;
    }
}

function customerExist($customer)
{
    global $conn;

    $checkUser = $conn->query("SELECT `customer_name` FROM `customer` WHERE `id`='$customer'");
    if ($checkUser->num_rows > 0) {
        return true;
    } else {
        return false;
    }
}

// <<<<<<<<<<===================== Function for get the username =====================>>>>>>>>>>
function getUserName($user)
{
    global $conn;
    $result = "";

    $checkUser = $conn->query("SELECT `name` FROM `user` WHERE `id`='$user'");
    if ($checkUser->num_rows > 0) {
        if ($userData = $checkUser->fetch_assoc()) {
            $result = $userData['name'];
        }
    } else {
        $checkUser = $conn->query("SELECT `staff_name` FROM `staff` WHERE `id`='$user'");
        if ($userData = $checkUser->fetch_assoc()) {
            $result = $userData['staff_name'];
        }
    }

    return $result;
}

function convertUniqueName($value)
{

    $value = str_replace(' ', '', $value);
    $value = strtolower($value);

    return $value;
}

function getCategoryId($namecategory)
{
    global $conn;

    $Getcategory_id = $conn->query("SELECT `id` FROM `category` WHERE name LIKE '%$namecategory%'");
    $row = $Getcategory_id->fetch_row();
    $category_id = $row[0];

    return $category_id;
}
function generateReceiptNo()
{
    global $conn;

    // Query to get the maximum receipt number from the database
    $sql = "SELECT MAX(CAST(recipt_no AS UNSIGNED)) AS max_recipt_no FROM pawnjewelry";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastReciptNo = $row['max_recipt_no'];

        if ($lastReciptNo) {
            // Increment the last receipt number by 1
            $newReciptNo = intval($lastReciptNo) + 1;
        } else {
            // If no valid receipt number is found, start with 1001
            $newReciptNo = '101';
        }
    } else {
        // If no records are found, start with 1001
        $newReciptNo = '101';
    }

    return $newReciptNo;
}



function generateReceiptesitmateNo()
{
    global $conn;

    // Query to get the maximum receipt number from the database
    $sql = "SELECT MAX(CAST(recipt_no AS UNSIGNED)) AS max_recipt_no FROM pawnjewelry_estimate";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastReciptNo = $row['max_recipt_no'];

        if ($lastReciptNo) {

            // Increment the last receipt number by 1
            $newReciptNo = intval($lastReciptNo) + 1;
        } else {
            // If no valid receipt number is found, start with 001
            $newReciptNo = '101';
        }
    } else {
        // If no records are found, start with 001
        $newReciptNo = '101';
    }

    return $newReciptNo;
}


function getGroupName($GroupID)
{
    global $conn;

    $sql_Group = $conn->query("SELECT `Group_type` FROM `groups` WHERE (`Group_id` = '$GroupID' OR Group_type ='$GroupID') AND `delete_at` ='0'");
    if ($sql_Group->num_rows > 0) {
        $row = $sql_Group->fetch_row();
        $Group_type = $row[0];

        return $Group_type;
    } else {
        return null;
    }
}

function getCategoryName($CategoryID)
{
    global $conn;

    $sql_Group = $conn->query("SELECT `Category_type` FROM `category` WHERE `category_id` = '$CategoryID' AND `delete_at` ='0'");
    if ($sql_Group->num_rows > 0) {
        $row = $sql_Group->fetch_row();
        $Category_type = $row[0];

        return $Category_type;
    } else {
        return null;
    }
}

function pngImageToWebP($data, $file_path)
{
    // Check if the GD extension is available
    if (!extension_loaded('gd')) {
        echo 'GD extension is not available. Please install or enable the GD extension.';
        return false;
    }

    // Decode the base64 image data
    $imageData = base64_decode($data);

    // Create an image resource from the PNG data
    $sourceImage = imagecreatefromstring($imageData);

    if ($sourceImage === false) {
        echo 'Failed to create the source image.';
        return false;
    }
    //dyanamic file path
    date_default_timezone_set('Asia/Calcutta');

    $timestamp = date('Y-m-d H:i:s');

    $timestamp = str_replace(array(" ", ":"), "-", $timestamp);

    $file_pathnew = $file_path . $timestamp . ".webp";

    $retunfilename = $timestamp . ".webp";
    try {
        // Convert PNG to WebP
        if (!imagewebp($sourceImage, $file_pathnew, 80)) {
            echo 'Failed to convert PNG to WebP.';
            return false;
        }
    } catch (\Throwable $th) {
        echo $th;
    }



    // Free up memory
    imagedestroy($sourceImage);

    //echo 'WebP image saved successfully.';
    return $retunfilename;
}

function isBase64ImageValid($base64Image)
{
    // Check if the provided string is a valid base64 string
    if (!preg_match('/^(data:image\/(png|jpeg|jpg|gif);base64,)/', $base64Image)) {
        return false;
    }

    // Remove the data URI prefix
    $base64Image = str_replace('data:image/png;base64,', '', $base64Image);
    $base64Image = str_replace('data:image/jpeg;base64,', '', $base64Image);
    $base64Image = str_replace('data:image/jpg;base64,', '', $base64Image);
    $base64Image = str_replace('data:image/gif;base64,', '', $base64Image);

    // Check if the remaining string is a valid base64 string
    if (!base64_decode($base64Image, true)) {
        return false;
    }

    // Check if the decoded data is a valid image
    $image = imagecreatefromstring(base64_decode($base64Image));
    if (!$image) {
        return false;
    }

    // Clean up resources
    imagedestroy($image);

    return true;
}


function ImageRemove($string, $id)
{
    global $conn;
    $status = "No Data Updated";
    if ($string == "user") {
        $sql_user = "UPDATE `user` SET `img`=null WHERE `id` ='$id' ";
        if ($conn->query($sql_user) === TRUE) {
            $status = "User Image Removed Successfully";
        } else {
            $status = "User Image Not Removed !";
        }
    } else if ($string == "staff") {
        $sql_staff = "UPDATE `staff` SET `img`=null WHERE `id`='$id' ";
        if ($conn->query($sql_staff) === TRUE) {
            $status = "staff Image Removed Successfully";
        } else {
            $status = "staff Image Not Removed !";
        }
    } else if ($string == "company") {
        $sql_company = "UPDATE `company` SET  `img`=null WHERE `id`='$id' ";
        if ($conn->query($sql_company) === TRUE) {
            $status = "company Image Removed Successfully";
        } else {
            $status = "company Image Not Removed !";
        }
    } else if ($string == "product") {
        $sql_products = " UPDATE `products` SET `img`=null WHERE `id`='$id' ";
        if ($conn->query($sql_products) === TRUE) {
            $status = "products Image Removed Successfully";
        } else {
            $status = "products Image Not Removed !";
        }
    }
    return $status;
}
function uniqueID($prefix_name, $auto_increment_id)
{

    date_default_timezone_set('Asia/Calcutta');
    $timestamp = date('Y-m-d H:i:s');
    $encryptId = $prefix_name . "_" . $timestamp . "_" . $auto_increment_id;

    $hashid = md5($encryptId);

    return $hashid;
}

/**
 * Get current balance
 */
function getBalance($conn)
{
    $query = "SELECT total_balance FROM balance LIMIT 1";
    $result = $conn->query($query);
    $balance = $result->fetch_assoc();
    return $balance["total_balance"] ?? 0;
}

/**
 * Add a transaction (Expense = patru, Income = varavu)
 */
function addTransaction($conn, $description, $amount, $type, $date)
{
    if (!$description || $amount <= 0 || !in_array($type, ['varavu', 'patru', 'balance'])) {
        return ["head" => ["code" => 400, "msg" => "Invalid input!"]];
    }

    $current_balance = getBalance($conn);

    if ($type !== 'balance') {
        // Check if balance is sufficient for expense
        if ($type === 'patru' && $current_balance < $amount) {
            return ["head" => ["code" => 400, "msg" => "Insufficient balance!"]];
        }

        // Update balance based on type
        $new_balance = ($type == 'patru') ? $current_balance - $amount : $current_balance + $amount;
        $update_balance_query = "UPDATE balance SET total_balance = ? WHERE balance_id = 1";

        $stmt = $conn->prepare($update_balance_query);
        $stmt->bind_param("d", $new_balance); // Bind new_balance as a double
        if (!$stmt->execute()) {
            return ["head" => ["code" => 500, "msg" => "Database error while updating balance!"]];
        }
        $stmt->close();
    } else {
        $type = "varavu"; // Change type from balance to varavu
    }

    // Insert transaction record using prepared statement
    $insert_transaction_query = "INSERT INTO transactions (description, amount, type,transaction_date) VALUES (?, ?, ?,?)";
    $stmt = $conn->prepare($insert_transaction_query);
    $stmt->bind_param("sdss", $description, $amount, $type, $date); // s = string, d = double, s = string

    if ($stmt->execute()) {
        $stmt->close();
        return ["head" => ["code" => 200, "msg" => "Transaction recorded successfully!"]];
    } else {
        $stmt->close();
        return ["head" => ["code" => 500, "msg" => "Database error while inserting transaction!"]];
    }
}


/**
 * List all transactions
 */
function listTransactions($conn)
{
    $query = "SELECT * FROM transactions ORDER BY transaction_date DESC";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}
function logCustomerHistory($conn, $customer_id, $customer_no, $action_type, $old_value, $new_value, $remarks, $by_id, $by_name)
{
    $log_timestamp = date('Y-m-d H:i:s');
    $old_value = $old_value ?? '{}';
    $new_value = $new_value ?? '{}';
    $sql = "INSERT INTO `customer_history` (`customer_id`, `customer_no`, `action_type`, `old_value`, `new_value`, `remarks`, `create_by_name`, `create_by_id`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssss", $customer_id, $customer_no, $action_type, $old_value, $new_value, $remarks, $by_name, $by_id, $log_timestamp);
    return $stmt->execute();
}
