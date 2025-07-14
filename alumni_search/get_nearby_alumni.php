<?php
require_once 'db_config.php';


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['email'], $input['radius'])) {
    echo json_encode(["status" => "error", "message" => "Email and radius are required."]);
    exit;
}

$email = trim($input['email']);
$radius = floatval($input['radius']);

$db->where("email", $email);
$currentUser = $db->getOne("users");

if (!$currentUser) {
    echo json_encode(["status" => "error", "message" => "User not found."]);
    exit;
}

$currentLat = $currentUser['latitude'];
$currentLng = $currentUser['longitude'];
$currentUserId = $currentUser['id'];

$db->where("user_id", $currentUserId);
$networkLinks = $db->get("user_networks", null, "network_id");

if (empty($networkLinks)) {
    echo json_encode(["status" => "success", "alumni" => []]);
    exit;
}

$networkIds = array_column($networkLinks, "network_id");

$alumni = $db->rawQuery("
    SELECT u.id, u.name, u.email, u.latitude, u.longitude
    FROM users u
    JOIN user_networks un ON u.id = un.user_id
    WHERE un.network_id IN (" . implode(',', $networkIds) . ")
    AND u.id != ?
    GROUP BY u.id
    HAVING (
        6371 * acos(
            cos(radians(?)) * cos(radians(u.latitude)) *
            cos(radians(u.longitude) - radians(?)) +
            sin(radians(?)) * sin(radians(u.latitude))
        )
    ) <= ?
", [$currentUserId, $currentLat, $currentLng, $currentLat, $radius]);

foreach ($alumni as &$alum) {
    $db->where("user_id", $alum['id']);
    $nets = $db->join("networks n", "n.id = un.network_id", "INNER")
               ->get("user_networks un", null, "n.name");
    $alum['networks'] = array_column($nets, 'name');
}

echo json_encode(["status" => "success", "alumni" => $alumni]);
?>
