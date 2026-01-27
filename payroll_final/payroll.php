<?php
/**
 * Payroll System - Payroll List
 * Display all payroll records with filters and actions
 */

require_once 'includes/config.php';

$pageTitle = 'Payroll Records';

// Filter variables
$filterMonth = isset($_GET['month']) ? sanitize($_GET['month']) : '';
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$filterDepartment = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$filterStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$searchQuery = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query with filters
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
    WHERE 1=1
";

$params = [];
$types = '';

// Apply filters
if ($filterMonth) {
    $query .= " AND p.payroll_month = ?";
    $params[] = $filterMonth;
    $types .= 's';
}

if ($filterYear > 0) {
    $query .= " AND p.payroll_year = ?";
    $params[] = $filterYear;
    $types .= 'i';
}

if ($filterDepartment > 0) {
    $query .= " AND p.department_id = ?";
    $params[] = $filterDepartment;
    $types .= 'i';
}

if ($filterStatus) {
    $query .= " AND p.status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}

if ($searchQuery) {
    $query .= " AND (CONCAT(e.first_name, ' ', e.last_name) LIKE ? OR e.employee_id LIKE ?)";
    $searchParam = '%' . $searchQuery . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

$query .= " ORDER BY p.payroll_year DESC, p.payroll_month DESC, e.last_name ASC, e.first_name ASC";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get departments for filter dropdown
$deptQuery = "SELECT id, department_name, department_code FROM departments ORDER BY department_name";
$deptResult = $conn->query($deptQuery);

require_once 'includes/header.php';
?>

<!-- DataTables CSS and JS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<style>
/* DataTables Custom Styling */
.dataTables_wrapper {
    padding: 0;
}

.dataTables_wrapper .dataTables_top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-lg) var(--space-xl);
    background: var(--bg-light);
    border-bottom: 2px solid var(--border-color);
}

.dataTables_wrapper .dataTables_length {
    margin: 0;
}

.dataTables_wrapper .dataTables_length select {
    padding: var(--space-sm) var(--space-md);
    font-size: 1.1rem;
    border: 2px solid var(--border-dark);
    border-radius: var(--radius);
    margin: 0 var(--space-sm);
    min-height: 48px;
}

.dataTables_wrapper .dataTables_length label {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.dataTables_wrapper .dataTables_filter {
    margin: 0;
}

.dataTables_wrapper .dataTables_filter label {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.dataTables_wrapper .dataTables_filter input {
    padding: var(--space-sm) var(--space-md);
    font-size: 1.1rem;
    border: 2px solid var(--border-dark);
    border-radius: var(--radius);
    margin-left: var(--space-sm);
    min-height: 48px;
    min-width: 250px;
}

.dataTables_wrapper .dataTables_filter input:focus {
    outline: none;
    border-color: var(--primary-main);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.dataTables_wrapper .dataTables_bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-lg) var(--space-xl);
    background: var(--bg-light);
    border-top: 2px solid var(--border-color);
}

.dataTables_wrapper .dataTables_info {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-secondary);
}

.dataTables_wrapper .dataTables_paginate {
    display: flex;
    gap: var(--space-sm);
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: var(--space-sm) var(--space-lg);
    font-size: 1.1rem;
    font-weight: 700;
    border: 2px solid var(--border-dark);
    border-radius: var(--radius);
    background: var(--bg-white);
    color: var(--text-primary);
    cursor: pointer;
    min-height: 48px;
    min-width: 48px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.2s;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: var(--primary-main);
    color: var(--bg-white);
    border-color: var(--primary-main);
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: var(--primary-main);
    color: var(--bg-white);
    border-color: var(--primary-main);
    font-weight: 800;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* Remove default DataTables sorting icons */
table.dataTable thead .sorting:before,
table.dataTable thead .sorting_asc:before,
table.dataTable thead .sorting_desc:before,
table.dataTable thead .sorting:after,
table.dataTable thead .sorting_asc:after,
table.dataTable thead .sorting_desc:after {
    display: none;
}

/* Add custom sorting icons */
table.dataTable thead th {
    position: relative;
    cursor: pointer;
}

table.dataTable thead th.sorting:after {
    content: "⇅";
    position: absolute;
    right: 10px;
    font-size: 1.2rem;
    color: var(--text-muted);
}

table.dataTable thead th.sorting_asc:after {
    content: "↑";
    position: absolute;
    right: 10px;
    font-size: 1.3rem;
    color: var(--primary-main);
    font-weight: bold;
}

table.dataTable thead th.sorting_desc:after {
    content: "↓";
    position: absolute;
    right: 10px;
    font-size: 1.3rem;
    color: var(--primary-main);
    font-weight: bold;
}

/* Filter Bar Styling */
.filter-bar {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.filter-bar .form-control {
    min-width: 160px;
    padding: 0.5rem 2rem 0.5rem 0.75rem;
    font-size: 1rem;
    font-weight: 500;
    line-height: 1.5;
    color: #212529;
    background-color: #fff;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 16px 12px;
    border: 2px solid #ced4da;
    border-radius: 0.375rem;
    appearance: none;
    cursor: pointer;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.filter-bar .form-control:hover {
    border-color: #86b7fe;
}

.filter-bar .form-control:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Employee Info Cell */
.employee-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.employee-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-main);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
}

.employee-name {
    font-weight: 500;
    color: var(--text-primary);
}

.employee-id {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

/* Responsive */
@media (max-width: 768px) {
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-bar .form-control {
        width: 100%;
    }
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <span>Payroll</span>
    </div>
    <h1 class="page-title">Payroll</h1>
    <p class="page-subtitle">Manage payroll records</p>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-money-bill-wave"></i>
            Payroll List
        </h2>
        <div class="btn-group">
            <a href="payroll_create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Payroll
            </a>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card-body" style="padding: var(--space-md) var(--space-xl); border-bottom: 1px solid var(--gray-100);">
        <form method="GET" class="filter-bar" id="filterForm">
            <select name="month" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Months</option>
                <?php 
                $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                foreach($months as $m): ?>
                    <option value="<?php echo $m; ?>" <?php echo $filterMonth === $m ? 'selected' : ''; ?>>
                        <?php echo $m; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="year" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="0">All Years</option>
                <?php 
                $currentYear = date('Y');
                for($y = $currentYear; $y >= 2020; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $filterYear === $y ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                <?php endfor; ?>
            </select>
            
            <select name="department" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="0">All Departments</option>
                <?php 
                $deptResult->data_seek(0);
                while($dept = $deptResult->fetch_assoc()): ?>
                    <option value="<?php echo $dept['id']; ?>" <?php echo $filterDepartment === (int)$dept['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept['department_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            
            <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="Draft" <?php echo $filterStatus === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="Approved" <?php echo $filterStatus === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="Paid" <?php echo $filterStatus === 'Paid' ? 'selected' : ''; ?>>Paid</option>
            </select>
            
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
        </form>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="data-table" id="payrollTable">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>ID</th>
                        <th>Period</th>
                        <th>Department</th>
                        <th>Step Inc</th>
                        <th>Gross Pay</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $employeeName = $row['last_name'] . ', ' . $row['first_name'];
                            if ($row['middle_name']) {
                                $employeeName .= ' ' . substr($row['middle_name'], 0, 1) . '.';
                            }
                            
                            // Calculate correct step increment
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
                            
                            // Recalculate amounts
                            $correctGrossPay = $correctSalary + $row['pera'];
                            $correctNetPay = $correctGrossPay - $row['total_deductions'];
                            
                            // Status badge styling
                            $statusBadgeClass = 'badge-secondary';
                            $statusLabel = $row['status'];
                            
                            switch($row['status']) {
                                case 'Draft':
                                    $statusBadgeClass = 'badge-warning';
                                    break;
                                case 'Approved':
                                    $statusBadgeClass = 'badge-info';
                                    break;
                                case 'Paid':
                                    $statusBadgeClass = 'badge-success';
                                    break;
                            }
                        ?>
                            <tr>
                                <td>
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="employee-name"><?php echo htmlspecialchars($employeeName); ?></div>
                                            <div class="employee-id"><?php echo htmlspecialchars($row['emp_number']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?php echo htmlspecialchars($row['emp_number']); ?></code></td>
                                <td><?php echo htmlspecialchars($row['payroll_period']); ?></td>
                                <td>
                                    <span class="badge badge-primary">
                                        <?php echo htmlspecialchars($row['department_code'] ?: $row['department_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($stepInc !== '-'): ?>
                                        <span class="badge" style="background: #fef3c7; color: #92400e;">
                                            <i class="fas fa-layer-group"></i> Step <?php echo $stepInc; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: #10b981;">
                                        ₱<?php echo number_format($correctGrossPay, 2); ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong style="color: #ef4444;">
                                        ₱<?php echo number_format($row['total_deductions'], 2); ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong style="color: #10b981; font-size: 1.05em;">
                                        ₱<?php echo number_format($correctNetPay, 2); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge <?php echo $statusBadgeClass; ?>">
                                        <?php echo $statusLabel; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                    
                                        <a href="edit_payroll.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-secondary btn-icon sm" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <button onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($employeeName); ?>')" 
                                                class="btn btn-danger btn-icon sm" 
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted" style="padding: 2rem;">
                                No payroll records found. Add your first payroll above.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>



<?php 
$stmt->close();
require_once 'includes/footer.php'; 
?>