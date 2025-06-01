<?php
namespace App\Controllers;

require_once __DIR__ . '/../../core/auth/Auth.php';

/**
 * Authentication Controller
 * Handles all authentication-related API endpoints
 */
class AuthController {
    private $auth;
    
    public function __construct() {
        $this->auth = new Auth();
    }
    
    /**
     * User login
     */
    public function login($request, $params) {
        $email = $request['body']['email'] ?? '';
        $password = $request['body']['password'] ?? '';
        $twoFactorCode = $request['body']['two_factor_code'] ?? '';
        $rememberMe = $request['body']['remember_me'] ?? false;
        
        if (!$email || !$password) {
            return ['error' => 'Email and password are required', 'code' => 'MISSING_CREDENTIALS'];
        }
        
        $result = $this->auth->login($email, $password, $twoFactorCode, $rememberMe);
        
        if ($result['success']) {
            return [
                'success' => true,
                'token' => $result['token'],
                'refresh_token' => $result['refresh_token'] ?? null,
                'user' => $result['user'],
                'expires_in' => $result['expires_in']
            ];
        }
        
        return ['error' => $result['message'], 'code' => $result['code'] ?? 'LOGIN_FAILED'];
    }
    
    /**
     * User registration
     */
    public function register($request, $params) {
        $data = $request['body'];
        
        $required = ['email', 'password', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return ['error' => "Field '$field' is required", 'code' => 'MISSING_FIELD'];
            }
        }
        
        $result = $this->auth->register(
            $data['email'],
            $data['password'],
            $data['first_name'],
            $data['last_name'],
            $data['role'] ?? 'user'
        );
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Registration successful. Please check your email for verification.',
                'user_id' => $result['user_id']
            ];
        }
        
        return ['error' => $result['message'], 'code' => $result['code'] ?? 'REGISTRATION_FAILED'];
    }
    
    /**
     * User logout
     */
    public function logout($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $result = $this->auth->logout($user['id']);
        
        return [
            'success' => $result,
            'message' => 'Logged out successfully'
        ];
    }
    
    /**
     * Refresh authentication token
     */
    public function refresh($request, $params) {
        $refreshToken = $request['body']['refresh_token'] ?? '';
        
        if (!$refreshToken) {
            return ['error' => 'Refresh token is required', 'code' => 'MISSING_REFRESH_TOKEN'];
        }
        
        $result = $this->auth->refreshToken($refreshToken);
        
        if ($result['success']) {
            return [
                'success' => true,
                'token' => $result['token'],
                'refresh_token' => $result['refresh_token'],
                'expires_in' => $result['expires_in']
            ];
        }
        
        return ['error' => $result['message'], 'code' => 'REFRESH_FAILED'];
    }
    
    /**
     * Verify email address
     */
    public function verifyEmail($request, $params) {
        $token = $request['body']['token'] ?? '';
        
        if (!$token) {
            return ['error' => 'Verification token is required', 'code' => 'MISSING_TOKEN'];
        }
        
        $result = $this->auth->verifyEmail($token);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Email verified successfully'
            ];
        }
        
        return ['error' => 'Invalid or expired verification token', 'code' => 'INVALID_TOKEN'];
    }
    
    /**
     * Forgot password
     */
    public function forgotPassword($request, $params) {
        $email = $request['body']['email'] ?? '';
        
        if (!$email) {
            return ['error' => 'Email is required', 'code' => 'MISSING_EMAIL'];
        }
        
        $result = $this->auth->sendPasswordReset($email);
        
        return [
            'success' => true,
            'message' => 'If an account with that email exists, a password reset link has been sent.'
        ];
    }
    
    /**
     * Reset password
     */
    public function resetPassword($request, $params) {
        $token = $request['body']['token'] ?? '';
        $password = $request['body']['password'] ?? '';
        
        if (!$token || !$password) {
            return ['error' => 'Token and new password are required', 'code' => 'MISSING_FIELDS'];
        }
        
        $result = $this->auth->resetPassword($token, $password);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Password reset successfully'
            ];
        }
        
        return ['error' => 'Invalid or expired reset token', 'code' => 'INVALID_TOKEN'];
    }
    
    /**
     * Get current user information
     */
    public function me($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        // Remove sensitive information
        unset($user['password_hash']);
        unset($user['two_factor_secret']);
        
        return [
            'success' => true,
            'user' => $user
        ];
    }
    
    /**
     * Enable 2FA for user
     */
    public function enable2FA($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $result = $this->auth->enable2FA($user['id']);
        
        if ($result['success']) {
            return [
                'success' => true,
                'secret' => $result['secret'],
                'qr_code' => $result['qr_code'],
                'backup_codes' => $result['backup_codes']
            ];
        }
        
        return ['error' => $result['message'], 'code' => '2FA_ENABLE_FAILED'];
    }
    
    /**
     * Verify 2FA setup
     */
    public function verify2FA($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $code = $request['body']['code'] ?? '';
        
        if (!$code) {
            return ['error' => '2FA code is required', 'code' => 'MISSING_CODE'];
        }
        
        $result = $this->auth->verify2FA($user['id'], $code);
        
        if ($result) {
            return [
                'success' => true,
                'message' => '2FA enabled successfully'
            ];
        }
        
        return ['error' => 'Invalid 2FA code', 'code' => 'INVALID_2FA_CODE'];
    }
    
    /**
     * Disable 2FA for user
     */
    public function disable2FA($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $password = $request['body']['password'] ?? '';
        
        if (!$password) {
            return ['error' => 'Password confirmation is required', 'code' => 'MISSING_PASSWORD'];
        }
        
        $result = $this->auth->disable2FA($user['id'], $password);
        
        if ($result) {
            return [
                'success' => true,
                'message' => '2FA disabled successfully'
            ];
        }
        
        return ['error' => 'Invalid password or 2FA not enabled', 'code' => '2FA_DISABLE_FAILED'];
    }
}
?>
