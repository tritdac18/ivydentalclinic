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
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

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
} catch (Exception $e) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user_name = $user_info['first_name'] . ' ' . $user_info['last_name'];

// Fetch patient info and verify access
$patient = null;
$documents = [];

if ($patient_id > 0) {
    try {
        // Verify patient is assigned to this dentist
        $check_stmt = $conn->prepare("
            SELECT DISTINCT p.patient_id, p.first_name, p.last_name, p.email
            FROM tbl_patients p
            INNER JOIN tbl_appointments a ON p.patient_id = a.patient_id
            WHERE p.patient_id = ? AND a.dentist_id = ?
            LIMIT 1
        ");
        $check_stmt->bind_param("ii", $patient_id, $dentist_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 1) {
            $patient = $check_result->fetch_assoc();
            
            // Fetch documents for this patient
            $doc_stmt = $conn->prepare("
                SELECT d.document_id, d.document_type, d.file_name, d.file_path, d.uploaded_at, d.notes,
                       s.first_name as uploader_first, s.last_name as uploader_last
                FROM tbl_patient_documents d
                LEFT JOIN tbl_staff s ON d.uploaded_by = s.staff_id
                WHERE d.patient_id = ?
                ORDER BY d.uploaded_at DESC
            ");
            $doc_stmt->bind_param("i", $patient_id);
            $doc_stmt->execute();
            $doc_result = $doc_stmt->get_result();
            while ($row = $doc_result->fetch_assoc()) {
                $documents[] = $row;
            }
            $doc_stmt->close();
        }
        $check_stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching patient documents: " . $e->getMessage());
    }
}

$conn->close();

// Fetch patients assigned to this dentist for dropdown
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
?>
<?php
$page_title = 'Patient Files';
include ROOT_PATH . '/views/layout/header.php';
?>
<div class="flex min-h-screen">
    <?php include ROOT_PATH . '/views/layout/sidebar_staff.php'; ?>
    <div class="flex-1 flex flex-col lg:ml-64">
        <?php include ROOT_PATH . '/views/layout/topbar.php'; ?>
<main class="flex-grow w-full max-w-7xl mx-auto px-4 md:px-8 py-8">
<div class="mb-6">
<h2 class="text-2xl font-bold text-[#0d131b] dark:text-white">Patient Files</h2>
<p class="text-sm text-[#4c6c9a] dark:text-slate-400 mt-1">View and manage files uploaded to patient records</p>
</div>

<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-6">
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Select Patient</label>
<select id="patientSelect" onchange="window.location.href='dentist_view_files.php?patient_id=' + this.value" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
<option value="">-- Select Patient to View Files --</option>
<?php foreach ($patients as $p): ?>
<option value="<?php echo $p['patient_id']; ?>" <?php echo ($patient && $p['patient_id'] == $patient['patient_id']) ? 'selected' : ''; ?>>
<?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name'] . ' (' . $p['email'] . ')'); ?>
</option>
<?php endforeach; ?>
</select>
</div>

<?php if ($patient): ?>
<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-6">
<h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">
<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
</h3>
<p class="text-sm text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($patient['email']); ?></p>
</div>

<?php if (empty($documents)): ?>
<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-8 text-center">
<p class="text-[#4c6c9a] dark:text-slate-400">No files uploaded for this patient yet.</p>
<a href="dentist_upload_files.php?patient_id=<?php echo $patient['patient_id']; ?>" class="inline-flex items-center gap-2 mt-4 text-primary hover:text-primary-dark font-medium">
<span class="material-symbols-outlined">upload_file</span>
Upload First File
</a>
</div>
<?php else: ?>
<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
<div class="overflow-x-auto">
<table class="w-full">
<thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-[#e7ecf3] dark:border-slate-700">
<tr>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Document Type</th>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">File Name</th>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Notes</th>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Uploaded By</th>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Date</th>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-[#e7ecf3] dark:divide-slate-700">
<?php foreach ($documents as $doc): ?>
<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
<td class="px-6 py-4 whitespace-nowrap">
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
<?php echo htmlspecialchars($doc['document_type']); ?>
</span>
</td>
<td class="px-6 py-4">
<div class="text-sm font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($doc['file_name']); ?></div>
</td>
<td class="px-6 py-4">
<div class="text-sm text-[#4c6c9a] dark:text-slate-400">
<?php if ($doc['notes']): ?>
<?php echo htmlspecialchars($doc['notes']); ?>
<?php else: ?>
<span class="text-slate-400 dark:text-slate-500 italic">No notes</span>
<?php endif; ?>
</div>
</td>
<td class="px-6 py-4 whitespace-nowrap">
<div class="text-sm text-[#4c6c9a] dark:text-slate-400">
<?php 
if ($doc['uploader_first'] && $doc['uploader_last']) {
    echo htmlspecialchars($doc['uploader_first'] . ' ' . $doc['uploader_last']);
} else {
    echo 'System';
}
?>
</div>
</td>
<td class="px-6 py-4 whitespace-nowrap">
<div class="text-sm text-[#4c6c9a] dark:text-slate-400">
<?php 
$upload_date = new DateTime($doc['uploaded_at']);
echo $upload_date->format('M d, Y H:i');
?>
</div>
</td>
<td class="px-6 py-4 whitespace-nowrap">
<a href="download_patient_file.php?id=<?php echo $doc['document_id']; ?>" class="inline-flex items-center gap-2 text-sm text-primary hover:text-primary-dark font-medium">
<span class="material-symbols-outlined text-[18px]">download</span>
Download
</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>
<?php else: ?>
<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-8 text-center">
<p class="text-[#4c6c9a] dark:text-slate-400">Please select a patient to view their files.</p>
</div>
<?php endif; ?>
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
