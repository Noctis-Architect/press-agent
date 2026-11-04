# Wordfence Security (9.0.2) — capability notes

Source: `vendor/plugins/wordfence` (free). Full machine-readable spec: `wordfence.json`.

## Storage
- Config lives in the custom table `{prefix}wfConfig`, not `wp_options`.
- `wfConfig::get($key)` / `wfConfig::set($key, $value)` (`lib/wfConfig.php:552`, `:497`); booleans via `getBool()`; integers are stored as strings.
- Scan state: `wfScanEngine::isScanRunning(false)` (`lib/wfScanEngine.php:93`); start via `wfScanEngine::startScan()` (`:2482`).
- Issues: `new wfIssues()->getIssues(0, $limit)` (`lib/wfIssues.php:600`), count via `getIssueCount()`.
- Detection: `defined('WORDFENCE_VERSION')` (`wordfence.php:41`).

## Exposed settings (high value)
| Key | Type |
|---|---|
| `firewallEnabled`, `loginSecurityEnabled` | bool |
| `alertOn_block`, `alertOn_loginLockout`, `alertOn_scanIssues`, `alertOn_update` | bool |
| `autoUpdate`, `liveTrafficEnabled`, `lowResourceScansEnabled` | bool |
| `loginSec_blockAdminReg`, `loginSec_disableAuthorScan`, `loginSec_disableApplicationPasswords`, `loginSec_lockInvalidUsers` | bool |
| `alertEmails`, `whitelisted`, `howGetIPs`, `scan_include_extra` | string |
| `loginSec_maxFailures`, `loginSec_maxForgotPasswd`, `loginSec_countFailMins`, `loginSec_lockoutMins` | int |
| `maxMem`, `scan_maxIssues`, `alert_maxHourly`, `blockedTime` | int |
| `liveTraf_maxRows`, `liveTraf_maxAge` | int |
| `scanType`, `schedMode`, `manualScanType`, `scan_maxDuration`, `scan_exclude` | string |
| `schedStartHour`, `alertOn_severityLevel` | int |
| `email_summary_interval`, `liveTraf_ignoreUsers`, `liveTraf_ignoreIPs`, `liveTraf_ignoreUA` | string |
| `loginSec_strongPasswds`, `loginSec_breachPasswds`, `loginSec_userBlacklist` | string |
| `autoBlockScanners`, `allowHTTPSCaching`, `loginSec_enableSeparateTwoFactor` | bool |

## Actions
- `scan_status` — running state + new issue count.
- `start_scan` — request a scan; poll status.
- `get_issues` — latest new issues (severity, type, short message).

## Gotchas
- Use `wfConfig` APIs, never raw SQL.
- `wfConfig::set()` rejects arrays; structured values require `set_ser()`/`setJSON()` (not exposed).
- `startScan()` may fork or run in the background.
- Premium features (country blocking, real-time IP blocklist) are not exposed.
