<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use Exception;
use PDO;

/**
 * Dentist Clinical Treatment Records & Patient Dental History Model.
 * Compatible with both MySQL (XAMPP) and PostgreSQL (Supabase).
 */
final class TreatmentRecord
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Save a post-appointment treatment record and automatically mark the appointment as Completed.
     */
    public function saveRecord(
        int $appointmentId,
        int $dentistId,
        ?string $toothNumbers,
        string $diagnosis,
        string $procedurePerformed,
        ?string $prescription = null,
        ?string $dentistNotes = null,
        ?string $followUpDate = null
    ): array {
        try {
            $this->pdo->beginTransaction();

            $aptStmt = $this->pdo->prepare('
                SELECT id, patient_id, dentist_id, status
                FROM appointments
                WHERE id = :id AND dentist_id = :dentist_id
                LIMIT 1
                FOR UPDATE
            ');
            $aptStmt->execute([
                ':id'         => $appointmentId,
                ':dentist_id' => $dentistId,
            ]);
            $apt = $aptStmt->fetch();

            if (!$apt) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Appointment not found or not assigned to you.'];
            }

            // Check if a record already exists for this appointment (works identically in MySQL & PostgreSQL)
            $existingStmt = $this->pdo->prepare('
                SELECT id FROM treatment_records WHERE appointment_id = :appointment_id LIMIT 1
            ');
            $existingStmt->execute([':appointment_id' => $appointmentId]);
            $existingRecord = $existingStmt->fetch();

            $params = [
                ':appointment_id'      => $appointmentId,
                ':tooth_numbers'       => $toothNumbers ? mb_substr(trim($toothNumbers), 0, 100) : null,
                ':diagnosis'           => trim($diagnosis),
                ':procedure_performed' => trim($procedurePerformed),
                ':prescription'        => $prescription ? trim($prescription) : null,
                ':dentist_notes'       => $dentistNotes ? trim($dentistNotes) : null,
                ':follow_up_date'      => !empty($followUpDate) ? $followUpDate : null,
            ];

            if ($existingRecord) {
                $stmt = $this->pdo->prepare('
                    UPDATE treatment_records
                    SET tooth_numbers = :tooth_numbers,
                        diagnosis = :diagnosis,
                        procedure_performed = :procedure_performed,
                        prescription = :prescription,
                        dentist_notes = :dentist_notes,
                        follow_up_date = :follow_up_date
                    WHERE appointment_id = :appointment_id
                ');
                $stmt->execute($params);
            } else {
                $params[':patient_id'] = (int) $apt['patient_id'];
                $params[':dentist_id'] = $dentistId;
                $stmt = $this->pdo->prepare('
                    INSERT INTO treatment_records (
                        appointment_id, patient_id, dentist_id, tooth_numbers,
                        diagnosis, procedure_performed, prescription, dentist_notes, follow_up_date
                    ) VALUES (
                        :appointment_id, :patient_id, :dentist_id, :tooth_numbers,
                        :diagnosis, :procedure_performed, :prescription, :dentist_notes, :follow_up_date
                    )
                ');
                $stmt->execute($params);
            }

            $updateApt = $this->pdo->prepare("
                UPDATE appointments SET status = 'Completed', managed_by = :dentist_id WHERE id = :id
            ");
            $updateApt->execute([
                ':dentist_id' => $dentistId,
                ':id'         => $appointmentId,
            ]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Treatment record saved and appointment marked as Completed.'];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[TreatmentRecord Error]: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to save treatment record.'];
        }
    }

    /**
     * Retrieve complete dental treatment history for a patient.
     */
    public function getPatientHistory(int $patientId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT tr.*, a.reference_code, a.appointment_date, s.name AS service_name,
                   CONCAT('Dr. ', d.first_name, ' ', d.last_name) AS dentist_name
            FROM treatment_records tr
            INNER JOIN appointments a ON tr.appointment_id = a.id
            INNER JOIN services s ON a.service_id = s.id
            INNER JOIN users d ON tr.dentist_id = d.id
            WHERE tr.patient_id = :patient_id
            ORDER BY a.appointment_date DESC
        ");
        $stmt->execute([':patient_id' => $patientId]);
        return $stmt->fetchAll();
    }
}
