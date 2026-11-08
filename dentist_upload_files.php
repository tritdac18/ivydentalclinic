<?php
require_once 'config/db.php';
require_once 'config/security.php';

// Set security headers and check login
requireLogin();

$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';

// Only allow Dentist to access this page
if ($user_type !== 'staff' || $user_role !== 'Dentist') {
    header("Location: landing.php");
    exit();
}

$dentist_id = $_SESSION['user_id'];

// Fetch dentist info
$conn = getDBConnection();
$user_info = null;
try {
    $stmt = $conn->prepare("SELECT staff_id, first_name, last_name, email, role FROM tbl_staff WHERE staff_id = ? LIMIT 1");
    $stmt->bind_param("i", $dentist_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_info = $result->fetch_assoc();
    } else {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user_name = $user_info['first_name'] . ' ' . $user_info['last_name'];

// Get patient_id from URL if provided
$selected_patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

// Handle file upload
$upload_message = '';
$upload_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_file') {
    $patient_id = intval($_POST['patient_id'] ?? 0);
    $document_type = trim($_POST['document_type'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if ($patient_id <= 0) {
        $upload_error = "Please select a patient.";
    } elseif (empty($document_type)) {
        $upload_error = "Please select a document type.";
    } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $upload_error = "Please select a file to upload.";
    } else {
        $file = $_FILES['file'];
        
        // Validate file
        $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'xls', 'xlsx'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $max_file_size = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $upload_error = "File type not allowed. Allowed types: " . implode(', ', $allowed_extensions);
        } elseif ($file['size'] > $max_file_size) {
            $upload_error = "File size exceeds 10MB limit.";
        } else {
            // Verify patient exists and is assigned to this dentist
            try {
                $conn = getDBConnection();
                
                // Check if patient exists and has appointments with this dentist
                $check_stmt = $conn->prepare("
                    SELECT p.patient_id, p.first_name, p.last_name 
                    FROM tbl_patients p
                    INNER JOIN tbl_appointments a ON p.patient_id = a.patient_id
                    WHERE p.patient_id = ? AND a.dentist_id = ?
                    LIMIT 1
                ");
                $check_stmt->bind_param("ii", $patient_id, $dentist_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows === 0) {
                    $upload_error = "Patient not found or not assigned to you.";
                    $check_stmt->close();
                } else {
                    $check_stmt->close();
                    
                    // Create uploads directory if it doesn't exist
                    $uploads_dir = __DIR__ . '/uploads/patient_documents';
                    if (!is_dir($uploads_dir)) {
                        mkdir($uploads_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $file_name = $file['name'];
                    $unique_name = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
                    $file_path = $uploads_dir . '/' . $unique_name;
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        // Store in database
                        $relative_path = 'uploads/patient_documents/' . $unique_name;
                        $insert_stmt = $conn->prepare("
                            INSERT INTO tbl_patient_documents 
                            (patient_id, document_type, file_name, file_path, uploaded_by, notes) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $insert_stmt->bind_param("isssis", $patient_id, $document_type, $file_name, $relative_path, $dentist_id, $notes);
                        
                        if ($insert_stmt->execute()) {
                            $upload_message = "File uploaded successfully!";
                            // Clear form by redirecting
                            header("Location: dentist_upload_files.php?success=1");
                            exit();
                        } else {
                            // Delete uploaded file if database insert fails
                            @unlink($file_path);
                            $upload_error = "Failed to save file record: " . $conn->error;
                        }
                        $insert_stmt->close();
                    } else {
                        $upload_error = "Failed to upload file. Please try again.";
                    }
                }
                $conn->close();
            } catch (Exception $e) {
                $upload_error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch patients assigned to this dentist
$patients = [];
try {
    $conn = getDBConnection();
    
    $patient_stmt = $conn->prepare("
        SELECT DISTINCT 
            p.patient_id, 
            p.first_name, 
            p.last_name, 
            p.email
        FROM tbl_patients p
        INNER JOIN tbl_appointments a ON p.patient_id = a.patient_id
        WHERE a.dentist_id = ?
        ORDER BY p.last_name, p.first_name
    ");
    $patient_stmt->bind_param("i", $dentist_id);
    $patient_stmt->execute();
    $patient_result = $patient_stmt->get_result();
    while ($row = $patient_result->fetch_assoc()) {
        $patients[] = $row;
    }
    $patient_stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log("Error fetching patients: " . $e->getMessage());
}

// Check for success message
$success_message = isset($_GET['success']) ? "File uploaded successfully!" : '';
?>
<?php
$page_title = 'Upload Patient Files';
include ROOT_PATH . '/views/layout/header.php';
?>
<div class="flex min-h-screen">
    <?php include ROOT_PATH . '/views/layout/sidebar_staff.php'; ?>
    <div class="flex-1 flex flex-col lg:ml-64">
        <?php include ROOT_PATH . '/views/layout/topbar.php'; ?>
<main class="flex-grow w-full max-w-4xl mx-auto px-4 md:px-8 py-8">
<?php if ($success_message): ?>
<div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-400 mb-4">
<p class="text-sm font-medium"><?php echo htmlspecialchars($success_message); ?></p>
</div>
<?php endif; ?>
<?php if ($upload_error): ?>
<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-400 mb-4">
<p class="text-sm font-medium"><?php echo htmlspecialchars($upload_error); ?></p>
</div>
<?php endif; ?>
<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
<h2 class="text-xl font-bold text-slate-900 dark:text-white mb-6">Upload File to Patient Record</h2>
<form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
<input type="hidden" name="action" value="upload_file">
<div class="space-y-6">
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Select Patient <span class="text-red-500">*</span></label>
<select name="patient_id" required class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
<option value="">-- Select Patient --</option>
<?php foreach ($patients as $patient): ?>
<option value="<?php echo $patient['patient_id']; ?>" <?php echo ($selected_patient_id == $patient['patient_id']) ? 'selected' : ''; ?>>
<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['email'] . ')'); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Document Type <span class="text-red-500">*</span></label>
<select name="document_type" required class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
<option value="">-- Select Document Type --</option>
<option value="X-Ray">X-Ray</option>
<option value="Medical Record">Medical Record</option>
<option value="Consent Form">Consent Form</option>
<option value="Parent Consent">Parent Consent</option>
<option value="Treatment Plan">Treatment Plan</option>
<option value="Prescription">Prescription</option>
<option value="Lab Result">Lab Result</option>
<option value="Insurance Document">Insurance Document</option>
<option value="Other">Other</option>
</select>
</div>
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">File <span class="text-red-500">*</span></label>
<input type="file" name="file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.txt,.xls,.xlsx,image/png,image/jpeg,image/jpg,image/gif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Allowed: PDF, DOC, DOCX, JPG, JPEG, PNG, GIF, TXT, XLS, XLSX (Max 10MB)</p>
</div>
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Notes (Optional)</label>
<textarea name="notes" rows="3" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Add any notes about this file..."></textarea>
</div>
</div>
<div class="mt-6 flex justify-end gap-2">
<button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors flex items-center gap-2">
<span class="material-symbols-outlined text-lg">upload_file</span>
Upload File
</button>
</div>
</form>
</div>
</main>
        </main><!-- /.main -->
    </div><!-- /flex-1 -->
</div><!-- /flex min-h-screen -->
<?php ob_start(); ?>
<div id="confirmModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
<div id="confirmModalContent" class="bg-white dark:bg-[#1a2634] rounded-xl p-6 max-w-md w-full mx-4 shadow-xl">
</div>
</div>
<script>
function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function confirmLogout(event) {
    event.preventDefault();
    const modal = document.getElementById('confirmModal');
    const content = document.getElementById('confirmModalContent');
    if (modal && content) {
        content.innerHTML = `
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Log Out?</h3>
                <button onclick="closeConfirmModal()" class="text-[#4c6c9a] hover:text-[#0d131b] dark:text-slate-400 dark:hover:text-white">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <p class="text-sm text-[#4c6c9a] dark:text-slate-400 mb-4">Are you sure you want to log out?</p>
            <div class="flex gap-2">
                <button type="button" onclick="closeConfirmModal()" class="flex-1 rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white py-2 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    No
                </button>
                <a href="logout.php" class="flex-1 rounded-lg bg-red-600 text-white py-2 text-sm font-semibold hover:bg-red-700 transition-colors text-center">
                    Yes
                </a>
            </div>
        `;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeConfirmModal();
    }
});
</script>
<?php $extra_scripts = ob_get_clean(); include ROOT_PATH . '/views/layout/footer.php'; ?>
