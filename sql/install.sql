-- VOIP.ms SMS: General Log Table
CREATE TABLE IF NOT EXISTS sms_voipms_log (
    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    datetime DATETIME NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(50) NOT NULL,
    response TEXT,
    user VARCHAR(255),
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VOIP.ms SMS: Appointment Reminder Tracker
CREATE TABLE IF NOT EXISTS sms_voipms_appt_reminders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id INT NOT NULL,
    sent_at DATETIME NOT NULL,
    user VARCHAR(255),
    type ENUM('reminder', 'confirmation') NOT NULL DEFAULT 'reminder',
    PRIMARY KEY (id),
    UNIQUE KEY (event_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VOIP.ms SMS: Cancellation Tracker
CREATE TABLE IF NOT EXISTS sms_voipms_cancel_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id INT NOT NULL,
    sent_at DATETIME NOT NULL,
    user VARCHAR(255),
    PRIMARY KEY (id),
    UNIQUE KEY (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

