<?php
/**
 * User Management Page
 */

require_once __DIR__ . '/../../config/config.php';
requirePermission('users.view');

$pageTitle = 'User Management';
$breadcrumbs = [
    ['title' => 'Home', 'url' => BASE_URL . '/pages/jbi-dashboard/index.php'],
    ['title' => 'User Management']
];

$pageScripts = [
    BASE_URL . '/assets/js/users.js'
];

include __DIR__ . '/../../includes/header.php';
?>

<!-- Filters Section -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-filter me-2"></i>Search & Filters
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form id="userFilters" class="row">
                    <div class="col-md-4">
                        <label for="searchTerm" class="form-label">Search</label>
                        <input type="text" class="form-control" id="searchTerm" name="search" 
                               placeholder="Username, name, email...">
                    </div>
                    <div class="col-md-3">
                        <label for="roleFilter" class="form-label">Role</label>
                        <select class="form-select" id="roleFilter" name="role">
                            <option value="">All Roles</option>
                            <option value="super_admin">Super Admin</option>
                            <option value="admin">Admin</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="statusFilter" class="form-label">Status</label>
                        <select class="form-select" id="statusFilter" name="status">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                            <button type="button" class="btn btn-secondary" id="resetFilters">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                            <?php if (hasPermission('users.create')): ?>
                            <button type="button" class="btn btn-success" id="addUser">
                                <i class="fas fa-plus me-2"></i>Add User
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-users me-2"></i>Users
                </h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-hover" id="usersTable">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="8" class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Loading users...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span id="tableInfo">Showing 0 to 0 of 0 entries</span>
                    </div>
                    <nav>
                        <div id="pagination"></div>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalLabel">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="userForm">
                <div class="modal-body">
                    <input type="hidden" id="userId" name="id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fullName" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="fullName" name="full_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Role *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <?php if (getCurrentUser()['role'] === ROLE_SUPER_ADMIN): ?>
                                    <option value="super_admin">Super Admin</option>
                                    <?php endif; ?>
                                    <option value="admin">Admin</option>
                                    <option value="user">User</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password <span id="passwordRequired">*</span></label>
                                <input type="password" class="form-control" id="password" name="password">
                                <div class="form-text">Leave blank to keep current password (when editing)</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirmPassword">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewUserModalLabel">
                    <i class="fas fa-eye me-2"></i>User Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewUserModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$inlineScripts = "
// Initialize users page when DOM is ready
$(document).ready(function() {
    console.log('Users page loaded');
    
    if (typeof UsersController !== 'undefined') {
        window.usersController = new UsersController();
        window.usersController.init();
    } else {
        console.error('UsersController class not found');
    }
});

// Global functions for pagination and actions
function changePage(page) {
    if (window.usersController) {
        window.usersController.changePage(page);
    }
}

function viewUser(id) {
    if (window.usersController) {
        window.usersController.viewUser(id);
    }
}

function editUser(id) {
    if (window.usersController) {
        window.usersController.editUser(id);
    }
}

function deleteUser(id) {
    if (window.usersController) {
        window.usersController.deleteUser(id);
    }
}
";

include __DIR__ . '/../../includes/footer.php';
?>