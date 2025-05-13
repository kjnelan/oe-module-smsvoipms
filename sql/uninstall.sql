-- VOIP.ms SMS Module: Uninstall Script

DROP TABLE IF EXISTS sms_voipms_log;
DROP TABLE IF EXISTS sms_voipms_appt_reminders;
DROP TABLE IF EXISTS sms_voipms_cancel_log;

DELETE FROM globals WHERE gl_name IN (
  'sms_voipms_username',
  'sms_voipms_password',
  'sms_voipms_did',
  'sms_voipms_reminder_hours',
  'sms_voipms_template',
  'sms_voipms_notify_new',
  'sms_voipms_notify_cancel',
  'sms_voipms_template_new',
  'sms_voipms_template_cancel',
  'sms_voipms_last_confirmation_check',
  'sms_voipms_last_cancel_check'
);

