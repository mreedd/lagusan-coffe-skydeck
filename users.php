<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'includes/db_connect.php';

// Check if user is admin
if (!has_role('admin')) {
    redirect('dashboard.php');
}

$page_title = 'Users Management';

// Get all users
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");

include 'includes/header.php';
include 'includes/sidebar.php';
?>



<style>
.users-table {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.badge-role-admin {
    background: #96715e;
    color: white;
}

.badge-role-cashier {
    background: #d4a574;
    color: white;
}

.badge-role-staff {
    background: #c9a88a;
    color: white;
}

.badge-active {
    background: #d4edda;
    color: #155724;
}

.badge-inactive {
    background: #f8d7da;
    color: #721c24;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #333;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}

.btn-danger {
    color: #dc3545;
}

.btn-danger:hover {
    color: #c82333;
}

.btn-icon {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    padding: 4px 8px;
    transition: transform 0.2s;
}

.btn-icon:hover {
    opacity: 0.7;
}

/* Added complete modal styles for proper display */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    overflow: auto;
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #ddd;
}

.modal-header h2 {
    font-size: 20px;
    color: #333;
    margin: 0;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
    transition: color 0.3s;
}

.close:hover,
.close:focus {
    color: #333;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

/* Modal header actions */
.modal-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.scroll-btn {
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 30px;
    height: 30px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: #666;
    transition: all 0.3s;
}

.scroll-btn:hover {
    background: #e0e0e0;
    color: #333;
}

.scroll-btn:active {
    background: #d0d0d0;
}

.btn-secondary {
    background: #6c757d;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.3s;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-primary {
    background: #96715e;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.3s;
}

.btn-primary:hover {
    background: #7a5a4a;
}

.actions-bar {
    margin-bottom: 20px;
}

.btn {
    background: white;
    color: #333;
    padding: 10px 20px;
    border: 1px solid #ddd;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.3s;
}

.btn:hover {
    background: #f8f9fa;
}

.btn-secondary {
    background: #6c757d;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.3s;
}

.btn-secondary:hover {
    background: #5a6268;
}

.password-strength {
    display: block;
    margin-top: 6px;
    font-weight: 600;
    font-size: 13px;
}
.strength-weak { color: #d9534f; }
.strength-medium { color: #f0ad4e; }
.strength-strong { color: #5cb85c; }
/* Tablet styles: improve spacing and touch targets (768px - 1024px) */
@media (min-width: 768px) and (max-width: 1024px) {
    .users-table {
        padding: 24px;
        border-radius: 10px;
    }

    .form-group input,
    .form-group select {
        padding: 12px;
        font-size: 15px;
    }

    .btn, .btn-primary, .btn-secondary {
        padding: 14px 18px;
        font-size: 15px;
        border-radius: 8px;
    }

    .btn-icon {
        padding: 8px 12px;
        font-size: 20px;
    }

    .modal-content {
        max-width: 640px;
        width: 92%;
    }

    .modal-header {
        padding: 20px;
    }

    .modal-body {
        padding: 20px;
    }
}

</style>

<script>
function openAddUserModal() {
    document.getElementById('modalTitle').textContent = 'Add New User';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('password').required = true;
    document.getElementById('confirmPassword').required = true;
    document.getElementById('passwordStrength').textContent = '';
    document.getElementById('passwordStrength').className = 'password-strength';
    document.getElementById('userModal').style.display = 'block';
}

function editUser(userId) {
    document.getElementById('userModal').style.display = 'block'; // show modal immediately

    fetch(`<?php echo SITE_URL; ?>/api/get_user.php?id=${userId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalTitle').textContent = 'Edit User';
                document.getElementById('userId').value = data.user.id;
                document.getElementById('username').value = data.user.username;
                document.getElementById('fullName').value = data.user.full_name || '';
                document.getElementById('email').value = data.user.email || '';
                document.getElementById('role').value = data.user.role;
                // when editing, password change is optional
                document.getElementById('password').required = false;
                document.getElementById('confirmPassword').required = false;
                document.getElementById('password').value = '';
                document.getElementById('confirmPassword').value = '';
                document.getElementById('passwordStrength').textContent = '';
                document.getElementById('passwordStrength').className = 'password-strength';
            } else {
                alert('Error loading user data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Error loading user data. Check console.');
        });
}

function closeUserModal() {
    document.getElementById('userModal').style.display = 'none';
}

function scrollModal(modalId, direction) {
    const modalBody = document.getElementById(modalId + 'Body');
    const scrollAmount = 100; // pixels to scroll

    if (direction === 'up') {
        modalBody.scrollTop -= scrollAmount;
    } else if (direction === 'down') {
        modalBody.scrollTop += scrollAmount;
    }
}

function assessPasswordStrength(pw) {
    if (!pw) return '';
    let score = 0;
    if (pw.length >= 8) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    if (pw.length < 6) return 'Weak';
    if (score >= 3) return 'Strong';
    return 'Medium';
}

function updateStrengthIndicator() {
    const pw = document.getElementById('password').value || '';
    const indicator = document.getElementById('passwordStrength');
    const strength = assessPasswordStrength(pw);
    indicator.textContent = strength ? `Strength: ${strength}` : '';
    indicator.className = 'password-strength';
    if (strength === 'Weak') indicator.classList.add('strength-weak');
    else if (strength === 'Medium') indicator.classList.add('strength-medium');
    else if (strength === 'Strong') indicator.classList.add('strength-strong');
}

document.addEventListener('DOMContentLoaded', function() {
    const pwInput = document.getElementById('password');
    if (pwInput) pwInput.addEventListener('input', updateStrengthIndicator);
});

function saveUser(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const password = formData.get('password') || '';
    const confirmPassword = formData.get('confirm_password') || '';

    if (password) {
        if (password !== confirmPassword) {
            alert('Passwords do not match.');
            return;
        }
        const strength = assessPasswordStrength(password);
        if (strength === 'Weak') {
            if (!confirm('Password strength is weak. Do you want to proceed anyway?')) {
                return;
            }
        }
    }

    fetch('<?php echo SITE_URL; ?>/api/save_user.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('User saved successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Save user error:', err);
        alert('An error occurred while saving the user.');
    });
}

function toggleUserStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = newStatus === 'active' ? 'activate' : 'deactivate';

    if (confirm(`Are you sure you want to ${action} this user?`)) {
        fetch('<?php echo SITE_URL; ?>/api/toggle_user_status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({user_id: userId, status: newStatus})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        fetch('<?php echo SITE_URL; ?>/api/delete_user.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({user_id: userId})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('User deleted successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}
</script>
<main class="main-content">
    <div class="page-header">
        <h1>Users Management</h1>
        <p>Manage system users and roles</p>
    </div>

    <div class="actions-bar">
        <button class="btn btn-primary" onclick="openAddUserModal()">+ Add New User</button>
    </div>
    
    <div class="users-table">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><strong><?php echo safe_html($user['username'] ?? ''); ?></strong></td>
                    <td><?php echo safe_html($user['full_name'] ?? ''); ?></td>
                    <td><?php echo safe_html($user['email'] ?? ''); ?></td>
                    <td>
                        <span class="badge badge-role-<?php echo $user['role']; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $user['status']; ?>">
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <button onclick="editUser(<?php echo $user['id']; ?>)" class="btn-icon">✏️</button>

                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <button onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')" class="btn-icon" title="Toggle Status">
                            <?php echo $user['status'] === 'active' ? '🔒' : '🔓'; ?>
                        </button>
                        <button onclick="deleteUser(<?php echo $user['id']; ?>)" class="btn-icon btn-danger" title="Delete">
                            🗑️
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Add/Edit User Modal -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add New User</h2>
            <div class="modal-header-actions">
                <button class="scroll-btn" onclick="scrollModal('up')" title="Scroll Up">↑</button>
                <button class="scroll-btn" onclick="scrollModal('down')" title="Scroll Down">↓</button>
                <span class="close" onclick="closeUserModal()">&times;</span>
            </div>
        </div>
        <div class="modal-body" id="userModalBody" style="max-height: 400px; overflow-y: auto;">
            <form id="userForm" onsubmit="saveUser(event)">
                <input type="hidden" id="userId" name="user_id">
                
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group" id="passwordGroup">
                    <label>Password *</label>
                    <input type="password" id="password" name="password">
                    <small>Leave blank to keep current password (when editing)</small>
                    <small id="passwordStrength" class="password-strength"></small>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" id="confirmPassword" name="confirm_password">
                </div>
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="fullName" name="full_name">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="email" name="email">
                </div>

                <div class="form-group">
                    <label>Role *</label>
                    <select id="role" name="role" required>
                        <option value="cashier">Cashier</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="closeUserModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
