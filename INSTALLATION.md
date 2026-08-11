# Installation

## WP Blogger v1.1.0

WP Blogger is deployed as a **WordPress Must-Use (MU) plugin**. It is automatically loaded by WordPress and does not require normal plugin activation.

## Recommended Layout

```text
/var/www/html/wp/
├── wp-admin/
├── wp-includes/
├── wp-content/
│   ├── mu-plugins/
│   │   └── wp-blogger.php
│   └── wp-blogger/
│       ├── admin/
│       └── includes/
└── wp-config.php
```

Primary log directory:

```text
/var/log/wp-blogger/
```

Fallback log directory:

```text
/wp-content/uploads/wp-blogger/
```

## Requirements

- WordPress installation with filesystem access
- PHP supported by the installed WordPress release
- Linux server recommended
- Apache or equivalent web server
- SSH/root or sudo access for secure deployment
- PHP process must be able to write to the selected log directory

## 1. Create the MU-Plugin Directory

If it does not already exist:

```bash
cd /var/www/html/wp/wp-content
sudo mkdir -p mu-plugins
sudo chmod 755 mu-plugins
```

## 2. Deploy WP Blogger

Copy the MU bootstrap:

```text
wp-blogger.php
```

to:

```text
/var/www/html/wp/wp-content/mu-plugins/wp-blogger.php
```

Copy the main application directory:

```text
wp-blogger/
```

to:

```text
/var/www/html/wp/wp-content/wp-blogger/
```

No normal WordPress plugin activation is required.

## 3. Create the Primary Log Directory

For a typical Debian/Ubuntu Apache installation using `www-data`:

```bash
sudo mkdir -p /var/log/wp-blogger
sudo chown root:www-data /var/log/wp-blogger
sudo chmod 770 /var/log/wp-blogger
```

Verify:

```bash
ls -ld /var/log/wp-blogger
```

Do not use `chmod 777`.

## 4. Protect Plugin Files

Where operationally possible, keep plugin code owned by root and non-writable by the web-server process.

Example:

```bash
sudo chown -R root:root /var/www/html/wp/wp-content/mu-plugins/wp-blogger.php
sudo chown -R root:root /var/www/html/wp/wp-content/wp-blogger
sudo chmod 644 /var/www/html/wp/wp-content/mu-plugins/wp-blogger.php
sudo find /var/www/html/wp/wp-content/wp-blogger -type f -exec chmod 644 {} \;
sudo find /var/www/html/wp/wp-content/wp-blogger -type d -exec chmod 755 {} \;
```

Adapt ownership if your deployment model requires a different account.

## 5. Verify PHP Syntax

```bash
php -l /var/www/html/wp/wp-content/mu-plugins/wp-blogger.php
```

Check additional PHP files if needed:

```bash
find /var/www/html/wp/wp-content/wp-blogger -name "*.php" -exec php -l {} \;
```

## 6. Confirm WordPress Loads the Plugin

Log in to WordPress administration.

Navigate to:

```text
Plugins → Must-Use
```

WP Blogger should be listed.

The administration interface should appear under:

```text
WP Admin → Blogger
```

with:

```text
Blogger
├── Activity
└── Security Events
```

## 7. Verify Logging

Check the log directory:

```bash
ls -lah /var/log/wp-blogger/
```

Monitor the current monthly file:

```bash
tail -f /var/log/wp-blogger/wp-blogger-$(date +%Y-%m).jsonl
```

Then perform a controlled WordPress action, such as:

- log in;
- log out;
- edit a test page.

Confirm that a new JSONL record is written.

## 8. Create the Integrity Baseline

Before establishing the first baseline, ensure that the WordPress installation has been reviewed and is believed to be clean.

Recommended sequence:

```text
Verify WordPress Core
→ Verify plugins
→ Verify themes
→ Inspect uploads
→ Review wp-config.php
→ Review .htaccess
→ Create baseline
```

Then use:

```text
Blogger → Security Events → Run Security Scan Now
```

The integrity state is stored in:

```text
/var/log/wp-blogger/integrity-state.json
```

## 9. Verify Automatic Security Scanning

WP Blogger schedules periodic integrity checks using WP-Cron.

The intended interval is approximately 15 minutes, but WP-Cron is traffic-driven and does not guarantee exact execution times.

For critical environments, consider a future system-cron integration or external monitoring layer.

## 10. Fallback Logging

If the primary log directory cannot be used, WP Blogger can fall back to:

```text
/wp-content/uploads/wp-blogger/
```

Because this location is under the WordPress web tree, direct HTTP access should be blocked at the web-server level.

The primary `/var/log/wp-blogger/` location is preferred.

## Troubleshooting

### Blogger menu is not visible

Check:

```bash
ls -lah /var/www/html/wp/wp-content/mu-plugins/
cat /var/www/html/wp/wp-content/mu-plugins/wp-blogger.php
```

Then validate syntax:

```bash
php -l /var/www/html/wp/wp-content/mu-plugins/wp-blogger.php
```

### No log files are created

Check permissions:

```bash
ls -ld /var/log/wp-blogger
```

Check Apache/PHP error logs and verify the PHP service account can write to the directory.

### Integrity scan reports many changes

If this occurs immediately after a legitimate WordPress, plugin, or theme update, review the changes before establishing a new trusted baseline.

Do not automatically trust changed files without verification.

## Uninstallation

Because WP Blogger is an MU plugin, there is no standard WordPress "Deactivate" action.

To stop loading it:

```bash
sudo mv /var/www/html/wp/wp-content/mu-plugins/wp-blogger.php \
/var/www/html/wp/wp-content/mu-plugins/wp-blogger.php.disabled
```

After confirming that it is no longer required, the main code directory may be removed.

Preserve audit logs if they may be needed for incident response or compliance.
