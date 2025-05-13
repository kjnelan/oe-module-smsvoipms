<?php

namespace OpenEMR\Modules\SmsVoipms\Service;

class SmsSender
{
    public function send($to, $message, $user = 'system')
    {
        $username = $GLOBALS['sms_voipms_username'] ?? '';
        $password = $GLOBALS['sms_voipms_password'] ?? '';
        $did      = $GLOBALS['sms_voipms_did'] ?? '';

        if (empty($username) || empty($password) || empty($did)) {
            $error = 'Missing VOIP.ms API credentials or DID.';
            $this->log($to, $message, 'error', $error, $user);
            return ['status' => 'error', 'error' => $error];
        }

        $to = $this->normalizePhone($to);
        error_log("💬 SmsSender::send() was called with: $to");

        $url = "https://voip.ms/api/v1/rest.php?" . http_build_query([
            'api_username' => $username,
            'api_password' => $password,
            'method'       => 'sendSMS',
            'did'          => $did,
            'dst'          => $to,
            'message'      => $message,
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $this->log($to, $message, 'error', $curl_error, $user);
            return ['status' => 'error', 'error' => $curl_error];
        }

        $decoded = json_decode($result, true);

        if ($decoded['status'] === 'success') {
            $this->log($to, $message, 'success', $result, $user);
            return ['status' => 'success', 'message_id' => $decoded['sms']];
        }

        $this->log($to, $message, 'error', $result, $user);  // Log raw API error response
        return ['status' => 'error', 'error' => $error];
    }

    private function log($to, $message, $status, $response, $user)
    {
        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO sms_voipms_log (datetime, phone_number, message, status, response, user)
                VALUES (?, ?, ?, ?, ?, ?)";
        sqlStatement($sql, [$now, $to, $message, $status, $response, $user]);
    }

    private function normalizePhone($raw)
    {
        $digits = preg_replace('/[^0-9]/', '', $raw);

        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }

        if (strlen($digits) === 11 && $digits[0] === '1') {
            return '+' . $digits;
        }

        return '+' . $digits;
    }
}
