<?php
require_once 'config/db.php';
require_once 'config/security.php';

// Set security headers and check login
requireLogin();

$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';

// Determine patient ID based on user type
$patient_id = 0;

if ($user_type === 'patient') {
    // Patient viewing their own consent
    $patient_id = $_SESSION['user_id'] ?? 0;
} elseif ($user_type === 'staff' && in_array($user_role, ['Dentist', 'Manager', 'Admin'])) {
    // Staff viewing a patient's consent - get patient_id from query parameter
    $patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
} else {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied.");
}

if ($patient_id <= 0) {
    header("HTTP/1.1 400 Bad Request");
    die("Invalid patient ID.");
}

try {
    $conn = getDBConnection();
    
    // Fetch patient information (check if address column exists)
    $column_check = $conn->query("SHOW COLUMNS FROM tbl_patients LIKE 'address'");
    $has_address = $column_check && $column_check->num_rows > 0;
    
    $address_field = $has_address ? ', address' : '';
    $patient_stmt = $conn->prepare("
        SELECT patient_id, first_name, last_name, dob, email, primary_phone{$address_field}
        FROM tbl_patients
        WHERE patient_id = ?
        LIMIT 1
    ");
    $patient_stmt->bind_param("i", $patient_id);
    $patient_stmt->execute();
    $patient_result = $patient_stmt->get_result();
    
    if ($patient_result->num_rows === 0) {
        $patient_stmt->close();
        $conn->close();
        header("HTTP/1.1 404 Not Found");
        die("Patient not found.");
    }
    
    $patient = $patient_result->fetch_assoc();
    $patient_stmt->close();
    
    // Verify access for dentists
    if ($user_type === 'staff' && $user_role === 'Dentist') {
        // Dentist can only access parent consent for their assigned patients
        $check_stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM tbl_appointments a
            WHERE a.patient_id = ? AND a.dentist_id = ?
            LIMIT 1
        ");
        $check_stmt->bind_param("ii", $patient_id, $_SESSION['user_id']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_data = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if ($check_data['count'] == 0) {
            $conn->close();
            header("HTTP/1.1 403 Forbidden");
            die("Access denied. You can only access parent consent for your assigned patients.");
        }
    }
    
    // Calculate age
    $age = null;
    if (!empty($patient['dob'])) {
        $dob_date = new DateTime($patient['dob']);
        $today = new DateTime();
        $age = $today->diff($dob_date)->y;
    }
    
    // Fetch parent consent document
    $consent_stmt = $conn->prepare("
        SELECT document_id, document_type, notes, uploaded_at
        FROM tbl_patient_documents
        WHERE patient_id = ? AND document_type = 'Parent Consent'
        ORDER BY uploaded_at DESC
        LIMIT 1
    ");
    $consent_stmt->bind_param("i", $patient_id);
    $consent_stmt->execute();
    $consent_result = $consent_stmt->get_result();
    
    if ($consent_result->num_rows === 0) {
        $consent_stmt->close();
        $conn->close();
        header("HTTP/1.1 404 Not Found");
        die("Parent consent not found.");
    }
    
    $consent_data = $consent_result->fetch_assoc();
    $consent_stmt->close();
    $conn->close();
    
    // Parse parent consent notes
    $parent_name = '';
    $parent_relationship = '';
    $parent_phone = '';
    $parent_email = '';
    $consent_date = '';
    
    if (!empty($consent_data['notes'])) {
        $notes = $consent_data['notes'];
        
        // Parse notes format: "Parent/Guardian: {name} ({relationship})\nPhone: {phone}\nEmail: {email}\nConsent Date: {date}"
        if (preg_match('/Parent\/Guardian:\s*(.+?)\s*\(([^)]+)\)/', $notes, $matches)) {
            $parent_name = trim($matches[1]);
            $parent_relationship = trim($matches[2]);
        }
        
        if (preg_match('/Phone:\s*(.+?)(?:\n|$)/', $notes, $matches)) {
            $parent_phone = trim($matches[1]);
        }
        
        if (preg_match('/Email:\s*(.+?)(?:\n|$)/', $notes, $matches)) {
            $parent_email = trim($matches[1]);
        }
        
        if (preg_match('/Consent Date:\s*(.+?)(?:\n|$)/', $notes, $matches)) {
            $consent_date = trim($matches[1]);
        }
    }
    
    // Use uploaded_at if consent_date is empty
    if (empty($consent_date) && !empty($consent_data['uploaded_at'])) {
        $consent_date = date('F d, Y', strtotime($consent_data['uploaded_at']));
    }
    
    // Format patient date of birth
    $patient_dob = !empty($patient['dob']) ? date('F d, Y', strtotime($patient['dob'])) : 'N/A';
    
} catch (Exception $e) {
    error_log("Error fetching parent consent: " . $e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    die("Error generating PDF.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Consent Form - <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @page {
            margin: 20mm;
        }
        body {
            font-family: 'Times New Roman', serif;
            line-height: 1.6;
            color: #000;
            max-width: 210mm;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #136dec;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .clinic-name {
            font-size: 24px;
            font-weight: bold;
            color: #136dec;
            margin-bottom: 5px;
        }
        .document-title {
            font-size: 20px;
            font-weight: bold;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #136dec;
            border-bottom: 2px solid #136dec;
            padding-bottom: 5px;
        }
        .info-row {
            margin-bottom: 8px;
            display: flex;
        }
        .info-label {
            font-weight: bold;
            width: 180px;
            flex-shrink: 0;
        }
        .info-value {
            flex: 1;
        }
        .consent-text {
            text-align: justify;
            margin: 20px 0;
            line-height: 1.8;
            font-size: 14px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ccc;
            font-size: 12px;
            text-align: center;
            color: #666;
        }
        .date-stamp {
            text-align: right;
            margin-top: 30px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="clinic-name">IVY DENTAL CLINIC</div>
        <div style="font-size: 12px; color: #666; margin-top: 5px;">Professional Dental Care Services</div>
        <div class="document-title">Parent/Guardian Consent Form</div>
    </div>

    <div class="section">
        <div class="section-title">Patient Information</div>
        <div class="info-row">
            <div class="info-label">Patient Name:</div>
            <div class="info-value"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Date of Birth:</div>
            <div class="info-value"><?php echo htmlspecialchars($patient_dob); ?></div>
        </div>
        <?php if ($age !== null): ?>
        <div class="info-row">
            <div class="info-label">Age:</div>
            <div class="info-value"><?php echo $age; ?> years old</div>
        </div>
        <?php endif; ?>
        <div class="info-row">
            <div class="info-label">Patient Email:</div>
            <div class="info-value"><?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Patient Phone:</div>
            <div class="info-value"><?php echo htmlspecialchars($patient['primary_phone'] ?? 'N/A'); ?></div>
        </div>
        <?php if (!empty($patient['address'])): ?>
        <div class="info-row">
            <div class="info-label">Address:</div>
            <div class="info-value"><?php echo htmlspecialchars($patient['address']); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <div class="section-title">Parent/Guardian Information</div>
        <div class="info-row">
            <div class="info-label">Name:</div>
            <div class="info-value"><?php echo htmlspecialchars($parent_name ?: 'N/A'); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Relationship:</div>
            <div class="info-value"><?php echo htmlspecialchars($parent_relationship ?: 'N/A'); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Phone:</div>
            <div class="info-value"><?php echo htmlspecialchars($parent_phone ?: 'N/A'); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Email:</div>
            <div class="info-value"><?php echo htmlspecialchars($parent_email ?: 'N/A'); ?></div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Consent Statement</div>
        <div class="consent-text">
            <p>I, <strong><?php echo htmlspecialchars($parent_name ?: '[Parent/Guardian Name]'); ?></strong>, as the <?php echo htmlspecialchars($parent_relationship ?: '[Relationship]'); ?> of <strong><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></strong>, hereby give my consent for the registration and treatment of this minor patient at Ivy Dental Clinic.</p>
            
            <p>I understand and acknowledge that:</p>
            <ol style="margin-left: 20px; margin-top: 10px;">
                <li>I am the legal parent/guardian of the above-named minor patient.</li>
                <li>I authorize Ivy Dental Clinic to provide dental treatment and services to the minor patient as deemed necessary by the attending dentist.</li>
                <li>I understand the nature of the proposed treatment and any associated risks, and I consent to the performance of such treatment.</li>
                <li>I authorize the release of medical/dental information as necessary for the treatment of the minor patient.</li>
                <li>I understand that I am financially responsible for all charges incurred for the treatment provided to the minor patient.</li>
                <li>I have been informed of the clinic's policies and procedures, and I agree to comply with them.</li>
            </ol>
            
            <p>This consent is valid until the minor patient reaches the age of majority (18 years old) or until I revoke this consent in writing, whichever comes first.</p>
        </div>
    </div>

    <div class="date-stamp">
        <strong>Consent Date:</strong> <?php echo htmlspecialchars($consent_date ?: date('F d, Y')); ?>
    </div>

    <div class="footer">
        <p>This document was generated on <?php echo date('F d, Y \a\t g:i A'); ?> from the Ivy Dental Clinic Patient Portal.</p>
        <p>For questions or concerns, please contact Ivy Dental Clinic.</p>
    </div>

    <script>
        window.onload = function() {
            const element = document.body;
            const filename = 'Parent_Consent_<?php echo htmlspecialchars($patient['first_name'] . '_' . $patient['last_name']); ?>_<?php echo date('Y-m-d'); ?>.pdf';
            
            const options = {
                margin: [10, 10, 10, 10],
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(options).from(element).save().then(function() {
                setTimeout(function() {
                    window.close();
                }, 1000);
            });
        };
    </script>
</body>
</html>

