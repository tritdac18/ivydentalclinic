-- Update tbl_patient_documents to support multiple document types for dentist file uploads
-- This expands the document_type ENUM to include various file types dentists may upload

ALTER TABLE `tbl_patient_documents` 
MODIFY COLUMN `document_type` ENUM(
    'Parent Consent',
    'X-Ray',
    'Medical Record',
    'Consent Form',
    'Treatment Plan',
    'Prescription',
    'Lab Result',
    'Insurance Document',
    'Other'
) NOT NULL;

