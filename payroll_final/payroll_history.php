<?php
/**
 * Payroll History
 * Shows payroll records grouped by month
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = 'Payroll History';

// Get filter parameters
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$filterDeptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

// Get all departments for filter
$departments = $conn->query("SELECT id, department_name, department_code FROM departments ORDER BY department_name");

// Get available years
$yearsQuery = $conn->query("SELECT DISTINCT payroll_year FROM payroll ORDER BY payroll_year DESC");
$availableYears = [];
while ($y = $yearsQuery->fetch_assoc()) {
    $availableYears[] = $y['payroll_year'];
}
if (empty($availableYears)) {
    $availableYears[] = date('Y');
}

// Build WHERE clause
$whereClause = "WHERE p.payroll_year = $filterYear";
if ($filterDeptId > 0) {
    $whereClause .= " AND p.department_id = $filterDeptId";
}

// Get monthly summary
$monthlySummary = $conn->query("
    SELECT 
        p.payroll_month,
        COUNT(DISTINCT p.employee_id) as employee_count,
        COUNT(p.id) as record_count,
        SUM(CASE WHEN p.status = 'Draft' THEN 1 ELSE 0 END) as draft_count,
        SUM(CASE WHEN p.status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN p.status = 'Paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(p.basic_salary) as total_basic,
        SUM(p.gross_pay) as total_gross,
        SUM(p.total_deductions) as total_deductions,
        SUM(p.net_pay) as total_net
    FROM payroll p
    $whereClause
    GROUP BY p.payroll_month
    ORDER BY FIELD(p.payroll_month, 'January','February','March','April','May','June','July','August','September','October','November','December')
");

// Get yearly totals
$yearlyTotals = $conn->query("
    SELECT 
        COUNT(DISTINCT p.employee_id) as total_employees,
        COUNT(p.id) as total_records,
        SUM(p.basic_salary) as total_basic,
        SUM(p.gross_pay) as total_gross,
        SUM(p.total_deductions) as total_deductions,
        SUM(p.net_pay) as total_net
    FROM payroll p
    $whereClause
")->fetch_assoc();

// Get selected department info
$selectedDept = null;
if ($filterDeptId > 0) {
    $deptStmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
    $deptStmt->bind_param("i", $filterDeptId);
    $deptStmt->execute();
    $selectedDept = $deptStmt->get_result()->fetch_assoc();
    $deptStmt->close();
}

require_once 'includes/header.php';
?>

<style>
.history-header {
    background: linear-gradient(135deg, #1e3a5f, #2d5a87);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 1.5rem;
}

.history-header h1 {
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.history-header p {
    opacity: 0.9;
}

.filter-section {
    background: white;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-group label {
    font-weight: 600;
    color: #374151;
    white-space: nowrap;
}

.filter-group select {
    padding: 10px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 1rem;
    min-width: 180px;
    cursor: pointer;
}

.filter-group select:focus {
    outline: none;
    border-color: #2d5a87;
}

.yearly-summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.summary-card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    text-align: center;
}

.summary-card.primary {
    background: linear-gradient(135deg, #2d5a87, #1e3a5f);
    color: white;
}

.summary-card .summary-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(45, 90, 135, 0.1);
    color: #2d5a87;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin: 0 auto 0.75rem;
}

.summary-card.primary .summary-icon {
    background: rgba(255,255,255,0.2);
    color: white;
}

.summary-card .summary-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #111827;
    margin-bottom: 0.25rem;
}

.summary-card.primary .summary-value {
    color: white;
    font-size: 1.75rem;
}

.summary-card .summary-label {
    font-size: 0.8rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.summary-card.primary .summary-label {
    color: rgba(255,255,255,0.8);
}

.month-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.month-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: all 0.2s;
}

.month-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 20px rgba(0,0,0,0.1);
}

.month-card-header {
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.month-card-header h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e3a5f;
    display: flex;
    align-items: center;
    gap: 10px;
}

.month-card-header h3 i {
    color: #2d5a87;
}

.employee-count {
    background: #e0f2fe;
    color: #0369a1;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.month-card-body {
    padding: 1.5rem;
}

.status-row {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.status-badge {
    flex: 1;
    text-align: center;
    padding: 0.5rem;
    border-radius: 8px;
    font-size: 0.8rem;
}

.status-badge.draft {
    background: #fff3cd;
    color: #856404;
}

.status-badge.approved {
    background: #cfe2ff;
    color: #084298;
}

.status-badge.paid {
    background: #d1e7dd;
    color: #0f5132;
}

.status-badge .count {
    display: block;
    font-size: 1.25rem;
    font-weight: 800;
}

.amount-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.amount-item {
    text-align: center;
}

.amount-item .label {
    font-size: 0.75rem;
    color: #6b7280;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.amount-item .value {
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
}

.amount-item .value.gross {
    color: #2563eb;
}

.amount-item .value.deduction {
    color: #dc2626;
}

.net-pay-row {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    padding: 1rem;
    border-radius: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.net-pay-row .label {
    font-weight: 600;
}

.net-pay-row .value {
    font-size: 1.35rem;
    font-weight: 800;
}

.month-card-footer {
    padding: 1rem 1.5rem;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 0.75rem;
}

.btn-view {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: all 0.2s;
    font-size: 0.9rem;
}

.btn-view.primary {
    background: #2d5a87;
    color: white;
}

.btn-view.primary:hover {
    background: #1e3a5f;
}

.btn-view.secondary {
    background: #e5e7eb;
    color: #374151;
}

.btn-view.secondary:hover {
    background: #d1d5db;
}

.no-data {
    text-align: center;
    padding: 4rem 2rem;
    color: #6b7280;
}

.no-data i {
    font-size: 4rem;
    color: #d1d5db;
    margin-bottom: 1rem;
}

.no-data h3 {
    font-size: 1.25rem;
    color: #374151;
    margin-bottom: 0.5rem;
}
</style>

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <span>Payroll History</span>
    </div>
</div>

<div class="history-header">
    <h1><i class="fas fa-history"></i> Payroll History</h1>
    <p>View payroll records by month for <?php echo $filterYear; ?><?php echo $selectedDept ? ' - ' . htmlspecialchars($selectedDept['department_name']) : ' - All Departments'; ?></p>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-group">
        <label><i class="fas fa-calendar"></i> Year:</label>
        <select onchange="applyFilter('year', this.value)">
            <?php foreach($availableYears as $y): ?>
                <option value="<?php echo $y; ?>" <?php echo $filterYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label><i class="fas fa-building"></i> Department:</label>
        <select onchange="applyFilter('department_id', this.value)">
            <option value="0">All Departments</option>
            <?php 
            $departments->data_seek(0);
            while($dept = $departments->fetch_assoc()): 
            ?>
                <option value="<?php echo $dept['id']; ?>" <?php echo $filterDeptId == $dept['id'] ? 'selected' : ''; ?>>
                    [<?php echo htmlspecialchars($dept['department_code']); ?>] <?php echo htmlspecialchars($dept['department_name']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
</div>

<!-- Yearly Summary -->
<?php if ($yearlyTotals['total_records'] > 0): ?>
<div class="yearly-summary">

</div>
<?php endif; ?>

<!-- Monthly Cards -->
<?php if ($monthlySummary && $monthlySummary->num_rows > 0): ?>
<div class="month-grid">
    <?php while($month = $monthlySummary->fetch_assoc()): ?>
        <div class="month-card">
            <div class="month-card-header">
                <h3><i class="fas fa-calendar-alt"></i> <?php echo $month['payroll_month']; ?></h3>
                <span class="employee-count"><?php echo $month['employee_count']; ?> employee<?php echo $month['employee_count'] != 1 ? 's' : ''; ?></span>
            </div>
            <div class="month-card-body">
                <div class="status-row">
                    <div class="status-badge draft">
                        <span class="count"><?php echo $month['draft_count']; ?></span>
                        Draft
                    </div>
                    <div class="status-badge approved">
                        <span class="count"><?php echo $month['approved_count']; ?></span>
                        Approved
                    </div>
                    <div class="status-badge paid">
                        <span class="count"><?php echo $month['paid_count']; ?></span>
                        Paid
                    </div>
                </div>
                
                <div class="amount-row">
                    <div class="amount-item">
                        <div class="label">Gross Pay</div>
                        <div class="value gross">₱<?php echo number_format($month['total_gross'], 2); ?></div>
                    </div>
                    <div class="amount-item">
                        <div class="label">Deductions</div>
                        <div class="value deduction">₱<?php echo number_format($month['total_deductions'], 2); ?></div>
                    </div>
                </div>
                
                <div class="net-pay-row">
                    <span class="label">Total Net Pay</span>
                    <span class="value">₱<?php echo number_format($month['total_net'], 2); ?></span>
                </div>
            </div>
            <div class="month-card-footer">
                <a href="reports.php?month=<?php echo $month['payroll_month']; ?>&year=<?php echo $filterYear; ?><?php echo $filterDeptId ? '&department_id=' . $filterDeptId : ''; ?>" class="btn-view primary">
                    <i class="fas fa-eye"></i> View Details
                </a>
                <?php if ($month['paid_count'] > 0): ?>
                <a href="payroll_print.php?department_id=<?php echo $filterDeptId ?: 0; ?>&month=<?php echo $month['payroll_month']; ?>&year=<?php echo $filterYear; ?>" class="btn-view secondary" target="_blank">
                    <i class="fas fa-print"></i> Print
                </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="no-data">
            <i class="fas fa-inbox"></i>
            <h3>No Payroll Records Found</h3>
            <p>No payroll records for <?php echo $filterYear; ?><?php echo $selectedDept ? ' in ' . htmlspecialchars($selectedDept['department_name']) : ''; ?></p>
            <a href="payroll.php" class="btn btn-primary" style="margin-top: 1rem;">
                <i class="fas fa-plus"></i> Create Payroll
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function applyFilter(param, value) {
    const url = new URL(window.location.href);
    url.searchParams.set(param, value);
    window.location.href = url.toString();
}
</script>

<?php require_once 'includes/footer.php'; ?>