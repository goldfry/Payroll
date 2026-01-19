<?php
/**
 * Payroll System - Employees Management
 */

require_once 'includes/config.php';

$pageTitle = 'Employees';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $empId = sanitize($_POST['employee_id']);
        $firstName = sanitize($_POST['first_name']);
        $middleName = sanitize($_POST['middle_name']);
        $lastName = sanitize($_POST['last_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        $dateHired = $_POST['date_hired'] ?: null;
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $posId = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
        $status = sanitize($_POST['employment_status']);
        
        $stmt = $conn->prepare("INSERT INTO employees (employee_id, first_name, middle_name, last_name, email, phone, address, date_hired, department_id, position_id, employment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssiss", $empId, $firstName, $middleName, $lastName, $email, $phone, $address, $dateHired, $deptId, $posId, $status,);
        
        if ($stmt->execute()) {
            $message = 'Employee added successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $empId = sanitize($_POST['employee_id']);
        $firstName = sanitize($_POST['first_name']);
        $middleName = sanitize($_POST['middle_name']);
        $lastName = sanitize($_POST['last_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        $dateHired = $_POST['date_hired'] ?: null;
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $posId = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
        $status = sanitize($_POST['employment_status']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE employees SET employee_id = ?, first_name = ?, middle_name = ?, last_name = ?, email = ?, phone = ?, address = ?, date_hired = ?, department_id = ?, position_id = ?, employment_status = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("ssssssssisisi", $empId, $firstName, $middleName, $lastName, $email, $phone, $address, $dateHired, $deptId, $posId, $status, $isActive, $id);
        
        if ($stmt->execute()) {
            $message = 'Employee updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        // Check if employee has payroll records
        $check = $conn->query("SELECT COUNT(*) as count FROM payroll WHERE employee_id = $id");
        $count = $check->fetch_assoc()['count'];
        
        if ($count > 0) {
            $message = "Cannot delete employee. They have $count payroll record(s). Consider deactivating instead.";
            $messageType = 'warning';
        } else {
            $stmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = 'Employee deleted successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
}

// Get filter parameters
$filterDept = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$filterStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build query with filters
$whereClause = "1=1";
if ($filterDept > 0) {
    $whereClause .= " AND e.department_id = $filterDept";
}
if ($filterStatus !== '') {
    $whereClause .= " AND e.is_active = " . ($filterStatus === 'active' ? 1 : 0);
}

// Get all departments for dropdown
$departments = $conn->query("SELECT id, department_name, department_code FROM departments ORDER BY department_name");

// Get all positions for dropdown
$positions = $conn->query("SELECT id, position_title, salary_grade, basic_salary FROM positions ORDER BY salary_grade, position_title");

// Get all employees
$employees = $conn->query("
    SELECT e.*, d.department_name, d.department_code, p.position_title, p.salary_grade, p.basic_salary
    FROM employees e 
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE $whereClause
    ORDER BY e.last_name, e.first_name
");

require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <span>Employees</span>
    </div>
    <h1 class="page-title">Employees</h1>
    <p class="page-subtitle">Manage employee records</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <i class="alert-icon fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
        <div class="alert-content"><?php echo $message; ?></div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-users"></i>
            Employee List
        </h2>
        <div class="btn-group">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control" placeholder="Search employees..." data-search-table="employeesTable">
            </div>
            <button class="btn btn-primary" onclick="Modal.open('addEmployeeModal')">
                <i class="fas fa-plus"></i> Add Employee
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card-body" style="padding: var(--space-md) var(--space-xl); border-bottom: 1px solid var(--gray-100);">
        <form method="GET" class="filter-bar">
            <select name="department" class="form-control" style="width: auto; min-width: 200px;" onchange="this.form.submit()">
                <option value="0">All Departments</option>
                <?php 
                $departments->data_seek(0);
                while($dept = $departments->fetch_assoc()): 
                ?>
                    <option value="<?php echo $dept['id']; ?>" <?php echo $filterDept == $dept['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept['department_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <?php if ($filterDept > 0 || $filterStatus !== ''): ?>
                <a href="employees.php" class="btn btn-secondary btn-sm">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="data-table" id="employeesTable">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>ID</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Basic Salary</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($employees && $employees->num_rows > 0): ?>
                        <?php while($row = $employees->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="employee-name">
                                                <?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . ($row['middle_name'] ? substr($row['middle_name'], 0, 1) . '.' : '')); ?>
                                            </div>
                                            <div class="employee-id"><?php echo htmlspecialchars($row['email'] ?: '-'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?php echo htmlspecialchars($row['employee_id']); ?></code></td>
                                <td>
                                    <?php if ($row['department_name']): ?>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($row['department_code']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['position_title']): ?>
                                        <?php echo htmlspecialchars($row['position_title']); ?>
                                        <br><small class="text-muted">SG-<?php echo $row['salary_grade']; ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="currency"><?php echo $row['basic_salary'] ? formatCurrency($row['basic_salary']) : '-'; ?></td>
                                <td>
                                    <?php if ($row['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                    <br><small class="text-muted"><?php echo $row['employment_status']; ?></small>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-secondary btn-icon sm" 
                                                onclick="editEmployee(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                                title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="payroll_create.php?employee=<?php echo $row['id']; ?>" 
                                           class="btn btn-success btn-icon sm" title="Create Payroll">
                                            <i class="fas fa-file-invoice-dollar"></i>
                                        </a>
                                        <button class="btn btn-danger btn-icon sm" 
                                                onclick="deleteEmployee(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>')"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted" style="padding: 2rem;">
                                No employees found. Add your first employee above.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Employee Modal -->
<div class="modal-overlay" id="addEmployeeModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">Add New Employee</h3>
            <button class="modal-close" onclick="Modal.close('addEmployeeModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="addEmployeeForm">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Employee ID</label>
                        <input type="text" name="employee_id" class="form-control" required placeholder="e.g., EMP-2025-001">
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Employment Status</label>
                        <select name="employment_status" class="form-control" required>
                            <option value="Regular">Regular</option>
                            <option value="Contractual">Contractual</option>
                            <option value="Job Order">Job Order</option>
                            <option value="Casual">Casual</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">First Name</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="middle_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Last Name</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="email@example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="+63 XXX XXX XXXX">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date Hired</label>
                        <input type="date" name="date_hired" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department_id" class="form-control">
                            <option value="">-- Select Department --</option>
                            <?php 
                            $departments->data_seek(0);
                            while($dept = $departments->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    [<?php echo htmlspecialchars($dept['department_code']); ?>] <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <select name="position_id" class="form-control">
                            <option value="">-- Select Position --</option>
                            <?php 
                            $positions->data_seek(0);
                            while($pos = $positions->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $pos['id']; ?>">
                                    [SG-<?php echo $pos['salary_grade']; ?>] <?php echo htmlspecialchars($pos['position_title']); ?> - <?php echo formatCurrency($pos['basic_salary']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="Modal.close('addEmployeeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Employee
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal-overlay" id="editEmployeeModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">Edit Employee</h3>
            <button class="modal-close" onclick="Modal.close('editEmployeeModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="editEmployeeForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Employee ID</label>
                        <input type="text" name="employee_id" id="edit_employee_id" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Employment Status</label>
                        <select name="employment_status" id="edit_employment_status" class="form-control" required>
                            <option value="Regular">Regular</option>
                            <option value="Contractual">Contractual</option>
                            <option value="Job Order">Job Order</option>
                            <option value="Casual">Casual</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">First Name</label>
                        <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="middle_name" id="edit_middle_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Last Name</label>
                        <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date Hired</label>
                        <input type="date" name="date_hired" id="edit_date_hired" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department_id" id="edit_department_id" class="form-control">
                            <option value="">-- Select Department --</option>
                            <?php 
                            $departments->data_seek(0);
                            while($dept = $departments->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    [<?php echo htmlspecialchars($dept['department_code']); ?>] <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <select name="position_id" id="edit_position_id" class="form-control">
                            <option value="">-- Select Position --</option>
                            <?php 
                            $positions->data_seek(0);
                            while($pos = $positions->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $pos['id']; ?>">
                                    [SG-<?php echo $pos['salary_grade']; ?>] <?php echo htmlspecialchars($pos['position_title']); ?> - <?php echo formatCurrency($pos['basic_salary']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <span>Active Employee</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="Modal.close('editEmployeeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Employee
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
function editEmployee(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_employee_id').value = data.employee_id;
    document.getElementById('edit_first_name').value = data.first_name;
    document.getElementById('edit_middle_name').value = data.middle_name || '';
    document.getElementById('edit_last_name').value = data.last_name;
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_phone').value = data.phone || '';
    document.getElementById('edit_address').value = data.address || '';
    document.getElementById('edit_date_hired').value = data.date_hired || '';
    document.getElementById('edit_department_id').value = data.department_id || '';
    document.getElementById('edit_position_id').value = data.position_id || '';
    document.getElementById('edit_employment_status').value = data.employment_status;
    document.getElementById('edit_is_active').checked = data.is_active == 1;
    Modal.open('editEmployeeModal');
}

function deleteEmployee(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
