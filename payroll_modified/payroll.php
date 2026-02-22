<?php
/**
 * Payroll System - Payroll List
 * Shows departments first, then payroll records when a department is selected
 * Bulk status change - all employees change together (only non-paid records)
 * Paid status is permanent
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = 'Payroll Records';

$selectedDeptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

// Handle BULK status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_status_change']) && !empty($_POST['bulk_status_change'])) {
    $newStatus = sanitize($_POST['bulk_status_change']);
    $deptId = (int)$_POST['dept_id'];
    $filterMonth = isset($_POST['filter_month']) ? sanitize($_POST['filter_month']) : '';
    $filterYear = isset($_POST['filter_year']) ? (int)$_POST['filter_year'] : 0;
    
    $allowedStatuses = ['Draft', 'Approved', 'Paid'];
    if (isAdmin2()) {
        // admin2 can only mark as Paid
        $allowedStatuses = ['Paid'];
    }
    if (in_array($newStatus, $allowedStatuses) && $deptId > 0) {

        // If trying to mark as Paid, ensure ALL non-paid records are Approved first
        if ($newStatus === 'Paid') {
            $checkWhereClause = "department_id = ? AND status NOT IN ('Paid', 'Approved')";
            $checkParams = [$deptId];
            $checkTypes = "i";
            if ($filterMonth && $filterYear) {
                $checkWhereClause .= " AND payroll_month = ? AND payroll_year = ?";
                $checkParams[] = $filterMonth;
                $checkParams[] = $filterYear;
                $checkTypes .= "si";
            }
            $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM payroll WHERE $checkWhereClause");
            $checkStmt->bind_param($checkTypes, ...$checkParams);
            $checkStmt->execute();
            $notApproved = $checkStmt->get_result()->fetch_assoc()['cnt'];
            $checkStmt->close();

            if ($notApproved > 0) {
                $_SESSION['error_message'] = "Cannot mark as Paid. $notApproved record(s) are still in Draft status. All records must be Approved first.";
                $redirectUrl = 'payroll.php?department_id=' . $deptId;
                if ($filterMonth && $filterYear) {
                    $redirectUrl .= '&month=' . urlencode($filterMonth) . '&year=' . $filterYear;
                }
                header('Location: ' . $redirectUrl);
                exit;
            }
        }

        // Build WHERE clause
        $whereClause = "department_id = ? AND status != 'Paid'";
        $params = [$deptId];
        $types = "i";
        
        if ($filterMonth && $filterYear) {
            $whereClause .= " AND payroll_month = ? AND payroll_year = ?";
            $params[] = $filterMonth;
            $params[] = $filterYear;
            $types .= "si";
        }
        
        $updateStmt = $conn->prepare("UPDATE payroll SET status = ?, updated_at = NOW() WHERE $whereClause");
        $updateStmt->bind_param("s" . $types, $newStatus, ...$params);
        
        if ($updateStmt->execute()) {
            $affectedRows = $updateStmt->affected_rows;
            if ($affectedRows > 0) {
                $_SESSION['success_message'] = "Status changed to \"$newStatus\" for $affectedRows payroll record(s).";
            } else {
                $_SESSION['error_message'] = 'No records were updated. Records may already be paid.';
            }
        } else {
            $_SESSION['error_message'] = 'Error updating status: ' . $conn->error;
        }
        $updateStmt->close();
    }
    
    $redirectUrl = 'payroll.php?department_id=' . $deptId;
    if ($filterMonth && $filterYear) {
        $redirectUrl .= '&month=' . urlencode($filterMonth) . '&year=' . $filterYear;
    }
    header('Location: ' . $redirectUrl);
    exit;
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_payroll_id'])) {
    if (isAdmin2()) {
        $_SESSION['error_message'] = 'Access denied. You do not have permission to delete payroll records.';
        header('Location: payroll.php');
        exit;
    }
    $deleteId = (int)$_POST['delete_payroll_id'];
    
    $checkStmt = $conn->prepare("SELECT status FROM payroll WHERE id = ?");
    $checkStmt->bind_param("i", $deleteId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    
    if ($checkResult && $checkResult['status'] !== 'Draft') {
        $_SESSION['error_message'] = 'Cannot delete payroll records with status "' . $checkResult['status'] . '". Only Draft records can be deleted.';
    } else {
        $deleteStmt = $conn->prepare("DELETE FROM payroll WHERE id = ?");
        $deleteStmt->bind_param("i", $deleteId);
        
        if ($deleteStmt->execute()) {
            $_SESSION['success_message'] = 'Payroll record deleted successfully.';
        } else {
            $_SESSION['error_message'] = 'Error deleting payroll record.';
        }
        $deleteStmt->close();
    }
    
    header('Location: payroll.php?department_id=' . $selectedDeptId);
    exit;
}

// Get all departments with payroll counts
$departments = $conn->query("
    SELECT d.*, 
           COUNT(p.id) as total_payroll,
           SUM(CASE WHEN p.status = 'Draft' THEN 1 ELSE 0 END) as draft_count,
           SUM(CASE WHEN p.status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
           SUM(CASE WHEN p.status = 'Paid' THEN 1 ELSE 0 END) as paid_count
    FROM departments d 
    LEFT JOIN payroll p ON d.id = p.department_id
    GROUP BY d.id 
    ORDER BY d.department_name ASC
");

// Get selected department info
$selectedDept = null;
$currentDeptStatus = null;
if ($selectedDeptId > 0) {
    $deptStmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
    $deptStmt->bind_param("i", $selectedDeptId);
    $deptStmt->execute();
    $selectedDept = $deptStmt->get_result()->fetch_assoc();
    $deptStmt->close();
    
    // Get the current status of non-paid records
    $statusCheck = $conn->query("SELECT status FROM payroll WHERE department_id = $selectedDeptId AND status != 'Paid' LIMIT 1");
    if ($statusCheck && $statusCheck->num_rows > 0) {
        $currentDeptStatus = $statusCheck->fetch_assoc()['status'];
    }
}

// Get payroll records for selected department
$payrollRecords = null;
$totalRecords = 0;
$paidCount = 0;
$nonPaidCount = 0;
$draftCount = 0;
$allPaid = false;
$canMarkPaid = false;
$currentMonth = null;
$currentYear = null;
$availablePeriods = [];

if ($selectedDeptId > 0) {
    // Get available months/years with counts per month for tab badges
    $periodsQuery = $conn->query("
        SELECT 
            payroll_month, 
            payroll_year,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) as draft_count,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) as paid_count
        FROM payroll 
        WHERE department_id = $selectedDeptId 
        GROUP BY payroll_month, payroll_year
        ORDER BY payroll_year DESC, 
                 FIELD(payroll_month, 'December','November','October','September','August','July','June','May','April','March','February','January')
    ");
    while ($p = $periodsQuery->fetch_assoc()) {
        $availablePeriods[] = $p;
    }
    
    // Set current filter from URL — empty means show ALL months
    if (isset($_GET['month']) && isset($_GET['year']) && $_GET['month'] !== 'all') {
        $currentMonth = sanitize($_GET['month']);
        $currentYear = (int)$_GET['year'];
    } else {
        $currentMonth = null;
        $currentYear = null;
    }
    
    // Build WHERE clause - no month filter = show all records
    $whereClause = "p.department_id = $selectedDeptId";
    if ($currentMonth && $currentYear) {
        $whereClause .= " AND p.payroll_month = '$currentMonth' AND p.payroll_year = $currentYear";
    }
    
    $countQuery = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) as paid,
            SUM(CASE WHEN status != 'Paid' THEN 1 ELSE 0 END) as non_paid,
            SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) as draft_count,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_count
        FROM payroll p WHERE $whereClause
    ");
    $counts = $countQuery->fetch_assoc();
    $totalRecords = $counts['total'];
    $paidCount = $counts['paid'];
    $nonPaidCount = $counts['non_paid'];
    $draftCount = $counts['draft_count'];
    $allPaid = ($totalRecords > 0 && $paidCount == $totalRecords);
    // Can only mark Paid if there are non-paid records AND none are still in Draft
    $canMarkPaid = ($nonPaidCount > 0 && $draftCount == 0);
    
    $query = "
        SELECT 
            p.*,
            e.employee_id as emp_number,
            e.first_name,
            e.last_name,
            e.middle_name,
            e.date_hired,
            d.department_name,
            d.department_code,
            s.step_no,
            s.salary_grade,
            s.salary_rate as current_salary_rate
        FROM payroll p
        LEFT JOIN employees e ON p.employee_id = e.id
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN salary s ON p.salary_id = s.salary_id
        WHERE $whereClause
        ORDER BY e.last_name ASC, e.first_name ASC
    ";
    
    $payrollRecords = $conn->query($query);
}

require_once 'includes/header.php';
?>

<style>
.departments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.department-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
}

.department-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #4a8bc7, #234e78);
}

.department-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
    border-color: #7eb3e0;
}

.department-card.has-payroll::before {
    background: linear-gradient(90deg, #10b981, #059669);
}

.department-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.department-icon {
    width: 56px; height: 56px;
    border-radius: 12px;
    background: linear-gradient(135deg, #e3f0fa, #b5d5f0);
    color: #234e78;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.department-card.has-payroll .department-icon {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #10b981;
}

.department-badge {
    padding: 4px 8px;
    background: #e3f0fa;
    color: #234e78;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
}

.department-card-body h3 {
    font-size: 1.125rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 4px;
}

.department-card-body p {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 1rem;
}

.department-stats {
    display: flex;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid #f3f4f6;
}

.department-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 4px 8px;
    border-radius: 6px;
    min-width: 60px;
}

.department-stat.draft { background: #fff3cd; }
.department-stat.approved { background: #cfe2ff; }
.department-stat.paid { background: #d1e7dd; }

.department-stat-value {
    font-size: 1.125rem;
    font-weight: 800;
    color: #111827;
}

.department-stat-label {
    font-size: 0.625rem;
    color: #4b5563;
    text-transform: uppercase;
}

.department-card-arrow {
    position: absolute;
    bottom: 1.5rem;
    right: 1.5rem;
    width: 32px; height: 32px;
    border-radius: 50%;
    background: #f3f4f6;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.department-card:hover .department-card-arrow {
    background: #2d6394;
    color: white;
    transform: translateX(4px);
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #4b5563;
    font-weight: 600;
    margin-bottom: 1.5rem;
    text-decoration: none;
}

.back-link:hover { color: #2d6394; }

.selected-department-header {
    background: linear-gradient(135deg, #132840, #0c1929);
    color: white;
    padding: 1.5rem;
    border-radius: 16px;
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}

.selected-department-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(201, 162, 39, 0.15) 0%, transparent 70%);
    border-radius: 50%;
}

.selected-department-content {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.selected-department-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.selected-department-icon {
    width: 64px; height: 64px;
    border-radius: 12px;
    background: rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

.selected-department-text h2 {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 4px;
}

.selected-department-text p {
    color: #7eb3e0;
}

/* Bulk Status Control */
.bulk-status-control {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.08);
    border: 1px solid #f0f0f0;
}

.bulk-status-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.bulk-status-header h3 {
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.bulk-status-header h3 i {
    color: #2d6394;
}

.record-count {
    background: #f3f4f6;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #4b5563;
}

/* Workflow steps layout */
.workflow-steps {
    display: flex;
    align-items: stretch;
    gap: 0;
    background: #f8fafc;
    border-radius: 14px;
    padding: 0.5rem;
    border: 1px solid #e5e7eb;
}

.workflow-step {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 1rem 0.5rem;
    border-radius: 10px;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    font-family: inherit;
    text-decoration: none;
    min-width: 0;
}

/* Arrow connector between steps */
.workflow-arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #d1d5db;
    font-size: 1rem;
    padding: 0 0.1rem;
    flex-shrink: 0;
    align-self: center;
}

.workflow-step-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    transition: all 0.2s;
    border: 2px solid transparent;
}

.workflow-step-label {
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    transition: color 0.2s;
}

.workflow-step-sub {
    font-size: 0.65rem;
    font-weight: 500;
    opacity: 0.65;
    text-align: center;
    line-height: 1.3;
}

/* DRAFT step */
.workflow-step.draft .workflow-step-icon {
    background: #fef3c7;
    color: #d97706;
    border-color: #fde68a;
}
.workflow-step.draft .workflow-step-label { color: #92400e; }
.workflow-step.draft:hover {
    background: #fffbeb;
    box-shadow: 0 4px 14px rgba(245,158,11,0.2);
    transform: translateY(-2px);
}
.workflow-step.draft.is-active {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    box-shadow: 0 4px 14px rgba(245,158,11,0.25);
}
.workflow-step.draft.is-active .workflow-step-icon {
    background: #f59e0b;
    color: white;
    border-color: #d97706;
    box-shadow: 0 4px 10px rgba(245,158,11,0.4);
}

/* APPROVED step */
.workflow-step.approved .workflow-step-icon {
    background: #dbeafe;
    color: #2563eb;
    border-color: #bfdbfe;
}
.workflow-step.approved .workflow-step-label { color: #1e40af; }
.workflow-step.approved:hover {
    background: #eff6ff;
    box-shadow: 0 4px 14px rgba(59,130,246,0.2);
    transform: translateY(-2px);
}
.workflow-step.approved.is-active {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    box-shadow: 0 4px 14px rgba(59,130,246,0.25);
}
.workflow-step.approved.is-active .workflow-step-icon {
    background: #3b82f6;
    color: white;
    border-color: #2563eb;
    box-shadow: 0 4px 10px rgba(59,130,246,0.4);
}

/* PAID step */
.workflow-step.paid-btn .workflow-step-icon {
    background: #d1fae5;
    color: #059669;
    border-color: #a7f3d0;
}
.workflow-step.paid-btn .workflow-step-label { color: #065f46; }
.workflow-step.paid-btn:hover:not(:disabled) {
    background: #ecfdf5;
    box-shadow: 0 4px 14px rgba(16,185,129,0.2);
    transform: translateY(-2px);
}
.workflow-step.paid-btn.is-active {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    box-shadow: 0 4px 14px rgba(16,185,129,0.25);
}
.workflow-step.paid-btn.is-active .workflow-step-icon {
    background: #10b981;
    color: white;
    border-color: #059669;
    box-shadow: 0 4px 10px rgba(16,185,129,0.4);
}

/* Disabled paid button */
.workflow-step.paid-btn:disabled {
    cursor: not-allowed;
    opacity: 0.45;
    filter: grayscale(30%);
}
.workflow-step.paid-btn:disabled:hover {
    transform: none;
    box-shadow: none;
    background: transparent;
}

/* Locked notice */
.paid-locked-notice {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: linear-gradient(135deg, #d1fae5, #ecfdf5);
    border: 2px solid #6ee7b7;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    color: #065f46;
}
.paid-locked-notice i { font-size: 1.5rem; color: #10b981; }
.paid-locked-notice h4 { font-size: 1rem; font-weight: 700; margin-bottom: 2px; }
.paid-locked-notice p { font-size: 0.875rem; opacity: 0.75; margin: 0; }

.partial-paid-notice {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
    padding: 0.65rem 1rem;
    border-radius: 8px;
    color: #92400e;
    font-size: 0.825rem;
    margin-bottom: 1rem;
}

.paid-blocked-notice {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #fef2f2;
    border: 1px solid #fecaca;
    padding: 0.6rem 1rem;
    border-radius: 8px;
    color: #991b1b;
    font-size: 0.8rem;
    margin-top: 0.75rem;
}
.paid-blocked-notice i { color: #ef4444; }

.status-help {
    margin-top: 1rem;
    font-size: 0.8rem;
    color: #9ca3af;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.status-help strong { color: #ef4444; }

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 12px;
    font-size: 0.78rem;
    font-weight: 700;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.status-badge.draft    { background: #fef3c7; color: #92400e; }
.status-badge.approved { background: #dbeafe; color: #1e40af; }
.status-badge.paid     { background: #d1fae5; color: #065f46; }

.month-tabs-card {
    background: #fff;
    border-radius: 14px;
    padding: 1rem 1.25rem 0.85rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.month-tabs-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #9ca3af;
    margin-bottom: 0.75rem;
}

.month-tabs-header i { color: #2d6394; }

.month-tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.month-tab {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.55rem 0.9rem;
    border-radius: 10px;
    border: 2px solid #e5e7eb;
    background: #f9fafb;
    text-decoration: none;
    color: #374151;
    transition: all 0.18s;
    min-width: 58px;
    cursor: pointer;
    position: relative;
}

.month-tab:hover {
    border-color: #93c5fd;
    background: #eff6ff;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(45,99,148,0.12);
}

.month-tab.active {
    border-color: #2d6394;
    background: linear-gradient(135deg, #2d6394, #1a3a5c);
    color: white;
    box-shadow: 0 4px 14px rgba(45,99,148,0.35);
    transform: translateY(-2px);
}

.month-tab.all-paid {
    border-color: #6ee7b7;
    background: #f0fdf4;
}

.month-tab.all-paid.active {
    border-color: #10b981;
    background: linear-gradient(135deg, #059669, #047857);
}

.month-tab-year {
    font-size: 0.6rem;
    font-weight: 600;
    opacity: 0.65;
    line-height: 1;
    margin-bottom: 1px;
}

.month-tab.active .month-tab-year { opacity: 0.8; color: #bfdbfe; }

.month-tab-label {
    font-size: 0.875rem;
    font-weight: 700;
    line-height: 1;
}

.month-tab-count {
    font-size: 0.65rem;
    font-weight: 600;
    background: rgba(0,0,0,0.07);
    padding: 1px 6px;
    border-radius: 20px;
    margin-top: 3px;
    color: inherit;
}

.month-tab.active .month-tab-count {
    background: rgba(255,255,255,0.25);
}

.month-tab-dots {
    display: flex;
    gap: 3px;
    margin-top: 4px;
}

.dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    cursor: help;
}

.dot-draft    { background: #f59e0b; }
.dot-approved { background: #3b82f6; }
.dot-paid     { background: #10b981; }
</style>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<?php if ($selectedDeptId == 0): ?>
<!-- DEPARTMENT SELECTION VIEW -->

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <span>Payroll</span>
    </div>
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 class="page-title">Payroll</h1>
            <p class="page-subtitle">Select a department to view and manage payroll records</p>
        </div>
        <a href="payroll_history.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
            <i class="fas fa-history"></i> Payroll History
        </a>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <i class="alert-icon fas fa-check-circle"></i>
        <div class="alert-content"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger">
        <i class="alert-icon fas fa-exclamation-circle"></i>
        <div class="alert-content"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    </div>
<?php endif; ?>

<div class="departments-grid">
    <?php if ($departments && $departments->num_rows > 0): ?>
        <?php while($dept = $departments->fetch_assoc()): ?>
            <div class="department-card <?php echo $dept['total_payroll'] > 0 ? 'has-payroll' : ''; ?>" 
                 onclick="window.location.href='payroll.php?department_id=<?php echo $dept['id']; ?>'">
                <div class="department-card-header">
                    <div class="department-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <span class="department-badge"><?php echo htmlspecialchars($dept['department_code']); ?></span>
                </div>
                <div class="department-card-body">
                    <h3><?php echo htmlspecialchars($dept['department_name']); ?></h3>
                    <p><?php echo $dept['total_payroll']; ?> payroll record<?php echo $dept['total_payroll'] != 1 ? 's' : ''; ?></p>
                </div>
                <div class="department-stats">
                    <div class="department-stat draft">
                        <span class="department-stat-value"><?php echo $dept['draft_count'] ?: 0; ?></span>
                        <span class="department-stat-label">Draft</span>
                    </div>
                    <div class="department-stat approved">
                        <span class="department-stat-value"><?php echo $dept['approved_count'] ?: 0; ?></span>
                        <span class="department-stat-label">Approved</span>
                    </div>
                    <div class="department-stat paid">
                        <span class="department-stat-value"><?php echo $dept['paid_count'] ?: 0; ?></span>
                        <span class="department-stat-label">Paid</span>
                    </div>
                </div>
                <div class="department-card-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card" style="grid-column: 1 / -1;">
            <div class="card-body text-center" style="padding: 3rem;">
                <i class="fas fa-building" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                <h3 style="color: #4b5563;">No Departments Found</h3>
                <p style="color: #6b7280;">Please create departments first.</p>
                <a href="departments.php" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Create Department
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- PAYROLL LIST VIEW -->

<a href="payroll.php" class="back-link">
    <i class="fas fa-arrow-left"></i>
    Back to Departments
</a>

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <a href="payroll.php">Payroll</a>
        <span>/</span>
        <span><?php echo htmlspecialchars($selectedDept['department_code']); ?></span>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <i class="alert-icon fas fa-check-circle"></i>
        <div class="alert-content"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger">
        <i class="alert-icon fas fa-exclamation-circle"></i>
        <div class="alert-content"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    </div>
<?php endif; ?>

<div class="selected-department-header">
    <div class="selected-department-content">
        <div class="selected-department-info">
            <div class="selected-department-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="selected-department-text">
                <h2><?php echo htmlspecialchars($selectedDept['department_name']); ?></h2>
                <p>Payroll Records</p>
            </div>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if (!isAdmin2()): ?>
            <a href="payroll_generate.php?department_id=<?php echo $selectedDeptId; ?>" 
               class="btn btn-success" style="background: rgba(16, 185, 129, 0.9); border: 2px solid rgba(255,255,255,0.3);">
                <i class="fas fa-calculator"></i> Generate Payroll
            </a>
            <?php endif; ?>
            <a href="payroll_history.php?department_id=<?php echo $selectedDeptId; ?>" 
               class="btn btn-secondary" style="background: white; border: 2px solid rgba(255,255,255,0.3);">
                <i class="fas fa-history"></i> History
            </a>
            <?php if ($totalRecords > 0): ?>
            <a href="payroll_print.php?department_id=<?php echo $selectedDeptId; ?>&month=<?php echo urlencode($currentMonth ?? date('F')); ?>&year=<?php echo $currentYear ?? date('Y'); ?>" 
               class="btn btn-secondary" style="background: white; border: 2px solid rgba(255,255,255,0.3);" target="_blank">
                <i class="fas fa-print"></i> Print
            </a>
            <?php endif; ?>
            <?php if (!isAdmin2()): ?>
            <?php if (!$allPaid && $totalRecords == 0): ?>
            <a href="payroll_create.php?department_id=<?php echo $selectedDeptId; ?>" class="btn btn-primary" style="background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.3);">
                <i class="fas fa-plus"></i> Add Payroll
            </a>
            <?php elseif ($totalRecords > 0 && !$allPaid): ?>
        
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Month Filter Tabs -->
<?php if (!empty($availablePeriods)): ?>
<div class="month-tabs-card">
    <div class="month-tabs-header">
        <i class="fas fa-calendar-alt"></i>
        <span>Filter by Month</span>
    </div>
    <div class="month-tabs">
        <?php
        // "All" tab
        $isAll = (!$currentMonth && !$currentYear);
        // Total counts across all months
        $allTotal = array_sum(array_column($availablePeriods, 'total'));
        $allDraft = array_sum(array_column($availablePeriods, 'draft_count'));
        $allApproved = array_sum(array_column($availablePeriods, 'approved_count'));
        $allPaidAll = array_sum(array_column($availablePeriods, 'paid_count'));
        ?>
        <a href="payroll.php?department_id=<?php echo $selectedDeptId; ?>&month=all" 
           class="month-tab <?php echo $isAll ? 'active' : ''; ?>">
            <span class="month-tab-label">All Months</span>
            <span class="month-tab-count"><?php echo $allTotal; ?></span>
            <div class="month-tab-dots">
                <?php if ($allDraft > 0): ?><span class="dot dot-draft" title="<?php echo $allDraft; ?> Draft"></span><?php endif; ?>
                <?php if ($allApproved > 0): ?><span class="dot dot-approved" title="<?php echo $allApproved; ?> Approved"></span><?php endif; ?>
                <?php if ($allPaidAll > 0): ?><span class="dot dot-paid" title="<?php echo $allPaidAll; ?> Paid"></span><?php endif; ?>
            </div>
        </a>
        <?php foreach($availablePeriods as $p):
            $isActive = ($p['payroll_month'] == $currentMonth && $p['payroll_year'] == $currentYear);
            $monthShort = date('M', mktime(0,0,0,date('m', strtotime($p['payroll_month'].' 1')),1));
        ?>
        <a href="payroll.php?department_id=<?php echo $selectedDeptId; ?>&month=<?php echo urlencode($p['payroll_month']); ?>&year=<?php echo $p['payroll_year']; ?>"
           class="month-tab <?php echo $isActive ? 'active' : ''; ?> <?php echo $p['paid_count'] == $p['total'] ? 'all-paid' : ''; ?>">
            <span class="month-tab-year"><?php echo $p['payroll_year']; ?></span>
            <span class="month-tab-label"><?php echo $monthShort; ?></span>
            <span class="month-tab-count"><?php echo $p['total']; ?></span>
            <div class="month-tab-dots">
                <?php if ($p['draft_count'] > 0): ?><span class="dot dot-draft" title="<?php echo $p['draft_count']; ?> Draft"></span><?php endif; ?>
                <?php if ($p['approved_count'] > 0): ?><span class="dot dot-approved" title="<?php echo $p['approved_count']; ?> Approved"></span><?php endif; ?>
                <?php if ($p['paid_count'] > 0): ?><span class="dot dot-paid" title="<?php echo $p['paid_count']; ?> Paid"></span><?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Bulk Status Control -->
<?php if ($totalRecords > 0): ?>
<div class="bulk-status-control">
    <div class="bulk-status-header">
        <h3><i class="fas fa-tasks"></i> Change Status for All Records
            <?php if ($currentMonth && $currentYear): ?>
                <span style="font-size:0.85rem; font-weight:500; color:#6b7280; margin-left:8px;">— <?php echo $currentMonth . ' ' . $currentYear; ?></span>
            <?php else: ?>
                <span style="font-size:0.85rem; font-weight:500; color:#6b7280; margin-left:8px;">— All Months</span>
            <?php endif; ?>
        </h3>
        <span class="record-count"><?php echo $totalRecords; ?> total (<?php echo $nonPaidCount; ?> can be changed)</span>
    </div>
    
    <?php if ($allPaid): ?>
        <div class="paid-locked-notice">
            <i class="fas fa-lock"></i>
            <div>
                <h4>All Payroll Records are PAID and Locked</h4>
                <p>This department's payroll has been marked as paid and cannot be changed.</p>
            </div>
        </div>
    <?php else: ?>
        
        <form method="POST" id="statusForm">
            <input type="hidden" name="dept_id" value="<?php echo $selectedDeptId; ?>">
            <input type="hidden" name="filter_month" value="<?php echo htmlspecialchars($currentMonth ?? ''); ?>">
            <input type="hidden" name="filter_year" value="<?php echo $currentYear ?? ''; ?>">
            <input type="hidden" name="bulk_status_change" id="statusInput" value="">
            
            <div class="workflow-steps">
                <?php if (!isAdmin2()): ?>
                <button type="button"
                    class="workflow-step draft <?php echo $currentDeptStatus === 'Draft' ? 'is-active' : ''; ?>"
                    onclick="changeStatus('Draft')">
                    <div class="workflow-step-icon"><i class="fas fa-pencil-alt"></i></div>
                    <span class="workflow-step-label">Draft</span>
                    <span class="workflow-step-sub"><?php echo $draftCount; ?> record<?php echo $draftCount != 1 ? 's' : ''; ?></span>
                </button>

                <div class="workflow-arrow"><i class="fas fa-chevron-right"></i></div>

                <button type="button"
                    class="workflow-step approved <?php echo $currentDeptStatus === 'Approved' ? 'is-active' : ''; ?>"
                    onclick="changeStatus('Approved')">
                    <div class="workflow-step-icon"><i class="fas fa-check-circle"></i></div>
                    <span class="workflow-step-label">Approved</span>
                    <span class="workflow-step-sub"><?php echo $counts['approved_count'] ?? 0; ?> record<?php echo ($counts['approved_count'] ?? 0) != 1 ? 's' : ''; ?></span>
                </button>

                <div class="workflow-arrow"><i class="fas fa-chevron-right"></i></div>
                <?php endif; ?>

                <?php if ($canMarkPaid): ?>
                <button type="button"
                    class="workflow-step paid-btn"
                    onclick="changeStatus('Paid')">
                    <div class="workflow-step-icon"><i class="fas fa-lock"></i></div>
                    <span class="workflow-step-label">Paid</span>
                    <span class="workflow-step-sub">Mark all paid</span>
                </button>
                <?php else: ?>
                <button type="button" class="workflow-step paid-btn" disabled
                    title="All records must be Approved before marking as Paid">
                    <div class="workflow-step-icon"><i class="fas fa-lock"></i></div>
                    <span class="workflow-step-label">Paid</span>
                    <span class="workflow-step-sub">Needs approval</span>
                </button>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i>
            Payroll Records
            <?php if ($currentMonth && $currentYear): ?>
                <span class="badge badge-info" style="font-size:0.75rem; margin-left:8px;"><?php echo $currentMonth . ' ' . $currentYear; ?></span>
            <?php else: ?>
                <span class="badge badge-secondary" style="font-size:0.75rem; margin-left:8px;">All Months</span>
            <?php endif; ?>
        </h2>
        <span style="font-size:0.875rem; color:#6b7280;"><?php echo $totalRecords; ?> record<?php echo $totalRecords != 1 ? 's' : ''; ?></span>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="data-table" id="payrollTable">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Employee Name</th>
                        <th>Period</th>
                        <th>Step Inc</th>
                        <th>Gross Pay</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($payrollRecords && $payrollRecords->num_rows > 0): ?>
                        <?php while($row = $payrollRecords->fetch_assoc()): 
                            $employeeName = $row['last_name'] . ', ' . $row['first_name'];
                            if ($row['middle_name']) {
                                $employeeName .= ' ' . substr($row['middle_name'], 0, 1) . '.';
                            }
                            
                            $stepInc = '-';
                            $correctSalary = $row['basic_salary'];
                            
                            if ($row['date_hired']) {
                                $hireDate = new DateTime($row['date_hired']);
                                $today = new DateTime();
                                $yearsOfService = $hireDate->diff($today)->y;
                                $currentStep = min(8, floor($yearsOfService / 3) + 1);
                                $stepInc = $currentStep;
                                
                                if ($row['current_salary_rate']) {
                                    $correctSalary = $row['current_salary_rate'];
                                }
                            } elseif ($row['step_no']) {
                                $stepInc = $row['step_no'];
                                if ($row['current_salary_rate']) {
                                    $correctSalary = $row['current_salary_rate'];
                                }
                            }
                            
                            $correctGrossPay = $correctSalary + $row['pera'];
                            $correctNetPay = $correctGrossPay - $row['total_deductions'];
                            $rowIsPaid = $row['status'] === 'Paid';
                            $rowIsApproved = $row['status'] === 'Approved';
                            $rowIsDraft = $row['status'] === 'Draft';
                        ?>
                            <tr>
                                <td><strong><code><?php echo htmlspecialchars($row['emp_number']); ?></code></strong></td>
                                <td><strong><?php echo htmlspecialchars($employeeName); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['payroll_period']); ?></td>
                                <td>
                                    <?php if ($stepInc !== '-'): ?>
                                        <span class="badge" style="background: #fef3c7; color: #92400e;">
                                            <i class="fas fa-layer-group"></i> Step <?php echo $stepInc; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong style="color: #10b981;">₱<?php echo number_format($correctGrossPay, 2); ?></strong></td>
                                <td><strong style="color: #ef4444;">₱<?php echo number_format($row['total_deductions'], 2); ?></strong></td>
                                <td><strong style="color: #10b981;">₱<?php echo number_format($correctNetPay, 2); ?></strong></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($row['status']); ?>">
                                        <?php if ($rowIsPaid): ?>🔒 <?php endif; ?>
                                        <?php echo strtoupper($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="payroll_view.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-icon sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($rowIsDraft && !isAdmin2()): ?>
                                            <a href="payroll_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-secondary btn-icon sm" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['emp_number'])); ?>')" class="btn btn-danger btn-icon sm" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted" style="padding: 2rem;">
                                <i class="fas fa-file-invoice-dollar" style="font-size: 2rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
                                No payroll records found in this department.
                                <br>
                                <a href="payroll_create.php?department_id=<?php echo $selectedDeptId; ?>" class="btn btn-primary" style="margin-top: 1rem;">
                                    <i class="fas fa-plus"></i> Create Payroll
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="delete_payroll_id" id="deletePayrollId">
</form>

<script>
$(document).ready(function() {
    if ($('#payrollTable tbody tr').length > 0 && $('#payrollTable tbody tr:first td').length === 9) {
        $('#payrollTable').DataTable({
            "pageLength": 25,
            "order": [[1, "asc"]],
            "columnDefs": [{ "orderable": false, "targets": [8] }]
        });
    }
});

function changeStatus(status) {
    var nonPaidCount = <?php echo $nonPaidCount; ?>;
    var messages = {
        'Draft':    'Move ' + nonPaidCount + ' record(s) back to Draft?',
        'Approved': 'Mark ' + nonPaidCount + ' record(s) as Approved?',
        'Paid':     'Mark ' + nonPaidCount + ' record(s) as Paid? This cannot be undone.'
    };
    if (confirm(messages[status])) {
        document.getElementById('statusInput').value = status;
        document.getElementById('statusForm').submit();
    }
}

function confirmDelete(id, empId) {
    if (confirm('Delete payroll for ' + empId + '?')) {
        document.getElementById('deletePayrollId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php endif; ?>

<?php 
if (isset($stmt)) $stmt->close();
require_once 'includes/footer.php'; 
?>