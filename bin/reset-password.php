#!/usr/bin/env php
<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Framework\Core\Config\Config;
use Doctrine\DBAL\DriverManager;

$config = new Config();

try {
    // Get database connection parameters
    $driver = strtolower($config->getEnv('DB_MASTER_DRIVER'));
    
    switch ($driver) {
        case 'pdo_sqlite':
        case 'sqlite3':
            $connectionParams = [
                'path' => $config->getEnv('DB_MASTER_PATH'),
                'driver' => $driver,
            ];
            break;
        case 'pdo_mysql':
        case 'mysqli':
        case 'pdo_pgsql':
        case 'pgsql':
            $connectionParams = [
                'dbname' => $config->getEnv('DB_MASTER_NAME'),
                'user' => $config->getEnv('DB_MASTER_USER'),
                'password' => $config->getEnv('DB_MASTER_PASSWORD'),
                'host' => $config->getEnv('DB_MASTER_HOST'),
                'port' => $config->getEnv('DB_MASTER_PORT'),
                'driver' => $driver,
            ];
            if ($driver === 'pdo_mysql' || $driver === 'mysqli') {
                $connectionParams['charset'] = 'utf8mb4';
            }
            break;
        default:
            throw new \InvalidArgumentException('Unsupported DBAL driver: ' . $driver);
    }
    
    $connection = DriverManager::getConnection($connectionParams);
    
    // Get email from command line or prompt
    if ($argc < 2) {
        echo "Usage: php reset-password.php <email> [new-password]\n";
        echo "Example: php reset-password.php 757988@mail.ru\n";
        exit(1);
    }
    
    $email = $argv[1];
    
    // Generate new password if not provided
    $newPassword = $argv[2] ?? generatePassword();
    
    // Find user by email
    $queryBuilder = $connection->createQueryBuilder();
    $queryBuilder->select('UID, email, username')
        ->from('user_main')
        ->where('email = :email')
        ->setParameter('email', $email);
    
    $user = $queryBuilder->executeQuery()->fetchAssociative();
    
    if (!$user) {
        echo "Error: User with email '{$email}' not found.\n";
        exit(1);
    }
    
    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Update the password
    $updateBuilder = $connection->createQueryBuilder();
    $updateBuilder->update('user_main')
        ->set('password', ':password')
        ->where('UID = :uid')
        ->setParameter('password', $hashedPassword)
        ->setParameter('uid', $user['UID']);
    
    $updateBuilder->executeStatement();
    
    echo "✓ Password reset successfully!\n";
    echo "Email: {$user['email']}\n";
    echo "Username: {$user['username']}\n";
    echo "New password: {$newPassword}\n";
    echo "\nImportant: Please save the new password in a secure location.\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
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
