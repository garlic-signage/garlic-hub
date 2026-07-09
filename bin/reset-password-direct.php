#!/usr/bin/env php
<?php
/*
 garlic-hub: Digital Signage Management Platform

 Direct password reset for SQLite database
 Usage: php reset-password-direct.php <db-path> <email> [new-password]
*/
declare(strict_types=1);

if ($argc < 3) {
    echo "Usage: php reset-password-direct.php <database-path> <email> [new-password]\n";
    echo "Example: php reset-password-direct.php var/garlic_hub.db 757988@mail.ru\n";
    exit(1);
}

$dbPath = $argv[1];
$email = $argv[2];
$newPassword = $argv[3] ?? generatePassword();

if (!file_exists($dbPath)) {
    echo "Error: Database file not found at '{$dbPath}'\n";
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Find user by email
    $stmt = $pdo->prepare('SELECT UID, email, username FROM user_main WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "Error: User with email '{$email}' not found.\n";
        exit(1);
    }
    
    // Hash the password
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Update password
    $updateStmt = $pdo->prepare('UPDATE user_main SET password = ? WHERE UID = ?');
    $updateStmt->execute([$hashedPassword, $user['UID']]);
    
    echo "✓ Password reset successfully!\n";
    echo "Email: {$user['email']}\n";
    echo "Username: {$user['username']}\n";
    echo "New password: {$newPassword}\n";
    echo "\nImportant: Please save the new password in a secure location.\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}

function generatePassword(int $length = 16): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}
