<?php
require_once __DIR__ . '/pickleball_sms_lib.php';

class PickleballSMSService {
    private $conn;
    private $settings;

    public function __construct($connection) {
        $this->conn = $connection;
        $this->loadSettings();
    }

    private function loadSettings() {
        $row = pickleballSmsSettings($this->conn);
        $envSid = trim((string)getenv('TWILIO_ACCOUNT_SID'));
        $envToken = trim((string)getenv('TWILIO_AUTH_TOKEN'));
        $envFrom = pickleballNormalizePhone(getenv('TWILIO_FROM_NUMBER'));
        if ($envSid !== '' && $envToken !== '' && $envFrom !== '') {
            $row = $row ?: [];
            $row['provider'] = 'twilio';
            $row['api_key'] = $envSid;
            $row['api_secret'] = $envToken;
            $row['from_number'] = $envFrom;
            $row['is_active'] = 1;
            $row['from_env'] = true;
        }
        $this->settings = $row;
    }

    public function isConfigured() {
        return $this->settings
            && ($this->settings['provider'] ?? '') === 'twilio'
            && !empty($this->settings['api_key'])
            && !empty($this->settings['api_secret'])
            && pickleballNormalizePhone($this->settings['from_number'] ?? '') !== '';
    }

    public function usesEnvironmentCredentials() {
        return !empty($this->settings['from_env']);
    }

    public function sendSMS($phone_number, $message, $player_id = null, $template_id = null, $season_id = null, $game_id = null, $kind = null, $play_date = null) {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Add the Twilio account before sending texts.'];
        }

        $phone_number = $this->cleanPhoneNumber($phone_number);
        if (!$phone_number) {
            return ['success' => false, 'error' => 'Invalid phone number'];
        }

        $log_id = $this->logNotification($phone_number, $message, $player_id, $template_id, $season_id, $game_id, $kind, $play_date);
        $result = $this->sendViaProvider($phone_number, $message);
        $this->updateNotificationLog($log_id, $result);

        return $result;
    }

    private function cleanPhoneNumber($phone) {
        return pickleballNormalizePhone($phone);
    }
    
    private function logNotification($phone_number, $message, $player_id, $template_id, $season_id, $game_id, $kind = null, $play_date = null) {
        $columns = ['phone_number', 'message', 'status'];
        $values = ['?', '?', "'pending'"];
        $types = 'ss';
        $params = [$phone_number, $message];
        $optional = [
            'template_id' => $template_id ? (int)$template_id : null,
            'player_id' => $player_id ? (int)$player_id : null,
            'season_id' => $season_id ? (int)$season_id : null,
            'game_id' => $game_id ? (int)$game_id : null,
            'kind' => $kind ?: null,
            'play_date' => $play_date ?: null,
        ];
        foreach ($optional as $column => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $columns[] = $column;
            $values[] = '?';
            $types .= is_int($value) ? 'i' : 's';
            $params[] = $value;
        }
        $sql = 'INSERT INTO pickleball_sms_notifications (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $refs = [];
        foreach ($params as $index => $value) {
            $refs[$index] = &$params[$index];
        }
        $stmt->bind_param($types, ...$refs);
        $stmt->execute();
        $log_id = (int)$this->conn->insert_id;
        $stmt->close();
        return $log_id;
    }
    
    private function updateNotificationLog($log_id, $result) {
        if (!$log_id) {
            return;
        }
        $status = !empty($result['success']) ? 'sent' : 'failed';
        $error_message = !empty($result['success']) ? null : ($result['error'] ?? 'Unknown error');
        $sent_at = !empty($result['success']) ? date('Y-m-d H:i:s') : null;
        $message_id = $result['message_id'] ?? null;
        $stmt = $this->conn->prepare("
            UPDATE pickleball_sms_notifications
            SET status = ?, error_message = ?, sent_at = ?, message_id = ?
            WHERE id = ?
        ");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param("ssssi", $status, $error_message, $sent_at, $message_id, $log_id);
        $stmt->execute();
        $stmt->close();
    }
    
    private function sendViaProvider($phone_number, $message) {
        switch ($this->settings['provider']) {
            case 'twilio':
                return $this->sendViaTwilio($phone_number, $message);
            case 'nexmo':
                return $this->sendViaNexmo($phone_number, $message);
            case 'textbelt':
                return $this->sendViaTextbelt($phone_number, $message);
            default:
                return ['success' => false, 'error' => 'Unsupported SMS provider'];
        }
    }
    
    private function sendViaTwilio($phone_number, $message) {
        $account_sid = $this->settings['api_key'];
        $auth_token = $this->settings['api_secret'];
        $from_number = pickleballNormalizePhone($this->settings['from_number']);
        $url = "https://api.twilio.com/2010-04-01/Accounts/" . rawurlencode($account_sid) . "/Messages.json";
        $data = [
            'From' => $from_number,
            'To' => $phone_number,
            'Body' => $message,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $account_sid . ':' . $auth_token);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $response = curl_exec($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => $curl_error !== '' ? $curl_error : 'Could not reach Twilio'];
        }
        $payload = json_decode($response, true);
        if ($http_code === 201) {
            return ['success' => true, 'message_id' => $payload['sid'] ?? ''];
        }
        return ['success' => false, 'error' => $payload['message'] ?? 'Twilio rejected the text'];
    }
    
    private function sendViaNexmo($phone_number, $message) {
        $api_key = $this->settings['api_key'];
        $api_secret = $this->settings['api_secret'];
        $from_number = $this->settings['from_number'];
        
        $url = "https://rest.nexmo.com/sms/json";
        
        $data = [
            'api_key' => $api_key,
            'api_secret' => $api_secret,
            'to' => $phone_number,
            'from' => $from_number,
            'text' => $message
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($result['messages'][0]['status'] == '0') {
            return ['success' => true, 'message_id' => $result['messages'][0]['message-id']];
        } else {
            return ['success' => false, 'error' => $result['messages'][0]['error-text']];
        }
    }
    
    private function sendViaTextbelt($phone_number, $message) {
        $api_key = $this->settings['api_key'];
        
        $url = "https://textbelt.com/text";
        
        $data = [
            'phone' => $phone_number,
            'message' => $message,
            'key' => $api_key
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($result['success']) {
            return ['success' => true, 'message_id' => $result['textId']];
        } else {
            return ['success' => false, 'error' => $result['error']];
        }
    }
    
    public function getTemplates() {
        $result = $this->conn->query("SELECT * FROM pickleball_sms_templates WHERE is_active = 1 ORDER BY template_type, name");
        $templates = [];
        while ($row = $result->fetch_assoc()) {
            $templates[] = $row;
        }
        return $templates;
    }
    
    public function getTemplate($template_id) {
        $stmt = $this->conn->prepare("SELECT * FROM pickleball_sms_templates WHERE id = ?");
        $stmt->bind_param("i", $template_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $template = $result->fetch_assoc();
        $stmt->close();
        return $template;
    }
    
    public function processTemplate($template, $variables) {
        $message = $template['message_template'];
        
        foreach ($variables as $key => $value) {
            $message = str_replace('{' . $key . '}', $value, $message);
        }
        
        return $message;
    }
    
    public function sendBulkSMS($player_ids, $template_id, $variables = []) {
        $template = $this->getTemplate($template_id);
        if (!$template) {
            return ['success' => false, 'error' => 'Template not found'];
        }
        
        $results = [];
        $success_count = 0;
        $failure_count = 0;
        
        foreach ($player_ids as $player_id) {
            // Get player info
            $stmt = $this->conn->prepare("SELECT name, phone FROM pickleball_players WHERE id = ?");
            $stmt->bind_param("i", $player_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $player = $result->fetch_assoc();
            $stmt->close();
            
            if (!$player || !$player['phone']) {
                $results[] = ['player_id' => $player_id, 'success' => false, 'error' => 'No phone number'];
                $failure_count++;
                continue;
            }
            
            // Add player-specific variables
            $player_variables = array_merge($variables, [
                'player_name' => $player['name']
            ]);
            
            $message = $this->processTemplate($template, $player_variables);
            
            $result = $this->sendSMS($player['phone'], $message, $player_id, $template_id);
            
            if ($result['success']) {
                $success_count++;
            } else {
                $failure_count++;
            }
            
            $results[] = array_merge(['player_id' => $player_id], $result);
        }
        
        return [
            'success' => true,
            'total' => count($player_ids),
            'success_count' => $success_count,
            'failure_count' => $failure_count,
            'results' => $results
        ];
    }
    
    public function getNotificationHistory($limit = 50) {
        $stmt = $this->conn->prepare("
            SELECT sn.*, p.name as player_name
            FROM pickleball_sms_notifications sn
            LEFT JOIN pickleball_players p ON sn.player_id = p.id
            ORDER BY sn.created_at DESC
            LIMIT ?
        ");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
        return $notifications;
    }
}
?>




