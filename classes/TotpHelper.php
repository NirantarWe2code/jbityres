<?php
/**
 * TOTP Helper - Time-based One-Time Password for Authenticator apps
 * RFC 6238 compliant - works with Google Authenticator, Authy, Microsoft Authenticator
 */

class TotpHelper
{
    private static $secretLength = 16; // 128 bits
    private static $period = 30;       // 30 second window
    private static $digits = 6;

    /**
     * Generate a new random secret (base32 encoded)
     */
    public static function generateSecret()
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        $random = random_bytes(self::$secretLength);
        for ($i = 0; $i < self::$secretLength; $i++) {
            $secret .= $chars[ord($random[$i]) % 32];
        }
        return $secret;
    }

    /**
     * Get otpauth:// URL for QR code (use with Google Charts or similar)
     */
    public static function getProvisioningUri($secret, $accountName, $issuer = 'FinalReport')
    {
        $accountName = rawurlencode($accountName);
        $issuer = rawurlencode($issuer);
        return "otpauth://totp/{$issuer}:{$accountName}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=" . self::$digits . "&period=" . self::$period;
    }

    /**
     * Get QR code image URL (Google Charts API - free, no API key)
     */
    public static function getQRCodeUrl($secret, $accountName, $issuer = 'FinalReport')
    {
        $uri = self::getProvisioningUri($secret, $accountName, $issuer);
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($uri);
    }

    /**
     * Verify TOTP code (allows 1 step before/after for clock drift)
     */
    public static function verify($secret, $code)
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::$digits) {
            return false;
        }

        $timeSlice = floor(time() / self::$period);
        for ($i = -1; $i <= 1; $i++) {
            if (self::getCode($secret, $timeSlice + $i) === $code) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get TOTP code for a given time slice
     */
    private static function getCode($secret, $timeSlice = null)
    {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / self::$period);
        }

        $secretKey = self::base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );
        return str_pad((string)($truncated % pow(10, self::$digits)), self::$digits, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 decode (RFC 3548)
     */
    private static function base32Decode($secret)
    {
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));
        $paddingCharCount = substr_count($secret, '=');
        $allowedValues = [6, 4, 3, 1, 0];
        if (!in_array($paddingCharCount, $allowedValues)) {
            return false;
        }
        for ($i = 0; $i < 4; $i++) {
            if ($paddingCharCount === $allowedValues[$i] && substr($secret, -($allowedValues[$i])) !== str_repeat('=', $allowedValues[$i])) {
                return false;
            }
        }
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = '';
        for ($i = 0; $i < count($secret); $i = $i + 8) {
            $x = '';
            if (!in_array($secret[$i], array_keys($base32charsFlipped))) {
                return false;
            }
            for ($j = 0; $j < 8; $j++) {
                $x .= str_pad(base_convert(@$base32charsFlipped[@$secret[$i + $j]] ?? 0, 10, 2), 5, '0', STR_PAD_LEFT);
            }
            $eightBits = str_split($x, 8);
            for ($z = 0; $z < count($eightBits); $z++) {
                $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
            }
        }
        return $binaryString;
    }
}
