<?php
require_once __DIR__ . '/db.php';

class Auth {
    public static function login(string $email, string $password): array {
        $user = DB::fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
        if (!$user) return ['success' => false, 'message' => 'ไม่พบอีเมลนี้ในระบบ'];
        if (!$user['is_verified']) return ['success' => false, 'message' => 'กรุณายืนยันอีเมลก่อนเข้าใช้งาน'];
        if (!password_verify($password, $user['password'])) return ['success' => false, 'message' => 'รหัสผ่านไม่ถูกต้อง'];

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['logged_in']  = true;

        self::log($user['id'], 'LOGIN', 'users', $user['id'], 'User logged in');
        return ['success' => true, 'message' => 'เข้าสู่ระบบสำเร็จ'];
    }

    public static function register(array $data): array {
        $existing = DB::fetchOne("SELECT id FROM users WHERE email = ?", [$data['email']]);
        if ($existing) return ['success' => false, 'message' => 'อีเมลนี้ถูกใช้งานแล้ว'];

        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $otp  = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));

        $id = DB::insert(
            "INSERT INTO users (first_name, last_name, phone, email, password, otp_code, otp_expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$data['first_name'], $data['last_name'], $data['phone'], $data['email'], $hash, $otp, $expires]
        );

        self::sendOtpEmail($data['email'], $data['first_name'], $otp);
        return ['success' => true, 'message' => 'ลงทะเบียนสำเร็จ กรุณาตรวจสอบอีเมลเพื่อรับรหัส OTP', 'user_id' => $id];
    }

    public static function verifyOtp(int $userId, string $otp): array {
        $user = DB::fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        if (!$user) return ['success' => false, 'message' => 'ไม่พบผู้ใช้งาน'];
        if ($user['is_verified']) return ['success' => false, 'message' => 'บัญชีนี้ยืนยันแล้ว'];
        if ($user['otp_code'] !== $otp) return ['success' => false, 'message' => 'รหัส OTP ไม่ถูกต้อง'];
        if (strtotime($user['otp_expires_at']) < time()) return ['success' => false, 'message' => 'รหัส OTP หมดอายุแล้ว'];

        DB::execute("UPDATE users SET is_verified=1, otp_code=NULL, otp_expires_at=NULL WHERE id=?", [$userId]);
        return ['success' => true, 'message' => 'ยืนยันตัวตนสำเร็จ'];
    }

    public static function resendOtp(int $userId): array {
        $user = DB::fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        if (!$user) return ['success' => false, 'message' => 'ไม่พบผู้ใช้งาน'];

        $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
        DB::execute("UPDATE users SET otp_code=?, otp_expires_at=? WHERE id=?", [$otp, $expires, $userId]);
        self::sendOtpEmail($user['email'], $user['first_name'], $otp);
        return ['success' => true, 'message' => 'ส่งรหัส OTP ใหม่แล้ว'];
    }

    public static function logout(): void {
        if (isset($_SESSION['user_id'])) {
            self::log($_SESSION['user_id'], 'LOGOUT', 'users', $_SESSION['user_id'], 'User logged out');
        }
        session_destroy();
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }

    public static function requireLogin(): void {
        if (empty($_SESSION['logged_in'])) {
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }
    }

    private static function sendOtpEmail(string $email, string $name, string $otp): void {
        // In production, use PHPMailer or similar SMTP library
        // For development, just write to log
        $logFile = __DIR__ . '/../otp_log.txt';
        $entry = date('Y-m-d H:i:s') . " | TO: {$email} | NAME: {$name} | OTP: {$otp}\n";
        file_put_contents($logFile, $entry, FILE_APPEND);

        // Uncomment and configure for real email sending:
        // $subject = 'CSMS - รหัส OTP ยืนยันตัวตน';
        // $message = "เรียน {$name},\n\nรหัส OTP ของท่าน: {$otp}\n\nรหัสนี้จะหมดอายุใน " . OTP_EXPIRY_MINUTES . " นาที";
        // mail($email, $subject, $message);
    }

    private static function log(int $userId, string $action, string $entity, int $entityId, string $detail): void {
        try {
            DB::execute(
                "INSERT INTO system_logs (user_id, action, entity_type, entity_id, detail, ip_address) VALUES (?,?,?,?,?,?)",
                [$userId, $action, $entity, $entityId, $detail, $_SERVER['REMOTE_ADDR'] ?? '']
            );
        } catch (Exception $e) { /* silent */ }
    }
}
