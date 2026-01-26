<?php
/**
 * Payroll System - Create Payroll
 * With correct salary calculation based on salary grade and step increment
 */

require_once 'includes/config.php';

$pageTitle = 'Create Payroll';
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeId = (int)$_POST['employee_id'];
    $payrollPeriod = sanitize($_POST['payroll_period']);
    $payrollMonth = sanitize($_POST['payroll_month']);
    $payrollYear = (int)$_POST['payroll_year'];
    $periodType = sanitize($_POST['period_type']);
    $basicSalary = (float)$_POST['basic_salary'];
    $pera = (float)$_POST['pera'];
    $grossPay = (float)$_POST['gross_pay'];
    
    // Deductions
    $wtax = (float)$_POST['wtax'];
    $philhealth = (float)$_POST['philhealth'];
    $gsis = (float)$_POST['gsis'];
    $pagibig = (float)$_POST['pagibig'];
    $provident = (float)$_POST['provident'];
    $bcgeu = (float)$_POST['bcgeu'];
    $nocgem = (float)$_POST['nocgem'];
    $bacgem = (float)$_POST['bacgem'];
    $otherDeductions = (float)$_POST['other_deductions'];
    $totalDeductions = (float)$_POST['total_deductions'];
    $netPay = (float)$_POST['net_pay'];
    $status = 'Draft';
    
    // Check for duplicate
    $check = $conn->prepare("SELECT id FROM payroll WHERE employee_id = ? AND payroll_month = ? AND payroll_year = ? AND period_type = ?");
    $check->bind_param("isis", $employeeId, $payrollMonth, $payrollYear, $periodType);
    $check->execute();
    $checkResult = $check->get_result();
    
    if ($checkResult->num_rows > 0) {
        $message = 'Payroll record already exists for this employee and period!';
        $messageType = 'warning';
    } else {
        $stmt = $conn->prepare("INSERT INTO payroll (employee_id, payroll_period, payroll_month, payroll_year, period_type, basic_salary, pera, gross_pay, wtax, philhealth, gsis, pagibig, provident, bcgeu, nocgem, bacgem, other_deductions, total_deductions, net_pay, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        
        $stmt->bind_param("issisddddddddddddds", 
            $employeeId, 
            $payrollPeriod, 
            $payrollMonth, 
            $payrollYear, 
            $periodType, 
            $basicSalary, 
            $pera, 
            $grossPay, 
            $wtax, 
            $philhealth, 
            $gsis, 
            $pagibig, 
            $provident, 
            $bcgeu, 
            $nocgem, 
            $bacgem, 
            $otherDeductions, 
            $totalDeductions, 
            $netPay,
            $status
        );
        
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

// Get employees with their current step increment and correct salary
$employees = $conn->query("
    SELECT 
        e.id, 
        e.employee_id, 
        e.first_name, 
        e.last_name,
        e.date_hired,
        d.department_name, 
        p.position_title, 
        p.salary_grade,
        p.basic_salary as position_salary
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.is_active = 1
    ORDER BY e.last_name, e.first_name
");

// Pre-select employee if passed
$selectedEmployee = isset($_GET['employee']) ? (int)$_GET['employee'] : 0;

// Function to calculate current step based on date hired
function getCurrentStep($dateHired) {
    if (!$dateHired) return 1;
    
    $hireDate = new DateTime($dateHired);
    $today = new DateTime();
    $yearsOfService = $hireDate->diff($today)->y;
    
    return min(8, floor($yearsOfService / 3) + 1);
}

// Build employee data array with correct salaries
$employeeData = [];
if ($employees && $employees->num_rows > 0) {
    $employees->data_seek(0);
    while($emp = $employees->fetch_assoc()) {
        $currentStep = getCurrentStep($emp['date_hired']);
        
        // Get salary from salary table based on salary grade and current step
        $salaryQuery = $conn->prepare("
            SELECT salary_rate, salary_code 
            FROM salary 
            WHERE salary_grade = ? AND step_no = ?
            LIMIT 1
        ");
        $salaryQuery->bind_param("si", $emp['salary_grade'], $currentStep);
        $salaryQuery->execute();
        $salaryResult = $salaryQuery->get_result();
        $salaryData = $salaryResult->fetch_assoc();
        
        $correctSalary = $salaryData ? $salaryData['salary_rate'] : $emp['position_salary'];
        $salaryCode = $salaryData ? $salaryData['salary_code'] : 'N/A';
        
        $employeeData[] = [
            'id' => $emp['id'],
            'employee_id' => $emp['employee_id'],
            'full_name' => $emp['last_name'] . ', ' . $emp['first_name'],
            'position' => $emp['position_title'],
            'department' => $emp['department_name'],
            'salary_grade' => $emp['salary_grade'],
            'current_step' => $currentStep,
            'basic_salary' => $correctSalary,
            'salary_code' => $salaryCode,
            'years_of_service' => $emp['date_hired'] ? (new DateTime($emp['date_hired']))->diff(new DateTime())->y : 0
        ];
    }
}

require_once 'includes/header.php';
?>

<style>
.employee-info-display {
    background: #f8f9fa;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.employee-info-display .info-row {
    display: grid;
    grid-template-columns: 150px 1fr;
    gap: 1rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid #dee2e6;
}

.employee-info-display .info-row:last-child {
    border-bottom: none;
}

.employee-info-display .info-label {
    font-weight: 600;
    color: #666;
}

.employee-info-display .info-value {
    color: #333;
    font-weight: 500;
}

.salary-highlight {
    background: #d4edda;
    border: 2px solid #28a745;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.salary-highlight h4 {
    color: #155724;
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
}

.salary-highlight .salary-amount {
    font-size: 1.8rem;
    font-weight: 700;
    color: #28a745;
}

.deduction-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}

.deduction-item {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 6px;
    border-left: 3px solid #2196f3;
}

.deduction-label {
    font-size: 0.875rem;
    color: #666;
    margin-bottom: 0.25rem;
}

.deduction-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: #333;
}
</style>

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
                        <?php foreach($employeeData as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" 
                                    data-salary="<?php echo $emp['basic_salary']; ?>"
                                    data-position="<?php echo htmlspecialchars($emp['position']); ?>"
                                    data-department="<?php echo htmlspecialchars($emp['department']); ?>"
                                    data-grade="<?php echo $emp['salary_grade']; ?>"
                                    data-step="<?php echo $emp['current_step']; ?>"
                                    data-code="<?php echo htmlspecialchars($emp['salary_code']); ?>"
                                    data-years="<?php echo $emp['years_of_service']; ?>"
                                    <?php echo $selectedEmployee == $emp['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['full_name'] . ' [' . $emp['employee_id'] . ']'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Employee Information Display -->
                <div id="employee_info_container" style="display: none;">
                    <div class="employee-info-display">
                        <div class="info-row">
                            <div class="info-label">Position:</div>
                            <div class="info-value" id="position_display">-</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Department:</div>
                            <div class="info-value" id="department_display">-</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Salary Grade:</div>
                            <div class="info-value" id="salary_grade_display">-</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Current Step:</div>
                            <div class="info-value" id="current_step_display">-</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Years of Service:</div>
                            <div class="info-value" id="years_service_display">-</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Salary Code:</div>
                            <div class="info-value" id="salary_code_display">-</div>
                        </div>
                    </div>
                    
                    <div class="salary-highlight">
                        <h4><i class="fas fa-money-bill-wave"></i> Monthly Basic Salary</h4>
                        <div class="salary-amount" id="salary_display">₱ 0.00</div>
                        <small style="color: #155724;">Based on Salary Grade and Current Step Increment</small>
                    </div>
                </div>
                
                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label required">Month</label>
                        <select name="payroll_month" id="payroll_month" class="form-control" required onchange="updatePayrollPeriod()">
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
                        <select name="payroll_year" id="payroll_year" class="form-control" required onchange="updatePayrollPeriod()">
                            <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Period</label>
                        <select name="period_type" id="period_type" class="form-control" required onchange="updatePayrollPeriod()">
                            <option value="1-15">1-15 (First Half)</option>
                            <option value="16-31">16-31 (Second Half)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Payroll Period</label>
                    <input type="text" name="payroll_period" id="payroll_period" class="form-control" readonly style="background: #f8f9fa;">
                </div>
                
                <div class="form-group">
                    <label class="form-label required">Basic Salary (Monthly)</label>
                    <input type="number" name="basic_salary" id="basic_salary" class="form-control" step="0.01" required value="0.00" readonly style="background: #e9ecef; font-weight: 700; font-size: 1.1rem;">
                    <small class="form-hint">Automatically calculated based on salary grade and step increment</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">PERA (Personnel Economic Relief Allowance)</label>
                    <input type="number" name="pera" id="pera" class="form-control" step="0.01" value="2000.00" onchange="updatePayrollCalculations()">
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
                        <input type="number" name="provident" id="provident" class="form-control" step="0.01" value="0.00" onchange="updatePayrollCalculations()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">BCGEU</label>
                        <input type="number" name="bcgeu" id="bcgeu" class="form-control" step="0.01" value="0.00" onchange="updatePayrollCalculations()">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">NOCGEM</label>
                        <input type="number" name="nocgem" id="nocgem" class="form-control" step="0.01" value="0.00" onchange="updatePayrollCalculations()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">BACGEM</label>
                        <input type="number" name="bacgem" id="bacgem" class="form-control" step="0.01" value="0.00" onchange="updatePayrollCalculations()">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Other Deductions</label>
                    <input type="number" name="other_deductions" id="other_deductions" class="form-control" step="0.01" value="0.00" onchange="updatePayrollCalculations()">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Summary -->
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header" style="background: linear-gradient(135deg, #1976d2, #1565c0); color: white;">
            <h2 class="card-title" style="color: white;"><i class="fas fa-receipt"></i> Payroll Summary</h2>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; text-align: center;">
                <div>
                    <div class="text-muted" style="font-size: 0.875rem;">Gross Pay</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #2196f3;" id="calc_grossPay">₱ 0.00</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem;">Total Deductions</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #f44336;" id="calc_totalDeductions">₱ 0.00</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.875rem;">Net Pay</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #4caf50;" id="calc_netPay">₱ 0.00</div>
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
    <input type="hidden" name="gross_pay" id="gross_pay" value="0.00">
    <input type="hidden" name="wtax" id="wtax" value="0.00">
    <input type="hidden" name="philhealth" id="philhealth" value="0.00">
    <input type="hidden" name="gsis" id="gsis" value="0.00">
    <input type="hidden" name="pagibig" id="pagibig" value="0.00">
    <input type="hidden" name="total_deductions" id="total_deductions" value="0.00">
    <input type="hidden" name="net_pay" id="net_pay" value="0.00">
</form>

<script>
function formatCurrency(amount) {
    return '₱ ' + parseFloat(amount).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function updatePayrollPeriod() {
    const month = document.getElementById('payroll_month').value;
    const year = document.getElementById('payroll_year').value;
    const period = document.getElementById('period_type').value;
    
    if (month && year && period) {
        document.getElementById('payroll_period').value = `${month} ${period}, ${year}`;
    }
}

function loadEmployeeDetails(employeeId) {
    const select = document.getElementById('employee_id');
    const selected = select.options[select.selectedIndex];
    
    if (!selected.value) {
        document.getElementById('employee_info_container').style.display = 'none';
        document.getElementById('basic_salary').value = '0.00';
        updatePayrollCalculations();
        return;
    }
    
    // Show employee info
    document.getElementById('employee_info_container').style.display = 'block';
    
    // Populate employee details
    const salary = parseFloat(selected.dataset.salary) || 0;
    const position = selected.dataset.position || '-';
    const department = selected.dataset.department || '-';
    const grade = selected.dataset.grade || '-';
    const step = selected.dataset.step || '-';
    const code = selected.dataset.code || '-';
    const years = selected.dataset.years || '0';
    
    document.getElementById('position_display').textContent = position;
    document.getElementById('department_display').textContent = department;
    document.getElementById('salary_grade_display').textContent = 'SG-' + grade;
    document.getElementById('current_step_display').textContent = 'Step ' + step + ' of 8';
    document.getElementById('years_service_display').textContent = years + ' years';
    document.getElementById('salary_code_display').textContent = code;
    document.getElementById('salary_display').textContent = formatCurrency(salary);
    
    // Set basic salary
    document.getElementById('basic_salary').value = salary.toFixed(2);
    
    // Calculate payroll
    updatePayrollCalculations();
}

function updatePayrollCalculations() {
    const basicSalary = parseFloat(document.getElementById('basic_salary').value) || 0;
    const pera = parseFloat(document.getElementById('pera').value) || 0;
    
    // Calculate Gross Pay
    const grossPay = basicSalary + pera;
    
    // Calculate Deductions
    const gsis = basicSalary * 0.09; // 9% of basic salary
    const philhealth = calculatePhilHealth(basicSalary);
    const pagibig = calculatePagIbig(basicSalary);
    const wtax = calculateWithholdingTax(basicSalary);
    
    // Get other deductions
    const provident = parseFloat(document.getElementById('provident').value) || 0;
    const bcgeu = parseFloat(document.getElementById('bcgeu').value) || 0;
    const nocgem = parseFloat(document.getElementById('nocgem').value) || 0;
    const bacgem = parseFloat(document.getElementById('bacgem').value) || 0;
    const otherDeductions = parseFloat(document.getElementById('other_deductions').value) || 0;
    
    // Calculate Total Deductions
    const totalDeductions = gsis + philhealth + pagibig + wtax + provident + bcgeu + nocgem + bacgem + otherDeductions;
    
    // Calculate Net Pay
    const netPay = grossPay - totalDeductions;
    
    // Update displays
    document.getElementById('calc_gsis').textContent = formatCurrency(gsis);
    document.getElementById('calc_philhealth').textContent = formatCurrency(philhealth);
    document.getElementById('calc_pagibig').textContent = formatCurrency(pagibig);
    document.getElementById('calc_wtax').textContent = formatCurrency(wtax);
    document.getElementById('calc_grossPay').textContent = formatCurrency(grossPay);
    document.getElementById('calc_totalDeductions').textContent = formatCurrency(totalDeductions);
    document.getElementById('calc_netPay').textContent = formatCurrency(netPay);
    
    // Update hidden fields
    document.getElementById('gross_pay').value = grossPay.toFixed(2);
    document.getElementById('wtax').value = wtax.toFixed(2);
    document.getElementById('philhealth').value = philhealth.toFixed(2);
    document.getElementById('gsis').value = gsis.toFixed(2);
    document.getElementById('pagibig').value = pagibig.toFixed(2);
    document.getElementById('total_deductions').value = totalDeductions.toFixed(2);
    document.getElementById('net_pay').value = netPay.toFixed(2);
}

function calculatePhilHealth(basicSalary) {
    // PhilHealth 2024 rates (simplified)
    if (basicSalary <= 10000) return 450;
    if (basicSalary >= 80000) return 3600;
    return basicSalary * 0.045; // 4.5% for middle range
}

function calculatePagIbig(basicSalary) {
    // Pag-IBIG rates
    if (basicSalary <= 1500) return basicSalary * 0.01;
    if (basicSalary <= 5000) return basicSalary * 0.02;
    return 100; // Maximum contribution
}

function calculateWithholdingTax(monthlyBasic) {
    // Simplified withholding tax calculation (2024)
    const taxableIncome = monthlyBasic;
    
    if (taxableIncome <= 20833) return 0;
    if (taxableIncome <= 33332) return (taxableIncome - 20833) * 0.15;
    if (taxableIncome <= 66666) return 1875 + (taxableIncome - 33333) * 0.20;
    if (taxableIncome <= 166666) return 8541.80 + (taxableIncome - 66667) * 0.25;
    if (taxableIncome <= 666666) return 33541.80 + (taxableIncome - 166667) * 0.30;
    return 183541.80 + (taxableIncome - 666667) * 0.35;
}

// Trigger initial load if employee pre-selected
window.addEventListener('DOMContentLoaded', function() {
    // Set initial payroll period
    updatePayrollPeriod();
    
    // Load employee if pre-selected
    const employeeSelect = document.getElementById('employee_id');
    if (employeeSelect.value) {
        loadEmployeeDetails(employeeSelect.value);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>