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
        $title = sanitize($_POST['position_title']);
        $grade = (int)$_POST['salary_grade'];
        $salary = (float)$_POST['basic_salary'];
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        
        $stmt = $conn->prepare("INSERT INTO positions (position_title, salary_grade, basic_salary, department_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sidi", $title, $grade, $salary, $deptId);
        
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
        $title = sanitize($_POST['position_title']);
        $grade = (int)$_POST['salary_grade'];
        $salary = (float)$_POST['basic_salary'];
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        
        $stmt = $conn->prepare("UPDATE positions SET position_title = ?, salary_grade = ?, basic_salary = ?, department_id = ? WHERE id = ?");
        $stmt->bind_param("sidii", $title, $grade, $salary, $deptId, $id);
        
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

// Get all departments for dropdown
$departments = $conn->query("SELECT id, department_name, department_code FROM departments ORDER BY department_name");

// Get all positions with department and employee count
$positions = $conn->query("
    SELECT p.*, d.department_name, d.department_code, COUNT(e.id) as employee_count 
    FROM positions p 
    LEFT JOIN departments d ON p.department_id = d.id
    LEFT JOIN employees e ON p.id = e.position_id AND e.is_active = 1
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
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="data-table" id="positionsTable">
                <thead>
                    <tr>
                        <th>SG</th>
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
                                <td><span class="badge badge-info">SG-<?php echo $row['salary_grade']; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($row['position_title']); ?></strong></td>
                                <td>
                                    <?php if ($row['department_name']): ?>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($row['department_code']); ?></span>
                                        <?php echo htmlspecialchars($row['department_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
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
                            <td colspan="6" class="text-center text-muted" style="padding: 2rem;">
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
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Position Title</label>
                        <input type="text" name="position_title" class="form-control" required placeholder="e.g., Administrative Officer I">
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Salary Grade</label>
                        <input type="number" name="salary_grade" class="form-control" required min="1" max="33" placeholder="e.g., 15">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label required">Basic Salary (Monthly)</label>
                    <input type="number" name="basic_salary" class="form-control" required min="0" step="0.01" placeholder="e.g., 35000.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-control">
                        <option value="">-- Select Department (Optional) --</option>
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
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Position Title</label>
                        <input type="text" name="position_title" id="edit_title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Salary Grade</label>
                        <input type="number" name="salary_grade" id="edit_grade" class="form-control" required min="1" max="33">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label required">Basic Salary (Monthly)</label>
                    <input type="number" name="basic_salary" id="edit_salary" class="form-control" required min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <select name="department_id" id="edit_department" class="form-control">
                        <option value="">-- Select Department (Optional) --</option>
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
function editPosition(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_title').value = data.position_title;
    document.getElementById('edit_grade').value = data.salary_grade;
    document.getElementById('edit_salary').value = data.basic_salary;
    document.getElementById('edit_department').value = data.department_id || '';
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
