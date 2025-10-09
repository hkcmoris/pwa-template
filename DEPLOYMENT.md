# Deployment Guide

This document explains how to deploy the application to a production hosting account using the FileZilla FTP client. The steps assume you already have the following:

- Access credentials for the production FTP account (host, username, password, and port).
- Access to the production database (hostname, database name, username, password).
- A terminal or SSH client to run one-off commands that cannot be executed via FTP (optional but recommended).
- The latest production build artifacts generated with `npm run build`.

> **Important:** The production hosting environment only supports PHP. Node.js is used locally to build assets before upload.

---

## 1. Prepare the Build Locally

1. Ensure dependencies are installed: `npm install`.
2. Run the production build: `npm run build`.
3. Verify the `server/`, `public/`, and any other PHP-facing directories contain the generated assets required for deployment.
4. Review the build output (e.g., `public/assets/`) for hashed filenames and confirm only necessary files will be uploaded.

---

## 2. Export Database Configuration

1. Copy the production database credentials into a secure note. You will need the host, database name, username, and password.
2. Update `server/config.php` (or the equivalent environment configuration file) locally with the production values.
3. If the project uses environment variables (e.g., `.env`), duplicate the example file, populate the production values, and exclude the file from source control if required.

---

## 3. Open FileZilla and Configure the Site

1. Launch FileZilla.
2. Open **File → Site Manager…**.
3. Click **New Site** and name it (for example, `Project Production`).
4. Fill in the **Host** with the production FTP hostname.
5. Set **Port** to the value provided by the hosting provider (default FTP: `21`, SFTP: `22`).
6. Select the appropriate **Protocol** (`FTP - File Transfer Protocol` or `SFTP - SSH File Transfer Protocol`) depending on the hosting setup. SFTP is preferred for security.
7. Set **Encryption** to `Require explicit FTP over TLS` (for FTP) or leave as `Use explicit FTP over TLS if available` if unsure. For SFTP, the encryption is handled automatically.
8. Choose **Logon Type → Normal** and enter the **User** and **Password**.
9. Click **Connect** (or **OK** to save and connect later).

---

## 4. Upload the Application Files

1. In FileZilla, browse the **Local site** pane to the project directory containing the built artifacts (typically the repository root).
2. In the **Remote site** pane, navigate to the public web root on the server (often named `public_html`, `www`, or the document root provided by the host).
3. Before uploading, remove any existing outdated files on the server that should be replaced. Maintain backups when necessary.
4. Upload the following directories/files:
   - `/server` (PHP application code).
   - `/public` (static assets, service worker, manifest, etc.).
   - `/install.php` (installer script, if it is not already on the server).
   - Any supporting directories like `/storage`, `/config`, or `/vendor` depending on your project layout.
5. Ensure `.htaccess` (if used) is transferred; enable FileZilla’s option **Server → Force showing hidden files** to view dotfiles.
6. Wait until FileZilla completes all transfers with zero failed files. Retry any failed transfers.

> **Tip:** Uploading can be faster if you compress the build locally, upload the archive, and extract it via SSH. However, this guide assumes FTP-only access.

---

## 5. Set File and Directory Permissions

After uploading, correct permissions to meet the host’s security requirements:

1. In FileZilla, right-click the `public` directory and select **File permissions…**.
2. Set directories to `755` (read/execute for everyone, write for owner).
3. Set PHP files to `644` (read for everyone, write for owner).
4. For any directory that must be writable by PHP (e.g., `storage`, `cache`, or `uploads`), set permissions to `775` or `755` depending on the host. Avoid `777` unless the host explicitly requires it.
5. Apply permissions recursively where appropriate.

If you have SSH access, you can alternatively run commands such as:

```bash
find public -type d -exec chmod 755 {} \;
find public -type f -exec chmod 644 {} \;
```

---

## 6. Run the Installer (`install.php`)

1. Confirm that `install.php` is present in the web root on the server.
2. In a browser, navigate to `https://your-domain.example/install.php`.
3. Follow the on-screen prompts:
   - Provide database host, name, username, and password.
   - Confirm the base URL of the application.
   - Configure any mail or cache settings requested by the installer.
4. Submit the form to run the installation. The script will:
   - Create necessary database tables.
   - Seed default configuration values.
   - Create the storage directories if they do not exist.
5. When the installer completes, note any credentials displayed for the initial administrative user.
6. Delete `install.php` from the server after success to prevent unauthorized reinstallation. You can do this directly in FileZilla (right-click → **Delete**).

---

## 7. Create the First Superadmin User

If the installer did not create an admin account automatically, manually create one:

1. Access the application in a browser at `https://your-domain.example/`.
2. If prompted, complete any first-run setup steps.
3. Visit the registration or admin creation page (commonly `/admin/setup` or `/register`).
4. Provide the required information (email, password, name).
5. Submit the form to create the superadmin user. Check your email for confirmation if the application requires verification.
6. Log in with the new superadmin credentials and verify access to the administrative dashboard.

> **Alternative (database script):** If there is no UI flow, run a SQL insert script against the database to create the first user. Ensure the password is hashed using the same algorithm as the application (e.g., PHP’s `password_hash`).

---

## 8. Post-Deployment Verification

1. Load the homepage and confirm assets, service worker registration, and manifest link all respond with `200` status.
2. Browse key pages and ensure htmx-enhanced navigation works without JavaScript errors.
3. Test user authentication flows (login, logout, password reset).
4. Confirm email sending (if configured) by triggering a password reset or contact form.
5. Inspect browser console for errors or failed network requests.
6. Run any automated smoke tests if available.

---

## 9. Ongoing Maintenance

- Keep a local record of the exact build version deployed (commit hash and build timestamp).
- Schedule regular backups of the database and uploaded assets.
- Repeat the build and upload process for each release. Upload only the files that changed when possible to reduce downtime.
- Review server logs periodically to monitor performance and errors.
- Update any cron jobs or scheduled tasks as required by the application.

---

## 10. Rollback Strategy

1. Maintain a copy of the previous release (files and database backup).
2. If you need to roll back, upload the previous build via FileZilla and restore the database backup.
3. Clear application caches or temporary directories if required.
4. Verify the application behaves as expected after the rollback.

---

## 11. Security Considerations

- Use SFTP whenever possible to encrypt credentials during transfer.
- Limit FTP account permissions to the specific directory required for deployment.
- Rotate FTP and database passwords periodically and after any team member leaves.
- Remove installation and maintenance scripts (`install.php`, `upgrade.php`) immediately after use.
- Ensure writable directories are not executable to reduce risk.

Following these steps ensures a repeatable and secure deployment workflow using the FileZilla FTP client.
