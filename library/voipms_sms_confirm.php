<?php
/**
 * voipms_sms_confirm.php
 *
 * VOIP.ms SMS Confirmation Script for OpenEMR
 * Sends confirmation texts for newly created appointments.
 * Should be scheduled separately from reminder cron.
 *
 * @package   OpenEMR
 * @module    oe-module-smsvoipms
 * @author    Kenneth J. Nelan
 * @copyright Copyright (c) 2024 Kenneth J. Nelan
 * @license   GPL-3.0-or-later
 * @link      https://sacwan.org
 */

// ----------------------[ BOOTSTRAP ENVIRONMENT ]----------------------
parse_str(implode('&', array_slice($argv, 1)), $_GET);

// Flags for CLI
define('NO_SESSION_VALIDATION', true);
define('NO_AUTHenticate', true);
define('skip_timeout_reset', true);
define('skip_session_start', true);
$GLOBALS['ignoreAuth'] = true;
$ignoreAuth = true;

if (!isset($_GET['site'])) {
    fwrite(STDERR, "❌ Missing required argument: site=default\n");
    exit(1);
}

$site_id = $_GET['site'];
$_GET['site'] = $site_id;
$basePath = realpath(__DIR__ . '/../../../../../');
require_once("$basePath/interface/globals.php");

// ----------------------[ MODULE DEPENDENCIES ]----------------------
require_once(__DIR__ . '/../src/SmsVoipms/Service/SmsSender.php');
require_once(__DIR__ . '/../src/SmsVoipms/Service/MessageBuilder.php');

use OpenEMR\Modules\SmsVoipms\Service\SmsSender;
use OpenEMR\Modules\SmsVoipms\Service\MessageBuilder;

// ----------------------[ USER & SETTINGS ]---------------------------
$user = $_GET['user'] ?? 'cronjob';
$testrun = (bool) ($_GET['testrun'] ?? false);
$sender = new SmsSender();

// ✅ Honor Admin Config: only run if confirmation is enabled
if (empty($GLOBALS['sms_voipms_notify_new']) || $GLOBALS['sms_voipms_notify_new'] !== '1') {
    echo "🔕 Confirmation texts are disabled in the module settings.
";
    exit;
}

// ----------------------[ TIMING & MESSAGE TEMPLATE ]-----------------
$now = new DateTimeImmutable();
$lastCheckKey = 'sms_voipms_last_confirmation_check';
$lastChecked = $GLOBALS[$lastCheckKey] ?? $now->modify('-15 minutes')->format('Y-m-d H:i:s');
$template = $GLOBALS['sms_voipms_template_new'] ?? 'Your appointment with ***PROVIDER*** is on ***DATE*** at ***STARTTIME***.';

echo "⏰ Checking for new appointments created since {$lastChecked}...
";

// ----------------------[ APPOINTMENT QUERY ]-------------------------
$sql = "
    SELECT
        e.pc_time,
        e.pc_eventDate,
        e.pc_startTime,
        e.pc_duration,
        e.pc_eid,
        e.pc_aid,
        e.pc_location,
        e.pc_pid,
        p.fname,
        p.lname,
        p.phone_cell,
        u.lname AS provider_lname,
        u.fname AS provider_fname
    FROM openemr_postcalendar_events AS e
    JOIN patient_data AS p ON e.pc_pid = p.pid
    LEFT JOIN users AS u ON e.pc_aid = u.id
    WHERE
        e.pc_time > ?
        AND e.pc_apptstatus IN ('', 'Sched')
        AND p.phone_cell IS NOT NULL
        AND TRIM(p.phone_cell) != ''
        AND NOT EXISTS (
            SELECT 1 FROM sms_voipms_appt_reminders r
            WHERE r.event_id = e.pc_eid AND r.type = 'confirmation'
        )
";


$params = [$lastChecked];
$result = sqlStatement($sql, $params);
$count = 0;

// ----------------------[ MESSAGE GENERATION & SENDING ]--------------
while ($row = sqlFetchArray($result)) {
    $message = MessageBuilder::buildAppointmentMessage($row, $template);
    $phone = $row['phone_cell'];

    if ($testrun) {
        echo "[TEST][CONFIRM] Would send to {$phone}: {$message}
";
    } else {
        $response = $sender->send($phone, $message, $user);
        if ($response['status'] === 'success') {
            sqlStatement(
                "INSERT INTO sms_voipms_appt_reminders (event_id, sent_at, user, type) VALUES (?, NOW(), ?, 'confirmation')",
                [$row['pc_eid'], $user]
            );
        }
        echo "📨 [CONFIRM] Sent to {$phone} – Status: {$response['status']}
";
    }

    $count++;
}

// ----------------------[ UPDATE TIMESTAMP ]--------------------------
sqlStatement("REPLACE INTO globals (gl_name, gl_value) VALUES (?, ?)", [$lastCheckKey, $now->format('Y-m-d H:i:s')]);

// ----------------------[ DONE ]--------------------------------------
echo "\n✅ Done. {$count} confirmation(s) processed.\n";
