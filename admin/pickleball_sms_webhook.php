<?php
// Pickleball SMS Webhook Handler
// Handles delivery status updates from SMS providers

include 'db.php';

// Get the raw POST data
$input = file_get_contents('php://input');

// Log the webhook data for debugging
error_log("SMS Webhook received: " . $input);

// Parse JSON data
$data = json_decode($input, true);

if (!$data) {
    // Try form data if JSON fails
    $data = $_POST;
}

if (!$data) {
    http_response_code(400);
    echo "Invalid data";
    exit;
}

// Determine provider based on data structure
$provider = 'unknown';
$message_id = null;
$status = 'unknown';

if (isset($data['MessageSid'])) {
    // Twilio
    $provider = 'twilio';
    $message_id = $data['MessageSid'];
    $status = $data['MessageStatus'];
} elseif (isset($data['message-id'])) {
    // Nexmo
    $provider = 'nexmo';
    $message_id = $data['message-id'];
    $status = $data['status'];
} elseif (isset($data['textId'])) {
    // Textbelt
    $provider = 'textbelt';
    $message_id = $data['textId'];
    $status = $data['status'] ?? 'delivered';
}

// Update notification status in database
if ($message_id && $status) {
    $stmt = $conn->prepare("
        UPDATE pickleball_sms_notifications 
        SET status = ?, delivered_at = ? 
        WHERE message_id = ? OR id = ?
    ");
    
    $delivered_at = ($status === 'delivered') ? date('Y-m-d H:i:s') : null;
    $status_mapping = [
        'sent' => 'sent',
        'delivered' => 'delivered',
        'failed' => 'failed',
        'undelivered' => 'failed',
        'queued' => 'pending'
    ];
    
    $db_status = $status_mapping[$status] ?? 'pending';
    
    $stmt->bind_param("ssss", $db_status, $delivered_at, $message_id, $message_id);
    $stmt->execute();
    $stmt->close();
    
    error_log("Updated SMS notification: $message_id to status: $db_status");
}

// Return success response
http_response_code(200);
echo "OK";
?>

