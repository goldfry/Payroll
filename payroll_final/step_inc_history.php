<?php
/**
 * Payroll System - Employee Step Increment History
 */

require_once 'includes/config.php';

$pageTitle = 'Step Increment History';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $employeeId = (int)$_POST['employee_id'];
        $dateInc = $_POST['date_inc'] ?: null;
        $stepinc = (int)$_POST['stepinc'];
        $salaryId = (int)$_POST['salary_id'];
        $positionId = (int)$_POST['position_id'];
        $active = isset($_POST['active']) ? 'Y' : 'N';
        
        $stmt = $conn->prepare("INSERT INTO step_inc_history (employee_id, date_inc, stepinc, salary_id, position_id, active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isiiss", $employeeId, $dateInc, $stepinc, $salaryId, $positionId, $active);
        
        if ($stmt->execute()) {
            $message = 'Step increment record added successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $employeeId = (int)$_POST['employee_id'];
        $dateInc = $_POST['date_inc'] ?: null;
        $stepinc = (int)$_POST['stepinc'];
        $salaryId = (int)$_POST['salary_id'];
        $positionId = (int)$_POST['position_id'];
        $active = isset($_POST['active']) ? 'Y' : 'N';
        
        $stmt = $conn->prepare("UPDATE step_inc_history SET employee_id = ?, date_inc = ?, stepinc = ?, salary_id = ?, position_id = ?, active = ? WHERE stepinc_history_id = ?");
        $stmt->bind_param("isiissi", $employeeId, $dateInc, $stepinc, $salaryId, $positionId, $active, $id);
        
        if ($stmt->execute()) {
            $message = 'Step increment record updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM step_inc_history WHERE stepinc_history_id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = 'Step increment record deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// Get filter parameters
$filterEmployee = isset($_GET['employee']) ? (int)$_GET['employee'] : 0;
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$filterActive = isset($_GET['active']) ? sanitize($_GET['active']) : '';

// Build query with filters
$whereClause = "1=1";
if ($filterEmployee > 0) {
    $whereClause .= " AND h.employee_id = $filterEmployee";
}
if ($filterYear > 0) {
    $whereClause .= " AND YEAR(h.date_inc) = $filterYear";
}
if ($filterActive !== '') {
    $whereClause .= " AND h.active = '$filterActive'";
}

// Get all employees for dropdown
$employees = $conn->query("SELECT id, employee_id, first_name, middle_name, last_name FROM employees WHERE is_active = 1 ORDER BY last_name, first_name");

// Get all positions for dropdown
$positions = $conn->query("SELECT id, position_title, salary_grade FROM positions ORDER BY salary_grade, position_title");

// Get all salary grades for dropdown
$salaryGrades = $conn->query("SELECT id, salary_grade, step_1, step_2, step_3, step_4, step_5, step_6, step_7, step_8 FROM step_inc ORDER BY salary_grade");

// Get step increment history
$history = $conn->query("
    SELECT h.*, 
           e.employee_id as emp_id, e.first_name, e.middle_name, e.last_name,
           p.position_title, p.salary_grade,
           s.salary_grade as sg_level,
           CASE h.stepinc
               WHEN 1 THEN s.step_1
               WHEN 2 THEN s.step_2
               WHEN 3 THEN s.step_3
               WHEN 4 THEN s.step_4
               WHEN 5 THEN s.step_5
               WHEN 6 THEN s.step_6
               WHEN 7 THEN s.step_7
               WHEN 8 THEN s.step_8
           END as salary_amount
    FROM step_inc_history h
    LEFT JOIN employees e ON h.employee_id = e.id
    LEFT JOIN positions p ON h.position_id = p.id
    LEFT JOIN step_inc s ON h.salary_id = s.id
    WHERE $whereClause
    ORDER BY h.date_inc DESC, h.created_at DESC
");

// Get summary statistics
$summary = $conn->query("
    SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN active = 'Y' THEN 1 ELSE 0 END) as active_records,
        COUNT(DISTINCT employee_id) as unique_employees
    FROM step_inc_history h
    WHERE $whereClause
")->fetch_assoc();

require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <span>Step Increment History</span>
    </div>
    <h1 class="page-title">Step Increment History</h1>
    <p class="page-subtitle">Track and manage employee step increments</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <i class="alert-icon fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <div class="alert-content"><?php echo $message; ?></div>
    </div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="stats-grid">
    <div class="stat-card info">
        <div class="stat-icon"><i class="fas fa-list"></i></div>
        <div class="stat-value"><?php echo number_format($summary['total_records']); ?></div>
        <div class="stat-label">Total Records</div>
    </div>
    <div class="stat-card success">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?php echo number_format($summary['active_records']); ?></div>
        <div class="stat-label">Active Records</div>
    </div>
    <div class="stat-card primary">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-value"><?php echo number_format($summary['unique_employees']); ?></div>
        <div class="stat-label">Employees</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-history"></i>
            Step Increment Records
        </h2>
        <div class="btn-group">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control" placeholder="Search records..." data-search-table="historyTable">
            </div>
            <button class="btn btn-primary" onclick="Modal.open('addHistoryModal')">
                <i class="fas fa-plus"></i> Add Record
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card-body" style="padding: var(--space-md) var(--space-xl); border-bottom: 1px solid var(--gray-100);">
        <form method="GET" class="filter-bar">
            <select name="employee" class="form-control" style="width: auto; min-width: 250px;" onchange="this.form.submit()">
                <option value="0">All Employees</option>
                <?php 
                $employees->data_seek(0);
                while($emp = $employees->fetch_assoc()): 
                ?>
                    <option value="<?php echo $emp['id']; ?>" <?php echo $filterEmployee == $emp['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']); ?> (<?php echo htmlspecialchars($emp['employee_id']); ?>)
                    </option>
                <?php endwhile; ?>
            </select>
            <select name="year" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="0">All Years</option>
                <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $filterYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
            <select name="active" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="Y" <?php echo $filterActive === 'Y' ? 'selected' : ''; ?>>Active</option>
                <option value="N" <?php echo $filterActive === 'N' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <?php if ($filterEmployee > 0 || $filterYear > 0 || $filterActive !== ''): ?>
                <a href="step_inc_history.php" class="btn btn-secondary btn-sm">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="data-table" id="historyTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Position</th>
                        <th>SG</th>
                        <th>Step</th>
                        <th>Salary Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($history && $history->num_rows > 0): ?>
                        <?php while($row = $history->fetch_assoc()): ?>
                            <tr>
                                <td><code>INC-<?php echo str_pad($row['stepinc_history_id'], 4, '0', STR_PAD_LEFT); ?></code></td>
                                <td>
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="employee-name">
                                                <?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name']); ?>
                                            </div>
                                            <div class="employee-id"><?php echo htmlspecialchars($row['emp_id']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($row['date_inc']): ?>
                                        <strong><?php echo date('M d, Y', strtotime($row['date_inc'])); ?></strong>
                                        <br><small class="text-muted"><?php echo date('Y', strtotime($row['date_inc'])); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['position_title']): ?>
                                        <?php echo htmlspecialchars($row['position_title']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-info">SG-<?php echo $row['sg_level']; ?></span></td>
                                <td><span class="badge badge-secondary">Step <?php echo $row['stepinc']; ?></span></td>
                                <td class="currency"><?php echo formatCurrency($row['salary_amount']); ?></td>
                                <td>
                                    <?php if ($row['active'] === 'Y'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-secondary btn-icon sm" 
                                                onclick="editHistory(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                                title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-icon sm" 
                                                onclick="deleteHistory(<?php echo $row['stepinc_history_id']; ?>)"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted" style="padding: 2rem;">
                                No step increment records found. Add the first record above.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add History Modal -->
<div class="modal-overlay" id="addHistoryModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">Add Step Increment Record</h3>
            <button class="modal-close" onclick="Modal.close('addHistoryModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="addHistoryForm">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Employee</label>
                        <select name="employee_id" id="add_employee" class="form-control" required onchange="loadEmployeeInfo('add')">
                            <option value="">-- Select Employee --</option>
                            <?php 
                            $employees->data_seek(0);
                            while($emp = $employees->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $emp['id']; ?>">
                                    <?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']); ?> (<?php echo htmlspecialchars($emp['employee_id']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Date of Increment</label>
                        <input type="date" name="date_inc" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Position</label>
                        <select name="position_id" id="add_position" class="form-control" required>
                            <option value="">-- Select Position --</option>
                            <?php 
                            $positions->data_seek(0);
                            while($pos = $positions->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $pos['id']; ?>" data-sg="<?php echo $pos['salary_grade']; ?>">
                                    [SG-<?php echo $pos['salary_grade']; ?>] <?php echo htmlspecialchars($pos['position_title']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Salary Grade</label>
                        <select name="salary_id" id="add_salary_id" class="form-control" required onchange="updateStepOptions('add')">
                            <option value="">-- Select Salary Grade --</option>
                            <?php 
                            $salaryGrades->data_seek(0);
                            while($sg = $salaryGrades->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $sg['id']; ?>" 
                                        data-grade="<?php echo $sg['salary_grade']; ?>"
                                        data-step1="<?php echo $sg['step_1']; ?>"
                                        data-step2="<?php echo $sg['step_2']; ?>"
                                        data-step3="<?php echo $sg['step_3']; ?>"
                                        data-step4="<?php echo $sg['step_4']; ?>"
                                        data-step5="<?php echo $sg['step_5']; ?>"
                                        data-step6="<?php echo $sg['step_6']; ?>"
                                        data-step7="<?php echo $sg['step_7']; ?>"
                                        data-step8="<?php echo $sg['step_8']; ?>">
                                    SG-<?php echo $sg['salary_grade']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Step</label>
                        <select name="stepinc" id="add_stepinc" class="form-control" required onchange="updateSalaryDisplay('add')">
                            <option value="">-- Select Step --</option>
                            <option value="1">Step 1</option>
                            <option value="2">Step 2</option>
                            <option value="3">Step 3</option>
                            <option value="4">Step 4</option>
                            <option value="5">Step 5</option>
                            <option value="6">Step 6</option>
                            <option value="7">Step 7</option>
                            <option value="8">Step 8</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Salary Amount</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="text" id="add_salary_display" class="form-control" readonly style="background-color: #f0f0f0; font-weight: bold; font-size: 1.25rem; color: var(--success);" placeholder="Select SG and Step">
                    </div>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="active" value="1" checked>
                        <span>Active Record</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="Modal.close('addHistoryModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit History Modal -->
<div class="modal-overlay" id="editHistoryModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">Edit Step Increment Record</h3>
            <button class="modal-close" onclick="Modal.close('editHistoryModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="editHistoryForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Employee</label>
                        <select name="employee_id" id="edit_employee" class="form-control" required>
                            <option value="">-- Select Employee --</option>
                            <?php 
                            $employees->data_seek(0);
                            while($emp = $employees->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $emp['id']; ?>">
                                    <?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']); ?> (<?php echo htmlspecialchars($emp['employee_id']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Date of Increment</label>
                        <input type="date" name="date_inc" id="edit_date_inc" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Position</label>
                        <select name="position_id" id="edit_position" class="form-control" required>
                            <option value="">-- Select Position --</option>
                            <?php 
                            $positions->data_seek(0);
                            while($pos = $positions->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $pos['id']; ?>">
                                    [SG-<?php echo $pos['salary_grade']; ?>] <?php echo htmlspecialchars($pos['position_title']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Salary Grade</label>
                        <select name="salary_id" id="edit_salary_id" class="form-control" required onchange="updateStepOptions('edit')">
                            <option value="">-- Select Salary Grade --</option>
                            <?php 
                            $salaryGrades->data_seek(0);
                            while($sg = $salaryGrades->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $sg['id']; ?>"
                                        data-grade="<?php echo $sg['salary_grade']; ?>"
                                        data-step1="<?php echo $sg['step_1']; ?>"
                                        data-step2="<?php echo $sg['step_2']; ?>"
                                        data-step3="<?php echo $sg['step_3']; ?>"
                                        data-step4="<?php echo $sg['step_4']; ?>"
                                        data-step5="<?php echo $sg['step_5']; ?>"
                                        data-step6="<?php echo $sg['step_6']; ?>"
                                        data-step7="<?php echo $sg['step_7']; ?>"
                                        data-step8="<?php echo $sg['step_8']; ?>">
                                    SG-<?php echo $sg['salary_grade']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Step</label>
                        <select name="stepinc" id="edit_stepinc" class="form-control" required onchange="updateSalaryDisplay('edit')">
                            <option value="">-- Select Step --</option>
                            <option value="1">Step 1</option>
                            <option value="2">Step 2</option>
                            <option value="3">Step 3</option>
                            <option value="4">Step 4</option>
                            <option value="5">Step 5</option>
                            <option value="6">Step 6</option>
                            <option value="7">Step 7</option>
                            <option value="8">Step 8</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Salary Amount</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="text" id="edit_salary_display" class="form-control" readonly style="background-color: #f0f0f0; font-weight: bold; font-size: 1.25rem; color: var(--success);">
                    </div>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="active" id="edit_active" value="1">
                        <span>Active Record</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="Modal.close('editHistoryModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form (hidden) -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function updateStepOptions(mode) {
    updateSalaryDisplay(mode);
}

function updateSalaryDisplay(mode) {
    const salarySelect = document.getElementById(mode + '_salary_id');
    const stepSelect = document.getElementById(mode + '_stepinc');
    const salaryDisplay = document.getElementById(mode + '_salary_display');
    
    const selectedOption = salarySelect.options[salarySelect.selectedIndex];
    const step = stepSelect.value;
    
    if (selectedOption.value && step) {
        const salary = selectedOption.getAttribute('data-step' + step);
        salaryDisplay.value = parseFloat(salary).toLocaleString('en-PH', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        });
    } else {
        salaryDisplay.value = '';
    }
}

function editHistory(data) {
    document.getElementById('edit_id').value = data.stepinc_history_id;
    document.getElementById('edit_employee').value = data.employee_id;
    document.getElementById('edit_date_inc').value = data.date_inc || '';
    document.getElementById('edit_position').value = data.position_id;
    document.getElementById('edit_salary_id').value = data.salary_id;
    document.getElementById('edit_stepinc').value = data.stepinc;
    document.getElementById('edit_active').checked = data.active === 'Y';
    
    // Update salary display
    updateSalaryDisplay('edit');
    
    Modal.open('editHistoryModal');
}

function deleteHistory(id) {
    if (confirm('Are you sure you want to delete this step increment record? This action cannot be undone.')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>