<?php
require_once 'db_config.php';


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    echo json_encode(["status" => "error", "message" => "No input received."]);
    exit;
}


if (isset($input['email']) && !isset($input['name']) && !isset($input['latitude'])) {
    $email = trim($input['email']);
    $db->where("email", $email);
    $user = $db->getOne("users");

    if ($user) {
        echo json_encode([
            "status" => "exists",
            "message" => "User already registered.",
            "user" => $user
        ]);
    } else {
        echo json_encode([
            "status" => "not_found",
            "message" => "User not found."
        ]);
    }
    exit;
}

try {
    $db->rawQuery("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        latitude DECIMAL(10,7),
        longitude DECIMAL(10,7),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $db->rawQuery("CREATE TABLE IF NOT EXISTS networks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT
    )");

    $db->rawQuery("CREATE TABLE IF NOT EXISTS user_networks (
        user_id INT NOT NULL,
        network_id INT NOT NULL,
        PRIMARY KEY (user_id, network_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (network_id) REFERENCES networks(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Table creation failed: " . $e->getMessage()]);
    exit;
}

//Location update only (auto on login) when the user logins then the locationis updated 
if (isset($input['email'], $input['latitude'], $input['longitude']) && !isset($input['name'])) {
    $email = trim($input['email']);
    $db->where("email", $email);
    $updated = $db->update("users", [
        "latitude" => floatval($input['latitude']),
        "longitude" => floatval($input['longitude']),
        "updated_at" => $db->now()
    ]);

    echo json_encode([
        "status" => $updated ? "success" : "error",
        "message" => $updated ? "Location updated." : "Location update failed or user not found."
    ]);
    exit;
}

//Full insert or update (name + email + lat/lng + network_ids) updating is done here 
if (!isset($input['name'], $input['email'], $input['latitude'], $input['longitude'], $input['network_ids'])) {
    echo json_encode(["status" => "error", "message" => "Missing required fields."]);
    exit;
}

$name = trim($input['name']);
$email = trim($input['email']);
$latitude = floatval($input['latitude']);
$longitude = floatval($input['longitude']);
$networks = $input['network_ids'];

$db->where("email", $email);
$user = $db->getOne("users");

$db->startTransaction();

try {
    if ($user) {
        
        $userId = $user['id'];
        $db->where("id", $userId);
        $db->update("users", [
            "name" => $name,
            "latitude" => $latitude,
            "longitude" => $longitude,
            "updated_at" => $db->now()
        ]);

        $db->where("user_id", $userId);
        $db->delete("user_networks");
    } else {
        
        $userId = $db->insert("users", [
            "name" => $name,
            "email" => $email,
            "latitude" => $latitude,
            "longitude" => $longitude,
            "updated_at" => $db->now()
        ]);

        if (!$userId) {
            throw new Exception("Insert failed: " . $db->getLastError());
        }
    }

    foreach ($networks as $networkName) {
        $db->where("name", $networkName);
        $existing = $db->getOne("networks", "id");
        $networkId = $existing ? $existing['id'] : $db->insert("networks", ["name" => $networkName]);

        if (!$networkId) {
            throw new Exception("Failed to insert/find network: " . $db->getLastError());
        }

        $db->insert("user_networks", [
            "user_id" => $userId,
            "network_id" => $networkId
        ]);
    }

    $db->commit();
    echo json_encode([
        "status" => "success",
        "message" => $user ? "User updated successfully." : "User registered successfully.",
        "user_id" => $userId
    ]);
} catch (Exception $e) {
    $db->rollback();
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
