<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use DateTimeImmutable;
use Exception;
use PDO;
use RuntimeException;

/**
 * Appointment Reservation & Conflict-Prevention Engine.
 */
final class Appointment
{
    private const SLOT_STEP_MINUTES = 30;
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Fetch all active dental services.
     */
    public function getActiveServices(): array
    {
        $stmt = $this->pdo->query('
            SELECT id, name, description, duration_minutes, price
            FROM services
            WHERE is_active = 1
            ORDER BY name ASC
        ');
        return $stmt->fetchAll();
    }

    /**
     * Fetch all active dentists.
     */
    public function getActiveDentists(): array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.id, u.first_name, u.last_name, u.specialization, u.license_number
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE r.slug = :role AND u.is_active = 1
            ORDER BY u.last_name ASC
        ');
        $stmt->execute([':role' => 'dentist']);
        return $stmt->fetchAll();
    }

    /**
     * Fetch all active patients (for Receptionist walk-in booking).
     */
    public function getActivePatients(): array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.id, u.first_name, u.last_name, u.email, u.phone
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE r.slug = :role AND u.is_active = 1
            ORDER BY u.last_name ASC, u.first_name ASC
        ');
        $stmt->execute([':role' => 'client']);
        return $stmt->fetchAll();
    }

    /**
     * Compute available time slots for a given dentist, service, and date.
     */
    public function getAvailableSlots(int $dentistId, int $serviceId, string $date, ?int $excludeAppointmentId = null): array
    {
        $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            throw new RuntimeException('Invalid date format.');
        }

        $today = new DateTimeImmutable('today');
        if ($dateObj < $today) {
            return [];
        }

        $serviceStmt = $this->pdo->prepare('
            SELECT duration_minutes FROM services WHERE id = :id AND is_active = 1 LIMIT 1
        ');
        $serviceStmt->execute([':id' => $serviceId]);
        $service = $serviceStmt->fetch();
        if (!$service) {
            throw new RuntimeException('Selected dental service does not exist or is inactive.');
        }
        $durationMinutes = (int) $service['duration_minutes'];

        $dayOfWeek = (int) $dateObj->format('w');
        $schedStmt = $this->pdo->prepare('
            SELECT start_time, end_time, break_start, break_end, is_available
            FROM dentist_schedules
            WHERE dentist_id = :dentist_id AND day_of_week = :day_of_week
            LIMIT 1
        ');
        $schedStmt->execute([
            ':dentist_id'  => $dentistId,
            ':day_of_week' => $dayOfWeek,
        ]);
        $schedule = $schedStmt->fetch();

        if (!$schedule || (int) $schedule['is_available'] !== 1) {
            return [];
        }

        $sql = "
            SELECT start_time, end_time
            FROM appointments
            WHERE dentist_id = :dentist_id
              AND appointment_date = :appointment_date
              AND status IN ('Pending', 'Confirmed', 'Rescheduled')
        ";
        $params = [
            ':dentist_id'       => $dentistId,
            ':appointment_date' => $date,
        ];

        if ($excludeAppointmentId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params[':exclude_id'] = $excludeAppointmentId;
        }
        $sql .= ' ORDER BY start_time ASC';

        $bookedStmt = $this->pdo->prepare($sql);
        $bookedStmt->execute($params);
        $existingBookings = $bookedStmt->fetchAll();

        $shiftStart = self::timeToMinutes($schedule['start_time']);
        $shiftEnd   = self::timeToMinutes($schedule['end_time']);
        $breakStart = !empty($schedule['break_start']) ? self::timeToMinutes($schedule['break_start']) : null;
        $breakEnd   = !empty($schedule['break_end']) ? self::timeToMinutes($schedule['break_end']) : null;

        $bookedIntervals = [];
        foreach ($existingBookings as $b) {
            $bookedIntervals[] = [
                'start' => self::timeToMinutes($b['start_time']),
                'end'   => self::timeToMinutes($b['end_time']),
            ];
        }

        $now = new DateTimeImmutable('now');
        $isToday = ($dateObj->format('Y-m-d') === $now->format('Y-m-d'));
        $currentMinutesToday = ((int) $now->format('H') * 60) + (int) $now->format('i');

        $availableSlots = [];
        for ($candidateStart = $shiftStart; ($candidateStart + $durationMinutes) <= $shiftEnd; $candidateStart += self::SLOT_STEP_MINUTES) {
            $candidateEnd = $candidateStart + $durationMinutes;

            if ($isToday && $candidateStart <= ($currentMinutesToday + 30)) {
                continue;
            }

            if ($breakStart !== null && $breakEnd !== null) {
                if ($candidateStart < $breakEnd && $candidateEnd > $breakStart) {
                    continue;
                }
            }

            $hasConflict = false;
            foreach ($bookedIntervals as $interval) {
                if ($candidateStart < $interval['end'] && $candidateEnd > $interval['start']) {
                    $hasConflict = true;
                    break;
                }
            }

            if (!$hasConflict) {
                $startFormatted = self::minutesToTime($candidateStart);
                $endFormatted   = self::minutesToTime($candidateEnd);
                $availableSlots[] = [
                    'start_time' => $startFormatted,
                    'end_time'   => $endFormatted,
                    'label'      => date('g:i A', strtotime($startFormatted)) . ' - ' . date('g:i A', strtotime($endFormatted)),
                ];
            }
        }

        return $availableSlots;
    }

    /**
     * Atomically reserve an appointment using InnoDB row locking (SELECT ... FOR UPDATE).
     */
    public function createAppointment(
        int $patientId,
        int $dentistId,
        int $serviceId,
        string $appointmentDate,
        string $startTime,
        string $bookingType = 'Online',
        ?string $patientNotes = null,
        ?int $managedBy = null
    ): array {
        $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $appointmentDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $appointmentDate) {
            return ['success' => false, 'message' => 'Invalid appointment date format.'];
        }

        if (!preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9](?::[0-5][0-9])?$/', $startTime)) {
            return ['success' => false, 'message' => 'Invalid start time format.'];
        }
        $normalizedStartTime = substr($startTime, 0, 5) . ':00';

        try {
            $this->pdo->beginTransaction();

            $svcStmt = $this->pdo->prepare('
                SELECT duration_minutes FROM services WHERE id = :id AND is_active = 1 LIMIT 1
            ');
            $svcStmt->execute([':id' => $serviceId]);
            $service = $svcStmt->fetch();
            if (!$service) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Selected service is unavailable.'];
            }

            $startMinutes = self::timeToMinutes($normalizedStartTime);
            $endMinutes   = $startMinutes + (int) $service['duration_minutes'];
            $normalizedEndTime = self::minutesToTime($endMinutes);

            $dayOfWeek = (int) $dateObj->format('w');
            $schedStmt = $this->pdo->prepare('
                SELECT start_time, end_time, break_start, break_end, is_available
                FROM dentist_schedules
                WHERE dentist_id = :dentist_id AND day_of_week = :day_of_week
                LIMIT 1
                FOR UPDATE
            ');
            $schedStmt->execute([
                ':dentist_id'  => $dentistId,
                ':day_of_week' => $dayOfWeek,
            ]);
            $schedule = $schedStmt->fetch();

            if (!$schedule || (int) $schedule['is_available'] !== 1) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'The selected dentist is not available on this day.'];
            }

            $shiftStart = self::timeToMinutes($schedule['start_time']);
            $shiftEnd   = self::timeToMinutes($schedule['end_time']);
            if ($startMinutes < $shiftStart || $endMinutes > $shiftEnd) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Requested time falls outside the dentist\'s working hours.'];
            }

            if (!empty($schedule['break_start']) && !empty($schedule['break_end'])) {
                $breakStart = self::timeToMinutes($schedule['break_start']);
                $breakEnd   = self::timeToMinutes($schedule['break_end']);
                if ($startMinutes < $breakEnd && $endMinutes > $breakStart) {
                    $this->pdo->rollBack();
                    return ['success' => false, 'message' => 'Requested time overlaps with the dentist\'s scheduled break.'];
                }
            }

            $conflictStmt = $this->pdo->prepare("
                SELECT id
                FROM appointments
                WHERE dentist_id = :dentist_id
                  AND appointment_date = :appointment_date
                  AND status IN ('Pending', 'Confirmed', 'Rescheduled')
                  AND start_time < :new_end_time
                  AND end_time > :new_start_time
                LIMIT 1
                FOR UPDATE
            ");
            $conflictStmt->execute([
                ':dentist_id'       => $dentistId,
                ':appointment_date' => $appointmentDate,
                ':new_end_time'     => $normalizedEndTime,
                ':new_start_time'   => $normalizedStartTime,
            ]);

            if ($conflictStmt->fetch()) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'This time slot has just been reserved or conflicts with another appointment.',
                ];
            }

            $patientConflictStmt = $this->pdo->prepare("
                SELECT id
                FROM appointments
                WHERE patient_id = :patient_id
                  AND appointment_date = :appointment_date
                  AND status IN ('Pending', 'Confirmed', 'Rescheduled')
                  AND start_time < :new_end_time
                  AND end_time > :new_start_time
                LIMIT 1
                FOR UPDATE
            ");
            $patientConflictStmt->execute([
                ':patient_id'       => $patientId,
                ':appointment_date' => $appointmentDate,
                ':new_end_time'     => $normalizedEndTime,
                ':new_start_time'   => $normalizedStartTime,
            ]);

            if ($patientConflictStmt->fetch()) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'This patient already has an overlapping appointment on this date.',
                ];
            }

            $referenceCode = sprintf(
                'APT-%s-%s',
                $dateObj->format('Ymd'),
                strtoupper(bin2hex(random_bytes(2)))
            );

            $insertStmt = $this->pdo->prepare('
                INSERT INTO appointments (
                    reference_code, patient_id, dentist_id, service_id,
                    appointment_date, start_time, end_time, status,
                    booking_type, patient_notes, managed_by
                ) VALUES (
                    :reference_code, :patient_id, :dentist_id, :service_id,
                    :appointment_date, :start_time, :end_time, :status,
                    :booking_type, :patient_notes, :managed_by
                )
            ');

            $initialStatus = ($bookingType === 'Walk-In') ? 'Confirmed' : 'Pending';

            $insertStmt->execute([
                ':reference_code'   => $referenceCode,
                ':patient_id'       => $patientId,
                ':dentist_id'       => $dentistId,
                ':service_id'       => $serviceId,
                ':appointment_date' => $appointmentDate,
                ':start_time'       => $normalizedStartTime,
                ':end_time'         => $normalizedEndTime,
                ':status'           => $initialStatus,
                ':booking_type'     => $bookingType,
                ':patient_notes'    => $patientNotes ? mb_substr(trim($patientNotes), 0, 500) : null,
                ':managed_by'       => $managedBy,
            ]);

            $appointmentId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();

            return [
                'success'        => true,
                'appointment_id' => $appointmentId,
                'reference_code' => $referenceCode,
                'message'        => "Appointment booked! Reference Code: {$referenceCode}",
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[Appointment Creation Error]: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while processing the booking.',
            ];
        }
    }

    /**
     * Update appointment status (Confirm, Complete, Cancel) or Reschedule with conflict check.
     */
    public function updateStatusOrReschedule(
        int $appointmentId,
        string $newStatus,
        int $managedByUserId,
        ?string $newDate = null,
        ?string $newStartTime = null,
        ?string $cancellationReason = null
    ): array {
        $allowedStatuses = ['Pending', 'Confirmed', 'Rescheduled', 'Completed', 'Cancelled'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            return ['success' => false, 'message' => 'Invalid appointment status.'];
        }

        try {
            $this->pdo->beginTransaction();

            $aptStmt = $this->pdo->prepare('
                SELECT a.*, s.duration_minutes
                FROM appointments a
                INNER JOIN services s ON a.service_id = s.id
                WHERE a.id = :id
                LIMIT 1
                FOR UPDATE
            ');
            $aptStmt->execute([':id' => $appointmentId]);
            $apt = $aptStmt->fetch();

            if (!$apt) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Appointment not found.'];
            }

            $targetDate      = $apt['appointment_date'];
            $targetStartTime = $apt['start_time'];
            $targetEndTime   = $apt['end_time'];

            // If rescheduling, validate new date/time and check conflicts
            if ($newStatus === 'Rescheduled' && $newDate !== null && $newStartTime !== null && $newDate !== '' && $newStartTime !== '') {
                $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $newDate);
                if (!$dateObj || $dateObj->format('Y-m-d') !== $newDate) {
                    $this->pdo->rollBack();
                    return ['success' => false, 'message' => 'Invalid reschedule date.'];
                }

                $targetDate      = $newDate;
                $targetStartTime = substr($newStartTime, 0, 5) . ':00';
                $startMins       = self::timeToMinutes($targetStartTime);
                $endMins         = $startMins + (int) $apt['duration_minutes'];
                $targetEndTime   = self::minutesToTime($endMins);

                // Check dentist conflict excluding the current appointment ID
                $conflictStmt = $this->pdo->prepare("
                    SELECT id
                    FROM appointments
                    WHERE dentist_id = :dentist_id
                      AND appointment_date = :appointment_date
                      AND id != :exclude_id
                      AND status IN ('Pending', 'Confirmed', 'Rescheduled')
                      AND start_time < :new_end_time
                      AND end_time > :new_start_time
                    LIMIT 1
                    FOR UPDATE
                ");
                $conflictStmt->execute([
                    ':dentist_id'       => $apt['dentist_id'],
                    ':appointment_date' => $targetDate,
                    ':exclude_id'       => $appointmentId,
                    ':new_end_time'     => $targetEndTime,
                    ':new_start_time'   => $targetStartTime,
                ]);

                if ($conflictStmt->fetch()) {
                    $this->pdo->rollBack();
                    return ['success' => false, 'message' => 'Cannot reschedule: the requested slot conflicts with another booking.'];
                }
            }

            $updateStmt = $this->pdo->prepare('
                UPDATE appointments
                SET status = :status,
                    appointment_date = :appointment_date,
                    start_time = :start_time,
                    end_time = :end_time,
                    cancellation_reason = :cancellation_reason,
                    managed_by = :managed_by
                WHERE id = :id
            ');
            $updateStmt->execute([
                ':status'              => $newStatus,
                ':appointment_date'    => $targetDate,
                ':start_time'          => $targetStartTime,
                ':end_time'            => $targetEndTime,
                ':cancellation_reason' => $cancellationReason ? mb_substr(trim($cancellationReason), 0, 255) : $apt['cancellation_reason'],
                ':managed_by'          => $managedByUserId,
                ':id'                  => $appointmentId,
            ]);

            $this->pdo->commit();
            return ['success' => true, 'message' => "Appointment #{$apt['reference_code']} updated to {$newStatus}."];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[Status Update Error]: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update appointment status.'];
        }
    }

    /**
     * Fetch appointments for a specific patient.
     */
    public function getPatientAppointments(int $patientId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.id, a.reference_code, a.appointment_date, a.start_time, a.end_time,
                   a.status, a.patient_notes,
                   s.name AS service_name, s.price,
                   CONCAT('Dr. ', d.first_name, ' ', d.last_name) AS dentist_name
            FROM appointments a
            INNER JOIN services s ON a.service_id = s.id
            INNER JOIN users d ON a.dentist_id = d.id
            WHERE a.patient_id = :patient_id
            ORDER BY a.appointment_date DESC, a.start_time DESC
        ");
        $stmt->execute([':patient_id' => $patientId]);
        return $stmt->fetchAll();
    }

    /**
     * Fetch all clinic appointments for Receptionist / Admin (with optional status or date filters).
     */
    public function getAllAppointments(?string $statusFilter = null, ?string $dateFilter = null): array
    {
        $sql = "
            SELECT a.id, a.reference_code, a.appointment_date, a.start_time, a.end_time,
                   a.status, a.booking_type, a.patient_notes, a.cancellation_reason,
                   s.name AS service_name, s.price, s.duration_minutes,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                   p.phone AS patient_phone,
                   CONCAT('Dr. ', d.first_name, ' ', d.last_name) AS dentist_name
            FROM appointments a
            INNER JOIN services s ON a.service_id = s.id
            INNER JOIN users p ON a.patient_id = p.id
            INNER JOIN users d ON a.dentist_id = d.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($statusFilter)) {
            $sql .= ' AND a.status = :status';
            $params[':status'] = $statusFilter;
        }
        if (!empty($dateFilter)) {
            $sql .= ' AND a.appointment_date = :date';
            $params[':date'] = $dateFilter;
        }

        $sql .= ' ORDER BY a.appointment_date ASC, a.start_time ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch a specific dentist's schedule of appointments.
     */
    public function getDentistAppointments(int $dentistId, ?string $dateFilter = null): array
    {
        $sql = "
            SELECT a.id, a.reference_code, a.patient_id, a.appointment_date, a.start_time, a.end_time,
                   a.status, a.patient_notes,
                   s.name AS service_name, s.duration_minutes,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                   p.phone AS patient_phone, p.date_of_birth,
                   tr.id AS treatment_record_id
            FROM appointments a
            INNER JOIN services s ON a.service_id = s.id
            INNER JOIN users p ON a.patient_id = p.id
            LEFT JOIN treatment_records tr ON tr.appointment_id = a.id
            WHERE a.dentist_id = :dentist_id
              AND a.status != 'Cancelled'
        ";
        $params = [':dentist_id' => $dentistId];

        if (!empty($dateFilter)) {
            $sql .= ' AND a.appointment_date = :date';
            $params[':date'] = $dateFilter;
        }

        $sql .= ' ORDER BY a.appointment_date ASC, a.start_time ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private static function timeToMinutes(string $timeStr): int
    {
        $parts = explode(':', $timeStr);
        return ((int) $parts[0] * 60) + (int) ($parts[1] ?? 0);
    }

    private static function minutesToTime(int $totalMinutes): string
    {
        $hours = intdiv($totalMinutes, 60);
        $mins  = $totalMinutes % 60;
        return sprintf('%02d:%02d:00', $hours, $mins);
    }
}
