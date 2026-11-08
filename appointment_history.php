<?php
require_once 'config/db.php';
require_once 'config/security.php';

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
} catch (Exception $e) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user_name = $user_info['first_name'] . ' ' . $user_info['last_name'];

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Search query
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = '';

// Fetch appointments assigned to this dentist
$all_appointments = [];
$total_appointments = 0;

try {
    // Build search condition
    $search_condition = "";
    if (!empty($search_query)) {
        $search_param = '%' . $search_query . '%';
        $search_condition = " AND (
            p.first_name LIKE ? OR 
            p.last_name LIKE ? OR 
            CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR
            sv.service_name LIKE ? OR
            a.status LIKE ? OR
            DATE_FORMAT(a.scheduled_datetime, '%M %d, %Y') LIKE ? OR
            DATE_FORMAT(a.scheduled_datetime, '%b %d, %Y') LIKE ?
        )";
    }
    
    // Count total with search
    $count_sql = "
        SELECT COUNT(*) as total
        FROM tbl_appointments a
        INNER JOIN tbl_patients p ON a.patient_id = p.patient_id
        LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
        WHERE a.dentist_id = ?" . $search_condition;
    
    $count_stmt = $conn->prepare($count_sql);
    if (!empty($search_query)) {
        $count_stmt->bind_param("isssssss", $dentist_id, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param);
    } else {
        $count_stmt->bind_param("i", $dentist_id);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_appointments = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
    
    // Fetch appointments with pagination and search
    $appointment_sql = "
        SELECT 
            a.appointment_id, 
            a.scheduled_datetime, 
            a.duration_minutes, 
            a.status,
            p.first_name as patient_first_name,
            p.last_name as patient_last_name,
            sv.service_name as catalog_service_name
        FROM tbl_appointments a
        INNER JOIN tbl_patients p ON a.patient_id = p.patient_id
        LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
        WHERE a.dentist_id = ?" . $search_condition . "
        ORDER BY a.scheduled_datetime DESC
        LIMIT ? OFFSET ?";
    
    $appointment_stmt = $conn->prepare($appointment_sql);
    if (!empty($search_query)) {
        $appointment_stmt->bind_param("isssssssii", $dentist_id, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $per_page, $offset);
    } else {
        $appointment_stmt->bind_param("iii", $dentist_id, $per_page, $offset);
    }
    $appointment_stmt->execute();
    $appointment_result = $appointment_stmt->get_result();
    
    while ($row = $appointment_result->fetch_assoc()) {
        // Set service_name
        if (isset($row['catalog_service_name']) && !empty($row['catalog_service_name'])) {
            $row['service_name'] = $row['catalog_service_name'];
        } else {
            $row['service_name'] = 'Appointment';
        }
        $all_appointments[] = $row;
    }
    $appointment_stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
    if ($conn) $conn->close();
}

$total_pages = ceil($total_appointments / $per_page);

// Handle AJAX requests for real-time search
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'appointments' => $all_appointments,
        'total_appointments' => $total_appointments,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'search_query' => $search_query
    ]);
    exit();
}
?>
<?php
$page_title = 'Appointment History';
$user_name  = $user_name ?? '';
include ROOT_PATH . '/views/layout/header.php';
?>
<div class="flex min-h-screen">
    <?php include ROOT_PATH . '/views/layout/sidebar_staff.php'; ?>
    <div class="flex-1 flex flex-col lg:ml-64">
        <?php include ROOT_PATH . '/views/layout/topbar.php'; ?>
<main class="flex-grow w-full max-w-7xl mx-auto px-4 md:px-8 py-8">
<div class="max-w-7xl mx-auto">
<div class="mb-6">
<h2 class="text-2xl font-bold text-[#0d131b] dark:text-white">Appointment History</h2>
<p class="text-sm text-[#4c6c9a] dark:text-slate-400 mt-1">All appointments assigned to you</p>
</div>

<!-- Search Bar -->
<div class="mb-6">
<div class="flex gap-3">
<div class="flex-1 relative">
<input 
    type="text" 
    name="search" 
    id="searchInput"
    value="<?php echo htmlspecialchars($search_query); ?>" 
    placeholder="Search by patient name, service, status, or date..." 
    class="w-full px-4 py-2.5 pl-10 rounded-lg border border-[#e7ecf3] dark:border-slate-700 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary focus:border-primary transition-all"
    autocomplete="off"
/>
<span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-[#4c6c9a] dark:text-slate-400">
<span class="material-symbols-outlined text-xl" id="searchIcon">search</span>
</span>
<span id="loadingSpinner" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-primary hidden">
<span class="material-symbols-outlined text-xl animate-spin">sync</span>
</span>
<button 
    type="button" 
    id="clearSearchBtn"
    onclick="clearSearch()" 
    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-[#4c6c9a] dark:text-slate-400 hover:text-[#0d131b] dark:hover:text-white <?php echo empty($search_query) ? 'hidden' : ''; ?>"
    title="Clear search"
>
<span class="material-symbols-outlined text-xl">close</span>
</button>
</div>
<button 
    type="button"
    id="clearBtn"
    onclick="clearSearch()" 
    class="px-6 py-2.5 rounded-lg border border-[#e7ecf3] dark:border-slate-700 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors flex items-center gap-2 <?php echo empty($search_query) ? 'hidden' : ''; ?>"
>
<span class="material-symbols-outlined text-xl">clear</span>
<span>Clear</span>
</button>
</div>
<div id="searchResultsInfo" class="mt-3 text-sm text-[#4c6c9a] dark:text-slate-400 <?php echo empty($search_query) ? 'hidden' : ''; ?>">
Showing <span id="resultsCount"><?php echo $total_appointments; ?></span> result<span id="resultsPlural"><?php echo $total_appointments !== 1 ? 's' : ''; ?></span> for "<strong class="text-[#0d131b] dark:text-white" id="searchQueryText"><?php echo htmlspecialchars($search_query); ?></strong>"
</div>
</div>

<div id="appointmentsContainer">
<?php if (empty($all_appointments)): ?>
<div class="rounded-xl border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 shadow-sm p-8 text-center">
<p class="text-[#4c6c9a] dark:text-slate-400">No appointment history found.</p>
</div>
<?php else: ?>
<div class="rounded-xl border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 shadow-sm overflow-hidden">
<div class="overflow-x-auto">
<table class="w-full">
<thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-[#e7ecf3] dark:border-slate-700">
<tr>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Date & Time</th>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Patient</th>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Service</th>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Status</th>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Duration</th>
</tr>
</thead>
<tbody id="appointmentsTableBody" class="divide-y divide-[#e7ecf3] dark:divide-slate-700">
<?php foreach ($all_appointments as $apt): 
    $apt_date = new DateTime($apt['scheduled_datetime']);
    $status = strtolower($apt['status']);
    
    // Status badge colors
    if ($status === 'completed') {
        $status_class = 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
    } elseif ($status === 'missed') {
        $status_class = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
    } elseif ($status === 'appointing') {
        $status_class = 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400';
    } elseif ($status === 'proceed to payment') {
        $status_class = 'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-400';
    } elseif ($status === 'scheduled' || $status === 'confirmed') {
        $status_class = 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
    } else {
        $status_class = 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
    }
?>
<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
<td class="px-6 py-4 whitespace-nowrap">
<div class="text-sm font-medium text-[#0d131b] dark:text-white"><?php echo $apt_date->format('M d, Y'); ?></div>
<div class="text-xs text-[#4c6c9a] dark:text-slate-400"><?php echo $apt_date->format('h:i A'); ?></div>
</td>
<td class="px-6 py-4 whitespace-nowrap">
<div class="text-sm font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($apt['patient_first_name'] . ' ' . $apt['patient_last_name']); ?></div>
</td>
<td class="px-6 py-4 whitespace-nowrap">
<div class="text-sm text-[#4c6c9a] dark:text-slate-400"><?php echo htmlspecialchars($apt['service_name']); ?></div>
</td>
<td class="px-6 py-4 whitespace-nowrap">
<span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $status_class; ?>">
<?php echo htmlspecialchars(ucfirst($apt['status'])); ?>
</span>
</td>
<td class="px-6 py-4 whitespace-nowrap">
<div class="text-sm text-[#4c6c9a] dark:text-slate-400"><?php echo $apt['duration_minutes'] ?? 60; ?> min</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>
</div>

<div id="paginationContainer">
<?php if ($total_pages > 1): ?>
<div class="mt-4 flex items-center justify-center gap-2">
<?php 
$pagination_url = "appointment_history.php";
if (!empty($search_query)) {
    $pagination_url .= "?search=" . urlencode($search_query) . "&";
} else {
    $pagination_url .= "?";
}
?>
<?php if ($page > 1): ?>
<a href="<?php echo $pagination_url; ?>page=<?php echo $page - 1; ?>" class="px-3 py-1 rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Previous</a>
<?php endif; ?>
<?php for ($i = 1; $i <= $total_pages; $i++): ?>
<?php if ($i === $page): ?>
<span class="px-3 py-1 rounded-lg bg-primary text-white"><?php echo $i; ?></span>
<?php else: ?>
<a href="<?php echo $pagination_url; ?>page=<?php echo $i; ?>" class="px-3 py-1 rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"><?php echo $i; ?></a>
<?php endif; ?>
<?php endfor; ?>
<?php if ($page < $total_pages): ?>
<a href="<?php echo $pagination_url; ?>page=<?php echo $page + 1; ?>" class="px-3 py-1 rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Next</a>
<?php endif; ?>
</div>
<?php endif; ?>
</div>
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

// Real-time search functionality
let searchTimeout;
let currentPage = <?php echo $page; ?>;

function getStatusClass(status) {
    const statusLower = status.toLowerCase();
    if (statusLower === 'completed') {
        return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
    } else if (statusLower === 'missed') {
        return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
    } else if (statusLower === 'appointing') {
        return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400';
    } else if (statusLower === 'proceed to payment') {
        return 'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-400';
    } else if (statusLower === 'scheduled' || statusLower === 'confirmed') {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
    } else {
        return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
    }
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const month = months[date.getMonth()];
    const day = date.getDate();
    const year = date.getFullYear();
    
    let hours = date.getHours();
    const minutes = date.getMinutes();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const minutesStr = minutes < 10 ? '0' + minutes : minutes;
    
    return {
        date: `${month} ${day}, ${year}`,
        time: `${hours}:${minutesStr} ${ampm}`
    };
}

function updateAppointmentsTable(data) {
    const container = document.getElementById('appointmentsContainer');
    const tableBody = document.getElementById('appointmentsTableBody');
    const paginationContainer = document.getElementById('paginationContainer');
    const resultsInfo = document.getElementById('searchResultsInfo');
    const resultsCount = document.getElementById('resultsCount');
    const resultsPlural = document.getElementById('resultsPlural');
    const searchQueryText = document.getElementById('searchQueryText');
    const clearBtn = document.getElementById('clearSearchBtn');
    const clearBtnMain = document.getElementById('clearBtn');
    
    if (!data.appointments || data.appointments.length === 0) {
        container.innerHTML = '<div class="rounded-xl border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 shadow-sm p-8 text-center"><p class="text-[#4c6c9a] dark:text-slate-400">No appointment history found.</p></div>';
        paginationContainer.innerHTML = '';
    } else {
        let tableHTML = '<div class="rounded-xl border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 shadow-sm overflow-hidden"><div class="overflow-x-auto"><table class="w-full"><thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-[#e7ecf3] dark:border-slate-700"><tr><th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Date & Time</th><th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Patient</th><th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Service</th><th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Status</th><th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Duration</th></tr></thead><tbody id="appointmentsTableBody" class="divide-y divide-[#e7ecf3] dark:divide-slate-700">';
        
        data.appointments.forEach(function(apt) {
            const dateFormatted = formatDate(apt.scheduled_datetime);
            const statusClass = getStatusClass(apt.status);
            const patientName = (apt.patient_first_name || '') + ' ' + (apt.patient_last_name || '');
            const serviceName = apt.service_name || apt.catalog_service_name || 'Appointment';
            const duration = apt.duration_minutes || 60;
            
            tableHTML += `<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-[#0d131b] dark:text-white">${dateFormatted.date}</div>
                    <div class="text-xs text-[#4c6c9a] dark:text-slate-400">${dateFormatted.time}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-[#0d131b] dark:text-white">${escapeHtml(patientName.trim())}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-[#4c6c9a] dark:text-slate-400">${escapeHtml(serviceName)}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold ${statusClass}">${escapeHtml(apt.status.charAt(0).toUpperCase() + apt.status.slice(1))}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-[#4c6c9a] dark:text-slate-400">${duration} min</div>
                </td>
            </tr>`;
        });
        
        tableHTML += '</tbody></table></div></div>';
        container.innerHTML = tableHTML;
        
        // Update pagination
        if (data.total_pages > 1) {
            let paginationHTML = '<div class="mt-4 flex items-center justify-center gap-2">';
            const paginationUrl = data.search_query ? `appointment_history.php?search=${encodeURIComponent(data.search_query)}&` : 'appointment_history.php?';
            
            if (data.current_page > 1) {
                paginationHTML += `<a href="${paginationUrl}page=${data.current_page - 1}" class="px-3 py-1 rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Previous</a>`;
            }
            
            for (let i = 1; i <= data.total_pages; i++) {
                if (i === data.current_page) {
                    paginationHTML += `<span class="px-3 py-1 rounded-lg bg-primary text-white">${i}</span>`;
                } else {
                    paginationHTML += `<a href="${paginationUrl}page=${i}" class="px-3 py-1 rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">${i}</a>`;
                }
            }
            
            if (data.current_page < data.total_pages) {
                paginationHTML += `<a href="${paginationUrl}page=${data.current_page + 1}" class="px-3 py-1 rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Next</a>`;
            }
            
            paginationHTML += '</div>';
            paginationContainer.innerHTML = paginationHTML;
        } else {
            paginationContainer.innerHTML = '';
        }
    }
    
    // Update results info
    if (data.search_query) {
        resultsCount.textContent = data.total_appointments;
        resultsPlural.textContent = data.total_appointments !== 1 ? 's' : '';
        searchQueryText.textContent = data.search_query;
        resultsInfo.classList.remove('hidden');
        clearBtn.classList.remove('hidden');
        clearBtnMain.classList.remove('hidden');
    } else {
        resultsInfo.classList.add('hidden');
        clearBtn.classList.add('hidden');
        clearBtnMain.classList.add('hidden');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function performSearch(searchQuery, page = 1) {
    const searchInput = document.getElementById('searchInput');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const searchIcon = document.getElementById('searchIcon');
    
    // Show loading state
    if (loadingSpinner && searchIcon) {
        searchIcon.classList.add('hidden');
        loadingSpinner.classList.remove('hidden');
    }
    
    // Update URL without page reload
    const url = new URL(window.location.href);
    if (searchQuery) {
        url.searchParams.set('search', searchQuery);
        url.searchParams.set('page', page);
    } else {
        url.searchParams.delete('search');
        url.searchParams.set('page', page);
    }
    window.history.pushState({}, '', url);
    
    // Make AJAX request
    fetch(`appointment_history.php?ajax=1&search=${encodeURIComponent(searchQuery)}&page=${page}`)
        .then(response => response.json())
        .then(data => {
            updateAppointmentsTable(data);
            currentPage = data.current_page;
            
            // Hide loading state
            if (loadingSpinner && searchIcon) {
                loadingSpinner.classList.add('hidden');
                searchIcon.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            // Hide loading state
            if (loadingSpinner && searchIcon) {
                loadingSpinner.classList.add('hidden');
                searchIcon.classList.remove('hidden');
            }
        });
}

// Clear search function
function clearSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.value = '';
        performSearch('', 1);
    }
}

// Real-time search with debouncing
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchQuery = e.target.value.trim();
            
            // Clear previous timeout
            clearTimeout(searchTimeout);
            
            // Set new timeout for debouncing (500ms delay)
            searchTimeout = setTimeout(function() {
                performSearch(searchQuery, 1);
            }, 500);
        });
        
        // Handle Enter key to search immediately
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimeout);
                const searchQuery = e.target.value.trim();
                performSearch(searchQuery, 1);
            }
        });
    }
});
<?php $extra_scripts = ob_get_clean(); include ROOT_PATH . '/views/layout/footer.php'; ?>
