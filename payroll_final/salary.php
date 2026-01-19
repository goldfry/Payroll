<?php
/**
 * Payroll System - Salary Management
 */

require_once 'includes/config.php';

$pageTitle = 'Salary';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $salaryRate = floatval($_POST['salary_rate']);
        
        $stmt = $conn->prepare("INSERT INTO salary (salary_rate) VALUES (?)");
        $stmt->bind_param("d", $salaryRate);
        
        if ($stmt->execute()) {
            $message = 'Salary added successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['salary_id'];
        $salaryRate = floatval($_POST['salary_rate']);
        
        $stmt = $conn->prepare("UPDATE salary SET salary_rate = ? WHERE salary_id = ?");
        $stmt->bind_param("di", $salaryRate, $id);
        
        if ($stmt->execute()) {
            $message = 'Salary updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['salary_id'];
        
        $stmt = $conn->prepare("DELETE FROM salary WHERE salary_id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = 'Salary deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// Get all employees with their salary information
$query = "SELECT 
    e.id,
    e.employee_id,
    e.first_name,
    e.middle_name,
    e.last_name,
    d.department_name,
    p.position_title,
    p.basic_salary,
    e.employment_status,
    e.is_active
FROM employees e
LEFT JOIN departments d ON e.department_id = d.id
LEFT JOIN positions p ON e.position_id = p.id
ORDER BY e.last_name, e.first_name";

$result = $conn->query($query);

// Get total count
$totalQuery = "SELECT COUNT(*) as total FROM employees";
$totalResult = $conn->query($totalQuery);
$totalEmployees = $totalResult->fetch_assoc()['total'];

// Calculate total monthly salary
$salaryQuery = "SELECT SUM(p.basic_salary) as total_salary 
                FROM employees e 
                LEFT JOIN positions p ON e.position_id = p.id 
                WHERE e.is_active = 1";
$salaryResult = $conn->query($salaryQuery);
$totalSalary = $salaryResult->fetch_assoc()['total_salary'] ?? 0;

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <span>Salary</span>
    </div>
    <h1 class="page-title">Employee Salaries</h1>
    <p class="page-subtitle">View and manage employee salary information</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <i class="alert-icon fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
        <div class="alert-content"><?php echo $message; ?></div>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Total Employees</div>
            <div class="stat-value"><?php echo number_format($totalEmployees); ?></div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Total Monthly Salary</div>
            <div class="stat-value"><?php echo formatCurrency($totalSalary); ?></div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-money-bill-wave"></i>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Average Salary</div>
            <div class="stat-value"><?php echo formatCurrency($totalEmployees > 0 ? $totalSalary / $totalEmployees : 0); ?></div>
        </div>
        <div class="stat-icon">
            <i class="fas fa-calculator"></i>
        </div>
    </div>
</div>

<!-- Salary Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-table"></i>
            Employee Salary List
        </h2>
        <div class="btn-group">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control" id="tableSearch" placeholder="Search employees...">
            </div>
            <button class="btn btn-secondary no-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="data-table" id="salaryTable">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Employment Status</th>
                        <th>Basic Salary</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['employee_id']); ?></strong>
                            </td>
                            <td>
                                <?php 
                                $fullName = htmlspecialchars($row['last_name']) . ', ' . 
                                           htmlspecialchars($row['first_name']);
                                if (!empty($row['middle_name'])) {
                                    $fullName .= ' ' . htmlspecialchars(substr($row['middle_name'], 0, 1)) . '.';
                                }
                                echo $fullName;
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['department_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['position_title'] ?? '-'); ?></td>
                            <td>
                                <span class="badge badge-secondary">
                                    <?php echo htmlspecialchars($row['employment_status']); ?>
                                </span>
                            </td>
                            <td class="currency">
                                <?php echo formatCurrency($row['basic_salary'] ?? 0); ?>
                            </td>
                            <td>
                                <?php if ($row['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted" style="padding: 2rem;">
                                No employees found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('tableSearch').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const tableRows = document.querySelectorAll('#salaryTable tbody tr');
    
    tableRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    });
});
</script>

<style>
/* Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-content {
    flex: 1;
}

.stat-label {
    font-size: 0.875rem;
    color: #666;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 600;
    color: #333;
}

.stat-icon {
    font-size: 2rem;
    color: #999;
    opacity: 0.5;
}

/* Print styles */
@media print {
    .sidebar, 
    .page-header .breadcrumb, 
    .no-print,
    .search-box,
    .btn-group {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0 !important;
        padding: 1rem !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        page-break-inside: avoid;
    }
    
    .table {
        font-size: 0.875rem;
    }
    
    .badge {
        border: 1px solid currentColor;
    }
    
    .stat-card {
        border: 1px solid #ddd;
        page-break-inside: avoid;
    }
    
    .page-header {
        margin-bottom: 1rem;
    }
    
    .page-title {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }
}
</style>

<?php
include 'includes/footer.php';
?>