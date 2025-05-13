<?php
/**
 * VOIP.ms SMS Cancellation Script
 *
 * Sends cancellation texts for appointments marked as cancelled.
 * Should be scheduled separately from reminder/confirmation crons.
 *
 * @package   OpenEMR
 * @module    oe-module-smsvoipms
 * @author    Kenneth J. Nelan
 * @license   GPL-3.0-or-later
 * @link      https://sacwan.org
 */

// ----------------------[ BOOTSTRAP ENVIRONMENT ]----------------------
parse_str(implode('&', array_slice($argv, 1)), $_GET);

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

// ----------------------[ USER & SETTINGS ]--------------------------
$user = $_GET['user'] ?? 'cronjob';
$testrun = (bool) ($_GET['testrun'] ?? false);
$sender = new SmsSender();

if (empty($GLOBALS['sms_voipms_notify_cancel']) || $GLOBALS['sms_voipms_notify_cancel'] !== '1') {
    echo "🔕 Cancellation texts are disabled in module settings.\n";
    exit;
}

$template = $GLOBALS['sms_voipms_template_cancel'] ?? 'Your appointment on ***DATE*** at ***STARTTIME*** has been cancelled.';

// ----------------------[ TIME RANGE & LAST CHECK ]------------------
$now = new DateTimeImmutable();
$lastCheckKey = 'sms_voipms_last_cancel_check';
$lastChecked = $GLOBALS[$lastCheckKey] ?? $now->modify('-30 minutes')->format('Y-m-d H:i:s');

echo "⏰ Checking for cancellations since {$lastChecked}...\n";

// ----------------------[ APPOINTMENT QUERY ]------------------------
$sql = "
    SELECT
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
        e.pc_apptstatus = 'x'
        AND e.pc_time > ?
        AND p.phone_cell IS NOT NULL
        AND TRIM(p.phone_cell) != ''
        AND NOT EXISTS (
            SELECT 1 FROM sms_voipms_cancel_log r
            WHERE r.event_id = e.pc_eid
        )
";

$params = [$lastChecked];
$result = sqlStatement($sql, $params);
$count = 0;

// ----------------------[ MESSAGE SENDING LOOP ]---------------------
while ($row = sqlFetchArray($result)) {
    $message = MessageBuilder::buildAppointmentMessage($row, $template);
    $phone = $row['phone_cell'];

    if ($testrun) {
        echo "[TEST][CANCEL] Would send to {$phone}: {$message}\n";
    } else {
        $response = $sender->send($phone, $message, $user);
        if ($response['status'] === 'success') {
            sqlStatement(
                "INSERT INTO sms_voipms_cancel_log (event_id, sent_at, user) VALUES (?, NOW(), ?)",
                [$row['pc_eid'], $user]
            );
        }
        echo "📨 [CANCEL] Sent to {$phone} – Status: {$response['status']}\n";
    }

    $count++;
}

// ----------------------[ TIMESTAMP UPDATE ]-------------------------
sqlStatement("REPLACE INTO globals (gl_name, gl_value) VALUES (?, ?)", [$lastCheckKey, $now->format('Y-m-d H:i:s')]);

echo "\n✅ Done. {$count} cancellation(s) processed.\n";
