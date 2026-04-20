<?php
// /auth/user.php

// 1. Require your database config
require_once '../config/database.php'; 

// 2. Initialize the connection using your custom function
$conn = getConnection();

try {
    // --- HANDLE GET REQUEST (Fetch Users) ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $role = isset($_GET['role']) ? $_GET['role'] : '';

        // Base Query using the columns from your brgy_data.sql users table
        // We MUST include is_active here so the Dashboard knows if the account is working!
        $query = "SELECT id, name, email, role, created_at, is_active FROM users WHERE 1=1";
        $params = [];
        $types = "";

        // Add search filters if they exist
        if (!empty($search)) {
            $query .= " AND (name LIKE ? OR email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $types .= "ss";
        }

        // Add role filters if they exist
        if (!empty($role)) {
            $query .= " AND role = ?";
            $params[] = $role;
            $types .= "s";
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $conn->prepare($query);
        
        // Safety check if the query fails to prepare
        if ($stmt === false) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        // Bind parameters dynamically
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }

        // Return the exact JSON structure the frontend expects
        echo json_encode([
            "success" => true,
            "data" => $users
        ]);
        exit;
    }

    // --- HANDLE DELETE REQUEST (Delete User) ---
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt === false) {
                throw new Exception("Database prepare error: " . $conn->error);
            }
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            echo json_encode(["success" => true, "message" => "User deleted"]);
        } else {
            echo json_encode(["success" => false, "message" => "Invalid ID"]);
        }
        exit;
    }

} catch (Exception $e) {
    // Catch any DB errors and output them cleanly as JSON
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server Error: " . $e->getMessage()
    ]);
}
?>