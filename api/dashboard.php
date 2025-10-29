<?php

include 'headers.php';
header('Content-Type: application/json; charset=utf-8');

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');


if (isset($obj->search_text)) {
    $customer = 0;
    $enquiry = 0;
    $invoice = 0;
    $products = 0;
    $sqlcus = "SELECT COUNT(`id`) FROM `customer` WHERE `deleted_at`=0";
    $result = $conn->query($sqlcus);
    $sqlenq = "SELECT COUNT(`id`) FROM `enquiry` WHERE `deleted_at`=0";
    $resultenq = $conn->query($sqlenq);
    $sqlinv = "SELECT COUNT(`id`) FROM `invoice` WHERE `deleted_at`=0";
    $resultinv = $conn->query($sqlinv);
    $sqlpro = "SELECT COUNT(`id`) FROM `products` WHERE `deleted_at`='false'";
    $resultpro = $conn->query($sqlpro);

    if ($result->num_rows >= 0 || $resultenq->num_rows >= 0 || $resultinv->num_rows >= 0 || $resultpro->num_rows >= 0) {
            $row=$result->fetch_row();
            $customer = $row[0];
            $rowenq = $resultenq->fetch_row();
            $enquiry = $rowenq[0];
            $rowinv=$resultinv->fetch_row();
            $invoice=$rowinv[0];
            $rowpro=$resultpro->fetch_row();
            $products=$rowpro[0];

            $output["head"]["code"] = 200;
            $output["head"]["msg"] = "Success";
            $output["body"]["Dashboard"]["customer"] = $customer;
            $output["body"]["Dashboard"]["enquiry"] = $enquiry;
            $output["body"]["Dashboard"]["invoice"] = $invoice;
            $output["body"]["Dashboard"]["product"] = $products;
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Dashboard Count Not Found";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);