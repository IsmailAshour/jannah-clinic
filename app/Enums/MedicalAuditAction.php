<?php

namespace App\Enums;

enum MedicalAuditAction: string
{
    case EntryCreated = 'entry.created';
    case EntryUpdated = 'entry.updated';
    case EntryViewed = 'entry.viewed';
    case PrescriptionCreated = 'prescription.created';
    case PrescriptionUpdated = 'prescription.updated';
    case PrescriptionDeleted = 'prescription.deleted';
    case ProfileMedicalViewed = 'profile_medical.viewed';
    case ProfileMedicalUpdated = 'profile_medical.updated';
}
