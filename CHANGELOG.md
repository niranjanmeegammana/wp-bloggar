# Changelog

All notable changes to WP Blogger are documented in this file.

The project uses semantic-style version numbering where practical.

---

## [1.1.0] - 2026-08

### Added

- Filesystem integrity monitoring.
- SHA-256 file hashing for monitored WordPress files.
- Integrity baseline stored as `integrity-state.json`.
- Detection of newly added monitored files.
- Detection of modified monitored files.
- Detection of deleted monitored files.
- Monitoring of `wp-config.php`.
- Monitoring of `.htaccess`.
- Monitoring of `wp-admin/`.
- Monitoring of `wp-includes/`.
- Monitoring of plugin files.
- Monitoring of theme files.
- Monitoring of Must-Use plugin files.
- Detection of executable/PHP files in the WordPress uploads area.
- Suspicious filename indicators including common web-shell-style names.
- XML-RPC activity logging.
- Additional REST API user/security activity logging.
- Failed-login counting and brute-force indicator thresholds.
- Security escalation thresholds at 5, 10 and 20 failed attempts from the same source.
- `Blogger → Security Events` administration view.
- Manual `Run Security Scan Now` function.
- Periodic integrity scans using WP-Cron.
- Security-focused filtering of high-value events.

### Changed

- WP Blogger expanded from a WordPress activity logger into a combined activity and integrity-monitoring system.
- Security-relevant records continue to use the monthly JSONL audit stream.
- Integrity state is stored in the protected logging area rather than the WordPress database where possible.

### Security

- Added monitoring for direct filesystem modification that may bypass normal WordPress action hooks.
- Added support for identifying unexpected PHP files in upload locations.
- Added monitoring of WP Blogger's MU-plugin environment.

### Known Limitations

- WP-Cron execution is traffic-dependent and not guaranteed to run exactly every 15 minutes.
- The initial integrity baseline must be created from a trusted installation.
- Local logs can be altered by an attacker with sufficient server privileges.
- Filename indicators are heuristic and are not malware signatures.
- Integrity change detection does not determine whether a modification is legitimate or malicious.

---

## [1.0.0] - 2026-08

### Added

- Initial WP Blogger release.
- WordPress Must-Use plugin deployment.
- MU bootstrap loader.
- Main application code stored under `/wp-content/wp-blogger/`.
- Primary log path `/var/log/wp-blogger/`.
- Fallback log path `/wp-content/uploads/wp-blogger/`.
- Monthly JSONL log rotation.
- WordPress administration activity viewer.
- Authentication event logging.
- Successful login logging.
- Failed login logging.
- Logout logging.
- User creation logging.
- User deletion logging.
- User role change logging.
- User profile update logging.
- Plugin activation logging.
- Plugin deactivation logging.
- Plugin deletion logging.
- Theme switching logging.
- WordPress Core upgrade logging.
- Plugin upgrade logging.
- Theme upgrade logging.
- Post/page creation logging.
- Post/page update logging.
- Post/page deletion logging.
- Media upload logging.
- Media deletion logging.
- WordPress option-change logging.
- Event severity model.
- User ID and username context.
- Source IP context.
- Request URI context.
- UTC timestamps.

### Design

- Primary audit records stored outside the WordPress database.
- JSONL format selected for compatibility with command-line tools and SIEM ingestion.
- MU-plugin design selected to remove dependency on normal WordPress plugin activation/deactivation.

---

## Planned

Potential future work may include:

- configurable event selection;
- configurable scan schedules;
- configurable file exclusions;
- trusted baseline replacement workflow;
- retention controls;
- CSV and JSON export;
- email alerting;
- remote syslog;
- Wazuh integration;
- SIEM forwarding;
- Core checksum validation against official WordPress distributions;
- risk scoring;
- event correlation;
- incident timeline generation;
- external immutable log forwarding;
- system-cron integrity scanning.
