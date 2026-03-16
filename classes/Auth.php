<?php
/**
 * Authentication and User Management Class
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class Auth
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Login user
     */
    public function login($username, $password)
    {
        try {
            // Get user from database
            $sql = "SELECT id, username, password, full_name, email, role, status, 
                           created_at, last_login 
                    FROM users 
                    WHERE username = '" . $username . "' AND status = 'active'";

            $user = $this->db->fetchOne($sql, [$username]);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid username or password'
                ];
            }

            // Verify password
            if (!password_verify($password, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid username or password'
                ];
            }

            // Update last login
            $this->updateLastLogin($user['id']);

            // Set session
            $this->setUserSession($user);

            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ];

        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Logout user
     */
    public function logout()
    {
        // Clear session
        session_unset();
        session_destroy();

        // Start new session
        session_start();

        return [
            'success' => true,
            'message' => 'Logged out successfully'
        ];
    }

    /**
     * Register new user
     */
    public function register($data)
    {
        try {
            // Validate input
            $validation = $this->validateUserData($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }

            // Check if username exists
            if ($this->usernameExists($data['username'])) {
                return [
                    'success' => false,
                    'message' => 'Username already exists'
                ];
            }

            // Check if email exists
            if ($this->emailExists($data['email'])) {
                return [
                    'success' => false,
                    'message' => 'Email already exists'
                ];
            }

            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

            // Insert user
            $sql = "INSERT INTO users (username, password, full_name, email, role, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'active', NOW())";

            $result = $this->db->execute($sql, [
                $data['username'],
                $hashedPassword,
                $data['full_name'],
                $data['email'],
                $data['role'] ?? ROLE_USER
            ]);

            if ($result['affected_rows'] > 0) {
                return [
                    'success' => true,
                    'message' => 'User created successfully',
                    'user_id' => $result['insert_id']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create user'
                ];
            }

        } catch (Exception $e) {
            error_log('Registration error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ];
        }
    }

    /**
     * Get user by ID
     */
    public function getUserById($id)
    {
        $sql = "SELECT id, username, full_name, email, role, status, 
                       created_at, last_login 
                FROM users 
                WHERE id = ?";

        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Get all users
     */
    public function getAllUsers($page = 1, $limit = 25, $filters = [])
    {
        $offset = ($page - 1) * $limit;

        // Build WHERE clause
        $whereConditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $whereConditions[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($filters['role'])) {
            $whereConditions[] = "role = ?";
            $params[] = $filters['role'];
        }

        if (!empty($filters['status'])) {
            $whereConditions[] = "status = ?";
            $params[] = $filters['status'];
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM users $whereClause";
        $totalResult = $this->db->fetchOne($countSql, $params);
        $totalRecords = (int) $totalResult['total'];

        // Get records
        $sql = "SELECT id, username, full_name, email, role, status, 
                       created_at, last_login 
                FROM users 
                $whereClause 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $users = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'data' => $users,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalRecords / $limit),
                'total_records' => $totalRecords,
                'records_per_page' => $limit
            ]
        ];
    }

    /**
     * Update user
     */
    public function updateUser($id, $data)
    {
        try {
            // Validate input
            $validation = $this->validateUserData($data, $id);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }

            $updateFields = [];
            $params = [];

            if (isset($data['username'])) {
                $updateFields[] = 'username = ?';
                $params[] = $data['username'];
            }

            if (isset($data['full_name'])) {
                $updateFields[] = 'full_name = ?';
                $params[] = $data['full_name'];
            }

            if (isset($data['email'])) {
                $updateFields[] = 'email = ?';
                $params[] = $data['email'];
            }

            if (isset($data['role'])) {
                $updateFields[] = 'role = ?';
                $params[] = $data['role'];
            }

            if (isset($data['status'])) {
                $updateFields[] = 'status = ?';
                $params[] = $data['status'];
            }

            if (isset($data['password']) && !empty($data['password'])) {
                $updateFields[] = 'password = ?';
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            if (empty($updateFields)) {
                return [
                    'success' => false,
                    'message' => 'No fields to update'
                ];
            }

            $updateFields[] = 'updated_at = NOW()';
            $params[] = $id;

            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";

            $result = $this->db->execute($sql, $params);

            if ($result['affected_rows'] > 0) {
                return [
                    'success' => true,
                    'message' => 'User updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'No changes made'
                ];
            }

        } catch (Exception $e) {
            error_log('Update user error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Update failed. Please try again.'
            ];
        }
    }

    /**
     * Delete user
     */
    public function deleteUser($id)
    {
        try {
            // Don't allow deleting current user
            if ($id == ($_SESSION['user_id'] ?? 0)) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete your own account'
                ];
            }

            $sql = "DELETE FROM users WHERE id = ?";
            $result = $this->db->execute($sql, [$id]);

            if ($result['affected_rows'] > 0) {
                return [
                    'success' => true,
                    'message' => 'User deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

        } catch (Exception $e) {
            error_log('Delete user error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Delete failed. Please try again.'
            ];
        }
    }

    /**
     * Set user session
     */
    private function setUserSession($user)
    {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
    }

    /**
     * Update last login
     */
    private function updateLastLogin($userId)
    {
        $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $this->db->execute($sql, [$userId]);
    }

    /**
     * Check if username exists
     */
    private function usernameExists($username, $excludeId = null)
    {
        $sql = "SELECT id FROM users WHERE username = ?";
        $params = [$username];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $result = $this->db->fetchOne($sql, $params);
        return !empty($result);
    }

    /**
     * Check if email exists
     */
    private function emailExists($email, $excludeId = null)
    {
        $sql = "SELECT id FROM users WHERE email = ?";
        $params = [$email];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $result = $this->db->fetchOne($sql, $params);
        return !empty($result);
    }

    /**
     * Validate user data
     */
    private function validateUserData($data, $excludeId = null)
    {
        if (empty($data['username'])) {
            return ['valid' => false, 'message' => 'Username is required'];
        }

        if (empty($data['full_name'])) {
            return ['valid' => false, 'message' => 'Full name is required'];
        }

        if (empty($data['email'])) {
            return ['valid' => false, 'message' => 'Email is required'];
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Invalid email format'];
        }

        if (!$excludeId && empty($data['password'])) {
            return ['valid' => false, 'message' => 'Password is required'];
        }

        if (!empty($data['password']) && strlen($data['password']) < 6) {
            return ['valid' => false, 'message' => 'Password must be at least 6 characters'];
        }

        return ['valid' => true];
    }
}
?>