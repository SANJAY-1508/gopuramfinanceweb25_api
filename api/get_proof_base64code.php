<?php
include 'headers.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}
header('Content-Type: application/json; charset=utf-8');

$postData = json_decode(file_get_contents("php://input"));

if (isset($postData->pawn_id)) {
    $pawn_id = intval($postData->pawn_id);
    $sql = "SELECT proof_base64code FROM pawnjewelry WHERE id = $pawn_id AND delete_at = 0 LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["proof_base64code" => json_decode($row['proof_base64code'], true)],
        ]);
    } else {
        echo json_encode(["head" => ["code" => 404, "msg" => "Data not found"]]);
    }
}
?>
