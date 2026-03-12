
# Internship Project - Setup Instructions

This project implements a Registration, Login, and Profile system using PHP, MySQL, MongoDB, and Redis.

## Prerequisites
Ensure you have the following installed and running:
1. **Web Server** (PHP built-in server or Apache)
2. **PHP 8.x**
3. **MySQL / MariaDB Server**
4. **MongoDB Server**
5. **Redis Server**

## Quick Start (If dependencies are installed)

1.  **Start Database Server**:
    Ensure your MySQL/MariaDB service is running.
    *(Example: `& "C:\Program Files\MariaDB 12.1\bin\mysqld.exe" --console`)*

2.  **Initialize Database**:
    Run `php php/setup_db.php` or visit `http://localhost:8000/php/setup_db.php` in your browser.

3.  **Start PHP Server**:
    Run `php -S localhost:8000` in the project root.

4.  **Access Application**:
    Go to [http://localhost:8000/register.html](http://localhost:8000/register.html).

---

## ⚠️ Important: Enabling MongoDB & Redis
The application uses **MySQL** for login credentials and **MongoDB** for profile data.
If you see **"MongoDB missing"** on your profile, it means the PHP extension is not active.

**To fix this on Windows:**
1.  Download `php_mongodb.dll` from [PECL](https://pecl.php.net/package/mongodb) (Select version matching your PHP version, e.g., 8.3 Thread Safe).
2.  Download `php_redis.dll` from [PECL](https://pecl.php.net/package/redis).
3.  Copy these `.dll` files to your PHP `ext` folder (e.g., `C:\php\ext`).
4.  Edit your `php.ini` file and add:
    ```ini
    extension=mongodb
    extension=redis
    ```
5.  Restart your PHP server.

## Troubleshooting
- **"Class 'mysqli' not found"**: Ensure `extension=mysqli` is enabled in `php.ini`.
- **"Authentication Failed"**: Check your database credentials in `php/config.php`.
- **White Screen / 500 Error**: Check the terminal output or browser console for JSON error messages.
