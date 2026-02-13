<?php
session_start();
require_once '../../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'vendor') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $db = Database::connect();
    
    // Get vendor ID
    $user_id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
    $vendor = $db->vendors->findOne(['user_id' => $user_id]);
    
    if (!$vendor) {
        echo json_encode(['error' => 'Vendor not found']);
        exit();
    }
    
    // Count pending orders
    $pending_count = $db->orders->countDocuments([
        'vendor_id' => $vendor['_id'],
        'status' => 'pending'
    ]);
    
    echo json_encode([
        'pending_count' => $pending_count,
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
