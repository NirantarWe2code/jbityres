<?php
/**
 * Setup Two-Factor Authentication (Authenticator App)
 * User must be logged in. Generates QR code, verifies, enables 2FA.
 */

require_once __DIR__ . '/config/config.php';
requireAuth();

$pageTitle = 'Setup Two-Factor Authentication';
$error = '';
$success = '';
$step = 'setup'; // setup | verify | done

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $user = getCurrentUser();
        if (!$user) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }

        require_once __DIR__ . '/classes/Auth.php';
        $auth = new Auth();

        if (!empty($_POST['action']) && $_POST['action'] === 'verify') {
            $secret = $_POST['secret'] ?? '';
            $otpCode = trim($_POST['otp_code'] ?? '');
            if (empty($secret) || empty($otpCode)) {
                $error = 'Please enter the 6-digit code from your authenticator app.';
                $step = 'verify';
                $totpSecret = $secret; // Preserve for re-display
            } else {
                $result = $auth->enableTotp($user['id'], $secret, $otpCode);
                if ($result['success']) {
                    $_SESSION['totp_enabled'] = 1;
                    $success = $result['message'];
                    $step = 'done';
                } else {
                    $error = $result['message'];
                    $step = 'verify';
                    $totpSecret = $secret; // Preserve for re-display
                }
            }
        } elseif (!empty($_POST['action']) && $_POST['action'] === 'disable') {
            $result = $auth->disableTotp($user['id']);
            if ($result['success']) {
                $_SESSION['totp_enabled'] = 0;
                $success = $result['message'];
                $step = 'setup';
            } else {
                $error = $result['message'];
            }
        }
    }
}

$user = getCurrentUser();
if (!$user) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/classes/Auth.php';
$auth = new Auth();

// Check if user already has 2FA enabled
$userRow = $auth->getUserById($user['id']);
$has2FA = !empty($userRow['totp_enabled'] ?? 0);

if ($has2FA && $step === 'setup' && empty($_POST)) {
    $step = 'enabled'; // Already has 2FA
} elseif ($step === 'setup' && empty($_POST['action'])) {
    $setupResult = $auth->setupTotp($user['id']);
    if ($setupResult['success']) {
        $totpSecret = $setupResult['secret'];
        $qrUrl = $setupResult['qr_url'];
        $accountName = $setupResult['account_name'];
    } else {
        $error = $setupResult['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Two-Factor Authentication</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <?php if ($step === 'enabled'): ?>
                            <p>Two-factor authentication is already enabled for your account.</p>
                            <form method="POST" onsubmit="return confirm('Disable 2FA? You will only need password to login.');">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="disable">
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="fas fa-times me-2"></i>Disable 2FA
                                </button>
                            </form>
                        <?php elseif ($step === 'done'): ?>
                            <p class="text-success"><i class="fas fa-check-circle me-2"></i>2FA is now enabled. Next login will require your authenticator code.</p>
                            <a href="<?php echo BASE_URL; ?>/pages/dashboard/index.php" class="btn btn-primary">Go to Dashboard</a>
                        <?php elseif ($step === 'verify' && !empty($totpSecret)): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="verify">
                                <input type="hidden" name="secret" value="<?php echo htmlspecialchars($totpSecret); ?>">
                                <p>Enter the 6-digit code from your app to verify:</p>
                                <div class="mb-3">
                                    <input type="text" class="form-control form-control-lg text-center" name="otp_code" 
                                        maxlength="6" pattern="[0-9]*" inputmode="numeric" placeholder="000000" required autofocus>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Verify & Enable</button>
                            </form>
                        <?php elseif ($step === 'setup' && !empty($totpSecret)): ?>
                            <p class="mb-3">Scan this QR code with your authenticator app (Google Authenticator, Authy, Microsoft Authenticator):</p>
                            <div class="text-center mb-4">
                                <img src="<?php echo htmlspecialchars($qrUrl); ?>" alt="QR Code" class="img-fluid" style="max-width: 200px;">
                            </div>
                            <p class="small text-muted">Or enter this key manually: <code><?php echo htmlspecialchars($totpSecret); ?></code></p>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="verify">
                                <input type="hidden" name="secret" value="<?php echo htmlspecialchars($totpSecret); ?>">
                                <div class="mb-3">
                                    <label class="form-label">Enter code to verify:</label>
                                    <input type="text" class="form-control text-center" name="otp_code" 
                                        maxlength="6" pattern="[0-9]*" placeholder="000000" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Verify & Enable 2FA</button>
                            </form>
                        <?php endif; ?>

                        <hr class="my-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="<?php echo BASE_URL; ?>/logout.php" class="text-muted small">
                                <i class="fas fa-sign-out-alt me-1"></i>Login as different user
                            </a>
                            <?php if ($has2FA || $step === 'done'): ?>
                            <a href="<?php echo BASE_URL; ?>/pages/dashboard/index.php" class="text-muted small">
                                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
