<?php
include 'db/config.php';

$loginpass = 0;
$auth = getallheaders();
if (isset($auth['auth_token'])) {
    $authtoken = $auth['auth_token'];
    if (!preg_match('/[^a-zA-Z0-9]+/', $authtoken) && strlen($authtoken) == 32) {
        $checktoken = $conn->query("SELECT `user_id` FROM `sessions` WHERE `session_id`='$authtoken'");
        if ($checktoken->num_rows > 0) {
            if ($tokendata = $checktoken->fetch_assoc()) {
                $logged_in_id = $tokendata['user_id'];
                $loginpass = 1;
            }
        }
    }
}