<?php
require_once 'includes/db.php';

try {
    $db = Database::connect();
    // Get the latest order
    $order = $db->orders->findOne([], ['sort' => ['created_at' => -1]]);

    echo "Latest Order ID: " . $order['_id'] . "\n";
    echo "Payment Method: " . ($order['payment_method'] ?? 'Not Set') . "\n";
    echo "Payment Receipt: " . ($order['payment_receipt'] ?? 'Not Set') . "\n";
    
    echo "\nFull Document:\n";
    print_r($order);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
