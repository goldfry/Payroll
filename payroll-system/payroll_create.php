<?php
/**
 * Payroll System - Create Payroll
 */

require_once 'includes/config.php';

$pageTitle = 'Create Payroll';
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeId = (int)$_POST['employee_id'];
    $month = sanitize($_POST['payroll_month']);
    $year = (int)$_POST['payroll_year'];
    $period = sanitize($_POST['period_type']);
    $basicSalary = (float)$_POST['basic_salary'];
    $pera = (float)$_POST['pera'];
    $grossPay = (float)$_POST['gross_pay'];
    
    // Deductions
    $wtax = (float)$_POST['wtax_amount'];
    $philhealth = (float)$_POST['philhealth_amount'];
    $gsis = (float)$_POST['gsis_amount'];
    $pagibig = (float)$_POST['pagibig_amount'];
    $provident = (float)$_POST['provident'];
    $bcgeu = (float)$_POST['bcgeu'];
    $nocgem = (float)$_POST['nocgem'];
    $bacgem = (float)$_POST['bacgem'];
    $others = (float)$_POST['other_deductions'];
    $totalDeductions = (float)$_POST['total_deductions'];
    $netPay = (float)$_POST['net_pay'];
    
    // Check for duplicate
    $check = $conn->query("SELECT id FROM payroll WHERE employee_id = $employeeId AND payroll_month = '$month' AND payroll_year = $year AND period_type = '$period'");
    
    if ($check->num_rows > 0) {
        $message = 'Payroll record already exists for this employee and period!';
        $messageType = 'warning';
    } else {
        $stmt = $conn->prepare("INSERT INTO payroll (employee_id, payroll_period, payroll_month, payroll_year, period_type, basic_salary, pera, gross_pay, wtax, philhealth, gsis, pagibig, provident, bcgeu, nocgem, bacgem, other_deductions, total_deductions, net_pay) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $payrollPeriod = "$month $period, $year";
        $stmt->bind_param("issisdddddddddddddd", $employeeId, $payrollPeriod, $month, $year, $period, $basicSalary, $pera, $grossPay, $wtax, $philhealth, $gsis, $pagibig, $provident, $bcgeu, $nocgem, $bacgem, $others, $totalDeductions, $netPay);
        
        if ($stmt->execute()) {
            $message = 'Payroll created successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// Get employees
$employees = $conn->query("
    SELECT e.id, e.employee_id, e.first_name, e.last_name, 
           d.department_name, p.position_title, p.basic_salary
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.is_active = 1
    ORDER BY e.last_name, e.first_name
");

// Pre-select employee if passed
$selectedEmployee = isset($_GET['employee']) ? (int)$_GET['employee'] : 0;

require_once 'includes/header.php';
?>

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <a href="payroll.php">Payroll</a>
        <span>/</span>
        <span>Create</span>
    </div>
    <h1 class="page-title">Create Payroll</h1>
    <p class="page-subtitle">Generate payroll for an employee</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <i class="alert-icon fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <div class="alert-content"><?php echo $message; ?></div>
    </div>
<?php endif; ?>

<form method="POST" id="payrollForm">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        
        <!-- Left Column -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-user"></i> Employee & Period</h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label required">Select Employee</label>
                    <select name="employee_id" id="employee_id" class="form-control" required onchange="loadEmployeeDetails(this.value)">
                        <option value="">-- Select Employee --</option>
                        <?php while($emp = $employees->fetch_assoc()): ?>
                            <option value="<?php echo $emp['id']; ?>" 
                                    data-salary="<?php echo $emp['basic_salary']; ?>"
                                    data-position="<?php echo htmlspecialchars($emp['position_title']); ?>"
                                    data-department="<?php echo htmlspecialchars($emp['department_name']); ?>"
                                    <?php echo $selectedEmployee == $emp['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'] . ' [' . $emp['employee_id'] . ']'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <div id="position_display" class="form-control" style="background: var(--gray-100);">-</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <div id="department_display" class="form-control" style="background: var(--gray-100);">-</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Month</label>
                        <select name="payroll_month" class="form-control" required>
                            <?php 
                            $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                            $currentMonth = date('F');
                            foreach($months as $m): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m === $currentMonth ? 'selected' : ''; ?>><?php echo $m; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Year</label>
                        <select name="payroll_year" class="form-control" required>
                            <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Period</label>
                        <select name="period_type" class="form-control" required>
                            <option value="1-15">1-15 (First Half)</option>
                            <option value="16-31">16-31 (Second Half)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">Basic Salary (Monthly)</label>
                    <input type="number" name="basic_salary" id="basic_salary" class="form-control" step="0.01" required value="0">
                </div>
                
                <div class="form-group">
                    <label class="form-label">PERA (Personnel Economic Relief Allowance)</label>
                    <input type="number" name="pera" id="pera" class="form-control" step="0.01" value="2000" readonly>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-calculator"></i> Deductions</h2>
            </div>
            <div class="card-body">
                <div class="deduction-grid">
                    <div class="deduction-item">
                        <div class="deduction-label">GSIS (9%)</div>
                        <div class="deduction-value" id="calc_gsis">₱ 0.00</div>
                    </div>
                    <div class="deduction-item">
                        <div class="deduction-label">PhilHealth</div>
                        <div class="deduction-value" id="calc_philhealth">₱ 0.00</div>
                    </div>
                    <div class="deduction-item">
                        <div class="deduction-label">Pag-IBIG</div>
                        <div class="deduction-value" id="calc_pagibig">₱ 0.00</div>
                    </div>
                    <div class="deduction-item">
                        <div class="deduction-label">W-Tax</div>
                        <div class="deduction-value" id="calc_wtax">₱ 0.00</div>
                    </div>
                </div>
                
                <hr style="margin: 1.5rem 0; border-color: var(--gray-200);">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Provident</label>
                        <input type="number" name="provident" id="provident" class="form-control" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">BCGEU</label>
                        <input type="number" name="bcgeu" id="bcgeu" class="form-control" step="0.01" value="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">NOCGEM</label>
                        <input type="number" name="nocgem" id="nocgem" class="form-control" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">BACGEM</label>
                        <input type="number" name="bacgem" id="bacgem" class="form-control" step="0.01" value="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Other Deductions</label>
                    <input type="number" name="other_deductions" id="other_deductions" class="form-control" step="0.01" value="0">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Summary -->
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header" style="background: linear-gradient(135deg, var(--primary-800), var(--primary-900)); color: white;">
            <h2 class="card-title" style="color: white;"><i class="fas fa-receipt"></i> Payroll Summary</h2>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; text-align: center;">
                <div>
                    <div class="text-muted" style="font-size: 0.875rem;">Gross Pay</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-600);" id="calc_grossPay">₱ 0.00</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem;">Total Deductions</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--danger-500);" id="calc_totalDeductions">₱ 0.00</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem;">Net Pay</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--success-500);" id="calc_netPay">₱ 0.00</div>
                </div>
                <div>
                    <button type="submit" class="btn btn-success btn-lg" style="width: 100%;">
                        <i class="fas fa-save"></i> Save Payroll
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hidden fields for calculated values -->
    <input type="hidden" name="gsis_amount" id="gsis_amount" value="0">
    <input type="hidden" name="philhealth_amount" id="philhealth_amount" value="0">
    <input type="hidden" name="pagibig_amount" id="pagibig_amount" value="0">
    <input type="hidden" name="wtax_amount" id="wtax_amount" value="0">
    <input type="hidden" name="gross_pay" id="gross_pay" value="0">
    <input type="hidden" name="total_deductions" id="total_deductions" value="0">
    <input type="hidden" name="net_pay" id="net_pay" value="0">
</form>

<script>
// Load employee details when selected
document.getElementById('employee_id').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    if (selected.value) {
        document.getElementById('basic_salary').value = selected.dataset.salary || 0;
        document.getElementById('position_display').textContent = selected.dataset.position || '-';
        document.getElementById('department_display').textContent = selected.dataset.department || '-';
        updatePayrollCalculations();
    }
});

// Trigger initial load if employee pre-selected
if (document.getElementById('employee_id').value) {
    document.getElementById('employee_id').dispatchEvent(new Event('change'));
}
</script>

<?php require_once 'includes/footer.php'; ?>
