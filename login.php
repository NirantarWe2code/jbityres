<?php
/**
 * Login Page - Supports OTP/2FA with Authenticator app
 */

require_once __DIR__ . '/config/config.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard/index.php');
    exit;
}

$error = '';
$success = '';
$step = $_GET['step'] ?? $_POST['step'] ?? 'credentials';
$showOtpStep = false;

// OTP step: verify code and complete login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'otp') {
    $showOtpStep = true;
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($_SESSION['pending_2fa_user_id'])) {
        $error = 'Session expired. Please login again.';
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_time']);
        $showOtpStep = false;
    } elseif (isset($_SESSION['pending_2fa_time']) && (time() - $_SESSION['pending_2fa_time']) > 300) {
        $error = 'OTP session expired. Please login again.';
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_time']);
        $showOtpStep = false;
    } else {
        $otpCode = trim($_POST['otp_code'] ?? '');
        if (empty($otpCode)) {
            $error = 'Please enter the 6-digit code from your authenticator app.';
        } else {
            require_once __DIR__ . '/classes/Auth.php';
            $auth = new Auth();
            $result = $auth->verifyOtpAndLogin($_SESSION['pending_2fa_user_id'], $otpCode);
            if ($result['success']) {
                unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_time']);
                header('Location: ' . BASE_URL . '/pages/dashboard/index.php');
                exit;
            }
            $error = $result['message'];
        }
    }
}

// Clear pending 2FA if user navigates back
if (!empty($_GET['clear'])) {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_time']);
}

// Check if we should show OTP step (from credentials step redirect)
if ($step === 'otp' && !empty($_SESSION['pending_2fa_user_id'])) {
    $showOtpStep = true;
}

// Credentials step: username + password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') !== 'otp') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            try {
                require_once __DIR__ . '/classes/Auth.php';
                $auth = new Auth();
                $result = $auth->login($username, $password);

                if ($result['success']) {
                    header('Location: ' . BASE_URL . '/pages/dashboard/index.php');
                    exit;
                } elseif (!empty($result['need_otp']) && !empty($result['pending_user_id'])) {
                    $_SESSION['pending_2fa_user_id'] = $result['pending_user_id'];
                    $_SESSION['pending_2fa_time'] = time();
                    $showOtpStep = true;
                    $success = 'Enter the 6-digit code from your authenticator app.';
                } else {
                    $error = $result['message'];
                    error_log("Login failed for user '$username': " . $result['message']);
                }
            } catch (Exception $e) {
                $error = 'System error occurred. Please try again.';
                error_log("Login system error: " . $e->getMessage());
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    $error = 'System error: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .login-header h2 {
            margin: 0;
            font-weight: 300;
        }

        .login-header .subtitle {
            opacity: 0.9;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .login-body {
            padding: 2rem;
        }

        .form-floating {
            margin-bottom: 1rem;
        }

        .form-floating>.form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem 0.75rem;
        }

        .form-floating>.form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            width: 100%;
            color: white;
            transition: transform 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            color: white;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .demo-credentials {
            display: none;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.85rem;
        }

        .demo-credentials h6 {
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .demo-credentials .credential-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.25rem;
        }

        .loading {
            display: none;
        }

        .loading.show {
            display: inline-block;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-header">
            <h2><i class="fas fa-chart-line me-2"></i><?php echo APP_NAME; ?></h2>
            <div class="subtitle">Sales Reporting System</div>
        </div>

        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($showOtpStep): ?>
            <!-- OTP Verification Step (2FA) -->
            <form method="POST" id="otpForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="step" value="otp">

                <div class="mb-3 text-center">
                    <p class="text-muted">
                        <i class="fas fa-mobile-alt fa-2x mb-2"></i><br>
                        Open your authenticator app (Google Authenticator, Authy, etc.) and enter the 6-digit code
                    </p>
                </div>

                <div class="form-floating">
                    <input type="text" class="form-control text-center" id="otpCode" name="otp_code" 
                        placeholder="000000" maxlength="8" pattern="[0-9\s]*" 
                        inputmode="numeric" autocomplete="one-time-code" required autofocus>
                    <label for="otpCode"><i class="fas fa-key me-2"></i>Enter 6-digit code</label>
                </div>

                <button type="submit" class="btn btn-login">
                    <span class="btn-text"><i class="fas fa-check me-2"></i>Verify</span>
                    <span class="loading"><i class="fas fa-spinner fa-spin me-2"></i>Verifying...</span>
                </button>

                <div class="text-center mt-3">
                    <a href="<?php echo BASE_URL; ?>/login.php?clear=1" class="text-muted small">
                        <i class="fas fa-arrow-left me-1"></i>Back to login
                    </a>
                </div>
            </form>
            <?php else: ?>
            <!-- Credentials Step -->
            <form method="POST" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username"
                        required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <label for="username"><i class="fas fa-user me-2"></i>Username</label>
                </div>

                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password"
                        required>
                    <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                </div>

                <button type="submit" class="btn btn-login">
                    <span class="btn-text">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </span>
                    <span class="loading">
                        <i class="fas fa-spinner fa-spin me-2"></i>Logging in...
                    </span>
                </button>
            </form>
            <?php endif; ?>

            <div class="demo-credentials">
                <h6><i class="fas fa-info-circle me-2"></i>Demo Credentials:</h6>
                <div class="credential-item">
                    <strong>Super Admin:</strong>
                    <span>admin / admin123</span>
                </div>
                <div class="credential-item">
                    <strong>Admin:</strong>
                    <span>manager / manager123</span>
                </div>
                <div class="credential-item">
                    <strong>User:</strong>
                    <span>user / user123</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        (function() {
            const form = document.getElementById('loginForm') || document.getElementById('otpForm');
            if (form) {
                form.addEventListener('submit', function () {
                    const btnText = form.querySelector('.btn-text');
                    const loading = form.querySelector('.loading');
                    const submitBtn = form.querySelector('.btn-login');
                    if (btnText) btnText.style.display = 'none';
                    if (loading) loading.classList.add('show');
                    if (submitBtn) submitBtn.disabled = true;
                });
            }
            // Auto-focus OTP input and allow only digits
            const otpInput = document.getElementById('otpCode');
            if (otpInput) {
                otpInput.addEventListener('input', function() {
                    this.value = this.value.replace(/\D/g, '').slice(0, 6);
                });
            }
        })();

        // Auto-fill demo credentials
        document.addEventListener('DOMContentLoaded', function () {
            const credentialItems = document.querySelectorAll('.credential-item');
            credentialItems.forEach(item => {
                item.style.cursor = 'pointer';
                item.addEventListener('click', function () {
                    const credentialText = this.querySelector('span').textContent.trim();
                    const [username, password] = credentialText.split(' / ');

                    document.getElementById('username').value = username;
                    document.getElementById('password').value = password;

                    // Add visual feedback
                    this.style.background = '#e3f2fd';
                    setTimeout(() => {
                        this.style.background = '';
                    }, 1000);
                });
            });
        });
    </script>
</body>

</html>