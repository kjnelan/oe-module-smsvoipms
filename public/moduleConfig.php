<?php
/**
 * moduleConfig.php
 *
 * VOIP.ms SMS Module for OpenEMR
 * Settings, Test, Log Viewer, and Documentation UI
 *
 * @package    OpenEMR
 * @module     oe-module-smsvoipms
 * @author     Kenneth J Nelan
 * @license    GPL-3.0-or-later
 * @link       https://sacwan.org
 */

$sessionAllowWrite = true;
require_once(__DIR__ . '/../../../../../interface/globals.php');
require_once(__DIR__ . '/../src/SmsVoipms/Service/SmsLogViewer.php');
require_once(__DIR__ . '/../src/SmsVoipms/Service/SmsSender.php');

use OpenEMR\Modules\SmsVoipms\Service\SmsLogViewer;
use OpenEMR\Modules\SmsVoipms\Service\SmsSender;

$tab = $_GET['tab'] ?? 'config';
$base = $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-smsvoipms/public/moduleConfig.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>VOIP.ms SMS Module</title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
        }
        nav a {
            margin-right: 10px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            font-size: 14px;
            text-align: left;
        }
        th {
            background-color: #eee;
        }
        input[type="text"], input[type="password"], textarea {
            width: 400px;
            padding: 5px;
            font-size: 14px;
        }
        tr.cancelled { background-color: #fbe7e7; }
        tr.new { background-color: #e7f7e7; }
        tr.reminder { background-color: #e7f1fb; }
        .status-badge {
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}
.badge-success {
    background: #28a745;
    color: #fff;
}
.badge-error {
    background: #dc3545;
    color: #fff;
}
.badge-pending {
    background: #ffc107;
    color: #000;
}

    </style>
</head>
<body>
<div style="display: flex; justify-content: center; padding: 40px;">
    <div style="max-width: 1300px; width: 100%; background: #f9f9f9; padding: 20px 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); font-family: sans-serif; box-sizing: border-box;">
        <h2 style="text-align: center;">VOIP.ms SMS Module</h2>
        <nav style="text-align: center; margin-bottom: 20px;">
            <a href="<?= $base ?>?tab=config" style="margin: 0 10px;">⚙ Settings</a> |
            <a href="<?= $base ?>?tab=test" style="margin: 0 10px;">📤 Send Test</a> |
            <a href="<?= $base ?>?tab=log" style="margin: 0 10px;">📜 Log Viewer</a> |
            <a href="<?= $base ?>?tab=docs" style="margin: 0 10px;">📘 Instructions</a>
        </nav>
        <hr style="margin-bottom: 30px;">
<?php if ($tab === 'config'): ?>
<?php
$fields = [
    'sms_voipms_username' => '',
    'sms_voipms_password' => '',
    'sms_voipms_did' => '',
    'sms_voipms_reminder_hours' => '',
    'sms_voipms_template' => '',
    'sms_voipms_notify_new' => '',
    'sms_voipms_notify_cancel' => '',
    'sms_voipms_template_new' => '',
    'sms_voipms_template_cancel' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $key => $_) {
        $val = isset($_POST[$key]) ? trim($_POST[$key]) : '';
        if (in_array($key, ['sms_voipms_notify_new', 'sms_voipms_notify_cancel'])) {
            $val = isset($_POST[$key]) && $_POST[$key] === '1' ? '1' : '0';
        }
        sqlStatement("REPLACE INTO globals (gl_name, gl_value) VALUES (?, ?)", [$key, $val]);
    }
    header("Location: {$base}?tab=config&saved=1");
    exit;
}

foreach ($fields as $key => &$value) {
    $value = $GLOBALS[$key] ?? '';
}
?>
<h3>VOIP.ms SMS Settings</h3>
<?php if ($_GET['saved'] ?? '' === '1'): ?>
<div style="background: #d4edda; color: #155724; padding: 10px 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin-bottom: 20px;">
    ✅ Settings successfully saved.
</div>
<?php endif; ?>
<form method="POST">
    <div style="display: flex; gap: 40px; justify-content: space-between; align-items: flex-start;">
        <div style="flex: 1; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 0 6px rgba(0,0,0,0.1);">
            <h4>VOIP.ms API Settings</h4>
            <label>API Username</label><br>
            <input type="text" name="sms_voipms_username" value="<?= htmlspecialchars($fields['sms_voipms_username']) ?>"><br><br>

            <label>API Password</label><br>
            <input type="password" name="sms_voipms_password" value="<?= htmlspecialchars($fields['sms_voipms_password']) ?>"><br><br>

            <label>Default DID</label><br>
            <input type="text" name="sms_voipms_did" value="<?= htmlspecialchars($fields['sms_voipms_did']) ?>"><br><br>

            <label>Reminder Lookahead (hours)</label><br>
            <input type="text" name="sms_voipms_reminder_hours" value="<?= htmlspecialchars($fields['sms_voipms_reminder_hours']) ?>"><br><br>

            <label>Default Reminder Template</label><br>
            <textarea name="sms_voipms_template" rows="4"><?= htmlspecialchars($fields['sms_voipms_template']) ?></textarea>
        </div>
        <div style="flex: 1; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 0 6px rgba(0,0,0,0.1);">
            <h4>Real-Time Notifications</h4>
            <label><input type="checkbox" name="sms_voipms_notify_new" value="1" <?= $fields['sms_voipms_notify_new'] === '1' ? 'checked' : '' ?>> Enable new appointment texts</label><br><br>

            <label><input type="checkbox" name="sms_voipms_notify_cancel" value="1" <?= $fields['sms_voipms_notify_cancel'] === '1' ? 'checked' : '' ?>> Enable cancellation texts</label><br><br>

            <label>New Appointment Template:</label><br>
            <textarea name="sms_voipms_template_new" rows="4"><?= htmlspecialchars($fields['sms_voipms_template_new']) ?></textarea><br><br>

            <label>Cancelled Appointment Template:</label><br>
            <textarea name="sms_voipms_template_cancel" rows="4"><?= htmlspecialchars($fields['sms_voipms_template_cancel']) ?></textarea>
        </div>
    </div>
    <br style="clear: both;">
    <button type="submit" style="margin-top: 20px;">Save Settings</button>
</form>
<?php elseif ($tab === 'test'): ?>
<?php
$feedback = '';
$template = $GLOBALS['sms_voipms_template'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = $_POST['to'] ?? '';
    $msg = $_POST['msg'] ?? $template;
    $sender = new SmsSender();
    $result = $sender->send($to, $msg);
    $feedback = json_encode($result, JSON_PRETTY_PRINT);
}
?>
<h3>Send Test SMS</h3>
<form method="POST">
    <label>To (e.g. +1XXXXXXXXXX):</label><br>
    <input type="text" name="to" required><br><br>
    <label>Message:</label><br>
    <small style="color: gray;">If left blank, the default message from the settings tab will be used.</small><br>
    <textarea name="msg" rows="4"><?= htmlspecialchars($_POST['msg'] ?? $template) ?></textarea><br><br>
    <button type="submit">Send</button>
</form>
<?php if ($feedback): ?>
<h4>Result:</h4>
<pre><?= htmlspecialchars($feedback) ?></pre>
<?php endif; ?>
<?php elseif ($tab === 'log'): ?>
<?php
$viewer = new SmsLogViewer();
$data = $viewer->fetch();
?>
<h3>SMS Log</h3>
<form method="GET">
    <input type="hidden" name="tab" value="log">
    <label>Search:</label>
    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    <button type="submit">Search</button>
</form>
<table>
    <tr><th>Date</th><th>Phone</th><th>Message</th><th>Status</th><th>User</th></tr>
       <?php
    foreach ($data as $row):
        $status = strtolower($row['status']);
        $badgeClass = $status === 'success' ? 'badge-success'
                     : ($status === 'error' ? 'badge-error' : 'badge-pending');
    ?>
    <tr>
        <td><?= htmlspecialchars($row['datetime']) ?></td>
        <td><?= htmlspecialchars($row['phone_number']) ?></td>
        <td><?= htmlspecialchars($row['message']) ?></td>
        <td><span class="status-badge <?= $badgeClass ?>"><?= htmlspecialchars($row['status']) ?></span></td>
        <td><?= htmlspecialchars($row['user']) ?></td>
    </tr>
    <?php endforeach; ?>

</table>
<?php elseif ($tab === 'docs'): ?>
<h3>Module Setup & Instructions</h3>
<div style="margin-top: 20px; background: #ffffff; padding: 20px; border-radius: 10px; border: 1px solid #ccc;">
    <p><strong>After enabling this module:</strong></p>
    <ul>
        <li>Go to the <strong>Settings</strong> tab and enter your:</li>
        <ul>
            <li><strong>VOIP.ms Username</strong> – this is the login you use to access your VOIP.ms account.</li>
            <li><strong>API Password</strong> – you must create an API password in your VOIP.ms dashboard (not the same as your account password).</li>
            <li><strong>Default DID</strong> – your 10-digit VOIP.ms phone number (e.g., 12623457229).</li>
            <li><strong>Reminder Lookahead</strong> – number of hours before the appointment to send reminders (e.g., 24 or 48).</li>
            <li><strong>SMS Message Templates</strong> for Reminders, New Appointments, and Cancellations.</li>
        </ul>
    </ul>

    <hr>

    <h4>Message Tags</h4>
    <p>You can personalize messages using these tags:</p>
    <ul>
        <li><code>***NAME***</code> – Patient’s full name</li>
        <li><code>***DATE***</code> – Appointment date</li>
        <li><code>***STARTTIME***</code> – Start time</li>
        <li><code>***ENDTIME***</code> – End time</li>
        <li><code>***PROVIDER***</code> – Provider’s full name</li>
        <li><code>***LOCATION***</code> – Location name (if available)</li>
        <li><code>***LOCATION_PHONE***</code> – Phone number of the location (may fall back to 'by phone')</li>
    </ul>

    <hr>

    <h4>Test Messages</h4>
    <p>Use the <strong>Send Test</strong> tab to verify SMS delivery. Enter a number and an optional message. If blank, your default reminder template will be used.</p>

    <h4>View Logs</h4>
    <p>The <strong>Log Viewer</strong> tab displays all messages sent through the system, their status, and the user who triggered them. Cancellations, confirmations, and reminders are color-coded for clarity.</p>

    <hr>

    <h4>Important Notes</h4>
    <ul>
        <li>This module relies on cron jobs. If messages are not sending, verify your cron is active.</li>
        <li>SMS replies are not currently supported.</li>
    </ul>
</div>
<?php endif; ?>
</div></div>
</body>
</html>

