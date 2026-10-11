<?php

namespace App\Enums;

/**
 * Kinds of documents kept in an employee's 201 file.
 */
enum DocumentType: string
{
    case Appointment = 'appointment';
    case OathOfOffice = 'oath-of-office';
    case AssumptionToDuty = 'assumption-to-duty';
    case PersonalDataSheet = 'personal-data-sheet';
    case Eligibility = 'eligibility';
    case GovernmentId = 'government-id';
    case Diploma = 'diploma';
    case TrainingCertificate = 'training-certificate';
    case ServiceRecord = 'service-record';
    case Saln = 'saln';
    case MedicalCertificate = 'medical-certificate';
    case NbiClearance = 'nbi-clearance';
    case BirthCertificate = 'birth-certificate';
    case MarriageCertificate = 'marriage-certificate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Appointment => 'Appointment Paper',
            self::OathOfOffice => 'Oath of Office',
            self::AssumptionToDuty => 'Certificate of Assumption to Duty',
            self::PersonalDataSheet => 'Personal Data Sheet (CS Form 212)',
            self::Eligibility => 'Certificate of Eligibility',
            self::GovernmentId => 'Government ID',
            self::Diploma => 'Diploma / Transcript of Records',
            self::TrainingCertificate => 'Training Certificate',
            self::ServiceRecord => 'Service Record',
            self::Saln => 'SALN',
            self::MedicalCertificate => 'Medical Certificate',
            self::NbiClearance => 'NBI Clearance',
            self::BirthCertificate => 'Birth Certificate',
            self::MarriageCertificate => 'Marriage Certificate',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
