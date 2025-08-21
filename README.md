# pwa-template

A template project for basic pwa/spa.

## Local development and testing

### Prerequisites

- [Node.js](https://nodejs.org/) 18+ (for building assets)
- PHP 8 with the `pdo_mysql` extension
- MySQL or MariaDB server

### Install PHP

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

```sql
mysql -u root -p
CREATE DATABASE app;
CREATE USER 'appuser'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON app.* TO 'appuser'@'localhost';
FLUSH PRIVILEGES;
```

Copy the example environment file and update it with your credentials:

```bash
cp env.example .env
# edit .env to match the database details above
```

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
