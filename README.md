# VOIP.ms SMS Module for OpenEMR
**Version:** 1.1.0-stable 
**Compatible with:** OpenEMR 7.0.x 
**Author:** Kenneth J. Nelan

## Overview
This module enables OpenEMR installations to send SMS messages through the VOIP.ms platform. 
Supported features include:

- Appointment Reminders
- New Appointment Confirmations
- Cancellation Notices
- Manual Test Messages
- Full Logging and Audit Trail
- Configurable Message Templates
- Cron-Driven Delivery

---

## Installation
1. Extract the module to:

   ```
   interface/modules/custom_modules/oe-module-smsvoipms
   ```

2. Enable the module inside OpenEMR:

   - Go to **Modules → Manage Modules**
   - Find **VOIP.ms SMS**
   - Click **Install**, then **Enable**

3. Use the top navigation tab to access:

   - ⚙ Settings 
   - 📤 Send Test 
   - 📜 Log Viewer 
   - 📘 Instructions

---

## Configuration
In the **Settings** tab, provide the following:

- VOIP.ms API Username
- VOIP.ms API Password
- Default DID (phone number)
- Reminder Lookahead (in hours)
- Message Templates for:
  - Appointment Reminders
  - New Appointment Confirmations
  - Appointment Cancellations

Enable or disable each type of message individually.

---

## Message Template Tags
Templates support the following dynamic tags:

| Tag                  | Description                            |
|----------------------|----------------------------------------|
| `***NAME***`         | Patient's full name                    |
| `***DATE***`         | Appointment date (e.g., April 21)      |
| `***STARTTIME***`    | Appointment start time (e.g., 3:00 PM) |
| `***ENDTIME***`      | Appointment end time (e.g., 3:45 PM)   |
| `***PROVIDER***`     | Provider’s full name                   |
| `***LOCATION***`     | Appointment location name              |
| `***LOCATION_PHONE***` | *(optional)* Location phone number – currently not reliable in OpenEMR and may fall back to “by phone”

---

## Cron Job Setup
Three separate cron jobs are required for production (change username as needed - should be the admin user):

```bash
# Send reminders for upcoming appointments
*/1 * * * * php -f /path/to/voipms_sms_notify.php site=default user=cronjob type=reminder testrun=0

# Send confirmations for new appointments
*/1 * * * * php -f /path/to/voipms_sms_confirm.php site=default user=cronjob testrun=0

# Send cancellation messages for cancelled appointments
*/1 * * * * php -f /path/to/voipms_sms_cancel.php site=default user=cronjob testrun=0
```

Update `/path/to/` as needed for your OpenEMR installation.

To install as the web user (Debian/Ubuntu):

```bash
sudo -u www-data crontab -e
```

---

## Logging and Testing
- All messages sent are logged in `sms_voipms_log`, `sms_voipms_appt_reminders`, or `sms_voipms_cancel_log` tables
- View log data using the **Log Viewer** tab
- Use the **Send Test** tab to send manual messages for validation

---

## Limitations
This module does **not** support:

- Real-time SMS delivery outside cron
- SMS replies (inbound messages)

---

## License
This module is licensed under the [GNU GPL v3.0](https://www.gnu.org/licenses/gpl-3.0.en.html).

