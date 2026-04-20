<?php
// auth/user_status.php
header("Content-Type: application/json");

// 1. Correct the filename to database.php
require_once '../config/database.php'; 

// 2. Call the function to get the connection
$conn = getConnection();

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Failed to get database connection."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['id']) && isset($data['is_active'])) {
    $id = intval($data['id']);
    $status = intval($data['is_active']);

    // 'is_active' is confirmed in your SQL dump
    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $status, $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "Database Error: " . $conn->error]);
    }
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["success" => false, "message" => "Missing data"]);
}
?>