/**
 * Users Controller JavaScript
 */

class UsersController {
    constructor() {
        this.currentPage = 1;
        this.itemsPerPage = 25;
        this.filters = {};
        this.editingId = null;
    }
    
    init() {
        console.log('Users controller initializing...');
        this.setupEventListeners();
        this.loadUsersData();
    }
    
    setupEventListeners() {
        // Filter form submission
        $('#userFilters').on('submit', (e) => {
            e.preventDefault();
            this.applyFilters();
        });
        
        // Reset filters
        $('#resetFilters').on('click', () => {
            this.resetFilters();
        });
        
        // Add user button
        $('#addUser').on('click', () => {
            this.showAddModal();
        });
        
        // User form submission
        $('#userForm').on('submit', (e) => {
            e.preventDefault();
            this.saveUser();
        });
        
        // Search input with debounce
        $('#searchTerm').on('input', Utils.debounce(() => {
            this.applyFilters();
        }, 500));
        
        // Select filters
        $('#roleFilter, #statusFilter').on('change', () => {
            this.applyFilters();
        });
        
        // Password confirmation
        $('#confirmPassword').on('input', () => {
            this.validatePasswordMatch();
        });
        
        $('#password').on('input', () => {
            this.validatePasswordMatch();
        });
    }
    
    applyFilters() {
        const formData = new FormData(document.getElementById('userFilters'));
        this.filters = {};
        
        for (let [key, value] of formData.entries()) {
            if (value.trim()) {
                this.filters[key] = value.trim();
            }
        }
        
        this.currentPage = 1; // Reset to first page
        console.log('Applying filters:', this.filters);
        this.loadUsersData();
    }
    
    resetFilters() {
        document.getElementById('userFilters').reset();
        this.filters = {};
        this.currentPage = 1;
        this.loadUsersData();
    }
    
    async loadUsersData() {
        try {
            const params = {
                action: 'list',
                page: this.currentPage,
                limit: this.itemsPerPage,
                ...this.filters
            };
            
            console.log('Loading users data with params:', params);
            
            const response = await AjaxHelper.get(
                window.AppConfig.baseUrl + '/ajax/users.php',
                params
            );
            
            if (response.success) {
                this.displayUsersData(response.data);
                this.updatePagination(response.pagination);
                this.updateTableInfo(response.pagination);
            } else {
                throw new Error(response.message || 'Failed to load users data');
            }
            
        } catch (error) {
            console.error('Users data load error:', error);
            Utils.showToast('Failed to load users data: ' + error.message, 'error');
            this.showErrorState();
        }
    }
    
    displayUsersData(users) {
        const tbody = document.querySelector('#usersTable tbody');
        
        if (!users || users.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        <i class="fas fa-users fa-2x mb-3 d-block"></i>
                        No users found
                    </td>
                </tr>
            `;
            return;
        }
        
        const rows = users.map(user => {
            const statusClass = user.status === 'active' ? 'success' : 'secondary';
            const roleClass = this.getRoleClass(user.role);
            const createdDate = Utils.formatDate(user.created_at);
            const lastLogin = user.last_login ? Utils.formatDate(user.last_login) : 'Never';
            
            return `
                <tr>
                    <td><strong>${user.username}</strong></td>
                    <td>${user.full_name}</td>
                    <td>${user.email}</td>
                    <td>
                        <span class="badge badge-${roleClass}">
                            ${this.formatRole(user.role)}
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-${statusClass}">
                            ${user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                        </span>
                    </td>
                    <td>${createdDate}</td>
                    <td>${lastLogin}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-info btn-sm" onclick="viewUser(${user.id})" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${this.canEditUser(user) ? `
                                <button class="btn btn-warning btn-sm" onclick="editUser(${user.id})" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                            ` : ''}
                            ${this.canDeleteUser(user) ? `
                                <button class="btn btn-danger btn-sm" onclick="deleteUser(${user.id})" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
        
        tbody.innerHTML = rows;
    }
    
    getRoleClass(role) {
        const classes = {
            'super_admin': 'danger',
            'admin': 'warning',
            'user': 'info'
        };
        return classes[role] || 'secondary';
    }
    
    formatRole(role) {
        const labels = {
            'super_admin': 'Super Admin',
            'admin': 'Admin',
            'user': 'User'
        };
        return labels[role] || role;
    }
    
    canEditUser(user) {
        const currentUser = window.AppConfig.user;
        
        // Super admin can edit anyone
        if (currentUser.role === 'super_admin') return true;
        
        // Admin can edit users and other admins (but not super admins)
        if (currentUser.role === 'admin' && user.role !== 'super_admin') return true;
        
        // Users can only edit themselves (if we allow it)
        // if (currentUser.role === 'user' && user.id === currentUser.id) return true;
        
        return false;
    }
    
    canDeleteUser(user) {
        const currentUser = window.AppConfig.user;
        
        // Can't delete yourself
        if (user.id === currentUser.id) return false;
        
        // Super admin can delete anyone except themselves
        if (currentUser.role === 'super_admin') return true;
        
        // Admin can delete users and other admins (but not super admins)
        if (currentUser.role === 'admin' && user.role !== 'super_admin') return true;
        
        return false;
    }
    
    updatePagination(pagination) {
        const paginationContainer = document.getElementById('pagination');
        
        if (pagination.total_pages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }
        
        let html = '<ul class="pagination pagination-sm mb-0">';
        
        // Previous button
        if (pagination.current_page > 1) {
            html += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1})">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            `;
        }
        
        // Page numbers
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === pagination.current_page ? 'active' : '';
            html += `
                <li class="page-item ${activeClass}">
                    <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
                </li>
            `;
        }
        
        // Next button
        if (pagination.current_page < pagination.total_pages) {
            html += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1})">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            `;
        }
        
        html += '</ul>';
        paginationContainer.innerHTML = html;
    }
    
    updateTableInfo(pagination) {
        const start = ((pagination.current_page - 1) * pagination.records_per_page) + 1;
        const end = Math.min(pagination.current_page * pagination.records_per_page, pagination.total_records);
        
        document.getElementById('tableInfo').textContent = 
            `Showing ${start} to ${end} of ${pagination.total_records} entries`;
    }
    
    changePage(page) {
        this.currentPage = page;
        this.loadUsersData();
    }
    
    showAddModal() {
        this.editingId = null;
        document.getElementById('userModalLabel').textContent = 'Add User';
        document.getElementById('userForm').reset();
        document.getElementById('userId').value = '';
        document.getElementById('passwordRequired').style.display = 'inline';
        document.getElementById('password').required = true;
        
        const modal = new bootstrap.Modal(document.getElementById('userModal'));
        modal.show();
    }
    
    async editUser(id) {
        try {
            Utils.showLoading('Loading user...');
            
            const response = await AjaxHelper.get(
                window.AppConfig.baseUrl + '/ajax/users.php',
                { action: 'get', id: id }
            );
            
            if (response.success) {
                this.editingId = id;
                this.populateForm(response.data);
                
                document.getElementById('userModalLabel').textContent = 'Edit User';
                document.getElementById('passwordRequired').style.display = 'none';
                document.getElementById('password').required = false;
                
                const modal = new bootstrap.Modal(document.getElementById('userModal'));
                modal.show();
            } else {
                throw new Error(response.message || 'Failed to load user');
            }
            
        } catch (error) {
            console.error('Edit user error:', error);
            Utils.showToast('Failed to load user: ' + error.message, 'error');
        } finally {
            Utils.hideLoading();
        }
    }
    
    populateForm(user) {
        document.getElementById('userId').value = user.id;
        document.getElementById('username').value = user.username;
        document.getElementById('fullName').value = user.full_name;
        document.getElementById('email').value = user.email;
        document.getElementById('role').value = user.role;
        document.getElementById('status').value = user.status;
        document.getElementById('password').value = '';
        document.getElementById('confirmPassword').value = '';
    }
    
    validatePasswordMatch() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const confirmField = document.getElementById('confirmPassword');
        
        if (confirmPassword && password !== confirmPassword) {
            confirmField.setCustomValidity('Passwords do not match');
            confirmField.classList.add('is-invalid');
        } else {
            confirmField.setCustomValidity('');
            confirmField.classList.remove('is-invalid');
        }
    }
    
    async saveUser() {
        try {
            // Validate password match
            this.validatePasswordMatch();
            
            const form = document.getElementById('userForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const formData = new FormData(form);
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            data.action = this.editingId ? 'update' : 'create';
            
            Utils.showLoading('Saving user...');
            
            const response = await AjaxHelper.post(
                window.AppConfig.baseUrl + '/ajax/users.php',
                data
            );
            
            if (response.success) {
                Utils.showToast(response.message, 'success');
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
                modal.hide();
                
                this.loadUsersData(); // Refresh the table
            } else {
                throw new Error(response.message || 'Failed to save user');
            }
            
        } catch (error) {
            console.error('Save user error:', error);
            Utils.showToast('Failed to save user: ' + error.message, 'error');
        } finally {
            Utils.hideLoading();
        }
    }
    
    async viewUser(id) {
        try {
            Utils.showLoading('Loading user details...');
            
            const response = await AjaxHelper.get(
                window.AppConfig.baseUrl + '/ajax/users.php',
                { action: 'get', id: id }
            );
            
            if (response.success) {
                this.showViewModal(response.data);
            } else {
                throw new Error(response.message || 'Failed to load user');
            }
            
        } catch (error) {
            console.error('View user error:', error);
            Utils.showToast('Failed to load user: ' + error.message, 'error');
        } finally {
            Utils.hideLoading();
        }
    }
    
    showViewModal(user) {
        const statusClass = user.status === 'active' ? 'success' : 'secondary';
        const roleClass = this.getRoleClass(user.role);
        const createdDate = Utils.formatDate(user.created_at);
        const lastLogin = user.last_login ? Utils.formatDate(user.last_login) : 'Never';
        
        const content = `
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><strong>Username:</strong></label>
                        <p class="form-control-plaintext">${user.username}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Full Name:</strong></label>
                        <p class="form-control-plaintext">${user.full_name}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Email:</strong></label>
                        <p class="form-control-plaintext">${user.email}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Role:</strong></label>
                        <p class="form-control-plaintext">
                            <span class="badge badge-${roleClass}">
                                ${this.formatRole(user.role)}
                            </span>
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><strong>Status:</strong></label>
                        <p class="form-control-plaintext">
                            <span class="badge badge-${statusClass}">
                                ${user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                            </span>
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Created:</strong></label>
                        <p class="form-control-plaintext">${createdDate}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Last Login:</strong></label>
                        <p class="form-control-plaintext">${lastLogin}</p>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('viewUserModalBody').innerHTML = content;
        
        const modal = new bootstrap.Modal(document.getElementById('viewUserModal'));
        modal.show();
    }
    
    deleteUser(id) {
        Utils.confirm('Are you sure you want to delete this user?', async () => {
            try {
                Utils.showLoading('Deleting user...');
                
                const response = await AjaxHelper.post(
                    window.AppConfig.baseUrl + '/ajax/users.php',
                    { action: 'delete', id: id }
                );
                
                if (response.success) {
                    Utils.showToast(response.message, 'success');
                    this.loadUsersData(); // Refresh the table
                } else {
                    throw new Error(response.message || 'Failed to delete user');
                }
                
            } catch (error) {
                console.error('Delete user error:', error);
                Utils.showToast('Failed to delete user: ' + error.message, 'error');
            } finally {
                Utils.hideLoading();
            }
        });
    }
    
    showErrorState() {
        const tbody = document.querySelector('#usersTable tbody');
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-danger py-4">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3 d-block"></i>
                    Failed to load users data
                    <br>
                    <button class="btn btn-primary btn-sm mt-2" onclick="window.usersController.loadUsersData()">
                        <i class="fas fa-retry me-1"></i>Try Again
                    </button>
                </td>
            </tr>
        `;
    }
}

// Initialize when DOM is ready
$(document).ready(function() {
    console.log('Users script loaded');
});