# Password Reset Guide

This guide provides two methods to reset a user password in Garlic-Hub.

## Method 1: Using reset-password-direct.php (Recommended)

This is the simplest method that only requires PHP with SQLite support.

### Prerequisites
- PHP CLI with PDO and SQLite extensions
- Access to the database file (var/garlic_hub.db)

### Usage

```bash
php bin/reset-password-direct.php <database-path> <email> [new-password]
```

### Example

```bash
# Generate a random password
php bin/reset-password-direct.php var/garlic_hub.db 757988@mail.ru

# Or specify a custom password
php bin/reset-password-direct.php var/garlic_hub.db 757988@mail.ru "MyNewPassword123!"
```

### Output

```
✓ Password reset successfully!
Email: 757988@mail.ru
Username: testuser
New password: a1B2c3D4e5F6g7H8
```

---

## Method 2: Using reset-password.php

This method uses Garlic-Hub's full DI container and Doctrine DBAL. It respects your database configuration.

### Prerequisites
- Composer dependencies installed (`composer install`)
- Environment variables configured (.env file)
- Database driver configured (SQLite, MySQL, PostgreSQL)

### Usage

```bash
php bin/reset-password.php <email> [new-password]
```

### Example

```bash
# Generate a random password
php bin/reset-password.php 757988@mail.ru

# Or specify a custom password
php bin/reset-password.php 757988@mail.ru "MyNewPassword123!"
```

---

## Method 3: Direct SQL (For Advanced Users)

If you have direct database access, you can manually update the password:

### SQLite

```sql
UPDATE user_main 
SET password = '$2y$12$...' 
WHERE email = '757988@mail.ru';
```

Where `$2y$12$...` is a bcrypt-hashed password. You can generate one using:

```php
<?php
echo password_hash('YourNewPassword123!', PASSWORD_BCRYPT, ['cost' => 12]);
?>
```

### Security Notes

1. **Passwords are bcrypt-hashed** with a cost of 12
2. **Never store plain passwords** in the database
3. **Use strong passwords** following the pattern: `(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}`
   - At least 8 characters
   - At least one digit
   - At least one lowercase letter
   - At least one uppercase letter

---

## Troubleshooting

### "Database file not found"
- Check the path to your database file
- Make sure you're running the command from the application root directory
- Verify file permissions

### "User with email not found"
- Double-check the email address (case-sensitive)
- Verify the user exists in the database

### PDOException or connection errors
- Verify database file integrity
- Check file permissions (read/write access needed)
- Ensure PHP has SQLite extension enabled: `php -m | grep sqlite`

### Using MySQL or PostgreSQL
- Use `reset-password.php` instead (Method 2)
- Configure your .env file with database credentials

---

## What Happens After Password Reset?

After resetting the password:
1. The user can log in with the new password
2. Sessions are not automatically cleared, but the old session tokens will be invalid on next use
3. No email notification is sent (manual notification recommended)
4. Password change history is not logged

## Security Best Practices

1. **Change the password immediately after login** - Users should set their own password after the forced reset
2. **Use HTTPS** - Always ensure secure connection
3. **Communicate securely** - Don't send passwords via unencrypted channels
4. **Log the action** - Document when and why passwords were reset
5. **Monitor accounts** - Check login patterns after reset
