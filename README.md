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
GRANT ALL PRIVILEGES ON app.* TO 'user'@'localhost';
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
php install.php
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
   php -S localhost:8000
   ```

3. In another terminal, run the Vite dev server for client assets:

   ```bash
   npm run dev
   ```

Visit <http://localhost:8000> in your browser to test the app.
