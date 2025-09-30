# pwa-template

A template project for basic pwa/spa.

## Local development and testing

### Prerequisites

- [Node.js](https://nodejs.org/) 18+ (for building assets)
- PHP 8 with the `pdo_mysql` extension
- MySQL or MariaDB server

### Install PHP

**Windows (winget):**

```powershell
winget install -e --id PHP.PHP.8.4
```

**macOS (Homebrew):**

```bash
brew install php
```

**Ubuntu/Debian:**

```bash
sudo apt update
sudo apt install php php-mysql
```

Verify with `php -v`.

### Install SQL server

**Windows (winget):**

```powershell
winget install -e --id Oracle.MySQL
```

After installation, add mysql to path:

- Locate the MySQL Installation Directory (MySQL installed via winget is typically located in `C:\Program Files\MySQL\MySQL Server <version>\bin`)
- Open File Explorer and navigate to `C:\Program Files\MySQL` to confirm the exact path.

```powershell
[Environment]::SetEnvironmentVariable("Path", [Environment]::GetEnvironmentVariable("Path", [EnvironmentVariableTarget]::Machine) + ";C:\Program Files\MySQL\MySQL Server 8.4\bin", [EnvironmentVariableTarget]::Machine)
```

Install the MySQL service

```powershell
& "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --install
& "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --initialize --console
```

a temporary password will be generated for user `root@localhost`

Start MySQL service, secure the root account and create a regular user:

```sql
mysql -u root -p
ALTER USER 'root'@'localhost' IDENTIFIED BY 'new_password';
CREATE USER 'user'@'localhost' IDENTIFIED BY 'user_password';
GRANT ALL PRIVILEGES ON app.* TO 'root'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON app.* TO 'user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**macOS:**

```bash
brew install mysql
brew services start mysql
```

**Ubuntu/Debian:**

```bash
sudo apt install mysql-server
sudo service mysql start    # or systemctl start mysql
```

Verify with `mysql --version`.

### Configure database

Copy the example environment file and update it with your credentials:

```bash
cp env.example .env
# Use the root credentials above for the initial setup
```

You can generate JWT secret with

```powershell
$bytes = New-Object byte[] 64
[Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
[Convert]::ToBase64String($bytes)
```

Run the provided installation script to create the database and `users` table:

```bash
php server/install.php
```

Afterwards, update `.env` to use the `user` account you created.

### Install dependencies

```bash
npm install
```

### Run locally

1. Ensure the SQL service is running.
2. Start the PHP development server from the project root:

    ```bash
    npm run start
    ```

3. In another terminal, run the Vite dev server for client assets:

    ```bash
    npm run dev
    ```

Visit <http://localhost:8000> in your browser to test the app.

## PHP configuration (uploads + WebP)

Large image uploads and server‑side conversion to WebP require a couple of PHP settings and extensions.

1. Locate your php.ini

- Run `php --ini` and note the “Loaded Configuration File”.
- Examples:
    - Windows (winget PHP): typically `C:\Program Files\PHP\8.x\php.ini`
    - macOS (Homebrew): `/opt/homebrew/etc/php/8.x/php.ini`
    - Ubuntu/Debian: `/etc/php/8.x/cli/php.ini` (CLI/built‑in server) and `/etc/php/8.x/fpm/php.ini` (FPM)

2. Enable an image library (pick one)

- GD (recommended, lightweight): ensure this line is present/uncommented in php.ini:
    - `extension=gd`
- Imagick (optional alternative): if installed, enable:
    - `extension=imagick`

3. Verify WebP support

- Restart your PHP server, then check:
    - Cross‑platform: `php -i | findstr /I "gd\|webp"` (Windows PowerShell/CMD)
    - Linux/macOS: `php -i | grep -iE "gd|webp"`
- You should see `GD Support => enabled` and `WebP Support => enabled` or Imagick installed.

4. Allow larger uploads (e.g., 32 MB)

- Edit php.ini and set:
    - `upload_max_filesize = 32M`
    - `post_max_size = 32M` (must be ≥ upload_max_filesize)
    - Optionally, increase `memory_limit` (e.g., `256M`) for processing large images.
- Restart your PHP server after saving changes.

5. Web servers (if not using the built‑in dev server)

- Apache (mod_php): you may alternatively set in `.htaccess`:
    - `php_value upload_max_filesize 32M`
    - `php_value post_max_size 32M`
- Nginx + PHP‑FPM: also set in Nginx site config:
    - `client_max_body_size 32M;` and reload Nginx.

Notes

- For local development, the npm script `npm run dev:php` starts PHP with higher limits (`post_max_size` and `upload_max_filesize` set to 32M). If you run `php -S` manually, prefer editing php.ini as above.
- If neither GD (with WebP) nor Imagick is available, image conversion will fail; the UI will show a warning. Enable one of them to convert PNG/JPEG/GIF to WebP on upload.
