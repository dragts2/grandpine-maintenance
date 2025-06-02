<?php
// webhook.php - Handler for UptimeRobot webhooks
// Deploy this on Railway.app for free hosting

// Configuration
$api_token = "YOUR_API_TOKEN_HERE";
$zone_id = "YOUR_ZONE_ID_HERE";
$record_id = "YOUR_DNS_RECORD_ID_HERE";
$domain = "grandpineresort.icu";

// DNS targets
$tunnel_target = "YOUR_TUNNEL_UUID.cfargotunnel.com";
$maintenance_target = "grandpineresort-maintenance.github.io";

// Security: Verify the request (optional but recommended)
$expected_secret = "your_webhook_secret_here";
$received_secret = $_GET['secret'] ?? '';

if ($received_secret !== $expected_secret) {
    http_response_code(403);
    die('Unauthorized');
}

// Get the alert type from UptimeRobot
$alert_type = $_POST['alertType'] ?? '';
$monitor_name = $_POST['monitorFriendlyName'] ?? '';

// Log the webhook
error_log("Webhook received: $alert_type for $monitor_name");

function updateDNS($target, $type = 'CNAME') {
    global $api_token, $zone_id, $record_id, $domain;
    
    $data = [
        'type' => $type,
        'name' => $domain,
        'content' => $target,
        'ttl' => 60,
        'proxied' => true
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones/$zone_id/dns_records/$record_id");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_token,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($http_code === 200 && $result['success']) {
        error_log("DNS updated successfully to: $target");
        return true;
    } else {
        error_log("DNS update failed: " . $response);
        return false;
    }
}

// Handle the webhook
switch ($alert_type) {
    case '1': // Down
        error_log("Site DOWN - Switching to maintenance page");
        if (updateDNS($maintenance_target)) {
            echo "Switched to maintenance page";
        } else {
            echo "Failed to switch DNS";
        }
        break;
        
    case '2': // Up
        error_log("Site UP - Switching back to tunnel");
        if (updateDNS($tunnel_target)) {
            echo "Switched back to main site";
        } else {
            echo "Failed to switch DNS";
        }
        break;
        
    default:
        error_log("Unknown alert type: $alert_type");
        echo "Unknown alert type";
        break;
}

// Send notification email (optional)
if ($alert_type === '1') {
    $subject = "🚧 $domain switched to maintenance page";
    $message = "Your website is down. DNS has been switched to the maintenance page.";
    mail('your-email@example.com', $subject, $message);
}
?>
