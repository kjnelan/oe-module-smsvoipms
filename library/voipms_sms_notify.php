<?php
/**
 * VOIP.ms SMS Appointment Reminder Script
 *
 * This script sends SMS appointment reminders using the VOIP.ms API.
 * Designed for use with the oe-module-smsvoipms OpenEMR module.
 *
 * Author: Kenneth J. Nelan
 * License: GNU General Public License v3.0 (GPL-3.0)
 * Version: 1.0.0
 *
 * Usage:
 *   php voipms_sms_notify.php site=default user=cronjob type=reminder testrun=0
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
$type = $_GET['type'] ?? 'reminder';
$user = $_GET['user'] ?? 'cronjob';
$testrun = (bool) ($_GET['testrun'] ?? false);

require_once(__DIR__ . '/../src/SmsVoipms/Service/SmsSender.php');
require_once(__DIR__ . '/../src/SmsVoipms/Service/MessageBuilder.php');

use OpenEMR\Modules\SmsVoipms\Service\SmsSender;
use OpenEMR\Modules\SmsVoipms\Service\MessageBuilder;

// ----------------------[ TYPE DISPATCH ]----------------------------
if ($type !== 'reminder') {
    echo "⚠ Unknown type: $type\n";
    exit(1);
}

// ----------------------[ SETTINGS & TIME WINDOW ]-------------------
$hoursAhead = (int) ($GLOBALS['sms_voipms_reminder_hours'] ?? 24);
if ($hoursAhead <= 0) {
    $hoursAhead = 24;
}
$msgTemplate = $GLOBALS['sms_voipms_template'] ?? "Reminder: Your appointment with ***PROVIDER*** is on ***DATE*** at ***STARTTIME***.";

$now = new DateTimeImmutable();
$cutoff = $now->modify("+$hoursAhead hours");
echo "⏰ Scanning for reminders between now and {$cutoff->format('Y-m-d H:i:s')}...\n";

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
        CONCAT(e.pc_eventDate, ' ', e.pc_startTime) >= ?
        AND CONCAT(e.pc_eventDate, ' ', e.pc_startTime) <= ?
        AND p.phone_cell IS NOT NULL
        AND TRIM(p.phone_cell) != ''
        AND e.pc_apptstatus IN ('', 'Sched')
        AND NOT EXISTS (
            SELECT 1 FROM sms_voipms_appt_reminders r 
            WHERE r.event_id = e.pc_eid AND r.type = 'reminder'
        )
";

$params = [$now->format('Y-m-d H:i:s'), $cutoff->format('Y-m-d H:i:s')];
$result = sqlStatement($sql, $params);
$sender = new SmsSender();
$count = 0;

// ----------------------[ MESSAGE GENERATION & SENDING ]-------------
while ($row = sqlFetchArray($result)) {
    // 🟢 NEW: Use ***TAG*** replacements via MessageBuilder
    $message = MessageBuilder::buildAppointmentMessage($row, $msgTemplate);
    $phone = $row['phone_cell'];

    if ($testrun) {
        echo "[TEST] Would send to {$phone}: $message\n";
    } else {
        $response = $sender->send($phone, $message, $user);
        if ($response['status'] === 'success') {
            sqlStatement(
                "INSERT INTO sms_voipms_appt_reminders (event_id, sent_at, user) VALUES (?, NOW(), ?)",
                [$row['pc_eid'], $user]
            );
            sqlStatement(
                "UPDATE openemr_postcalendar_events SET pc_apptstatus = ? WHERE pc_eid = ?",
                ['SMS', $row['pc_eid']]
            );
        }
        echo "📨 Sent to {$phone} – Status: {$response['status']}\n";
    }

    $count++;
}

// ----------------------[ FINAL STATUS ]-----------------------------
echo "\n✅ Done. {$count} reminder(s) processed.\n";
