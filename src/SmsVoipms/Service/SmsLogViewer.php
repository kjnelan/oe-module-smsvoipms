<?php

namespace OpenEMR\Modules\SmsVoipms\Service;

class SmsLogViewer
{
    public function fetch(): array
    {
        $q = $_GET['q'] ?? '';
        $sql = "SELECT datetime, phone_number, message, status, user
                FROM sms_voipms_log";
        $params = [];

        if (!empty($q)) {
            $sql .= " WHERE phone_number LIKE ? OR status LIKE ?";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        $sql .= " ORDER BY id DESC LIMIT 100";

        $result = sqlStatement($sql, $params);
        $rows = [];

        while ($row = sqlFetchArray($result)) {
            $rows[] = $row;
        }

        return $rows;
    }
}
