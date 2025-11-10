<?php
/**
 * JWT Helper Class
 * Yobe State University Emergency Response System
 */

class JWTHelper {
    private static $secretKey = JWT_SECRET_KEY;
    private static $algorithm = JWT_ALGORITHM;
    private static $expireTime = JWT_EXPIRE_TIME;

    /**
     * Generate JWT Token
     */
    public static function generateToken($user) {
        $payload = [
            'iss' => 'YSU-Emergency-Response',
            'iat' => time(),
            'exp' => time() + self::$expireTime,
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'full_name' => $user['full_name']
        ];

        return self::encode($payload);
    }

    /**
     * Generate Refresh Token
     */
    public static function generateRefreshToken($user) {
        $payload = [
            'iss' => 'YSU-Emergency-Response',
            'iat' => time(),
            'exp' => time() + JWT_REFRESH_EXPIRE_TIME,
            'user_id' => $user['id'],
            'type' => 'refresh'
        ];

        return self::encode($payload);
    }

    /**
     * Encode JWT
     */
    private static function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => self::$algorithm]);
        $payload = json_encode($payload);

        $headerEncoded = self::base64UrlEncode($header);
        $payloadEncoded = self::base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, self::$secretKey, true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Decode and Verify JWT
     */
    public static function decodeToken($token) {
        if (empty($token)) {
            throw new Exception('Token is required');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token structure');
        }

        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

        // Decode header and payload
        $header = json_decode(self::base64UrlDecode($headerEncoded), true);
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        $signature = self::base64UrlDecode($signatureEncoded);

        // Verify signature
        $expectedSignature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, self::$secretKey, true);
        if (!hash_equals($expectedSignature, $signature)) {
            throw new Exception('Invalid token signature');
        }

        // Check algorithm
        if (!isset($header['alg']) || $header['alg'] !== self::$algorithm) {
            throw new Exception('Invalid token algorithm');
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception('Token has expired');
        }

        // Check issued at time
        if (isset($payload['iat']) && $payload['iat'] > time()) {
            throw new Exception('Token issued in the future');
        }

        // Check issuer
        if (isset($payload['iss']) && $payload['iss'] !== 'YSU-Emergency-Response') {
            throw new Exception('Invalid token issuer');
        }

        return $payload;
    }

    /**
     * Validate Refresh Token
     */
    public static function validateRefreshToken($token) {
        try {
            $payload = self::decodeToken($token);

            if (!isset($payload['type']) || $payload['type'] !== 'refresh') {
                throw new Exception('Invalid refresh token type');
            }

            return $payload;
        } catch (Exception $e) {
            throw new Exception('Invalid refresh token: ' . $e->getMessage());
        }
    }

    /**
     * Refresh Access Token
     */
    public static function refreshToken($refreshToken, $pdo) {
        try {
            $payload = self::validateRefreshToken($refreshToken);

            // Get user from database
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$payload['user_id']]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new Exception('User not found or inactive');
            }

            // Generate new access token
            return self::generateToken($user);

        } catch (Exception $e) {
            throw new Exception('Token refresh failed: ' . $e->getMessage());
        }
    }

    /**
     * Get Token from Authorization Header
     */
    public static function getTokenFromHeader() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($authHeader)) {
            throw new Exception('Authorization header required');
        }

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            throw new Exception('Invalid authorization header format');
        }

        return $matches[1];
    }

    /**
     * Authenticate User from Token
     */
    public static function authenticateUser($pdo) {
        try {
            $token = self::getTokenFromHeader();
            $payload = self::decodeToken($token);

            // Get fresh user data from database
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$payload['user_id']]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new Exception('User not found or inactive');
            }

            // Update last login time
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$payload['user_id']]);

            return $user;

        } catch (Exception $e) {
            throw new Exception('Authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * Check User Permissions
     */
    public static function checkPermission($user, $requiredPermissions) {
        $userRole = $user['role'];

        // Super admin has all permissions
        if ($userRole === ROLE_SUPER_ADMIN) {
            return true;
        }

        // Check specific permissions
        if (is_array($requiredPermissions)) {
            foreach ($requiredPermissions as $permission) {
                if (self::hasPermission($userRole, $permission)) {
                    return true;
                }
            }
            return false;
        }

        return self::hasPermission($userRole, $requiredPermissions);
    }

    /**
     * Check if role has specific permission
     */
    private static function hasPermission($role, $permission) {
        $permissions = [
            ROLE_STUDENT => ['report_emergency', 'view_own_emergencies', 'update_profile'],
            ROLE_STAFF => ['report_emergency', 'view_own_emergencies', 'update_profile'],
            ROLE_SECURITY_ADMIN => ['view_security_emergencies', 'update_security_emergencies', 'manage_locations'],
            ROLE_HEALTH_ADMIN => ['view_health_emergencies', 'update_health_emergencies', 'manage_locations'],
            ROLE_FIRE_ADMIN => ['view_fire_emergencies', 'update_fire_emergencies', 'manage_locations'],
            ROLE_SUPER_ADMIN => ['*'] // All permissions
        ];

        if ($role === ROLE_SUPER_ADMIN) {
            return true;
        }

        return in_array($permission, $permissions[$role] ?? []);
    }

    /**
     * Base64 URL Encode
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL Decode
     */
    private static function base64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * Get Token Expiry Time
     */
    public static function getTokenExpiry() {
        return time() + self::$expireTime;
    }

    /**
     * Check if token is about to expire (within 5 minutes)
     */
    public static function isTokenExpiringSoon($token) {
        try {
            $payload = self::decodeToken($token);
            return ($payload['exp'] - time()) < 300; // 5 minutes
        } catch (Exception $e) {
            return true; // Treat invalid tokens as expired
        }
    }
}
?>