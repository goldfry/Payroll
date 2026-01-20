<?php
/**
 * Payroll System - Positions Management
 */

require_once 'includes/config.php';

$pageTitle = 'Positions';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Handle custom position title
        $title = '';
        if (!empty($_POST['position_title']) && $_POST['position_title'] === 'Custom') {
            $title = sanitize($_POST['custom_position_title']);
        } else {
            $title = sanitize($_POST['position_title']);
        }
        
        $grade = (int)$_POST['salary_grade'];
        $step = (int)$_POST['salary_step'];
        $salary = (float)$_POST['basic_salary'];
        $salaryId = !empty($_POST['salary_id']) ? (int)$_POST['salary_id'] : null;
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        
        $stmt = $conn->prepare("INSERT INTO positions (position_title, salary_grade, salary_step, basic_salary, salary_id, department_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siidii", $title, $grade, $step, $salary, $salaryId, $deptId);
        
        if ($stmt->execute()) {
            $message = 'Position added successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        
        // Handle custom position title
        $title = '';
        if (!empty($_POST['position_title']) && $_POST['position_title'] === 'Custom') {
            $title = sanitize($_POST['custom_position_title']);
        } else {
            $title = sanitize($_POST['position_title']);
        }
        
        $grade = (int)$_POST['salary_grade'];
        $step = (int)$_POST['salary_step'];
        $salary = (float)$_POST['basic_salary'];
        $salaryId = !empty($_POST['salary_id']) ? (int)$_POST['salary_id'] : null;
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        
        $stmt = $conn->prepare("UPDATE positions SET position_title = ?, salary_grade = ?, salary_step = ?, basic_salary = ?, salary_id = ?, department_id = ? WHERE id = ?");
        $stmt->bind_param("siidiii", $title, $grade, $step, $salary, $salaryId, $deptId, $id);
        
        if ($stmt->execute()) {
            $message = 'Position updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        // Check if position has employees
        $check = $conn->query("SELECT COUNT(*) as count FROM employees WHERE position_id = $id");
        $count = $check->fetch_assoc()['count'];
        
        if ($count > 0) {
            $message = "Cannot delete position. It has $count employee(s) assigned.";
            $messageType = 'warning';
        } else {
            $stmt = $conn->prepare("DELETE FROM positions WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = 'Position deleted successfully!';
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

// Build query with filters
$whereClause = "1=1";
if ($filterDept > 0) {
    $whereClause .= " AND p.department_id = $filterDept";
}

// Get all salary grades for dropdown
$salaryGrades = $conn->query("SELECT * FROM step_inc ORDER BY salary_grade");

// Get all departments for dropdown
$departments = $conn->query("SELECT id, department_name, department_code FROM departments ORDER BY department_name");

// Get all positions with department and employee count
$positions = $conn->query("
    SELECT p.*, d.department_name, d.department_code, 
           s.id as step_inc_id, COUNT(e.id) as employee_count 
    FROM positions p 
    LEFT JOIN departments d ON p.department_id = d.id
    LEFT JOIN step_inc s ON p.salary_grade = s.salary_grade
    LEFT JOIN employees e ON p.id = e.position_id AND e.is_active = 1
    WHERE $whereClause
    GROUP BY p.id 
    ORDER BY p.salary_grade, p.position_title
");

require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <span>Positions</span>
    </div>
    <h1 class="page-title">Positions</h1>
    <p class="page-subtitle">Manage job positions and salary grades</p>
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
            <i class="fas fa-briefcase"></i>
            Position List
        </h2>
        <div class="btn-group">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control" placeholder="Search positions..." data-search-table="positionsTable">
            </div>
            <button class="btn btn-primary" onclick="Modal.open('addPositionModal')">
                <i class="fas fa-plus"></i> Add Position
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
            <?php if ($filterDept > 0): ?>
                <a href="positions.php" class="btn btn-secondary btn-sm">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="data-table" id="positionsTable">
                <thead>
                    <tr>
                        <th>Position ID</th>
                        <th>SG</th>
                        <th>Step</th>
                        <th>Position Title</th>
                        <th>Department</th>
                        <th>Basic Salary</th>
                        <th>Employees</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($positions && $positions->num_rows > 0): ?>
                        <?php while($row = $positions->fetch_assoc()): ?>
                            <tr>
                                <td><code>POS-<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></code></td>
                                <td><span class="badge badge-info">SG-<?php echo $row['salary_grade']; ?></span></td>
                                <td><span class="badge badge-secondary">Step <?php echo $row['salary_step'] ?? 1; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($row['position_title']); ?></strong></td>
                                <td>
                                    <?php if ($row['department_name']): ?>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($row['department_code']); ?></span>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($row['department_name']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">No Department</span>
                                    <?php endif; ?>
                                </td>
                                <td class="currency"><?php echo formatCurrency($row['basic_salary']); ?></td>
                                <td>
                                    <span class="badge <?php echo $row['employee_count'] > 0 ? 'badge-success' : 'badge-warning'; ?>">
                                        <?php echo $row['employee_count']; ?> staff
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-secondary btn-icon sm" 
                                                onclick="editPosition(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                                title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-icon sm" 
                                                onclick="deletePosition(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['position_title']); ?>')"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted" style="padding: 2rem;">
                                No positions found. Add your first position above.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Position Modal -->
<div class="modal-overlay" id="addPositionModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Add New Position</h3>
            <button class="modal-close" onclick="Modal.close('addPositionModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="addPositionForm">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="basic_salary" id="add_basic_salary">
            <input type="hidden" name="salary_id" id="add_salary_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Position Title</label>
                    <select name="position_title" id="add_position_title" class="form-control" required onchange="toggleCustomPosition('add')">
                        <option value="">-- Select Position Title --</option>
                        <optgroup label="SG 1-4 (Entry Level)">
                            <option value="Utility Worker I">Utility Worker I</option>
                            <option value="Utility Worker II">Utility Worker II</option>
                            <option value="Laborer I">Laborer I</option>
                            <option value="Laborer II">Laborer II</option>
                            <option value="Messenger">Messenger</option>
                            <option value="Helper">Helper</option>
                        </optgroup>
                        <optgroup label="SG 5-8 (Clerical/Technical)">
                            <option value="Clerk I">Clerk I</option>
                            <option value="Clerk II">Clerk II</option>
                            <option value="Clerk III">Clerk III</option>
                            <option value="Clerk IV">Clerk IV</option>
                            <option value="Administrative Aide I">Administrative Aide I</option>
                            <option value="Administrative Aide II">Administrative Aide II</option>
                            <option value="Administrative Aide III">Administrative Aide III</option>
                            <option value="Administrative Aide IV">Administrative Aide IV</option>
                            <option value="Administrative Aide V">Administrative Aide V</option>
                            <option value="Administrative Aide VI">Administrative Aide VI</option>
                            <option value="Driver I">Driver I</option>
                            <option value="Driver II">Driver II</option>
                            <option value="Security Guard I">Security Guard I</option>
                            <option value="Security Guard II">Security Guard II</option>
                        </optgroup>
                        <optgroup label="SG 9-11 (Administrative Assistant)">
                            <option value="Administrative Assistant I">Administrative Assistant I</option>
                            <option value="Administrative Assistant II">Administrative Assistant II</option>
                            <option value="Administrative Assistant III">Administrative Assistant III</option>
                            <option value="Bookkeeper I">Bookkeeper I</option>
                            <option value="Bookkeeper II">Bookkeeper II</option>
                            <option value="Cashier I">Cashier I</option>
                            <option value="Cashier II">Cashier II</option>
                            <option value="Computer Operator I">Computer Operator I</option>
                            <option value="Computer Operator II">Computer Operator II</option>
                        </optgroup>
                        <optgroup label="SG 12-15 (Administrative Officer/Specialist)">
                            <option value="Administrative Officer I">Administrative Officer I</option>
                            <option value="Administrative Officer II">Administrative Officer II</option>
                            <option value="Administrative Officer III">Administrative Officer III</option>
                            <option value="Administrative Officer IV">Administrative Officer IV</option>
                            <option value="Accountant I">Accountant I</option>
                            <option value="Accountant II">Accountant II</option>
                            <option value="Accountant III">Accountant III</option>
                            <option value="Budget Officer I">Budget Officer I</option>
                            <option value="Budget Officer II">Budget Officer II</option>
                            <option value="Budget Officer III">Budget Officer III</option>
                            <option value="Engineer I">Engineer I</option>
                            <option value="Engineer II">Engineer II</option>
                            <option value="Engineer III">Engineer III</option>
                            <option value="Planning Officer I">Planning Officer I</option>
                            <option value="Planning Officer II">Planning Officer II</option>
                            <option value="Planning Officer III">Planning Officer III</option>
                            <option value="Legal Officer I">Legal Officer I</option>
                            <option value="Legal Officer II">Legal Officer II</option>
                            <option value="Legal Officer III">Legal Officer III</option>
                        </optgroup>
                        <optgroup label="SG 16-18 (Assistant/Associate Positions)">
                            <option value="Administrative Officer V">Administrative Officer V</option>
                            <option value="Accountant IV">Accountant IV</option>
                            <option value="Engineer IV">Engineer IV</option>
                            <option value="Planning Officer IV">Planning Officer IV</option>
                            <option value="Legal Officer IV">Legal Officer IV</option>
                            <option value="Budget Officer IV">Budget Officer IV</option>
                            <option value="Assistant Department Head">Assistant Department Head</option>
                        </optgroup>
                        <optgroup label="SG 19-22 (Division Chief/Supervising)">
                            <option value="Division Chief">Division Chief</option>
                            <option value="Supervising Administrative Officer">Supervising Administrative Officer</option>
                            <option value="Supervising Accountant">Supervising Accountant</option>
                            <option value="Supervising Engineer">Supervising Engineer</option>
                            <option value="Supervising Planning Officer">Supervising Planning Officer</option>
                            <option value="Chief Administrative Officer">Chief Administrative Officer</option>
                            <option value="Chief Accountant">Chief Accountant</option>
                            <option value="Chief Engineer">Chief Engineer</option>
                        </optgroup>
                        <optgroup label="SG 23-24 (Department Head/Manager)">
                            <option value="Department Head">Department Head</option>
                            <option value="Department Manager">Department Manager</option>
                            <option value="Assistant City/Municipal Administrator">Assistant City/Municipal Administrator</option>
                            <option value="City/Municipal Administrator">City/Municipal Administrator</option>
                        </optgroup>
                        <optgroup label="SG 25-26 (Director/Senior Management)">
                            <option value="Director I">Director I</option>
                            <option value="Director II">Director II</option>
                            <option value="Director III">Director III</option>
                            <option value="Director IV">Director IV</option>
                        </optgroup>
                        <optgroup label="SG 27-30 (Executive/Top Management)">
                            <option value="Assistant Secretary">Assistant Secretary</option>
                            <option value="Undersecretary">Undersecretary</option>
                            <option value="Executive Director">Executive Director</option>
                        </optgroup>
                        <optgroup label="Elective Officials">
                            <option value="Barangay Kagawad">Barangay Kagawad</option>
                            <option value="Barangay Captain">Barangay Captain</option>
                            <option value="Sangguniang Bayan Member">Sangguniang Bayan Member</option>
                            <option value="Sangguniang Panlungsod Member">Sangguniang Panlungsod Member</option>
                            <option value="Vice Mayor">Vice Mayor</option>
                            <option value="Mayor">Mayor</option>
                        </optgroup>
                        <optgroup label="Education Positions">
                            <option value="Teacher I">Teacher I</option>
                            <option value="Teacher II">Teacher II</option>
                            <option value="Teacher III">Teacher III</option>
                            <option value="Master Teacher I">Master Teacher I</option>
                            <option value="Master Teacher II">Master Teacher II</option>
                            <option value="Master Teacher III">Master Teacher III</option>
                            <option value="Master Teacher IV">Master Teacher IV</option>
                            <option value="Head Teacher I">Head Teacher I</option>
                            <option value="Head Teacher II">Head Teacher II</option>
                            <option value="Head Teacher III">Head Teacher III</option>
                            <option value="Principal I">Principal I</option>
                            <option value="Principal II">Principal II</option>
                            <option value="Principal III">Principal III</option>
                            <option value="Principal IV">Principal IV</option>
                            <option value="School Superintendent">School Superintendent</option>
                        </optgroup>
                        <optgroup label="Health Positions">
                            <option value="Nurse I">Nurse I</option>
                            <option value="Nurse II">Nurse II</option>
                            <option value="Nurse III">Nurse III</option>
                            <option value="Medical Officer I">Medical Officer I</option>
                            <option value="Medical Officer II">Medical Officer II</option>
                            <option value="Medical Officer III">Medical Officer III</option>
                            <option value="Medical Officer IV">Medical Officer IV</option>
                            <option value="Dentist I">Dentist I</option>
                            <option value="Dentist II">Dentist II</option>
                            <option value="Dentist III">Dentist III</option>
                            <option value="Public Health Nurse I">Public Health Nurse I</option>
                            <option value="Public Health Nurse II">Public Health Nurse II</option>
                            <option value="Midwife I">Midwife I</option>
                            <option value="Midwife II">Midwife II</option>
                            <option value="Medical Technologist I">Medical Technologist I</option>
                            <option value="Medical Technologist II">Medical Technologist II</option>
                        </optgroup>
                        <optgroup label="Other Positions">
                            <option value="Social Welfare Officer I">Social Welfare Officer I</option>
                            <option value="Social Welfare Officer II">Social Welfare Officer II</option>
                            <option value="Social Welfare Officer III">Social Welfare Officer III</option>
                            <option value="Agriculturist I">Agriculturist I</option>
                            <option value="Agriculturist II">Agriculturist II</option>
                            <option value="Veterinarian I">Veterinarian I</option>
                            <option value="Veterinarian II">Veterinarian II</option>
                            <option value="Librarian I">Librarian I</option>
                            <option value="Librarian II">Librarian II</option>
                            <option value="Information Officer I">Information Officer I</option>
                            <option value="Information Officer II">Information Officer II</option>
                            <option value="Custom">-- Type Custom Position --</option>
                        </optgroup>
                    </select>
                    <input type="text" id="add_custom_position" name="custom_position_title" class="form-control" style="display: none; margin-top: 10px;" placeholder="Enter custom position title">
                </div>
                
                <div class="form-group">
                    <label class="form-label required">Department</label>
                    <select name="department_id" class="form-control" required>
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
                    <small class="text-muted">Department assignment is now required for better organization</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Salary Grade</label>
                        <select name="salary_grade" id="add_salary_grade" class="form-control" required onchange="updateSalaryAdd()">
                            <option value="">-- Select Salary Grade --</option>
                            <?php 
                            $salaryGrades->data_seek(0);
                            while($sg = $salaryGrades->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $sg['salary_grade']; ?>" 
                                        data-id="<?php echo $sg['id']; ?>"
                                        data-step1="<?php echo $sg['step_1']; ?>"
                                        data-step2="<?php echo $sg['step_2']; ?>"
                                        data-step3="<?php echo $sg['step_3']; ?>"
                                        data-step4="<?php echo $sg['step_4']; ?>"
                                        data-step5="<?php echo $sg['step_5']; ?>"
                                        data-step6="<?php echo $sg['step_6']; ?>"
                                        data-step7="<?php echo $sg['step_7']; ?>"
                                        data-step8="<?php echo $sg['step_8']; ?>">
                                    SG-<?php echo $sg['salary_grade']; ?> (₱<?php echo number_format($sg['step_1'], 2); ?> - ₱<?php echo number_format($sg['step_8'], 2); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Salary Step</label>
                        <select name="salary_step" id="add_salary_step" class="form-control" required onchange="updateSalaryAdd()">
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
                    <label class="form-label">Basic Salary (Monthly)</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="text" id="add_salary_display" class="form-control" readonly style="background-color: #f0f0f0; font-weight: bold;" placeholder="Select SG and Step first">
                    </div>
                    <small class="text-muted">Salary is automatically calculated based on the selected grade and step</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="Modal.close('addPositionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Position
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Position Modal -->
<div class="modal-overlay" id="editPositionModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Edit Position</h3>
            <button class="modal-close" onclick="Modal.close('editPositionModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="editPositionForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="basic_salary" id="edit_basic_salary">
            <input type="hidden" name="salary_id" id="edit_salary_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Position Title</label>
                    <select name="position_title" id="edit_position_title" class="form-control" required onchange="toggleCustomPosition('edit')">
                        <option value="">-- Select Position Title --</option>
                        <optgroup label="SG 1-4 (Entry Level)">
                            <option value="Utility Worker I">Utility Worker I</option>
                            <option value="Utility Worker II">Utility Worker II</option>
                            <option value="Laborer I">Laborer I</option>
                            <option value="Laborer II">Laborer II</option>
                            <option value="Messenger">Messenger</option>
                            <option value="Helper">Helper</option>
                        </optgroup>
                        <optgroup label="SG 5-8 (Clerical/Technical)">
                            <option value="Clerk I">Clerk I</option>
                            <option value="Clerk II">Clerk II</option>
                            <option value="Clerk III">Clerk III</option>
                            <option value="Clerk IV">Clerk IV</option>
                            <option value="Administrative Aide I">Administrative Aide I</option>
                            <option value="Administrative Aide II">Administrative Aide II</option>
                            <option value="Administrative Aide III">Administrative Aide III</option>
                            <option value="Administrative Aide IV">Administrative Aide IV</option>
                            <option value="Administrative Aide V">Administrative Aide V</option>
                            <option value="Administrative Aide VI">Administrative Aide VI</option>
                            <option value="Driver I">Driver I</option>
                            <option value="Driver II">Driver II</option>
                            <option value="Security Guard I">Security Guard I</option>
                            <option value="Security Guard II">Security Guard II</option>
                        </optgroup>
                        <optgroup label="SG 9-11 (Administrative Assistant)">
                            <option value="Administrative Assistant I">Administrative Assistant I</option>
                            <option value="Administrative Assistant II">Administrative Assistant II</option>
                            <option value="Administrative Assistant III">Administrative Assistant III</option>
                            <option value="Bookkeeper I">Bookkeeper I</option>
                            <option value="Bookkeeper II">Bookkeeper II</option>
                            <option value="Cashier I">Cashier I</option>
                            <option value="Cashier II">Cashier II</option>
                            <option value="Computer Operator I">Computer Operator I</option>
                            <option value="Computer Operator II">Computer Operator II</option>
                        </optgroup>
                        <optgroup label="SG 12-15 (Administrative Officer/Specialist)">
                            <option value="Administrative Officer I">Administrative Officer I</option>
                            <option value="Administrative Officer II">Administrative Officer II</option>
                            <option value="Administrative Officer III">Administrative Officer III</option>
                            <option value="Administrative Officer IV">Administrative Officer IV</option>
                            <option value="Accountant I">Accountant I</option>
                            <option value="Accountant II">Accountant II</option>
                            <option value="Accountant III">Accountant III</option>
                            <option value="Budget Officer I">Budget Officer I</option>
                            <option value="Budget Officer II">Budget Officer II</option>
                            <option value="Budget Officer III">Budget Officer III</option>
                            <option value="Engineer I">Engineer I</option>
                            <option value="Engineer II">Engineer II</option>
                            <option value="Engineer III">Engineer III</option>
                            <option value="Planning Officer I">Planning Officer I</option>
                            <option value="Planning Officer II">Planning Officer II</option>
                            <option value="Planning Officer III">Planning Officer III</option>
                            <option value="Legal Officer I">Legal Officer I</option>
                            <option value="Legal Officer II">Legal Officer II</option>
                            <option value="Legal Officer III">Legal Officer III</option>
                        </optgroup>
                        <optgroup label="SG 16-18 (Assistant/Associate Positions)">
                            <option value="Administrative Officer V">Administrative Officer V</option>
                            <option value="Accountant IV">Accountant IV</option>
                            <option value="Engineer IV">Engineer IV</option>
                            <option value="Planning Officer IV">Planning Officer IV</option>
                            <option value="Legal Officer IV">Legal Officer IV</option>
                            <option value="Budget Officer IV">Budget Officer IV</option>
                            <option value="Assistant Department Head">Assistant Department Head</option>
                        </optgroup>
                        <optgroup label="SG 19-22 (Division Chief/Supervising)">
                            <option value="Division Chief">Division Chief</option>
                            <option value="Supervising Administrative Officer">Supervising Administrative Officer</option>
                            <option value="Supervising Accountant">Supervising Accountant</option>
                            <option value="Supervising Engineer">Supervising Engineer</option>
                            <option value="Supervising Planning Officer">Supervising Planning Officer</option>
                            <option value="Chief Administrative Officer">Chief Administrative Officer</option>
                            <option value="Chief Accountant">Chief Accountant</option>
                            <option value="Chief Engineer">Chief Engineer</option>
                        </optgroup>
                        <optgroup label="SG 23-24 (Department Head/Manager)">
                            <option value="Department Head">Department Head</option>
                            <option value="Department Manager">Department Manager</option>
                            <option value="Assistant City/Municipal Administrator">Assistant City/Municipal Administrator</option>
                            <option value="City/Municipal Administrator">City/Municipal Administrator</option>
                        </optgroup>
                        <optgroup label="SG 25-26 (Director/Senior Management)">
                            <option value="Director I">Director I</option>
                            <option value="Director II">Director II</option>
                            <option value="Director III">Director III</option>
                            <option value="Director IV">Director IV</option>
                        </optgroup>
                        <optgroup label="SG 27-30 (Executive/Top Management)">
                            <option value="Assistant Secretary">Assistant Secretary</option>
                            <option value="Undersecretary">Undersecretary</option>
                            <option value="Executive Director">Executive Director</option>
                        </optgroup>
                        <optgroup label="Elective Officials">
                            <option value="Barangay Kagawad">Barangay Kagawad</option>
                            <option value="Barangay Captain">Barangay Captain</option>
                            <option value="Sangguniang Bayan Member">Sangguniang Bayan Member</option>
                            <option value="Sangguniang Panlungsod Member">Sangguniang Panlungsod Member</option>
                            <option value="Vice Mayor">Vice Mayor</option>
                            <option value="Mayor">Mayor</option>
                        </optgroup>
                        <optgroup label="Education Positions">
                            <option value="Teacher I">Teacher I</option>
                            <option value="Teacher II">Teacher II</option>
                            <option value="Teacher III">Teacher III</option>
                            <option value="Master Teacher I">Master Teacher I</option>
                            <option value="Master Teacher II">Master Teacher II</option>
                            <option value="Master Teacher III">Master Teacher III</option>
                            <option value="Master Teacher IV">Master Teacher IV</option>
                            <option value="Head Teacher I">Head Teacher I</option>
                            <option value="Head Teacher II">Head Teacher II</option>
                            <option value="Head Teacher III">Head Teacher III</option>
                            <option value="Principal I">Principal I</option>
                            <option value="Principal II">Principal II</option>
                            <option value="Principal III">Principal III</option>
                            <option value="Principal IV">Principal IV</option>
                            <option value="School Superintendent">School Superintendent</option>
                        </optgroup>
                        <optgroup label="Health Positions">
                            <option value="Nurse I">Nurse I</option>
                            <option value="Nurse II">Nurse II</option>
                            <option value="Nurse III">Nurse III</option>
                            <option value="Medical Officer I">Medical Officer I</option>
                            <option value="Medical Officer II">Medical Officer II</option>
                            <option value="Medical Officer III">Medical Officer III</option>
                            <option value="Medical Officer IV">Medical Officer IV</option>
                            <option value="Dentist I">Dentist I</option>
                            <option value="Dentist II">Dentist II</option>
                            <option value="Dentist III">Dentist III</option>
                            <option value="Public Health Nurse I">Public Health Nurse I</option>
                            <option value="Public Health Nurse II">Public Health Nurse II</option>
                            <option value="Midwife I">Midwife I</option>
                            <option value="Midwife II">Midwife II</option>
                            <option value="Medical Technologist I">Medical Technologist I</option>
                            <option value="Medical Technologist II">Medical Technologist II</option>
                        </optgroup>
                        <optgroup label="Other Positions">
                            <option value="Social Welfare Officer I">Social Welfare Officer I</option>
                            <option value="Social Welfare Officer II">Social Welfare Officer II</option>
                            <option value="Social Welfare Officer III">Social Welfare Officer III</option>
                            <option value="Agriculturist I">Agriculturist I</option>
                            <option value="Agriculturist II">Agriculturist II</option>
                            <option value="Veterinarian I">Veterinarian I</option>
                            <option value="Veterinarian II">Veterinarian II</option>
                            <option value="Librarian I">Librarian I</option>
                            <option value="Librarian II">Librarian II</option>
                            <option value="Information Officer I">Information Officer I</option>
                            <option value="Information Officer II">Information Officer II</option>
                            <option value="Custom">-- Type Custom Position --</option>
                        </optgroup>
                    </select>
                    <input type="text" id="edit_custom_position" name="custom_position_title" class="form-control" style="display: none; margin-top: 10px;" placeholder="Enter custom position title">
                </div>
                
                <div class="form-group">
                    <label class="form-label required">Department</label>
                    <select name="department_id" id="edit_department" class="form-control" required>
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
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Salary Grade</label>
                        <select name="salary_grade" id="edit_salary_grade" class="form-control" required onchange="updateSalaryEdit()">
                            <option value="">-- Select Salary Grade --</option>
                            <?php 
                            $salaryGrades->data_seek(0);
                            while($sg = $salaryGrades->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $sg['salary_grade']; ?>" 
                                        data-id="<?php echo $sg['id']; ?>"
                                        data-step1="<?php echo $sg['step_1']; ?>"
                                        data-step2="<?php echo $sg['step_2']; ?>"
                                        data-step3="<?php echo $sg['step_3']; ?>"
                                        data-step4="<?php echo $sg['step_4']; ?>"
                                        data-step5="<?php echo $sg['step_5']; ?>"
                                        data-step6="<?php echo $sg['step_6']; ?>"
                                        data-step7="<?php echo $sg['step_7']; ?>"
                                        data-step8="<?php echo $sg['step_8']; ?>">
                                    SG-<?php echo $sg['salary_grade']; ?> (₱<?php echo number_format($sg['step_1'], 2); ?> - ₱<?php echo number_format($sg['step_8'], 2); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Salary Step</label>
                        <select name="salary_step" id="edit_salary_step" class="form-control" required onchange="updateSalaryEdit()">
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
                    <label class="form-label">Basic Salary (Monthly)</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="text" id="edit_salary_display" class="form-control" readonly style="background-color: #f0f0f0; font-weight: bold;">
                    </div>
                    <small class="text-muted">Salary is automatically calculated based on the selected grade and step</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="Modal.close('editPositionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Position
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
function toggleCustomPosition(mode) {
    const select = document.getElementById(mode + '_position_title');
    const customInput = document.getElementById(mode + '_custom_position');
    
    if (select.value === 'Custom') {
        customInput.style.display = 'block';
        customInput.required = true;
    } else {
        customInput.style.display = 'none';
        customInput.required = false;
        customInput.value = '';
    }
}

function updateSalaryAdd() {
    const gradeSelect = document.getElementById('add_salary_grade');
    const stepSelect = document.getElementById('add_salary_step');
    const salaryInput = document.getElementById('add_basic_salary');
    const salaryDisplay = document.getElementById('add_salary_display');
    const salaryIdInput = document.getElementById('add_salary_id');
    
    const selectedOption = gradeSelect.options[gradeSelect.selectedIndex];
    const step = stepSelect.value;
    
    if (selectedOption.value && step) {
        const salary = selectedOption.getAttribute('data-step' + step);
        const salaryId = selectedOption.getAttribute('data-id');
        salaryInput.value = salary;
        salaryIdInput.value = salaryId;
        salaryDisplay.value = parseFloat(salary).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    } else {
        salaryInput.value = '';
        salaryIdInput.value = '';
        salaryDisplay.value = '';
    }
}

function updateSalaryEdit() {
    const gradeSelect = document.getElementById('edit_salary_grade');
    const stepSelect = document.getElementById('edit_salary_step');
    const salaryInput = document.getElementById('edit_basic_salary');
    const salaryDisplay = document.getElementById('edit_salary_display');
    const salaryIdInput = document.getElementById('edit_salary_id');
    
    const selectedOption = gradeSelect.options[gradeSelect.selectedIndex];
    const step = stepSelect.value;
    
    if (selectedOption.value && step) {
        const salary = selectedOption.getAttribute('data-step' + step);
        const salaryId = selectedOption.getAttribute('data-id');
        salaryInput.value = salary;
        salaryIdInput.value = salaryId;
        salaryDisplay.value = parseFloat(salary).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    } else {
        salaryInput.value = '';
        salaryIdInput.value = '';
        salaryDisplay.value = '';
    }
}

function editPosition(data) {
    document.getElementById('edit_id').value = data.id;
    
    // Check if the position title exists in the dropdown
    const editSelect = document.getElementById('edit_position_title');
    let optionExists = false;
    
    for (let i = 0; i < editSelect.options.length; i++) {
        if (editSelect.options[i].value === data.position_title) {
            optionExists = true;
            break;
        }
    }
    
    if (optionExists) {
        editSelect.value = data.position_title;
        document.getElementById('edit_custom_position').style.display = 'none';
    } else {
        editSelect.value = 'Custom';
        document.getElementById('edit_custom_position').style.display = 'block';
        document.getElementById('edit_custom_position').value = data.position_title;
    }
    
    document.getElementById('edit_salary_grade').value = data.salary_grade;
    document.getElementById('edit_salary_step').value = data.salary_step || 1;
    document.getElementById('edit_department').value = data.department_id || '';
    
    // Trigger salary update
    updateSalaryEdit();
    
    Modal.open('editPositionModal');
}

function deletePosition(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>