<?php
/**
 * MessageBuilder - VOIP.ms SMS Template Replacement Utility
 *
 * Generates appointment reminder messages by replacing ***TAGS*** with
 * appointment-specific data like provider name, time, and location.
 *
 * Author: Kenneth J. Nelan
 * License: GNU General Public License v3.0 (GPL-3.0)
 * Version: 1.2.0
 */

namespace OpenEMR\Modules\SmsVoipms\Service;

class MessageBuilder
{
    /**
     * Replaces ***TAG*** placeholders in the given template with actual values from an appointment row.
     *
     * Supported tags:
     *   - ***NAME***: Patient's full name
     *   - ***PROVIDER***: Provider's full name
     *   - ***DATE***: Formatted date like "May 8"
     *   - ***STARTTIME***: Formatted start time like "10:00 AM"
     *   - ***ENDTIME***: Formatted end time like "10:45 AM"
     *   - ***LOCATION***: Appointment location (or fallback string)
     *   - ***LOCATION_PHONE***: Phone number of the appointment facility (if assigned)
     *
     * @param array $row     Appointment and patient details from SQL
     * @param string $template The message template string with ***TAGS***
     * @return string         The final message with tags replaced
     */
    public static function buildAppointmentMessage(array $row, string $template): string
    {
        // Convert date and time strings into DateTime objects for clean formatting
        $eventDate = \DateTime::createFromFormat('Y-m-d', trim($row['pc_eventDate']));
        $start = new \DateTime($row['pc_startTime']);

        // Default duration fallback if not provided
        $duration = (int) ($row['pc_duration'] ?? 30);
        $end = (clone $start)->modify("+{$duration} minutes");

        // Attempt to load facility phone from pc_facility
        $locationPhone = '';
        if (!empty($row['pc_facility'])) {
            $facility = sqlQuery("SELECT phone FROM facility WHERE id = ?", [$row['pc_facility']]);
            $locationPhone = $facility['phone'] ?? '';
        }

        // Build tag replacements
        $replacements = [
            '***NAME***'           => trim($row['fname'] . ' ' . $row['lname']),
            '***PROVIDER***'       => trim($row['provider_fname'] . ' ' . $row['provider_lname']),
            '***DATE***'           => $eventDate ? $eventDate->format('M j') : $row['pc_eventDate'],
            '***STARTTIME***'      => $start->format('g:i A'),
            '***ENDTIME***'        => $end->format('g:i A'),
            '***LOCATION***'       => $row['pc_location'] ?? 'our clinic',
            '***LOCATION_PHONE***' => $locationPhone ?: 'by phone'
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
