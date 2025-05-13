<?php
/**
 * VOIP.ms SMS Module Bootstrap
 * Safely injects a submenu item under "Modules" without breaking the UI.
 * Also hooks into appointment save events to send SMS.
 *
 * Author: Kenneth J. Nelan
 * License: GPL-3.0
 * Version: 1.1.0
 */

use OpenEMR\Menu\MenuEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use OpenEMR\Events\Appointments\AppointmentSaveEvent;
use OpenEMR\Modules\SmsVoipms\Service\SmsSender;

if (isset($GLOBALS['kernel'])) {
    $dispatcher = $GLOBALS['kernel']->getEventDispatcher();

    // Inject VOIP.ms SMS into "Modules" submenu (not top-level!)

$dispatcher->addListener(MenuEvent::MENU_UPDATE, function (MenuEvent $event) {
    error_log("VOIP.ms SMS: Adding as a top-level menu item safely");

    $menu = $event->getMenu();

    // 🔍 DEBUG: List all top-level menu labels OpenEMR is using
    error_log("🧪 Top-Level Menu Labels:");
    foreach ($menu as $item) {
        $label = is_object($item) ? $item->label : ($item['label'] ?? '[unknown]');
        error_log("👉 Menu Label: $label");
    }

    $menu[] = (object)[
        'label' => 'VOIP.ms SMS',
        'menu_id' => 'voipms_sms',
        'url' => $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-smsvoipms/public/moduleConfig.php?tab=config',
        'target' => 'main',
        'children' => [],
        'requirement' => 0,
        'acl_req' => [],
        'global_req_strict' => [],
    ];

    error_log("🧪 Final top-level menu injected:");
    error_log(print_r($menu, true));

    $event->setMenu($menu);
});

    // Appointment save hook
    $dispatcher->addListener(AppointmentSaveEvent::class, function (AppointmentSaveEvent $event) {
        $appt = $event->getAppointment();
        $pid = $appt['pid'] ?? null;
        if (!$pid) return;

        $row = sqlQuery("SELECT phone_cell FROM patient_data WHERE pid = ?", [$pid]);
        $phone = $row['phone_cell'] ?? '';
        if (empty($phone)) return;

        $template = $GLOBALS['sms_voipms_template'] ?? "You have an appointment at our clinic.";
        $message = str_replace(
            ['{date}', '{time}', '{provider}'],
            [
                $appt['pc_eventDate'] ?? '',
                $appt['pc_startTime'] ?? '',
                $appt['pc_aid'] ?? 'your provider'
            ],
            $template
        );

        $sender = new SmsSender();
        $sender->send($phone, $message);
    });
}
