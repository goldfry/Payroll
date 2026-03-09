<?php
/**
 * Payroll System - Batch Payroll Creation
 * Create payroll for all employees in a department at once
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireSuperAdmin();

$pageTitle = 'Create Batch Payroll';
$message = '';
$messageType = '';

$selectedDeptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

// Handle batch form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_batch'])) {
    $deptId       = (int)$_POST['department_id'];
    $payrollMonth = sanitize($_POST['payroll_month']);
    $payrollYear  = (int)$_POST['payroll_year'];
    $periodType   = sanitize($_POST['period_type']);
    $payrollPeriod = $payrollMonth . ' ' . $periodType . ', ' . $payrollYear;

    $employeeIds   = $_POST['employee_ids']   ?? [];
    $basicSalaries = $_POST['basic_salaries'] ?? [];
    $peras         = $_POST['peras']          ?? [];
    $salaryIds     = $_POST['salary_ids']     ?? [];

    $isHalfPeriod = ($periodType === '1-15' || str_starts_with($periodType, '16-'));
    $peraLimit    = $isHalfPeriod ? 1000.00 : 2000.00;

    $bcgeuTotal_shared  = $isHalfPeriod ? 50.00 : 100.00;
    $bcgeu_edu_loan     = 0.00;
    $bcgeu_emergency    = 0.00;
    $bcgeu_grocery      = 0.00;
    $bcgeu_regular_loan = 0.00;
    $bcgeu_others       = 0.00;

    $successCount = 0; $skipCount = 0; $errorCount = 0;

    foreach ($employeeIds as $empId) {
        $empId       = (int)$empId;
        $basicSalary = (float)($basicSalaries[$empId] ?? 0);
        $pera        = min((float)($peras[$empId] ?? 0), $peraLimit);
        $salaryId    = (int)($salaryIds[$empId] ?? 0);

        $e_gsis_life_ret    = (float)($_POST['emp_gsis_life_ret'][$empId]    ?? 0);
        $e_gsis_emergency   = (float)($_POST['emp_gsis_emergency'][$empId]   ?? 0);
        $e_gsis_cpl         = (float)($_POST['emp_gsis_cpl'][$empId]         ?? 0);
        $e_gsis_gpal        = (float)($_POST['emp_gsis_gpal'][$empId]        ?? 0);
        $e_gsis_mpl         = (float)($_POST['emp_gsis_mpl'][$empId]         ?? 0);
        $e_gsis_mpl_lite    = (float)($_POST['emp_gsis_mpl_lite'][$empId]    ?? 0);
        $e_gsis_policy_loan = (float)($_POST['emp_gsis_policy_loan'][$empId] ?? 0);

        $e_pagibig_multi     = (float)($_POST['emp_pagibig_multi'][$empId]     ?? 0);
        $e_pagibig_emergency = (float)($_POST['emp_pagibig_emergency'][$empId] ?? 0);
        $e_pagibig_premium   = (float)($_POST['emp_pagibig_premium'][$empId]   ?? 0);
        $e_pagibig_mp2       = (float)($_POST['emp_pagibig_mp2'][$empId]       ?? 0);
        $e_pagibig_housing   = (float)($_POST['emp_pagibig_housing'][$empId]   ?? 0);

        $e_provident_fund      = (float)($_POST['emp_provident_fund'][$empId]      ?? 0);
        $e_provident_fund_loan = (float)($_POST['emp_provident_fund_loan'][$empId] ?? 0);
        $e_provident_edu_loan  = (float)($_POST['emp_provident_edu_loan'][$empId]  ?? 0);
        $e_provident_term_loan = (float)($_POST['emp_provident_term_loan'][$empId] ?? 0);

        $e_bacgem_edu_loan = (float)($_POST['emp_bacgem_edu_loan'][$empId] ?? 0);
        $e_bacgem_grocery  = (float)($_POST['emp_bacgem_grocery'][$empId]  ?? 0);
        $e_bacgem_others   = (float)($_POST['emp_bacgem_others'][$empId]   ?? 0);
        $e_bacgem_hcp      = (float)($_POST['emp_bacgem_hcp'][$empId]      ?? 0);
        $e_bacgem_loan     = (float)($_POST['emp_bacgem_loan'][$empId]     ?? 0);

        $e_nocgem_edu_loan     = (float)($_POST['emp_nocgem_edu_loan'][$empId]     ?? 0);
        $e_nocgem_emergency    = (float)($_POST['emp_nocgem_emergency'][$empId]    ?? 0);
        $e_nocgem_grocery      = (float)($_POST['emp_nocgem_grocery'][$empId]      ?? 0);
        $e_nocgem_hospital     = (float)($_POST['emp_nocgem_hospital'][$empId]     ?? 0);
        $e_nocgem_others       = (float)($_POST['emp_nocgem_others'][$empId]       ?? 0);
        $e_nocgem_plp          = (float)($_POST['emp_nocgem_plp'][$empId]          ?? 0);
        $e_nocgem_regular_loan = (float)($_POST['emp_nocgem_regular_loan'][$empId] ?? 0);

        $e_other_cash_advance = (float)($_POST['emp_other_cash_advance'][$empId] ?? 0);
        $e_other_insurance    = (float)($_POST['emp_other_insurance'][$empId]    ?? 0);
        $e_other_salary_loan  = (float)($_POST['emp_other_salary_loan'][$empId]  ?? 0);
        $e_other_others       = (float)($_POST['emp_other_others'][$empId]       ?? 0);

        $f_gsis_life_ret    = $e_gsis_life_ret;
        $f_gsis_emergency   = $e_gsis_emergency;
        $f_gsis_cpl         = $e_gsis_cpl;
        $f_gsis_gpal        = $e_gsis_gpal;
        $f_gsis_mpl         = $e_gsis_mpl;
        $f_gsis_mpl_lite    = $e_gsis_mpl_lite;
        $f_gsis_policy_loan = $e_gsis_policy_loan;
        $f_gsisTotal        = $f_gsis_life_ret + $f_gsis_emergency + $f_gsis_cpl + $f_gsis_gpal + $f_gsis_mpl + $f_gsis_mpl_lite + $f_gsis_policy_loan;

        $f_pagibig_multi     = $e_pagibig_multi;
        $f_pagibig_emergency = $e_pagibig_emergency;
        $f_pagibig_premium   = $e_pagibig_premium;
        $f_pagibig_mp2       = $e_pagibig_mp2;
        $f_pagibig_housing   = $e_pagibig_housing;
        $f_pagibigExtraTotal = $f_pagibig_multi + $f_pagibig_emergency + $f_pagibig_premium + $f_pagibig_mp2 + $f_pagibig_housing;

        $f_provident_fund      = $e_provident_fund;
        $f_provident_fund_loan = $e_provident_fund_loan;
        $f_provident_edu_loan  = $e_provident_edu_loan;
        $f_provident_term_loan = $e_provident_term_loan;
        $f_providentTotal      = $f_provident_fund + $f_provident_fund_loan + $f_provident_edu_loan + $f_provident_term_loan;

        $f_bacgem_edu_loan = $e_bacgem_edu_loan;
        $f_bacgem_grocery  = $e_bacgem_grocery;
        $f_bacgem_others   = $e_bacgem_others;
        $f_bacgem_hcp      = $e_bacgem_hcp;
        $f_bacgem_loan     = $e_bacgem_loan;
        $f_bacgemTotal     = $f_bacgem_edu_loan + $f_bacgem_grocery + $f_bacgem_others + $f_bacgem_hcp + $f_bacgem_loan;

        $f_nocgem_edu_loan     = $e_nocgem_edu_loan;
        $f_nocgem_emergency    = $e_nocgem_emergency;
        $f_nocgem_grocery      = $e_nocgem_grocery;
        $f_nocgem_hospital     = $e_nocgem_hospital;
        $f_nocgem_others       = $e_nocgem_others;
        $f_nocgem_plp          = $e_nocgem_plp;
        $f_nocgem_regular_loan = $e_nocgem_regular_loan;
        $f_nocgemTotal         = $f_nocgem_edu_loan + $f_nocgem_emergency + $f_nocgem_grocery + $f_nocgem_hospital + $f_nocgem_others + $f_nocgem_plp + $f_nocgem_regular_loan;

        $f_other_cash_advance = $e_other_cash_advance;
        $f_other_insurance    = $e_other_insurance;
        $f_other_salary_loan  = $e_other_salary_loan;
        $f_other_others       = $e_other_others;
        $f_otherTotal         = $f_other_cash_advance + $f_other_insurance + $f_other_salary_loan + $f_other_others;

        $bcgeuTotal        = $bcgeuTotal_shared;
        $consolidatedGrand = $f_gsisTotal + $f_pagibigExtraTotal + $f_providentTotal + $bcgeuTotal + $f_bacgemTotal + $f_nocgemTotal + $f_otherTotal;

        // Duplicate check
        $checkStmt = $conn->prepare("SELECT id FROM payroll WHERE employee_id = ? AND payroll_month = ? AND payroll_year = ? AND period_type = ?");
        $checkStmt->bind_param("isis", $empId, $payrollMonth, $payrollYear, $periodType);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) { $skipCount++; $checkStmt->close(); continue; }
        $checkStmt->close();

        $isHalfMonth      = ($periodType === '1-15' || str_starts_with($periodType, '16-'));
        $salaryThisPeriod = $isHalfMonth ? ($basicSalary / 2) : $basicSalary;

        $philhealth = calculatePhilHealth($salaryThisPeriod);
        $pagibig    = calculatePagIbig($salaryThisPeriod) + $f_pagibigExtraTotal;
        $wtax       = calculateWithholdingTax($salaryThisPeriod);
        $grossPay        = $salaryThisPeriod + $pera;
        $totalDeductions = $f_gsisTotal + $philhealth + $pagibig + $wtax + $f_providentTotal + $bcgeuTotal + $f_nocgemTotal + $f_bacgemTotal + $f_otherTotal;
        $netPay          = $grossPay - $totalDeductions;
        $basicSalaryInsert = $salaryThisPeriod;
        $status            = 'Draft';

        $stmt = $conn->prepare("INSERT INTO payroll (employee_id, department_id, salary_id, payroll_period, payroll_month, payroll_year, period_type, basic_salary, pera, gross_pay, wtax, philhealth, gsis, pagibig, provident, bcgeu, nocgem, bacgem, other_deductions, total_deductions, net_pay, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiissisdddddddddddddds", $empId, $deptId, $salaryId, $payrollPeriod, $payrollMonth, $payrollYear, $periodType, $basicSalaryInsert, $pera, $grossPay, $wtax, $philhealth, $f_gsisTotal, $pagibig, $f_providentTotal, $bcgeuTotal, $f_nocgemTotal, $f_bacgemTotal, $f_otherTotal, $totalDeductions, $netPay, $status);

        if ($stmt->execute()) {
            $payrollId = $conn->insert_id; $stmt->close();
            $cStmt = $conn->prepare("INSERT INTO consolidated (payroll_id, employee_id, department_id, payroll_month, payroll_year, period_type, gsis_life_ret, gsis_emergency, gsis_cpl, gsis_gpal, gsis_mpl, gsis_mpl_lite, gsis_policy_loan, gsis_total, pagibig_multi, pagibig_emergency, pagibig_premium, pagibig_mp2, pagibig_housing, pagibig_total, provident_fund, provident_fund_loan, provident_edu_loan, provident_term_loan, provident_total, bcgeu_edu_loan, bcgeu_emergency, bcgeu_grocery, bcgeu_regular_loan, bcgeu_others, bcgeu_total, bacgem_edu_loan, bacgem_grocery, bacgem_others, bacgem_hcp, bacgem_loan, bacgem_total, nocgem_edu_loan, nocgem_emergency, nocgem_grocery, nocgem_hospital, nocgem_others, nocgem_plp, nocgem_regular_loan, nocgem_total, other_cash_advance, other_insurance, other_salary_loan, other_others, other_total, grand_total) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $cStmt->bind_param("iiissi"."dddddddd"."dddddd"."ddddd"."dddddd"."dddddd"."dddddddd"."ddddd"."d",
                $payrollId,$empId,$deptId,$payrollMonth,$payrollYear,$periodType,
                $f_gsis_life_ret,$f_gsis_emergency,$f_gsis_cpl,$f_gsis_gpal,$f_gsis_mpl,$f_gsis_mpl_lite,$f_gsis_policy_loan,$f_gsisTotal,
                $f_pagibig_multi,$f_pagibig_emergency,$f_pagibig_premium,$f_pagibig_mp2,$f_pagibig_housing,$f_pagibigExtraTotal,
                $f_provident_fund,$f_provident_fund_loan,$f_provident_edu_loan,$f_provident_term_loan,$f_providentTotal,
                $bcgeu_edu_loan,$bcgeu_emergency,$bcgeu_grocery,$bcgeu_regular_loan,$bcgeu_others,$bcgeuTotal,
                $f_bacgem_edu_loan,$f_bacgem_grocery,$f_bacgem_others,$f_bacgem_hcp,$f_bacgem_loan,$f_bacgemTotal,
                $f_nocgem_edu_loan,$f_nocgem_emergency,$f_nocgem_grocery,$f_nocgem_hospital,$f_nocgem_others,$f_nocgem_plp,$f_nocgem_regular_loan,$f_nocgemTotal,
                $f_other_cash_advance,$f_other_insurance,$f_other_salary_loan,$f_other_others,$f_otherTotal,
                $consolidatedGrand);
            $cStmt->execute(); $cStmt->close();
            $successCount++;
        } else { $stmt->close(); $errorCount++; }
    }

    if ($successCount > 0) {
        $_SESSION['success_message'] = "Batch payroll created! $successCount record(s) created" .
            ($skipCount > 0 ? ", $skipCount skipped (already exists)" : "") .
            ($errorCount > 0 ? ", $errorCount failed" : "");
    } else {
        $_SESSION['error_message'] = "No records created. " .
            ($skipCount > 0 ? "$skipCount already exist. " : "") .
            ($errorCount > 0 ? "$errorCount failed." : "");
    }
    header('Location: payroll.php?department_id=' . $deptId); exit;
}

$departments = $conn->query("SELECT id, department_name, department_code FROM departments ORDER BY department_name");
$selectedDept = null; $employees = [];

if ($selectedDeptId > 0) {
    $deptStmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
    $deptStmt->bind_param("i", $selectedDeptId);
    $deptStmt->execute();
    $selectedDept = $deptStmt->get_result()->fetch_assoc();
    $deptStmt->close();

    $empStmt = $conn->prepare("SELECT e.id, e.employee_id, e.first_name, e.last_name, e.middle_name, e.date_hired, p.position_title, p.salary_grade, p.basic_salary FROM employees e LEFT JOIN positions p ON e.position_id = p.id WHERE e.department_id = ? AND e.is_active = 1 ORDER BY e.last_name, e.first_name");
    $empStmt->bind_param("i", $selectedDeptId);
    $empStmt->execute();
    $empResult = $empStmt->get_result();

    while ($emp = $empResult->fetch_assoc()) {
        $currentSalary  = $emp['basic_salary'] ?? 0;
        $salaryId       = 0;
        $currentStep    = 1;
        $activePosition = $emp['position_title'] ?? 'N/A';
        $activeSG       = $emp['salary_grade']   ?? 'N/A';

        $siQuery = $conn->prepare("SELECT si.step_inc, sp.position_title AS si_position FROM step_increment si LEFT JOIN positions sp ON si.position_id = sp.id WHERE si.employee_id = ? AND si.is_active = 'Y' LIMIT 1");
        $siQuery->bind_param("i", $emp['id']);
        $siQuery->execute();
        $siData = $siQuery->get_result()->fetch_assoc();
        $siQuery->close();

        if ($siData) {
            $currentStep = $siData['step_inc'];
            if ($siData['si_position']) $activePosition = $siData['si_position'];
            if ($activeSG !== 'N/A') {
                $salaryQuery = $conn->prepare("SELECT salary_id, salary_rate FROM salary WHERE salary_grade = ? AND step_no = ? LIMIT 1");
                $salaryQuery->bind_param("si", $activeSG, $currentStep);
                $salaryQuery->execute();
                if ($salaryData = $salaryQuery->get_result()->fetch_assoc()) {
                    $currentSalary = $salaryData['salary_rate'];
                    $salaryId      = $salaryData['salary_id'];
                }
                $salaryQuery->close();
            }
        } else {
            if ($emp['date_hired']) {
                $years = (new DateTime($emp['date_hired']))->diff(new DateTime())->y;
                $currentStep = min(8, (int)floor($years / 3) + 1);
            }
            if ($activeSG !== 'N/A') {
                $salaryQuery = $conn->prepare("SELECT salary_id, salary_rate FROM salary WHERE salary_grade = ? AND step_no = ? LIMIT 1");
                $salaryQuery->bind_param("si", $activeSG, $currentStep);
                $salaryQuery->execute();
                if ($salaryData = $salaryQuery->get_result()->fetch_assoc()) {
                    $currentSalary = $salaryData['salary_rate'];
                    $salaryId      = $salaryData['salary_id'];
                }
                $salaryQuery->close();
            }
        }

        $yearsOfService = $emp['date_hired'] ? (new DateTime($emp['date_hired']))->diff(new DateTime())->y : 0;
        $employees[] = [
            'id'              => $emp['id'],
            'employee_id'     => $emp['employee_id'],
            'full_name'       => $emp['last_name'].', '.$emp['first_name'].($emp['middle_name'] ? ' '.substr($emp['middle_name'],0,1).'.' : ''),
            'position'        => $activePosition,
            'salary_grade'    => $activeSG,
            'current_step'    => $currentStep,
            'basic_salary'    => $currentSalary,
            'salary_id'       => $salaryId,
            'years_of_service'=> $yearsOfService,
        ];
    }
    $empStmt->close();
}
require_once 'includes/header.php';
?>

<style>
/* ── Department selection ── */
.dept-select-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
.dept-card { background: #fff; border-radius: 14px; padding: 1.5rem; box-shadow: 0 1px 4px rgba(0,0,0,.08); cursor: pointer; transition: all .2s; border: 2px solid transparent; text-align: center; }
.dept-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(45,99,148,.15); border-color: #2d6394; }
.dept-card-icon { width: 56px; height: 56px; margin: 0 auto .875rem; border-radius: 50%; background: linear-gradient(135deg,#e3f0fa,#b5d5f0); color: #2d6394; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
.dept-card h3 { font-size: 1rem; font-weight: 700; color: #111827; margin-bottom: 3px; }
.dept-card p  { font-size: .8rem; color: #6b7280; }

/* ── Navigation ── */
.back-link { display: inline-flex; align-items: center; gap: .5rem; color: #6b7280; font-size: .875rem; font-weight: 600; margin-bottom: 1.25rem; text-decoration: none; transition: color .15s; }
.back-link:hover { color: #2d6394; }

/* ── Page header banner ── */
.batch-header { background: linear-gradient(135deg, #1a3a5c, #0f2135); color: #fff; padding: 1.25rem 1.5rem; border-radius: 14px; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; }
.batch-header h2 { font-size: 1.25rem; font-weight: 800; margin: 0 0 2px; }
.batch-header p  { color: #7eb3e0; font-size: .85rem; margin: 0; }

/* ── Period selector card ── */
.period-selector { background: #fff; border-radius: 14px; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
.period-selector h3 { font-size: .95rem; font-weight: 700; margin: 0 0 1rem; color: #111827; display: flex; align-items: center; gap: .5rem; }
.period-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; }

/* ── Employee table ── */
.employee-batch-table { width: 100%; border-collapse: collapse; }
.employee-batch-table th {
    background: #f8fafc;
    padding: 10px 14px;
    text-align: left;
    font-size: .78rem;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .05em;
    border-bottom: 2px solid #e5e7eb;
}
.employee-batch-table td { padding: 11px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.emp-main-row:hover > td { background: #f8fbff; }
.emp-main-row.has-warning > td { background: #fffbeb !important; }

.salary-input { width: 118px; padding: 7px 10px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: .875rem; font-weight: 600; text-align: right; color: #111827; transition: border-color .15s; }
.salary-input:focus { outline: none; border-color: #2d6394; box-shadow: 0 0 0 3px rgba(45,99,148,.1); }
.salary-input.over-limit { border-color: #ef4444 !important; background: #fef2f2; }

.pera-limit-badge { display: inline-block; font-size: .68rem; font-weight: 700; padding: 1px 7px; border-radius: 20px; background: #fef3c7; color: #92400e; margin-left: 5px; vertical-align: middle; }
.step-badge  { display: inline-block; padding: 3px 9px; background: #fefce8; color: #854d0e; border: 1px solid #fde68a; border-radius: 20px; font-size: .72rem; font-weight: 700; }
.grade-badge { display: inline-block; padding: 3px 9px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 20px; font-size: .72rem; font-weight: 700; }

/* ── Clickable employee name ── */
.emp-name-btn { background: none; border: none; padding: 0; cursor: pointer; text-align: left; display: inline-flex; align-items: center; gap: 8px; }
.employee-name { font-weight: 700; font-size: .9rem; color: #1d4ed8; text-decoration: underline; text-underline-offset: 2px; text-decoration-color: #bfdbfe; transition: color .15s, text-decoration-color .15s; }
.emp-name-btn:hover .employee-name { color: #1e40af; text-decoration-color: #1e40af; }
.emp-id-text { font-family: monospace; color: #9ca3af; font-size: .75rem; display: block; margin-top: 1px; }
.emp-name-chevron { font-size: .65rem; color: #9ca3af; transition: transform .25s, color .15s; }
.emp-name-chevron.open { transform: rotate(180deg); color: #1d4ed8; }

/* ── Loan total badge ── */
.emp-loan-total-badge { display: inline-flex; align-items: center; gap: 4px; font-size: .7rem; font-weight: 700; padding: 2px 8px; background: #f1f5f9; color: #64748b; border-radius: 20px; margin-left: 6px; vertical-align: middle; border: 1px solid #e2e8f0; transition: all .2s; }
.emp-loan-total-badge.has-value { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
.emp-loan-total-badge.over-salary { background: #fef2f2 !important; color: #b91c1c !important; border-color: #fecaca !important; animation: pulse-warn .8s ease-in-out infinite alternate; }

/* ── Per-row salary warning banner ── */
.emp-salary-warning {
    display: none;
    align-items: center;
    gap: 8px;
    background: #fff7ed;
    border: 1.5px solid #fed7aa;
    border-radius: 8px;
    padding: 6px 10px;
    margin-top: 5px;
    font-size: .75rem;
    font-weight: 600;
    color: #c2410c;
    white-space: nowrap;
}
.emp-salary-warning.visible { display: flex; }
.emp-salary-warning i { color: #ea580c; flex-shrink: 0; }

/* ── Global warning banner (above submit) ── */
#globalLoanWarning {
    display: none;
    background: #fef2f2;
    border: 2px solid #fca5a5;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-top: 1.25rem;
    color: #991b1b;
}
#globalLoanWarning.visible { display: flex; align-items: flex-start; gap: 12px; }
#globalLoanWarning i { font-size: 1.4rem; color: #ef4444; flex-shrink: 0; margin-top: 2px; }
#globalLoanWarning h4 { font-size: .9rem; font-weight: 800; margin: 0 0 4px; }
#globalLoanWarning ul { margin: 4px 0 0; padding-left: 1.2rem; font-size: .8rem; }
#globalLoanWarning li { margin-bottom: 2px; }

/* ── Per-employee loan panel ── */
.emp-loan-panel { padding: 16px 20px 20px; background: #f8fbff; border-top: 1px solid #dbeafe; }
.emp-loan-panel-title { font-size: .72rem; font-weight: 800; color: #3b82f6; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
.emp-loan-panel-title i { background: #dbeafe; color: #1d4ed8; padding: 5px; border-radius: 6px; }
.emp-loan-panel-title::after { content: ''; flex: 1; height: 1px; background: #dbeafe; }

/* ── Per-employee coop grid ── */
.emp-coop-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }

.emp-coop-block { background: #fff; border: 1.5px solid #e5e7eb; border-radius: 10px; overflow: hidden; transition: border-color .15s, box-shadow .15s; }
.emp-coop-block:focus-within { border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(59,130,246,.08); }

.emp-coop-header { display: flex; align-items: center; justify-content: space-between; padding: 9px 12px; background: #fafafa; cursor: pointer; user-select: none; transition: background .15s; }
.emp-coop-header:hover { background: #f0f7ff; }

.emp-coop-title { display: flex; align-items: center; gap: 8px; }
.emp-coop-tag { display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: .65rem; font-weight: 800; letter-spacing: .04em; white-space: nowrap; }
.emp-coop-label { font-size: .78rem; font-weight: 600; color: #374151; }

.emp-coop-right { display: flex; align-items: center; gap: 8px; }
.emp-coop-total { font-size: .72rem; font-weight: 700; color: #6b7280; background: #f3f4f6; padding: 2px 8px; border-radius: 20px; white-space: nowrap; }
.emp-coop-chevron { font-size: .65rem; color: #9ca3af; transition: transform .2s; }
.emp-coop-chevron.open { transform: rotate(180deg); color: #3b82f6; }

.emp-coop-body { display: none; padding: 12px; border-top: 1px solid #f1f5f9; background: #fdfdfd; animation: slideDown .18s ease; }
.emp-coop-body.open { display: block; }

.emp-coop-subfields { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; }
.emp-coop-subfield-item label { display: block; font-size: .68rem; font-weight: 600; color: #6b7280; margin-bottom: 3px; text-transform: uppercase; letter-spacing: .03em; }
.emp-coop-subfield-item input { width: 100%; padding: 6px 8px; border: 1.5px solid #e5e7eb; border-radius: 6px; text-align: right; font-size: .82rem; font-weight: 600; color: #111827; transition: border-color .15s; }
.emp-coop-subfield-item input:focus { outline: none; border-color: #3b82f6; background: #eff6ff; }

/* ── Submit bar ── */
.batch-summary { background: linear-gradient(135deg, #059669, #047857); color: #fff; border-radius: 14px; padding: 1.125rem 1.5rem; margin-top: 1.25rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(5,150,105,.25); }
.batch-summary.has-warnings { background: linear-gradient(135deg, #d97706, #b45309); box-shadow: 0 4px 12px rgba(217,119,6,.3); }
.batch-summary-info h3 { font-size: 1rem; font-weight: 700; margin: 0 0 2px; }
.batch-summary-info p  { font-size: .85rem; opacity: .85; margin: 0; }
.btn-create-batch { background: #fff; color: #047857; padding: 10px 24px; border-radius: 10px; font-weight: 700; font-size: .95rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; transition: all .2s; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.batch-summary.has-warnings .btn-create-batch { color: #92400e; }
.btn-create-batch:hover { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(0,0,0,.15); }

/* ── Empty state ── */
.no-employees { text-align: center; padding: 3rem; color: #6b7280; }
.no-employees i { font-size: 2.5rem; color: #d1d5db; margin-bottom: 1rem; display: block; }

@keyframes slideDown { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
@keyframes pulse-warn { from { opacity: 1; } to { opacity: .6; } }
</style>

<?php if ($selectedDeptId == 0): ?>
<div class="page-header">
    <div class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><span>/</span><a href="payroll.php">Payroll</a><span>/</span><span>Create Batch Payroll</span></div>
    <h1 class="page-title">Create Batch Payroll</h1>
    <p class="page-subtitle">Select a department to create payroll for all employees</p>
</div>
<div class="dept-select-grid">
<?php $departments->data_seek(0); while ($dept = $departments->fetch_assoc()):
    $empCount = $conn->query("SELECT COUNT(*) as count FROM employees WHERE department_id = {$dept['id']} AND is_active = 1")->fetch_assoc()['count']; ?>
    <div class="dept-card" onclick="window.location.href='payroll_create.php?department_id=<?php echo $dept['id'];?>'">
        <div class="dept-card-icon"><i class="fas fa-users"></i></div>
        <h3><?php echo htmlspecialchars($dept['department_name']);?></h3>
        <p><?php echo $empCount;?> active employee<?php echo $empCount!=1?'s':'';?></p>
    </div>
<?php endwhile; ?>
</div>

<?php else: ?>
<a href="payroll.php?department_id=<?php echo $selectedDeptId;?>" class="back-link"><i class="fas fa-arrow-left"></i> Back to Payroll List</a>
<div class="page-header">
    <div class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><span>/</span><a href="payroll.php">Payroll</a><span>/</span><a href="payroll.php?department_id=<?php echo $selectedDeptId;?>"><?php echo htmlspecialchars($selectedDept['department_code']);?></a><span>/</span><span>Create Batch</span></div>
</div>
<div class="batch-header">
    <h2><i class="fas fa-layer-group"></i> Batch Payroll — <?php echo htmlspecialchars($selectedDept['department_name']);?></h2>
    <p>Create payroll for <?php echo count($employees);?> active employee<?php echo count($employees)!=1?'s':'';?></p>
</div>

<?php if (count($employees) > 0): ?>
<form method="POST" id="batchForm">
    <input type="hidden" name="create_batch" value="1">
    <input type="hidden" name="department_id" value="<?php echo $selectedDeptId;?>">

    <!-- Period Selector -->
    <div class="period-selector">
        <h3><i class="fas fa-calendar-alt"></i> Payroll Period</h3>
        <div class="period-grid">
            <div class="form-group">
                <label class="form-label">Month</label>
                <select name="payroll_month" id="payroll_month" class="form-control" required onchange="updatePeriodOptions()">
                    <?php $months=['January','February','March','April','May','June','July','August','September','October','November','December']; $cm=date('F');
                    foreach($months as $m): ?><option value="<?php echo $m;?>"<?php echo $m===$cm?' selected':'';?>><?php echo $m;?></option><?php endforeach;?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Year</label>
                <select name="payroll_year" id="payroll_year" class="form-control" required onchange="updatePeriodOptions()">
                    <?php $cy=date('Y'); for($y=$cy+1;$y>=$cy-2;$y--): ?><option value="<?php echo $y;?>"<?php echo $y==$cy?' selected':'';?>><?php echo $y;?></option><?php endfor;?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Period</label>
                <select name="period_type" id="period_type" class="form-control" required onchange="updatePeriodOptions()">
                    <option value="1-15">1-15 (First Half)</option>
                    <option value="16-31">16-31 (Second Half)</option>
                    <option value="1-31" selected>1-31 (Full Month)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Employee List -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-users"></i> Employees</h2>
            <span style="background:#e0f2fe;color:#0369a1;padding:6px 14px;border-radius:20px;font-weight:600;"><?php echo count($employees);?> employee<?php echo count($employees)!=1?'s':'';?></span>
        </div>
        <div class="card-body" style="padding:0">
            <table class="employee-batch-table">
                <thead>
                    <tr>
                        <th style="width:40px"><input type="checkbox" id="selectAll" checked onchange="toggleSelectAll(this)"></th>
                        <th>Employee <small style="font-weight:500;color:#9ca3af;font-size:.7rem;">&nbsp;— click name to add loans</small></th>
                        <th>Position</th><th>Grade</th><th>Step</th><th>Basic Salary</th>
                        <th>PERA <span class="pera-limit-badge" id="pera-limit-label">Max ₱1,000</span></th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($employees as $emp): $eid = $emp['id']; ?>
                    <!-- Main row -->
                    <tr class="emp-main-row" id="emp-main-row-<?php echo $eid;?>">
                        <td>
                            <input type="checkbox" name="employee_ids[]" value="<?php echo $eid;?>" class="emp-checkbox" checked>
                            <input type="hidden" name="salary_ids[<?php echo $eid;?>]" value="<?php echo $emp['salary_id'];?>">
                        </td>
                        <td>
                            <button type="button" class="emp-name-btn" onclick="toggleEmpLoans(<?php echo $eid;?>)">
                                <span>
                                    <div class="employee-name"><?php echo htmlspecialchars($emp['full_name']);?></div>
                                    <div style="font-family:monospace;color:#6b7280;font-size:.8rem;"><?php echo htmlspecialchars($emp['employee_id']);?></div>
                                </span>
                                <i class="fas fa-chevron-down emp-name-chevron" id="emp-chevron-<?php echo $eid;?>"></i>
                            </button>
                            <span class="emp-loan-total-badge" id="emp-loan-badge-<?php echo $eid;?>">
                                <i class="fas fa-minus-circle" style="font-size:.6rem"></i> ₱0.00
                            </span>
                            <!-- Per-row warning shown when loans > salary -->
                            <div class="emp-salary-warning" id="emp-warn-<?php echo $eid;?>">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span id="emp-warn-text-<?php echo $eid;?>">Loans exceed salary!</span>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($emp['position']);?></td>
                        <td><span class="grade-badge">SG-<?php echo $emp['salary_grade'];?></span></td>
                        <td><span class="step-badge">Step <?php echo $emp['current_step'];?></span></td>
                        <td>
                            <input type="number"
                                   name="basic_salaries[<?php echo $eid;?>]"
                                   id="basic-salary-<?php echo $eid;?>"
                                   class="salary-input"
                                   value="<?php echo number_format($emp['basic_salary'],2,'.','')?>"
                                   step="0.01" min="0"
                                   oninput="checkLoanVsSalary(<?php echo $eid;?>)">
                        </td>
                        <td>
                            <input type="number"
                                   name="peras[<?php echo $eid;?>]"
                                   class="salary-input pera-input"
                                   value="1000.00" step="0.01" min="0" max="1000"
                                   style="width:100px"
                                   oninput="validatePera(this); checkLoanVsSalary(<?php echo $eid;?>)">
                        </td>
                    </tr>

                    <!-- Per-employee loan panel -->
                    <tr id="emp-loan-row-<?php echo $eid;?>" style="display:none">
                        <td colspan="7" style="padding:0;border-bottom:1.5px solid #dbeafe;">
                            <div class="emp-loan-panel">
                                <div class="emp-loan-panel-title"><i class="fas fa-user-tag"></i> Individual Loans &amp; Deductions — <?php echo htmlspecialchars($emp['full_name']);?></div>
                                <div class="emp-coop-grid">
<?php
$empBlocks = [
    'gsis'     => ['tag'=>'GSIS',     'style'=>'background:#dcfce7;color:#14532d;', 'label'=>'GSIS Loans',       'fields'=>['gsis_life_ret'=>'Life &amp; Ret.','gsis_emergency'=>'Emergency','gsis_cpl'=>'CPL','gsis_gpal'=>'GPAL','gsis_mpl'=>'MPL','gsis_mpl_lite'=>'MPL Lite','gsis_policy_loan'=>'Policy Loan']],
    'pagibig'  => ['tag'=>'PAG-IBIG', 'style'=>'background:#fce7f3;color:#9d174d;', 'label'=>'Pag-IBIG Loans',  'fields'=>['pagibig_multi'=>'Multi-Purpose','pagibig_emergency'=>'Emergency','pagibig_premium'=>'Premium','pagibig_mp2'=>'MP2','pagibig_housing'=>'Housing Loan']],
    'provident'=> ['tag'=>'PROVIDENT','style'=>'background:#ede9fe;color:#5b21b6;', 'label'=>'Provident Fund',  'fields'=>['provident_fund'=>'Provident Fund','provident_fund_loan'=>'Fund Loan','provident_edu_loan'=>'Edu Loan','provident_term_loan'=>'Term Loan']],
    'bacgem'   => ['tag'=>'BACGEM',   'style'=>'background:#dbeafe;color:#1e40af;', 'label'=>'BAC Coop',        'fields'=>['bacgem_edu_loan'=>'Edu Loan','bacgem_grocery'=>'Grocery','bacgem_others'=>'Others','bacgem_hcp'=>'HCP','bacgem_loan'=>'Loan']],
    'nocgem'   => ['tag'=>'NOCGEM',   'style'=>'background:#d1fae5;color:#065f46;', 'label'=>'NOC Coop',        'fields'=>['nocgem_edu_loan'=>'Edu Loan','nocgem_emergency'=>'Emergency','nocgem_grocery'=>'Grocery','nocgem_hospital'=>'Hospital','nocgem_others'=>'Others','nocgem_plp'=>'PLP','nocgem_regular_loan'=>'Regular Loan']],
    'other'    => ['tag'=>'OTHER',    'style'=>'background:#fee2e2;color:#991b1b;', 'label'=>'Misc. Deductions','fields'=>['other_cash_advance'=>'Cash Advance','other_insurance'=>'Insurance','other_salary_loan'=>'Salary Loan','other_others'=>'Others']],
];
foreach ($empBlocks as $pfx => $cfg): ?>
                                    <div class="emp-coop-block">
                                        <div class="emp-coop-header" onclick="toggleEmpCoop(<?php echo $eid;?>,'<?php echo $pfx;?>')">
                                            <div class="emp-coop-title">
                                                <span class="emp-coop-tag" style="<?php echo $cfg['style'];?>"><?php echo $cfg['tag'];?></span>
                                                <span class="emp-coop-label"><?php echo $cfg['label'];?></span>
                                            </div>
                                            <div class="emp-coop-right">
                                                <span class="emp-coop-total" id="emp-<?php echo $pfx;?>-total-<?php echo $eid;?>">₱0.00</span>
                                                <i class="fas fa-chevron-down emp-coop-chevron" id="emp-<?php echo $pfx;?>-chevron-<?php echo $eid;?>"></i>
                                            </div>
                                        </div>
                                        <div class="emp-coop-body" id="emp-<?php echo $pfx;?>-body-<?php echo $eid;?>">
                                            <div class="emp-coop-subfields">
<?php foreach ($cfg['fields'] as $fname => $flabel): ?>
                                                <div class="emp-coop-subfield-item">
                                                    <label><?php echo $flabel;?></label>
                                                    <input type="number" name="emp_<?php echo $fname;?>[<?php echo $eid;?>]"
                                                           class="emp-<?php echo $eid;?>-<?php echo $pfx;?>-sub emp-loan-input"
                                                           data-empid="<?php echo $eid;?>"
                                                           data-prefix="<?php echo $pfx;?>"
                                                           value="0.00" step="0.01" min="0"
                                                           oninput="updateEmpCoopTotal(<?php echo $eid;?>,'<?php echo $pfx;?>')">
                                                </div>
<?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
<?php endforeach; ?>
                                </div><!-- /.emp-coop-grid -->
                            </div><!-- /.emp-loan-panel -->
                        </td>
                    </tr>

<?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Global loan warning banner (shown above submit when any employee has loans > salary) -->
    <div id="globalLoanWarning">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <h4>Warning: Loan Deductions Exceed Salary</h4>
            <ul id="globalLoanWarningList"></ul>
            <p style="font-size:.78rem;margin:.5rem 0 0;opacity:.85;">You can still proceed, but please review the flagged employees before submitting.</p>
        </div>
    </div>

    <!-- Submit -->
    <div class="batch-summary" id="batchSummaryBar">
        <div class="batch-summary-info">
            <h3 id="summaryIcon"><i class="fas fa-check-circle"></i> Ready to Create</h3>
            <p><span id="selectedCount"><?php echo count($employees);?></span> employee(s) selected &nbsp;·&nbsp; <span id="warnCount" style="display:none;font-weight:700;"><i class="fas fa-exclamation-triangle"></i> <span id="warnCountNum">0</span> with loan warnings</span></p>
        </div>
        <button type="submit" class="btn-create-batch"><i class="fas fa-save"></i> Create Batch Payroll</button>
    </div>
</form>

<script>
// ── Salary data from PHP (used for PERA limit) ────────────────────────────────
var empBaseSalaries = {};
<?php foreach ($employees as $emp): ?>
empBaseSalaries[<?php echo $emp['id']; ?>] = <?php echo (float)$emp['basic_salary']; ?>;
<?php endforeach; ?>

// Employees with loan-over-salary warnings (empId → true/false)
var loanWarnings = {};

function fmt(n) { return '₱' + parseFloat(n).toLocaleString('en-PH', {minimumFractionDigits:2,maximumFractionDigits:2}); }

// ── Check loans vs salary for a single employee ───────────────────────────────
function checkLoanVsSalary(empId) {
    var salaryInput = document.getElementById('basic-salary-' + empId);
    var salary      = parseFloat(salaryInput ? salaryInput.value : 0) || 0;

    // Sum ALL loan inputs for this employee
    var totalLoans = 0;
    document.querySelectorAll('.emp-loan-input[data-empid="' + empId + '"]').forEach(function(inp) {
        totalLoans += parseFloat(inp.value) || 0;
    });

    var warn    = document.getElementById('emp-warn-' + empId);
    var badge   = document.getElementById('emp-loan-badge-' + empId);
    var mainRow = document.getElementById('emp-main-row-' + empId);
    var over    = totalLoans > 0 && totalLoans > salary;

    loanWarnings[empId] = over;

    // Per-row inline warning
    if (warn) {
        if (over) {
            var diff = totalLoans - salary;
            document.getElementById('emp-warn-text-' + empId).textContent =
                'Loans (' + fmt(totalLoans) + ') exceed salary (' + fmt(salary) + ') by ' + fmt(diff);
            warn.classList.add('visible');
        } else {
            warn.classList.remove('visible');
        }
    }

    // Badge colour
    if (badge) { badge.classList.toggle('over-salary', over); }

    // Row highlight
    if (mainRow) { mainRow.classList.toggle('has-warning', over); }

    // Update global warning banner + submit bar
    refreshGlobalWarning();
}

// ── Rebuild the global warning banner ────────────────────────────────────────
function refreshGlobalWarning() {
    var banner  = document.getElementById('globalLoanWarning');
    var list    = document.getElementById('globalLoanWarningList');
    var bar     = document.getElementById('batchSummaryBar');
    var warnSpan= document.getElementById('warnCount');
    var warnNum = document.getElementById('warnCountNum');

    var overEmp = Object.keys(loanWarnings).filter(function(id) { return loanWarnings[id]; });

    if (overEmp.length > 0) {
        list.innerHTML = '';
        overEmp.forEach(function(id) {
            var salary     = parseFloat((document.getElementById('basic-salary-' + id) || {}).value) || 0;
            var totalLoans = 0;
            document.querySelectorAll('.emp-loan-input[data-empid="' + id + '"]').forEach(function(inp) {
                totalLoans += parseFloat(inp.value) || 0;
            });
            // Get employee name from the name button
            var nameEl = document.querySelector('#emp-main-row-' + id + ' .employee-name');
            var name   = nameEl ? nameEl.textContent.trim() : 'Employee #' + id;
            var li = document.createElement('li');
            li.textContent = name + ' — loans ' + fmt(totalLoans) + ' vs salary ' + fmt(salary);
            list.appendChild(li);
        });
        banner.classList.add('visible');
        bar.classList.add('has-warnings');
        warnNum.textContent = overEmp.length;
        warnSpan.style.display = 'inline';
    } else {
        banner.classList.remove('visible');
        bar.classList.remove('has-warnings');
        warnSpan.style.display = 'none';
    }
}

// ── Existing helpers (unchanged) ─────────────────────────────────────────────
function getDaysInMonth(month,year){const mi=['January','February','March','April','May','June','July','August','September','October','November','December'].indexOf(month);return new Date(year,mi+1,0).getDate();}
function getPeraLimit(){const p=document.getElementById('period_type').value;return(p==='1-15'||p.startsWith('16-'))?1000:2000;}
function updatePeraLimits(){const l=getPeraLimit();document.getElementById('pera-limit-label').textContent='Max ₱'+l.toLocaleString('en-PH');document.querySelectorAll('.pera-input').forEach(i=>{i.max=l;i.value=l.toFixed(2);validatePera(i);});}
function validatePera(input){const l=getPeraLimit();if(parseFloat(input.value)>l){input.classList.add('over-limit');input.title='Exceeds PERA limit of ₱'+l.toLocaleString('en-PH')+' for this period';}else{input.classList.remove('over-limit');input.title='';}}
function updatePeriodOptions(forcedValue){const month=document.getElementById('payroll_month').value,year=document.getElementById('payroll_year').value,days=getDaysInMonth(month,year),sel=document.getElementById('period_type'),cur=forcedValue||sel.value;sel.innerHTML=`<option value="1-15">1-15 (First Half)</option><option value="16-${days}">16-${days} (Second Half)</option><option value="1-${days}">1-${days} (Full Month)</option>`;if(cur==='1-15')sel.value='1-15';else if(cur.startsWith('16-'))sel.value='16-'+days;else sel.value='1-'+days;updatePeraLimits();}
function toggleSelectAll(cb){document.querySelectorAll('.emp-checkbox').forEach(c=>c.checked=cb.checked);updateSelectedCount();}
function updateSelectedCount(){document.getElementById('selectedCount').textContent=document.querySelectorAll('.emp-checkbox:checked').length;}
document.querySelectorAll('.emp-checkbox').forEach(cb=>cb.addEventListener('change',updateSelectedCount));
document.addEventListener('DOMContentLoaded',function(){
    let hasSaved = false;
    try { hasSaved = !!localStorage.getItem(STORE_KEY); } catch(e){}
    if (hasSaved) { restoreFormState(); } else { updatePeriodOptions('1-31'); updatePeraLimits(); }
});

// Per-employee loan panel
function toggleEmpLoans(empId){
    const row=document.getElementById('emp-loan-row-'+empId);
    const chev=document.getElementById('emp-chevron-'+empId);
    const isHidden=(row.style.display===''||row.style.display==='none');
    row.style.display=isHidden?'table-row':'none';
    chev.classList.toggle('open',isHidden);
    try { localStorage.setItem('emp_panel_open_'+empId, isHidden ? '1' : '0'); } catch(e){}
}
function toggleEmpCoop(empId,prefix){
    document.getElementById('emp-'+prefix+'-body-'+empId).classList.toggle('open');
    document.getElementById('emp-'+prefix+'-chevron-'+empId).classList.toggle('open');
}
function updateEmpCoopTotal(empId,prefix){
    let t=0;
    document.querySelectorAll('.emp-'+empId+'-'+prefix+'-sub').forEach(i=>{t+=parseFloat(i.value)||0;});
    document.getElementById('emp-'+prefix+'-total-'+empId).textContent=fmt(t);
    let grand=0;
    ['gsis','pagibig','provident','bacgem','nocgem','other'].forEach(p=>{
        document.querySelectorAll('.emp-'+empId+'-'+p+'-sub').forEach(i=>{grand+=parseFloat(i.value)||0;});
    });
    const badge=document.getElementById('emp-loan-badge-'+empId);
    badge.innerHTML='<i class="fas fa-minus-circle" style="font-size:.6rem"></i> '+fmt(grand);
    badge.classList.toggle('has-value',grand>0);

    // Trigger loan vs salary check every time a loan field changes
    checkLoanVsSalary(empId);
}

// ── localStorage persistence ──────────────────────────────────────────────────
const STORE_KEY = 'payroll_batch_<?php echo $selectedDeptId; ?>';

function saveFormState() {
    try {
        const state = {};
        ['payroll_month','payroll_year','period_type'].forEach(id => {
            const el = document.getElementById(id);
            if (el) state[id] = el.value;
        });
        document.querySelectorAll('#batchForm input, #batchForm select').forEach(el => {
            if (!el.name) return;
            if (el.type === 'checkbox') state['chk_'+el.value] = el.checked;
            else if (el.type !== 'hidden') state[el.name] = el.value;
        });
        localStorage.setItem(STORE_KEY, JSON.stringify(state));
    } catch(e) {}
}

function restoreFormState() {
    try {
        const raw = localStorage.getItem(STORE_KEY);
        if (!raw) return;
        const state = JSON.parse(raw);
        ['payroll_month','payroll_year'].forEach(id => {
            const el = document.getElementById(id);
            if (el && state[id] !== undefined) el.value = state[id];
        });
        const savedPeriod = state['period_type'] || '1-31';
        updatePeriodOptions(savedPeriod);
        document.querySelectorAll('#batchForm input, #batchForm select').forEach(el => {
            if (!el.name) return;
            if (el.type === 'checkbox') {
                const saved = state['chk_'+el.value];
                if (saved !== undefined) el.checked = saved;
            } else if (el.type !== 'hidden' && state[el.name] !== undefined) {
                el.value = state[el.name];
            }
        });
        document.querySelectorAll('.emp-loan-input').forEach(inp => {
            const classes = [...inp.classList];
            const subClass = classes.find(c => c.endsWith('-sub') && c.startsWith('emp-'));
            if (!subClass) return;
            const parts = subClass.split('-');
            if (parts.length >= 4) {
                const empId = parts[1];
                const prefix = parts.slice(2, parts.length - 1).join('-');
                updateEmpCoopTotal(empId, prefix);
            }
        });
        document.querySelectorAll('[id^="emp-loan-row-"]').forEach(row => {
            const empId = row.id.replace('emp-loan-row-','');
            const wasOpen = localStorage.getItem('emp_panel_open_'+empId) === '1';
            if (wasOpen) {
                row.style.display = 'table-row';
                const chev = document.getElementById('emp-chevron-'+empId);
                if (chev) chev.classList.add('open');
            }
        });
        updateSelectedCount();
    } catch(e) {}
}

function clearFormState() {
    try {
        localStorage.removeItem(STORE_KEY);
        document.querySelectorAll('[id^="emp-loan-row-"]').forEach(row => {
            const empId = row.id.replace('emp-loan-row-','');
            localStorage.removeItem('emp_panel_open_'+empId);
        });
    } catch(e) {}
}

document.querySelectorAll('#batchForm input, #batchForm select').forEach(el => {
    el.addEventListener('input', saveFormState);
    el.addEventListener('change', saveFormState);
});

document.getElementById('batchForm').addEventListener('submit', function(e) {
    const c = document.querySelectorAll('.emp-checkbox:checked').length;
    if (c === 0) { e.preventDefault(); alert('Please select at least one employee.'); return false; }
    const l = getPeraLimit(); let over = false;
    document.querySelectorAll('.pera-input').forEach(i => { if (parseFloat(i.value) > l) over = true; });
    if (over) { e.preventDefault(); alert('One or more PERA values exceed the ₱'+l.toLocaleString('en-PH')+' limit.'); return false; }

    // Warn if any loans exceed salary — allow proceed with confirmation
    const overCount = Object.values(loanWarnings).filter(Boolean).length;
    if (overCount > 0) {
        if (!confirm('⚠️ Warning: ' + overCount + ' employee(s) have loan deductions exceeding their salary.\n\nThis will result in a negative net pay.\n\nDo you still want to proceed?')) {
            e.preventDefault(); return false;
        }
    }

    if (!confirm('Create payroll for '+c+' employee(s)?')) { e.preventDefault(); return false; }
    clearFormState();
});
</script>

<?php else: ?>
<div class="card"><div class="card-body"><div class="no-employees"><i class="fas fa-users-slash"></i><h3>No Active Employees</h3><p>There are no active employees in this department.</p><a href="employees.php?department_id=<?php echo $selectedDeptId;?>" class="btn btn-primary" style="margin-top:1rem"><i class="fas fa-plus"></i> Add Employees</a></div></div></div>
<?php endif; ?>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>