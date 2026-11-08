# Data Dictionary: if0_40821754_db_ivydental

| TABLE NAME | ATTRIBUTE NAME | CONTENTS | TYPE | FORMAT | REQUIRED | PK OR FK | FK REFERENCED TABLE |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **tbl_appointments** | appointment_id | Unique ID for each dental appointment | INT(11) | 99999 | Y | PK | |
| | patient_id | Unique identifier for the patient | INT(11) | 99999 | Y | FK | tbl_patients |
| | dentist_id | Unique identifier for the dentist/staff | INT(11) | 99999 | Y | FK | tbl_staff |
| | scheduled_datetime | Specific date and time for the scheduled appointment | DATETIME | YYYY-MM-DD HH:MM:SS | Y | | |
| | duration_minutes | Planned duration of the appointment in minutes | INT(11) | 999 | N | | |
| | service_type | Category or description of the dental service provided | VARCHAR(100) | X(100) | N | | |
| | price | Total cost or price for the specific appointment/service | DECIMAL(10,2) | 999999.99 | N | | |
| | service_id | Unique identifier for the specific service from catalog | INT(11) | 99999 | N | | |
| | triage_urgency | Urgency level (Low, Medium, High, Emergency) | ENUM | (List) | N | | |
| | status | Current state (Pending, Scheduled, Confirmed, etc.) | ENUM | (List) | Y | | |
| | created_at | Automatic timestamp when the record was created | TIMESTAMP | Auto | N | | |
| | updated_at | Automatic timestamp when the record was last modified | TIMESTAMP | Auto | N | | |
| **tbl_staff** | staff_id | Unique ID for Staff | INT(11) | 99999 | Y | PK | |
| | username | Unique login credential for staff members | VARCHAR(50) | X(50) | Y | | |
| | first_name | Staff member's first name | VARCHAR(100) | X(100) | Y | | |
| | last_name | Staff member's last name | VARCHAR(100) | X(100) | Y | | |
| | email | Login credential and primary email contact | VARCHAR(100) | X(100) | Y | | |
| | password | Securely hashed password for account access | VARCHAR(255) | X(255) | N | | |
| | role | Staff function (Dentist, Admin, Receptionist, etc.) | ENUM | (List) | Y | | |
| | license_number | Professional license number (Nurse/Dentist) | VARCHAR(50) | X(50) | N | | |
| | phone_number | Staff's primary contact number | VARCHAR(20) | X(20) | N | | |
| | created_at | Record creation timestamp | TIMESTAMP | Auto | N | | |
| | updated_at | Last modification timestamp | TIMESTAMP | Auto | N | | |
| **tbl_patients** | patient_id | Unique ID for each registered patient | INT(11) | 99999 | Y | PK | |
| | username | Patient's unique login username | VARCHAR(50) | X(50) | Y | | |
| | first_name | Patient's official first name | VARCHAR(100) | X(100) | Y | | |
| | last_name | Patient's official last name | VARCHAR(100) | X(100) | Y | | |
| | dob | Patient's legal date of birth | DATE | YYYY-MM-DD | N | | |
| | primary_phone | Main contact phone number for the patient | VARCHAR(20) | X(20) | N | | |
| | email | Patient's primary email address | VARCHAR(100) | X(100) | Y | | |
| | password | Encrypted password for patient portal access | VARCHAR(255) | X(255) | Y | | |
| | blood_type | Patient's blood group (A+, B-, etc.) | ENUM | (List) | N | | |
| | gender | Patient's gender identification | ENUM | (List) | N | | |
| | created_at | Registration timestamp | TIMESTAMP | Auto | N | | |
| | updated_at | Last profile update timestamp | TIMESTAMP | Auto | N | | |
| | status | Current account status | ENUM | (List) | N | | |
| **tbl_payments** | payment_id | Unique ID for each payment transaction | INT(11) | 99999 | Y | PK | |
| | payment_type | Category of payment (Downpayment, Invoice, etc.) | ENUM | (List) | N | | |
| | appointment_id | Associated appointment ID | INT(11) | 99999 | N | FK | tbl_appointments |
| | invoice_id | Associated invoice ID | INT(11) | 99999 | N | FK | tbl_invoices |
| | patient_id | Patient who made the payment | INT(11) | 99999 | N | FK | tbl_patients |
| | amount | Total amount of currency paid | DECIMAL(10,2) | 999999.99 | Y | | |
| | payment_method | Mechanism of payment (Cash, GCash, etc.) | ENUM | (List) | N | | |
| | transaction_id | Reference number from external payment processor | VARCHAR(255) | X(255) | N | | |
| | payment_status | State of the payment | ENUM | (List) | N | | |
| | payment_date | When the payment was processed | DATETIME | YYYY-MM-DD HH:MM:SS | N | | |
| | staff_validator_id | ID of staff who verified manual payments | INT(11) | 99999 | N | FK | tbl_staff |
| | overpayment_credit | Change or overpayment amount stored as credit | DECIMAL(10,2) | 9999.99 | N | | |
| | notes | Internal memos or details regarding the payment | TEXT | Memo | N | | |
| | created_at | Auto-generated record timestamp | TIMESTAMP | Auto | N | | |
| | updated_at | Auto-generated update timestamp | TIMESTAMP | Auto | N | | |
| **tbl_invoices** | invoice_id | Unique ID for each billing invoice | INT(11) | 99999 | Y | PK | |
| | patient_id | Reference to the billed patient | INT(11) | 99999 | N | FK | tbl_patients |
| | appointment_id | Reference to the treatment/appt | INT(11) | 99999 | N | FK | tbl_appointments |
| | total_amount | Gross amount specified in the invoice | DECIMAL(10,2) | 99999.99 | N | | |
| | payment_status | Current billing status (Unpaid, Paid, etc.) | ENUM | (List) | N | | |
| | generated_at | When invoice was issued | DATETIME | YYYY-MM-DD HH:MM:SS | N | | |
| | created_at | Record entry date | TIMESTAMP | Auto | N | | |
| | updated_at | Last update timestamp | TIMESTAMP | Auto | N | | |
| **tbl_service_catalog** | service_id | Unique ID for each clinic service offering | INT(11) | 99999 | Y | PK | |
| | service_name | Full name of the service | VARCHAR(150) | X(150) | Y | | |
| | base_price | Starting price before additional materials/fees | DECIMAL(10,2) | 99999.99 | Y | | |
| | default_duration_minutes| Average time requirement for the service | INT(11) | 999 | N | | |
| | is_active | Availability flag for booking | TINYINT(1) | 0/1 | N | | |
| | maintenance_period_months| Number of months before a required follow-up | INT(11) | 99 | N | | |
| | maintenance_interval | Frequency (Weekly, Monthly, Quarterly) | ENUM | (List) | N | | |
| | created_at | Record entry timestamp | TIMESTAMP | Auto | N | | |
| | updated_at | Last update timestamp | TIMESTAMP | Auto | N | | |
| **tbl_patient_maintenance**| maintenance_id | Unique ID for tracked treatment maintenance | INT(11) | 99999 | Y | PK | |
| | patient_id | The patient undergoing maintenance | INT(11) | 99999 | Y | FK | tbl_patients |
| | service_id | The specific treatment being maintained | INT(11) | 99999 | Y | FK | tbl_service_catalog |
| | related_appointment_id| ID of appointment that started this plan | INT(11) | 99999 | N | FK | tbl_appointments |
| | last_visit_date | The most recent visit for maintenance | DATE | YYYY-MM-DD | N | | |
| | recommended_next_visit| Calculated date for the next follow-up | DATE | YYYY-MM-DD | N | | |
| | status | Current state (Pending, Done, Overdue) | ENUM | (List) | N | | |
| | notes | Clinical notes regarding maintenance progress | TEXT | Memo | N | | |
| | created_at | Record creation timestamp | TIMESTAMP | Auto | N | | |
| | updated_at | Last modification timestamp | TIMESTAMP | Auto | N | | |
| **tbl_clinic_closures** | closure_id | Unique ID for each clinic closure event | INT(11) | 99999 | Y | PK | |
| | closure_date | Specific date when the clinic is closed | DATE | YYYY-MM-DD | Y | | |
| | reason | Reason for closure (Holiday, Maintenance, etc.) | VARCHAR(255) | X(255) | N | | |
| | created_by | ID of the staff member who recorded it | INT(11) | 99999 | Y | FK | tbl_staff |
| | created_at | System timestamp of record creation | DATETIME | Auto | Y | | |
| **tbl_dentist_schedules** | schedule_id | Unique ID for each dentist availability shift | INT(11) | 99999 | Y | PK | |
| | dentist_id | The dentist associated with this schedule | INT(11) | 99999 | Y | FK | tbl_staff |
| | day_of_week | Day (0=Sun to 6=Sat) | TINYINT(1) | 0-6 | Y | | |
| | start_time | Time the shift begins | TIME | HH:MM | Y | | |
| | end_time | Time the shift ends | TIME | HH:MM | Y | | |
| | is_active | Whether this shift is currently active | TINYINT(1) | 0/1 | Y | | |
| | created_at | Record entry timestamp | DATETIME | Auto | Y | | |
| | updated_at | Last modification timestamp | DATETIME | Auto | Y | | |
| **tbl_email_verification**| verification_id | Unique ID for email verification requests | INT(11) | 99999 | Y | PK | |
| | email | The email address being verified | VARCHAR(255) | X(255) | Y | | |
| | verification_code | 6-digit secret code for verification | VARCHAR(6) | X(6) | Y | | |
| | created_at | Code generation timestamp | TIMESTAMP | Auto | N | | |
| | expires_at | When the code becomes invalid | TIMESTAMP | Auto | Y | | |
| | verified_at | When the user successfully verified | TIMESTAMP | Auto | N | | |
| | is_used | Whether the code has already been utilized | TINYINT(1) | 0/1 | N | | |
| **tbl_patient_allergies**| allergy_id | Unique ID for patient allergy records | INT(11) | 99999 | Y | PK | |
| | patient_id | The patient who has the allergy | INT(11) | 99999 | Y | FK | tbl_patients |
| | allergy_name | Name of the allergen (e.g. Penicillin) | VARCHAR(255) | X(255) | Y | | |
| | created_at | Record entry timestamp | TIMESTAMP | Auto | N | | |
| | updated_at | Last modification timestamp | TIMESTAMP | Auto | N | | |
| **tbl_patient_documents** | document_id | Unique ID for each uploaded patient file | INT(11) | 99999 | Y | PK | |
| | patient_id | The patient whom the document belongs to | INT(11) | 99999 | Y | FK | tbl_patients |
| | document_type | Category (X-Ray, Consent Form, etc.)| ENUM | (List) | Y | | |
| | file_name | Original name of the uploaded file | VARCHAR(255) | X(255) | N | | |
| | file_path | Internal server path to the file | VARCHAR(500) | X(500) | N | | |
| | uploaded_at | Timestamp of file upload | TIMESTAMP | Auto | N | | |
| | uploaded_by | Staff member who performed the upload | INT(11) | 99999 | N | FK | tbl_staff |
| | notes | Description or notes about the document | TEXT | Memo | N | | |
| **tbl_service_prerequisites**| prerequisite_id | Unique ID for treatement requirements | INT(11) | 99999 | Y | PK | |
| | service_id | Treatment requiring a prerequisite | INT(11) | 99999 | Y | FK | tbl_service_catalog |
| | required_service_id | Treatment that must be completed first | INT(11) | 99999 | Y | FK | tbl_service_catalog |
| | is_mandatory | Whether treated as a hard block | TINYINT(1) | 0/1 | N | | |
| | created_at | Record creation timestamp | TIMESTAMP | Auto | N | | |
| | validity_period_days | Expiration period for the prerequisite | INT(11) | 999 | N | | |
| **tbl_specializations** | specialization_id | Unique ID for dental specializations | INT(11) | 99999 | Y | PK | |
| | name | Specialization name (e.g. Orthodontics) | VARCHAR(150) | X(150) | Y | | |
| | description | Narrative description of the field | TEXT | Memo | N | | |
| | is_active | Current availability status | TINYINT(1) | 0/1 | N | | |
| | created_at | Record entry timestamp | TIMESTAMP | Auto | N | | |
| | updated_at | Last modification timestamp | TIMESTAMP | Auto | N | | |
| **tbl_staff_education** | education_id | Unique ID for staff educational background | INT(11) | 99999 | Y | PK | |
| | staff_id | The staff member associated with this record | INT(11) | 99999 | Y | FK | tbl_staff |
| | institution_name | Name of the school or university | VARCHAR(255) | X(255) | Y | | |
| | degree | Earned academic degree | VARCHAR(255) | X(255) | Y | | |
| | field_of_study | Major or field of expertise | VARCHAR(255) | X(255) | N | | |
| | start_date | Enrollment date | DATE | YYYY-MM-DD | N | | |
| | end_date | Graduation or completion date | DATE | YYYY-MM-DD | N | | |
| | is_completed | Graduation status flag | TINYINT(1) | 0/1 | N | | |
| | created_at | Record entry timestamp | TIMESTAMP | Auto | N | | |
| | updated_at | Last modification timestamp | TIMESTAMP | Auto | N | | |
