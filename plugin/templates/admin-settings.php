<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

$site_url = get_site_url();

// Handle Rollback action
$alert_message_en = null;
$alert_message_fa = null;
$alert_type = 'success';

if ( isset( $_POST['pressagent_rollback_snapshot'] ) && check_admin_referer( 'pressagent_rollback_snapshot_nonce' ) ) {
    $snapshot_id = (int) $_POST['snapshot_id'];
    $res = PressAgent_Action_Guard::rollback( $snapshot_id );
    if ( is_wp_error( $res ) ) {
        $alert_message_en = 'Rollback failed: ' . $res->get_error_message();
        $alert_message_fa = 'خطا در بازگردانی: ' . $res->get_error_message();
        $alert_type = 'error';
    } else {
        $alert_message_en = 'Snapshot #' . $snapshot_id . ' was successfully restored and site caches were purged.';
        $alert_message_fa = 'اسنپ‌شات #' . $snapshot_id . ' با موفقیت بازگردانی شد و کش سایت تخلیه گردید.';
        $alert_type = 'success';
    }
}

// Handle quick default token generation
if ( isset( $_POST['pressagent_quick_setup'] ) && check_admin_referer( 'pressagent_quick_setup_nonce' ) ) {
    $default_scopes = array( 'pages:read', 'pages:write', 'elementor:read', 'elementor:write', 'settings:read', 'settings:write', 'cache:purge', 'debug:read', 'debug:write', 'security:read' );
    $new_token = PressAgent_Auth::generate_token( 'Default Standard Token (' . current_time( 'Y-m-d H:i' ) . ')', $default_scopes, get_current_user_id() );
    $alert_message_en = 'Standard access token generated successfully. IDE connection configurations are now active.';
    $alert_message_fa = 'کلید دسترسی استاندارد با موفقیت ایجاد شد و کدهای اتصال IDE فعال شدند.';
    $alert_type = 'success';
}

// Handle custom token generation
if ( isset( $_POST['pressagent_generate_token'] ) && check_admin_referer( 'pressagent_generate_token_nonce' ) ) {
    $label = sanitize_text_field( $_POST['token_label'] );
    $scopes = isset( $_POST['token_scopes'] ) ? array_map( 'sanitize_text_field', $_POST['token_scopes'] ) : array();
    if ( empty( $scopes ) ) {
        $alert_message_en = 'Please select at least one permission scope for the token.';
        $alert_message_fa = 'لطفاً حداقل یک سطح دسترسی برای کلید انتخاب کنید.';
        $alert_type = 'error';
    } else {
        $new_token = PressAgent_Auth::generate_token( $label, $scopes, get_current_user_id() );
        $alert_message_en = 'Access token issued successfully. Copy your token below.';
        $alert_message_fa = 'کلید دسترسی اختصاصی با موفقیت ساخته شد و آماده استفاده است.';
        $alert_type = 'success';
    }
}

// Handle token revoke
if ( isset( $_POST['pressagent_revoke_token'] ) && check_admin_referer( 'pressagent_revoke_token_nonce' ) ) {
    $token_id = sanitize_text_field( $_POST['token_id'] );
    PressAgent_Auth::revoke_token( $token_id );
    $alert_message_en = 'Access token revoked successfully.';
    $alert_message_fa = 'کلید دسترسی با موفقیت باطل گردید.';
    $alert_type = 'warning';
}

// Handle settings allowlist update
if ( isset( $_POST['pressagent_save_allowlist'] ) && check_admin_referer( 'pressagent_save_allowlist_nonce' ) ) {
    $raw_rules = sanitize_textarea_field( $_POST['allowlist_rules'] );
    $lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $raw_rules ) ) ) );
    $allowlist_option = get_option( 'pressagent_settings_allowlist', array() );
    $allowlist_option['custom'] = array_values( array_unique( $lines ) );
    update_option( 'pressagent_settings_allowlist', $allowlist_option );
    $alert_message_en = 'Settings allowlist updated successfully.';
    $alert_message_fa = 'لیست سفید تنظیمات با موفقیت به‌روزرسانی شد.';
    $alert_type = 'success';
}

$tokens = get_option( 'pressagent_tokens', array() );
$snapshots = PressAgent_Action_Guard::get_snapshots( 50 );
$total_snapshots = PressAgent_Action_Guard::count_snapshots();
$allowlist = get_option( 'pressagent_settings_allowlist', array() );
$custom_allowlist = isset( $allowlist['custom'] ) && is_array( $allowlist['custom'] ) ? $allowlist['custom'] : array();

// Diagnostics & Security Data
$security_summary = class_exists( 'PressAgent_Security_Audit' ) ? PressAgent_Security_Audit::get_summary() : array();
$debug_logs = class_exists( 'PressAgent_Debug_Log_Parser' ) ? PressAgent_Debug_Log_Parser::read_log( 15, 'all' ) : array();

// Active raw token for MCP config (only available immediately after generation)
$active_token = isset( $new_token ) ? $new_token : '';
$has_active_token = ! empty( $active_token );
?>

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<div class="wrap pressagent-root" id="pressagent-app" data-lang="en" dir="ltr">
    <style>
        /* Modern Engineering Design System */
        :root {
            --pa-bg: #f8fafc;
            --pa-card: #ffffff;
            --pa-border: #e2e8f0;
            --pa-border-subtle: #f1f5f9;
            --pa-text-main: #0f172a;
            --pa-text-muted: #64748b;
            --pa-text-subtle: #94a3b8;
            --pa-primary: #2563eb;
            --pa-primary-hover: #1d4ed8;
            --pa-primary-subtle: #eff6ff;
            --pa-primary-border: #bfdbfe;
            --pa-success: #059669;
            --pa-success-bg: #ecfdf5;
            --pa-success-border: #a7f3d0;
            --pa-warning: #d97706;
            --pa-warning-bg: #fffbeb;
            --pa-warning-border: #fde68a;
            --pa-danger: #dc2626;
            --pa-danger-bg: #fef2f2;
            --pa-danger-border: #fecaca;
            --pa-dark-surface: #090d16;
            --pa-dark-card: #0f172a;
            --pa-dark-border: #1e293b;
            --pa-font-en: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            --pa-font-fa: 'Vazirmatn', -apple-system, BlinkMacSystemFont, "Segoe UI", Tahoma, sans-serif;
            --pa-font-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        .pressagent-root {
            max-width: 1240px;
            margin: 24px auto 48px auto;
            color: var(--pa-text-main);
            font-family: var(--pa-font-en);
            line-height: 1.5;
            box-sizing: border-box;
        }

        .pressagent-root * {
            box-sizing: border-box;
        }

        /* Language Toggle Rules */
        .pressagent-root[data-lang="fa"] {
            font-family: var(--pa-font-fa);
        }
        .pressagent-root[data-lang="en"] .pa-fa { display: none !important; }
        .pressagent-root[data-lang="fa"] .pa-en { display: none !important; }

        /* Top Bar */
        .pa-navbar {
            background: var(--pa-dark-surface);
            border: 1px solid var(--pa-dark-border);
            border-radius: 12px;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.12);
        }
        .pa-nav-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .pa-nav-logo {
            width: 36px;
            height: 36px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #38bdf8;
            font-weight: 700;
            font-family: var(--pa-font-mono);
            font-size: 14px;
        }
        .pa-nav-title {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            color: #f8fafc;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.01em;
        }
        .pa-nav-subtitle {
            margin: 2px 0 0 0;
            font-size: 12px;
            color: #94a3b8;
        }
        .pa-nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Language Switcher */
        .pa-lang-switcher {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 3px;
            display: flex;
            align-items: center;
            gap: 2px;
        }
        .pa-lang-btn {
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .pa-lang-btn.active {
            background: #2563eb;
            color: #ffffff;
        }
        .pa-lang-btn:hover:not(.active) {
            color: #f8fafc;
        }

        .pa-badge-version {
            background: rgba(56, 189, 248, 0.1);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.25);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            font-family: var(--pa-font-mono);
        }

        /* Notification Banner */
        .pa-alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-width: 1px;
            border-style: solid;
        }
        .pa-alert-success {
            background: var(--pa-success-bg);
            color: #065f46;
            border-color: var(--pa-success-border);
        }
        .pa-alert-error {
            background: var(--pa-danger-bg);
            color: #991b1b;
            border-color: var(--pa-danger-border);
        }
        .pa-alert-warning {
            background: var(--pa-warning-bg);
            color: #92400e;
            border-color: var(--pa-warning-border);
        }

        /* Token Highlight Box */
        .pa-token-box {
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .pa-token-box-header {
            font-size: 13px;
            font-weight: 700;
            color: #15803d;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pa-token-box-input {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .pa-token-value {
            flex: 1;
            background: #ffffff;
            border: 1px solid #bbf7d0;
            padding: 8px 14px;
            border-radius: 6px;
            font-family: var(--pa-font-mono);
            font-size: 13px;
            color: #166534;
            word-break: break-all;
        }

        /* Tab Navigation */
        .pa-tabs-nav {
            display: flex;
            gap: 6px;
            border-bottom: 1px solid var(--pa-border);
            margin-bottom: 24px;
            padding-bottom: 2px;
            overflow-x: auto;
        }
        .pa-tab-link {
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            color: var(--pa-text-muted);
            padding: 10px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
            outline: none;
        }
        .pa-tab-link:hover {
            color: var(--pa-text-main);
        }
        .pa-tab-link.active {
            color: var(--pa-primary);
            border-bottom-color: var(--pa-primary);
        }
        .pa-tab-badge {
            background: var(--pa-border);
            color: var(--pa-text-muted);
            padding: 1px 7px;
            border-radius: 10px;
            font-size: 11px;
            font-family: var(--pa-font-mono);
            font-weight: 600;
        }

        /* Tab Panes */
        .pa-tab-pane {
            display: none;
        }
        .pa-tab-pane.active {
            display: block;
        }

        /* Cards & Grids */
        .pa-card {
            background: var(--pa-card);
            border: 1px solid var(--pa-border);
            border-radius: 10px;
            padding: 22px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.02);
        }
        .pa-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--pa-border-subtle);
        }
        .pa-card-title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: var(--pa-text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pa-card-desc {
            font-size: 12px;
            color: var(--pa-text-muted);
            margin-top: 2px;
            font-weight: 400;
        }

        /* Stat Grid */
        .pa-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .pa-stat-card {
            background: var(--pa-card);
            border: 1px solid var(--pa-border);
            border-radius: 10px;
            padding: 18px 20px;
        }
        .pa-stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--pa-text-muted);
            font-weight: 600;
            margin-bottom: 6px;
        }
        .pa-stat-val {
            font-size: 22px;
            font-weight: 700;
            color: var(--pa-text-main);
            font-family: var(--pa-font-mono);
            line-height: 1.1;
        }
        .pa-stat-sub {
            font-size: 12px;
            color: var(--pa-text-subtle);
            margin-top: 6px;
        }

        /* Buttons */
        .pa-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.15s ease;
            text-decoration: none;
            line-height: 1;
        }
        .pa-btn-primary {
            background: var(--pa-primary);
            color: #ffffff;
            border-color: var(--pa-primary);
        }
        .pa-btn-primary:hover {
            background: var(--pa-primary-hover);
            color: #ffffff;
        }
        .pa-btn-secondary {
            background: #ffffff;
            color: var(--pa-text-main);
            border-color: var(--pa-border);
        }
        .pa-btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        .pa-btn-danger {
            background: #ffffff;
            color: var(--pa-danger);
            border-color: var(--pa-danger-border);
        }
        .pa-btn-danger:hover {
            background: var(--pa-danger-bg);
        }
        .pa-btn-sm {
            padding: 5px 10px;
            font-size: 11px;
        }

        /* Tables */
        .pa-table-wrapper {
            overflow-x: auto;
            border: 1px solid var(--pa-border);
            border-radius: 8px;
        }
        .pa-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            text-align: left;
        }
        .pressagent-root[data-lang="fa"] .pa-table {
            text-align: right;
        }
        .pa-table th {
            background: #f8fafc;
            color: var(--pa-text-muted);
            font-weight: 600;
            padding: 10px 14px;
            border-bottom: 1px solid var(--pa-border);
            text-transform: uppercase;
            font-size: 10.5px;
            letter-spacing: 0.04em;
        }
        .pa-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--pa-border-subtle);
            color: var(--pa-text-main);
            vertical-align: middle;
        }
        .pa-table tr:last-child td {
            border-bottom: none;
        }
        .pa-table tr:hover td {
            background: #fcfdfe;
        }
        .pa-mono {
            font-family: var(--pa-font-mono);
        }

        /* Chips & Badges */
        .pa-chip {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            font-family: var(--pa-font-mono);
            border: 1px solid transparent;
        }
        .pa-chip-neutral { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
        .pa-chip-primary { background: var(--pa-primary-subtle); color: var(--pa-primary); border-color: var(--pa-primary-border); }
        .pa-chip-success { background: var(--pa-success-bg); color: #047857; border-color: var(--pa-success-border); }
        .pa-chip-warning { background: var(--pa-warning-bg); color: #b45309; border-color: var(--pa-warning-border); }
        .pa-chip-danger  { background: var(--pa-danger-bg); color: #b91c1c; border-color: var(--pa-danger-border); }

        /* IDE Connectors Grid */
        .pa-client-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .pa-client-btn {
            background: #ffffff;
            border: 1px solid var(--pa-border);
            border-radius: 8px;
            padding: 12px 14px;
            text-align: left;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .pressagent-root[data-lang="fa"] .pa-client-btn {
            text-align: right;
        }
        .pa-client-btn:hover {
            border-color: #94a3b8;
            background: #f8fafc;
        }
        .pa-client-btn.active {
            border-color: var(--pa-primary);
            background: var(--pa-primary-subtle);
            box-shadow: 0 0 0 1px var(--pa-primary);
        }
        .pa-client-btn-title {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--pa-text-main);
        }
        .pa-client-btn-sub {
            font-size: 11px;
            color: var(--pa-text-muted);
            margin-top: 1px;
        }

        /* Terminal Code Block */
        .pa-terminal {
            background: var(--pa-dark-card);
            border: 1px solid var(--pa-dark-border);
            border-radius: 8px;
            overflow: hidden;
        }
        .pa-terminal-header {
            background: var(--pa-dark-surface);
            padding: 8px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--pa-dark-border);
        }
        .pa-terminal-title {
            font-size: 11px;
            font-family: var(--pa-font-mono);
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .pa-terminal-dots {
            display: flex;
            gap: 4px;
        }
        .pa-terminal-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #334155;
        }
        .pa-terminal-body {
            padding: 14px 16px;
            margin: 0;
            font-family: var(--pa-font-mono);
            font-size: 12px;
            color: #e2e8f0;
            overflow-x: auto;
            white-space: pre;
            line-height: 1.6;
        }

        /* Scope Cards Grid */
        .pa-scopes-section {
            margin-bottom: 20px;
        }
        .pa-scopes-section-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--pa-text-muted);
            font-weight: 700;
            margin-bottom: 10px;
            padding-bottom: 4px;
            border-bottom: 1px solid var(--pa-border-subtle);
        }
        .pa-scopes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 10px;
        }
        .pa-scope-card {
            background: #ffffff;
            border: 1px solid var(--pa-border);
            border-radius: 6px;
            padding: 10px 12px;
            cursor: pointer;
            transition: all 0.12s ease;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            user-select: none;
        }
        .pa-scope-card:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }
        .pa-scope-card.is-checked {
            border-color: var(--pa-primary-border);
            background: var(--pa-primary-subtle);
        }
        .pa-scope-card input[type="checkbox"] {
            margin-top: 2px;
            cursor: pointer;
        }
        .pa-scope-name {
            display: block;
            font-family: var(--pa-font-mono);
            font-size: 11.5px;
            font-weight: 700;
            color: var(--pa-text-main);
        }
        .pa-scope-desc {
            display: block;
            font-size: 11px;
            color: var(--pa-text-muted);
            margin-top: 2px;
            line-height: 1.4;
        }

        /* Form Inputs */
        .pa-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--pa-border);
            border-radius: 6px;
            font-size: 13px;
            color: var(--pa-text-main);
            background: #ffffff;
            outline: none;
            transition: border-color 0.15s ease;
        }
        .pa-input:focus {
            border-color: var(--pa-primary);
            box-shadow: 0 0 0 1px var(--pa-primary);
        }
        .pa-textarea {
            font-family: var(--pa-font-mono);
            font-size: 12px;
            line-height: 1.6;
            min-height: 140px;
        }

        /* Preset Toolbar */
        .pa-toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .pa-toolbar-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--pa-text-muted);
            margin-right: 4px;
        }
        .pressagent-root[data-lang="fa"] .pa-toolbar-label {
            margin-right: 0;
            margin-left: 4px;
        }
    </style>

    <!-- Top Bar -->
    <div class="pa-navbar">
        <div class="pa-nav-brand">
            <div class="pa-nav-logo">PA</div>
            <div>
                <h1 class="pa-nav-title">
                    PressAgent
                    <span class="pa-badge-version">v1.0.0</span>
                </h1>
                <p class="pa-nav-subtitle">
                    <span class="pa-en">Autonomous AI Operations &amp; Design Bridge for WordPress</span>
                    <span class="pa-fa">پل ارتباطی هوشمند و امن مدیریت و طراحی وردپرس برای ایجنت‌های هوش مصنوعی</span>
                </p>
            </div>
        </div>
        <div class="pa-nav-actions">
            <!-- Language Switcher -->
            <div class="pa-lang-switcher">
                <button type="button" class="pa-lang-btn active" id="btn-lang-en" onclick="setLanguage('en')">English</button>
                <button type="button" class="pa-lang-btn" id="btn-lang-fa" onclick="setLanguage('fa')">فارسی</button>
            </div>

            <a href="https://github.com/Noctis-Architect/press-agent" target="_blank" class="pa-btn pa-btn-secondary pa-btn-sm" style="background:#1e293b; color:#f8fafc; border-color:#334155;">
                <span class="pa-en">GitHub</span>
                <span class="pa-fa">گیت‌هاب</span>
            </a>
        </div>
    </div>

    <!-- Alert Notification -->
    <?php if ( $alert_message_en ) : ?>
        <div class="pa-alert pa-alert-<?php echo esc_attr( $alert_type ); ?>">
            <span class="pa-en"><?php echo esc_html( $alert_message_en ); ?></span>
            <span class="pa-fa"><?php echo esc_html( $alert_message_fa ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Newly Generated Token Banner -->
    <?php if ( isset( $new_token ) ) : ?>
        <div class="pa-token-box">
            <div class="pa-token-box-header">
                <span class="pa-en">Active Secret Token Generated</span>
                <span class="pa-fa">کلید دسترسی با موفقیت صادر شد</span>
            </div>
            <div class="pa-token-box-input">
                <div class="pa-token-value" id="raw-token-display"><?php echo esc_html( $new_token ); ?></div>
                <button type="button" class="pa-btn pa-btn-primary" onclick="copyRawToken('<?php echo esc_js( $new_token ); ?>', this)">
                    <span class="pa-en">Copy Token</span>
                    <span class="pa-fa">کپی کلید</span>
                </button>
            </div>
            <p style="margin: 8px 0 0 0; font-size: 11.5px; color: #15803d;">
                <span class="pa-en">Save this secret key immediately. For security, raw tokens are never persisted in plaintext.</span>
                <span class="pa-fa">این کلید را در جای امن ذخیره کنید. به دلایل امنیتی، کلید خام در پایگاه داده نگهداری نمی‌شود.</span>
            </p>
        </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="pa-tabs-nav">
        <button type="button" class="pa-tab-link active" onclick="switchTab('overview', this)">
            <span class="pa-en">Overview &amp; Connect</span>
            <span class="pa-fa">نمای کلی و اتصال</span>
        </button>
        <button type="button" class="pa-tab-link" onclick="switchTab('tokens', this)">
            <span class="pa-en">Access Tokens &amp; RBAC</span>
            <span class="pa-fa">کلیدها و سطوح دسترسی</span>
            <span class="pa-tab-badge"><?php echo count( $tokens ); ?></span>
        </button>
        <button type="button" class="pa-tab-link" onclick="switchTab('snapshots', this)">
            <span class="pa-en">Action Guard™ &amp; Rollback</span>
            <span class="pa-fa">محافظت و بازگردانی</span>
            <span class="pa-tab-badge"><?php echo $total_snapshots; ?></span>
        </button>
        <button type="button" class="pa-tab-link" onclick="switchTab('allowlist', this)">
            <span class="pa-en">Settings Allowlist</span>
            <span class="pa-fa">لیست سفید تنظیمات</span>
        </button>
        <button type="button" class="pa-tab-link" onclick="switchTab('diagnostics', this)">
            <span class="pa-en">Diagnostics &amp; Logs</span>
            <span class="pa-fa">عیب‌یابی و لاگ‌ها</span>
        </button>
    </div>

    <!-- ==================== TAB 1: OVERVIEW & CONNECT ==================== -->
    <div id="tab-overview" class="pa-tab-pane active">
        <!-- Stat Grid -->
        <div class="pa-stats-grid">
            <div class="pa-stat-card">
                <div class="pa-stat-label">
                    <span class="pa-en">Active API Tokens</span>
                    <span class="pa-fa">کلیدهای فعال</span>
                </div>
                <div class="pa-stat-val"><?php echo count( $tokens ); ?></div>
                <div class="pa-stat-sub">
                    <span class="pa-en">Cryptographically hashed</span>
                    <span class="pa-fa">هش‌شده با الگوریتم امن</span>
                </div>
            </div>
            <div class="pa-stat-card">
                <div class="pa-stat-label">
                    <span class="pa-en">Action Guard Snapshots</span>
                    <span class="pa-fa">اسنپ‌شات‌های ثبت‌شده</span>
                </div>
                <div class="pa-stat-val"><?php echo $total_snapshots; ?></div>
                <div class="pa-stat-sub">
                    <span class="pa-en">Ready for 1-click rollback</span>
                    <span class="pa-fa">آماده بازگردانی فوری</span>
                </div>
            </div>
            <div class="pa-stat-card">
                <div class="pa-stat-label">
                    <span class="pa-en">REST API Bridge</span>
                    <span class="pa-fa">وضعیت پل ارتباطی</span>
                </div>
                <div class="pa-stat-val" style="color: var(--pa-success); font-size: 16px;">
                    <span class="pa-en">OPERATIONAL</span>
                    <span class="pa-fa">فعال و آماده</span>
                </div>
                <div class="pa-stat-sub pa-mono" style="font-size: 11px;">/wp-json/pressagent/v1</div>
            </div>
            <div class="pa-stat-card">
                <div class="pa-stat-label">
                    <span class="pa-en">WordPress Environment</span>
                    <span class="pa-fa">محیط وردپرس</span>
                </div>
                <div class="pa-stat-val" style="font-size: 16px;">PHP <?php echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION; ?></div>
                <div class="pa-stat-sub">WordPress <?php echo esc_html( get_bloginfo( 'version' ) ); ?></div>
            </div>
        </div>

        <!-- Quick Token Setup Callout (if none exist) -->
        <?php if ( empty( $tokens ) && ! $has_active_token ) : ?>
            <div class="pa-card" style="background: #eff6ff; border-color: #bfdbfe;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                    <div>
                        <h3 style="margin: 0 0 4px 0; font-size: 14px; font-weight: 700; color: #1e40af;">
                            <span class="pa-en">Quick Setup: Generate Default Access Token</span>
                            <span class="pa-fa">راه‌اندازی سریع: صدور کلید پیش‌فرض</span>
                        </h3>
                        <p style="margin: 0; font-size: 12px; color: #3b82f6;">
                            <span class="pa-en">Issue a standard token with core reading, Elementor builder, cache, and diagnostic permissions.</span>
                            <span class="pa-fa">یک کلید استاندارد با تمامی دسترسی‌های لازم برای طراحی المنتور و عیب‌یابی ایجاد کنید.</span>
                        </p>
                    </div>
                    <form method="post">
                        <?php wp_nonce_field( 'pressagent_quick_setup_nonce' ); ?>
                        <input type="hidden" name="pressagent_quick_setup" value="1">
                        <button type="submit" class="pa-btn pa-btn-primary">
                            <span class="pa-en">Generate Standard Token</span>
                            <span class="pa-fa">صدور کلید استاندارد</span>
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- MCP Client Configuration Hub -->
        <div class="pa-card">
            <div class="pa-card-header">
                <div>
                    <h2 class="pa-card-title">
                        <span class="pa-en">AI Assistant / MCP Connection Hub</span>
                        <span class="pa-fa">پیکربندی اتصال ایجنت‌های هوش مصنوعی (MCP)</span>
                    </h2>
                    <div class="pa-card-desc">
                        <span class="pa-en">Select your AI coding assistant to get copyable configuration code with your credentials embedded.</span>
                        <span class="pa-fa">ویرایشگر هوش مصنوعی مورد نظر خود را انتخاب کنید تا فایل کانفیگ اختصاصی تولید شود.</span>
                    </div>
                </div>
                <?php if ( $has_active_token ) : ?>
                    <span class="pa-chip pa-chip-success">
                        <span class="pa-en">Token Active</span>
                        <span class="pa-fa">توکن آماده</span>
                    </span>
                <?php else : ?>
                    <span class="pa-chip pa-chip-neutral">
                        <span class="pa-en">Token placeholder active</span>
                        <span class="pa-fa">نیازمند کلید</span>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Client Selectors -->
            <div class="pa-client-grid">
                <div class="pa-client-btn active" id="btn-client-cursor" onclick="selectClient('cursor', this)">
                    <div>
                        <div class="pa-client-btn-title">Cursor IDE</div>
                        <div class="pa-client-btn-sub">.cursor/mcp.json</div>
                    </div>
                </div>
                <div class="pa-client-btn" id="btn-client-claude-code" onclick="selectClient('claude-code', this)">
                    <div>
                        <div class="pa-client-btn-title">Claude Code (CLI)</div>
                        <div class="pa-client-btn-sub"><span class="pa-en">Single terminal command</span><span class="pa-fa">دستور ترمینال</span></div>
                    </div>
                </div>
                <div class="pa-client-btn" id="btn-client-claude-desktop" onclick="selectClient('claude-desktop', this)">
                    <div>
                        <div class="pa-client-btn-title">Claude Desktop</div>
                        <div class="pa-client-btn-sub">claude_desktop_config.json</div>
                    </div>
                </div>
                <div class="pa-client-btn" id="btn-client-antigravity" onclick="selectClient('antigravity', this)">
                    <div>
                        <div class="pa-client-btn-title">Antigravity / Windsurf</div>
                        <div class="pa-client-btn-sub">mcp_config.json</div>
                    </div>
                </div>
                <div class="pa-client-btn" id="btn-client-cline" onclick="selectClient('cline', this)">
                    <div>
                        <div class="pa-client-btn-title">Cline / Roo-Code</div>
                        <div class="pa-client-btn-sub">VS Code extension settings</div>
                    </div>
                </div>
            </div>

            <?php
            $display_token = $has_active_token ? $active_token : 'YOUR_PRESSAGENT_TOKEN';
            ?>

            <!-- Snippet 1: Cursor -->
            <div id="pane-client-cursor" class="pa-client-pane">
                <div class="pa-terminal">
                    <div class="pa-terminal-header">
                        <div class="pa-terminal-title">
                            <span class="pa-terminal-dots"><span class="pa-terminal-dot"></span><span class="pa-terminal-dot"></span><span class="pa-terminal-dot"></span></span>
                            <span>cursor_mcp.json</span>
                        </div>
                        <button type="button" class="pa-btn pa-btn-secondary pa-btn-sm" onclick="copySnippet('code-cursor', this)">
                            <span class="pa-en">Copy JSON</span>
                            <span class="pa-fa">کپی کانفیگ</span>
                        </button>
                    </div>
                    <pre class="pa-terminal-body" id="code-cursor">{
  "mcpServers": {
    "pressagent": {
      "command": "npx",
      "args": ["-y", "pressagent"],
      "env": {
        "PRESSAGENT_WP_URL": "<?php echo esc_js( $site_url ); ?>",
        "PRESSAGENT_TOKEN": "<?php echo esc_js( $display_token ); ?>"
      }
    }
  }
}</pre>
                </div>
                <p style="font-size: 11.5px; color: var(--pa-text-muted); margin: 8px 0 0 0;">
                    <span class="pa-en">In Cursor: Press <code>Ctrl + Shift + J</code> (or <code>Cmd + Shift + J</code>) &rarr; Features &rarr; MCP &rarr; Add New MCP Server.</span>
                    <span class="pa-fa">در محیط Cursor: کلیدهای <code>Ctrl + Shift + J</code> را بزنید &larr; بخش Features &larr; گزینه MCP &larr; کانفیگ بالا را پیست کنید.</span>
                </p>
            </div>

            <!-- Snippet 2: Claude Code CLI -->
            <div id="pane-client-claude-code" class="pa-client-pane" style="display:none;">
                <div class="pa-terminal">
                    <div class="pa-terminal-header">
                        <div class="pa-terminal-title">
                            <span class="pa-terminal-dots"><span class="pa-terminal-dot"></span><span class="pa-terminal-dot"></span><span class="pa-terminal-dot"></span></span>
                            <span>terminal</span>
                        </div>
                        <button type="button" class="pa-btn pa-btn-secondary pa-btn-sm" onclick="copySnippet('code-claude-code', this)">
                            <span class="pa-en">Copy Command</span>
                            <span class="pa-fa">کپی دستور</span>
                        </button>
                    </div>
                    <pre class="pa-terminal-body" id="code-claude-code">claude mcp add pressagent -e PRESSAGENT_WP_URL="<?php echo esc_attr( $site_url ); ?>" -e PRESSAGENT_TOKEN="<?php echo esc_attr( $display_token ); ?>" -- npx -y pressagent</pre>
                </div>
            </div>

            <!-- Snippet 3: Claude Desktop -->
            <div id="pane-client-claude-desktop" class="pa-client-pane" style="display:none;">
                <div class="pa-terminal">
                    <div class="pa-terminal-header">
                        <div class="pa-terminal-title">
                            <span class="pa-terminal-dots"><span class="pa-terminal-dot"></span><span class="pa-terminal-dot"></span><span class="pa-terminal-dot"></span></span>
                            <span>claude_desktop_config.json</span>
                        </div>
                        <button type="button" class="pa-btn pa-btn-secondary pa-btn-sm" onclick="copySnippet('code-claude-desktop', this)">
                            <span class="pa-en">Copy JSON</span>
                            <span class="pa-fa">کپی کانفیگ</span>
                        </button>
                    </div>
                    <pre class="pa-terminal-body" id="code-claude-desktop">{
  "mcpServers": {
    "pressagent": {
      "command": "npx",
      "args": ["-y", "pressagent"],
      "env": {
        "PRESSAGENT_WP_URL": "<?php echo esc_js( $site_url ); ?>",
        "PRESSAGENT_TOKEN": "<?php echo esc_js( $display_token ); ?>"
      }
    }
  }
}</pre>
                </div>
            </div>

            <!-- Snippet 4: Antigravity -->
            <div id="pane-client-antigravity" class="pa-client-pane" style="display:none;">
                <div class="pa-terminal">
                    <div class="pa-terminal-header">
                        <div class="pa-terminal-title">
                            <span class="pa-terminal-dots"><span class="pa-terminal-dot"></span><span class="pa-terminal-dot"></span><span class="pa-terminal-dot"></span></span>
                            <span>mcp_config.json</span>
                        </div>
                        <button type="button" class="pa-btn pa-btn-secondary pa-btn-sm" onclick="copySnippet('code-antigravity', this)">
                            <span class="pa-en">Copy JSON</span>
                            <span class="pa-fa">کپی کانفیگ</span>
                        </button>
                    </div>
                    <pre class="pa-terminal-body" id="code-antigravity">{
  "mcpServers": {
    "pressagent": {
      "command": "npx",
      "args": ["-y", "pressagent"],
      "env": {
        "PRESSAGENT_WP_URL": "<?php echo esc_js( $site_url ); ?>",
        "PRESSAGENT_TOKEN": "<?php echo esc_js( $display_token ); ?>"
      }
    }
  }
}</pre>
                </div>
            </div>

            <!-- Snippet 5: Cline -->
            <div id="pane-client-cline" class="pa-client-pane" style="display:none;">
                <div class="pa-terminal">
                    <div class="pa-terminal-header">
                        <div class="pa-terminal-title">
                            <span class="pa-terminal-dots"><span class="pa-terminal-dot"></span><span class="pa-terminal-dot"></span><span class="pa-terminal-dot"></span></span>
                            <span>cline_mcp_settings.json</span>
                        </div>
                        <button type="button" class="pa-btn pa-btn-secondary pa-btn-sm" onclick="copySnippet('code-cline', this)">
                            <span class="pa-en">Copy JSON</span>
                            <span class="pa-fa">کپی کانفیگ</span>
                        </button>
                    </div>
                    <pre class="pa-terminal-body" id="code-cline">{
  "mcpServers": {
    "pressagent": {
      "command": "npx",
      "args": ["-y", "pressagent"],
      "env": {
        "PRESSAGENT_WP_URL": "<?php echo esc_js( $site_url ); ?>",
        "PRESSAGENT_TOKEN": "<?php echo esc_js( $display_token ); ?>"
      },
      "disabled": false,
      "autoApprove": []
    }
  }
}</pre>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== TAB 2: ACCESS TOKENS & RBAC ==================== -->
    <div id="tab-tokens" class="pa-tab-pane">
        <!-- Token Generator Card -->
        <div class="pa-card">
            <div class="pa-card-header">
                <div>
                    <h2 class="pa-card-title">
                        <span class="pa-en">Generate New Access Token</span>
                        <span class="pa-fa">صدور کلید دسترسی جدید</span>
                    </h2>
                    <div class="pa-card-desc">
                        <span class="pa-en">Assign least-privilege permission scopes to your AI agent instance.</span>
                        <span class="pa-fa">سطوح دسترسی مشخص و تفکیک‌شده را به دستیار هوش مصنوعی خود اختصاص دهید.</span>
                    </div>
                </div>
            </div>

            <!-- Presets Toolbar -->
            <div class="pa-toolbar">
                <span class="pa-toolbar-label">
                    <span class="pa-en">Presets:</span>
                    <span class="pa-fa">الگوهای آماده:</span>
                </span>
                <button type="button" class="pa-btn pa-btn-secondary pa-btn-sm" onclick="applyPreset('safe')">
                    <span class="pa-en">Read-Only (Safe)</span>
                    <span class="pa-fa">فقط خواندن (امن)</span>
                </button>
                <button type="button" class="pa-btn pa-btn-secondary pa-btn-sm" onclick="applyPreset('standard')">
                    <span class="pa-en">Standard AI Ops</span>
                    <span class="pa-fa">عملیات استاندارد AI</span>
                </button>
                <button type="button" class="pa-btn pa-btn-secondary pa-btn-sm" onclick="applyPreset('god')" style="color: var(--pa-danger);">
                    <span class="pa-en">Full Access (God Mode)</span>
                    <span class="pa-fa">دسترسی کامل (گاد مود)</span>
                </button>
                <button type="button" class="pa-btn pa-btn-secondary pa-btn-sm" onclick="applyPreset('none')" style="color: var(--pa-text-subtle);">
                    <span class="pa-en">Clear All</span>
                    <span class="pa-fa">پاک کردن همه</span>
                </button>
            </div>

            <form method="post">
                <?php wp_nonce_field( 'pressagent_generate_token_nonce' ); ?>
                <input type="hidden" name="pressagent_generate_token" value="1">

                <div style="margin-bottom: 20px;">
                    <label for="token_label" style="display:block; font-size:12px; font-weight:600; margin-bottom:6px; color: var(--pa-text-main);">
                        <span class="pa-en">Token Label / Description:</span>
                        <span class="pa-fa">برچسب یا نام کلید:</span>
                    </label>
                    <input type="text" name="token_label" id="token_label" class="pa-input" placeholder="e.g. Cursor MacBook, Antigravity Agent, Staging CI/CD" required style="max-width: 480px;">
                </div>

                <!-- Scope Category 1: Content -->
                <div class="pa-scopes-section">
                    <div class="pa-scopes-section-title">
                        <span class="pa-en">1. Pages &amp; Content Management</span>
                        <span class="pa-fa">۱. مدیریت برگه‌ها و محتوا</span>
                    </div>
                    <div class="pa-scopes-grid">
                        <label class="pa-scope-card is-checked" onclick="toggleCard(this)">
                            <input type="checkbox" name="token_scopes[]" value="pages:read" class="cb-read" checked>
                            <div>
                                <span class="pa-scope-name">pages:read</span>
                                <span class="pa-scope-desc">
                                    <span class="pa-en">List pages, titles, statuses, and Elementor metadata</span>
                                    <span class="pa-fa">مشاهده برگه‌ها، نوشته‌ها و وضعیت لایوت المنتور</span>
                                </span>
                            </div>
                        </label>
                        <label class="pa-scope-card is-checked" onclick="toggleCard(this)">
                            <input type="checkbox" name="token_scopes[]" value="pages:write" class="cb-write" checked>
                            <div>
                                <span class="pa-scope-name">pages:write</span>
                                <span class="pa-scope-desc">
                                    <span class="pa-en">Create, draft, publish, and delete pages</span>
                                    <span class="pa-fa">ایجاد، ویرایش، انتشار و حذف برگه‌ها</span>
                                </span>
                            </div>
                        </label>
                        <label class="pa-scope-card" onclick="toggleCard(this)">
                            <input type="checkbox" name="token_scopes[]" value="rest:general" class="cb-write">
                            <div>
                                <span class="pa-scope-name">rest:general</span>
                                <span class="pa-scope-desc">
                                    <span class="pa-en">Query standard core REST API endpoints</span>
                                    <span class="pa-fa">ارسال درخواست به سایر اندپوینت‌های REST وردپرس</span>
                                </span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Scope Category 2: Elementor -->
                <div class="pa-scopes-section">
                    <div class="pa-scopes-section-title">
                        <span class="pa-en">2. Elementor Builder &amp; Appearance</span>
                        <span class="pa-fa">۲. طراحی و ویرایشگر المنتور</span>
                    </div>
                    <div class="pa-scopes-grid">
                        <label class="pa-scope-card is-checked" onclick="toggleCard(this)">
                            <input type="checkbox" name="token_scopes[]" value="elementor:read" class="cb-read" checked>
                            <div>
                                <span class="pa-scope-name">elementor:read</span>
                                <span class="pa-scope-desc">
                                    <span class="pa-en">Read Elementor container tree and widget settings</span>
                                    <span class="pa-fa">خواندن درخت المان‌ها و تنظیمات ابزارک‌های المنتور</span>
                                </span>
                            </div>
                        </label>
                        <label class="pa-scope-card is-checked" onclick="toggleCard(this)">
                            <input type="checkbox" name="token_scopes[]" value="elementor:write" class="cb-write" checked>
                            <div>
                                <span class="pa-scope-name">elementor:write</span>
                                <span class="pa-scope-desc">
                                    <span class="pa-en">Add containers, update native widgets, clear CSS cache</span>
                                    <span class="pa-fa">طراحی، افزودن سکشن و ویرایش ویجت‌های بومی المنتور</span>
                                </span>
                            </div>
                        </label>
                        <label class="pa-scope-card" onclick="toggleCard(this)">
                            <input type="checkbox" name="token_scopes[]" value="appearance:css" class="cb-write">
                            <div>
                                <span class="pa-scope-name">appearance:css</span>
                                <span class="pa-scope-desc">
                                    <span class="pa-en">Manage page templates and custom stylesheet injections</span>
                                    <span class="pa-fa">تنظیم قالب برگه (تمام‌صفحه) و اعمال استایل CSS</span>
                                </span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Scope Category 3: Settings & Cache -->
                <div class="pa-scopes-section">
                    <div class="pa-scopes-section-title">
                        <span class="pa-en">3. Settings, Optimization &amp; Cache</span>
                        <span class="pa-fa">۳. بهینه‌سازی، تنظیمات و کش</span>
                    </div>
                    <div class="pa-scopes-grid">
                        <label class="pa-scope-card is-checked" onclick="toggleCard(this)">
                            <input type="checkbox" name="token_scopes[]" value="settings:read" class="cb-read" checked>
                            <div>
                                <span class="pa-scope-name">settings:read</span>
                                <span class="pa-scope-desc">
                                    <span class="pa-en">Read allowlisted options (Rocket, LiteSpeed, Wordfence)</span>
                                    <span class="pa-fa">خواندن گزینه‌های مجاز وردپرس و افزونه‌ها</span>
                                </span>
                            </div>
                        </label>
                        <label class="pa-scope-card is-checked" onclick="toggleCard(this)">
                            <input type="checkbox" name="token_scopes[]" value="settings:write" class="cb-write" checked>
                            <div>
                                <span class="pa-scope-name">settings:write</span>
                                <span class="pa-scope-desc">
                                    <span class="pa-en">Write allowlisted options with pre-execution snapshots</span>
                                    <span class="pa-fa">ویرایش تنظیمات مجاز با ثبت اسنپ‌شات خودکار</span>
                                </span>
                            </div>
                        </label>
                        <label class="pa-scope-card is-checked" onclick="toggleCard(this)">
                            <input type="checkbox" name="token_scopes[]" value="cache:purge" class="cb-write" checked>
                            <div>
                                <span class="pa-scope-name">cache:purge</span>
                                <span class="pa-scope-desc">
                                    <span class="pa-en">Flush page and asset caches (WP Rocket / LiteSpeed)</span>
                                    <span class="pa-fa">تخلیه کش سایت و بهینه‌سازها</span>
                                </span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Scope Category 4: Diagnostics & Security -->
                <div class="pa-scopes-section">
                    <div class="pa-scopes-section-title">
                        <span class="pa-en">4. Diagnostics, Security &amp; Developer</span>
                        <span class="pa-fa">۴. عیب‌یابی، امنیت و حالت توسعه</span>
                    </div>
                    <div class="pa-scopes-grid">
                        <label class="pa-scope-card is-checked" onclick="toggleCard(this)">
                            <input type="checkbox" name="token_scopes[]" value="debug:read" class="cb-read" checked>
                            <div>
                                <span class="pa-scope-name">debug:read</span>
                                <span class="pa-scope-desc">
                                    <span class="pa-en">Parse WP debug.log and view structured error reports</span>
                                    <span class="pa-fa">خواندن لاگ‌های خطای debug.log با آنالیز هوشمند</span>
                                </span>
                            </div>
                        </label>
                        <label class="pa-scope-card is-checked" onclick="toggleCard(this)">
                            <input type="checkbox" name="token_scopes[]" value="security:read" class="cb-read" checked>
                            <div>
                                <span class="pa-scope-name">security:read</span>
                                <span class="pa-scope-desc">
                                    <span class="pa-en">Inspect environment health, versions, and file permissions</span>
                                    <span class="pa-fa">بررسی وضعیت سلامت سرور، مجوزها و افزونه‌ها</span>
                                </span>
                            </div>
                        </label>
                        <label class="pa-scope-card" onclick="toggleCard(this)" style="border-color: #fecaca;">
                            <input type="checkbox" name="token_scopes[]" value="code:execute" class="cb-god">
                            <div>
                                <span class="pa-scope-name" style="color: var(--pa-danger);">code:execute (God Mode)</span>
                                <span class="pa-scope-desc" style="color: #991b1b;">
                                    <span class="pa-en">Scaffold custom plugins and execute raw system code (Staging only)</span>
                                    <span class="pa-fa">ساخت پلاگین و اختیارات کامل هسته (فقط استیجینگ)</span>
                                </span>
                            </div>
                        </label>
                    </div>
                </div>

                <div style="margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--pa-border-subtle);">
                    <button type="submit" class="pa-btn pa-btn-primary">
                        <span class="pa-en">Issue Access Token</span>
                        <span class="pa-fa">صدور کلید دسترسی</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Active Tokens Table Card -->
        <div class="pa-card">
            <div class="pa-card-header">
                <div>
                    <h2 class="pa-card-title">
                        <span class="pa-en">Active API Tokens</span>
                        <span class="pa-fa">کلیدهای دسترسی فعال</span>
                    </h2>
                    <div class="pa-card-desc">
                        <span class="pa-en">All cryptographically hashed tokens authorized to interact with this site.</span>
                        <span class="pa-fa">فهرست توکن‌های معتبر که امکان اتصال به این وب‌سایت را دارند.</span>
                    </div>
                </div>
            </div>

            <?php if ( empty( $tokens ) ) : ?>
                <p style="text-align:center; padding: 24px 0; color: var(--pa-text-muted); font-size: 13px;">
                    <span class="pa-en">No active API tokens found. Generate a token using the form above.</span>
                    <span class="pa-fa">هیچ کلید فعالی ثبت نشده است. با فرم بالا کلید جدید ایجاد نمایید.</span>
                </p>
            <?php else : ?>
                <div class="pa-table-wrapper">
                    <table class="pa-table">
                        <thead>
                            <tr>
                                <th><span class="pa-en">Label</span><span class="pa-fa">برچسب کلید</span></th>
                                <th><span class="pa-en">Scopes</span><span class="pa-fa">دسترسی‌ها</span></th>
                                <th><span class="pa-en">Created</span><span class="pa-fa">تاریخ ساخت</span></th>
                                <th><span class="pa-en">Last Used</span><span class="pa-fa">آخرین استفاده</span></th>
                                <th style="text-align: center;"><span class="pa-en">Action</span><span class="pa-fa">عملیات</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $tokens as $id => $tok ) : 
                                $is_god = in_array( 'code:execute', $tok['scopes'], true ) || in_array( '*', $tok['scopes'], true );
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $tok['label'] ); ?></strong>
                                        <?php if ( $is_god ) : ?>
                                            <span class="pa-chip pa-chip-danger" style="margin-left: 6px;">GOD MODE</span>
                                        <?php endif; ?>
                                        <div class="pa-mono" style="font-size: 10.5px; color: var(--pa-text-subtle); margin-top: 2px;">
                                            ID: <?php echo esc_html( substr( $id, 0, 8 ) ); ?>...
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                            <?php foreach ( $tok['scopes'] as $sc ) : ?>
                                                <span class="pa-chip pa-chip-neutral"><?php echo esc_html( $sc ); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="pa-mono" style="font-size: 11.5px; color: var(--pa-text-muted);">
                                        <?php echo esc_html( $tok['created_at'] ); ?>
                                    </td>
                                    <td class="pa-mono" style="font-size: 11.5px; color: var(--pa-text-muted);">
                                        <?php echo esc_html( $tok['last_used'] ? $tok['last_used'] : '-' ); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Revoke this token permanently?');">
                                            <?php wp_nonce_field( 'pressagent_revoke_token_nonce' ); ?>
                                            <input type="hidden" name="token_id" value="<?php echo esc_attr( $id ); ?>">
                                            <input type="hidden" name="pressagent_revoke_token" value="1">
                                            <button type="submit" class="pa-btn pa-btn-danger pa-btn-sm">
                                                <span class="pa-en">Revoke</span>
                                                <span class="pa-fa">ابطال</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ==================== TAB 3: ACTION GUARD & SNAPSHOTS ==================== -->
    <div id="tab-snapshots" class="pa-tab-pane">
        <div class="pa-card">
            <div class="pa-card-header">
                <div>
                    <h2 class="pa-card-title">
                        <span class="pa-en">Action Guard™ Snapshot History</span>
                        <span class="pa-fa">تاریخچه اسنپ‌شات‌های محافظت عملیات</span>
                    </h2>
                    <div class="pa-card-desc">
                        <span class="pa-en">Before any Elementor layout change or settings update, an encrypted snapshot is captured for zero-downtime rollback.</span>
                        <span class="pa-fa">قبل از هرگونه تغییر در المنتور یا تنظیمات، یک اسنپ‌شات ثبت می‌شود تا بتوانید با ۱ کلیک تغییرات را برگردانید.</span>
                    </div>
                </div>
                <span class="pa-chip pa-chip-success">
                    <span class="pa-en"><?php echo $total_snapshots; ?> Snapshots Recorded</span>
                    <span class="pa-fa"><?php echo $total_snapshots; ?> اسنپ‌شات ثبت‌شده</span>
                </span>
            </div>

            <?php if ( empty( $snapshots ) ) : ?>
                <p style="text-align:center; padding: 24px 0; color: var(--pa-text-muted); font-size: 13px;">
                    <span class="pa-en">No snapshots recorded yet. Snapshots will appear automatically when AI operations execute.</span>
                    <span class="pa-fa">هنوز اسنپ‌شاتی ثبت نشده است. با شروع عملیات ایجنت‌ها، اسنپ‌شات‌ها اینجا نمایش می‌یابند.</span>
                </p>
            <?php else : ?>
                <div class="pa-table-wrapper">
                    <table class="pa-table">
                        <thead>
                            <tr>
                                <th style="width: 70px;">#</th>
                                <th><span class="pa-en">Action Type</span><span class="pa-fa">نوع عملیات</span></th>
                                <th><span class="pa-en">Context / Target</span><span class="pa-fa">هدف / جزئیات</span></th>
                                <th><span class="pa-en">Timestamp</span><span class="pa-fa">تاریخ و زمان</span></th>
                                <th><span class="pa-en">Status</span><span class="pa-fa">وضعیت</span></th>
                                <th style="text-align: center;"><span class="pa-en">Action</span><span class="pa-fa">عملیات</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $snapshots as $snap ) : 
                                $ctx = json_decode( $snap['context'], true );
                                $is_active = ( $snap['status'] === 'active' );
                            ?>
                                <tr>
                                    <td class="pa-mono">#<?php echo esc_html( $snap['id'] ); ?></td>
                                    <td>
                                        <?php if ( $snap['action_type'] === 'update_elementor' ) : ?>
                                            <span class="pa-chip pa-chip-primary">Elementor Layout</span>
                                        <?php elseif ( $snap['action_type'] === 'update_option' ) : ?>
                                            <span class="pa-chip pa-chip-warning">Option Setting</span>
                                        <?php else : ?>
                                            <span class="pa-chip pa-chip-neutral"><?php echo esc_html( $snap['action_type'] ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( isset( $ctx['page_id'] ) ) : ?>
                                            <span>Post #<?php echo esc_html( $ctx['page_id'] ); ?> (<?php echo esc_html( get_the_title( $ctx['page_id'] ) ?: 'Untitled' ); ?>)</span>
                                        <?php elseif ( isset( $ctx['option_name'] ) ) : ?>
                                            <code class="pa-mono"><?php echo esc_html( $ctx['option_name'] ); ?></code>
                                        <?php else : ?>
                                            <code class="pa-mono"><?php echo esc_html( $snap['context'] ); ?></code>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pa-mono" style="font-size: 11.5px; color: var(--pa-text-muted);">
                                        <?php echo esc_html( $snap['created_at'] ); ?>
                                    </td>
                                    <td>
                                        <?php if ( $is_active ) : ?>
                                            <span class="pa-chip pa-chip-success">
                                                <span class="pa-en">Rollback Ready</span>
                                                <span class="pa-fa">آماده بازگردانی</span>
                                            </span>
                                        <?php else : ?>
                                            <span class="pa-chip pa-chip-neutral">
                                                <span class="pa-en">Rolled Back</span>
                                                <span class="pa-fa">بازگردانی‌شده</span>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ( $is_active ) : ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Restore this snapshot? Changes made after this snapshot will be reverted.');">
                                                <?php wp_nonce_field( 'pressagent_rollback_snapshot_nonce' ); ?>
                                                <input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $snap['id'] ); ?>">
                                                <input type="hidden" name="pressagent_rollback_snapshot" value="1">
                                                <button type="submit" class="pa-btn pa-btn-secondary pa-btn-sm" style="color: var(--pa-warning); border-color: var(--pa-warning-border);">
                                                    <span class="pa-en">Rollback</span>
                                                    <span class="pa-fa">بازگردانی</span>
                                                </button>
                                            </form>
                                        <?php else : ?>
                                            <span style="color: var(--pa-text-subtle);">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ==================== TAB 4: SETTINGS ALLOWLIST ==================== -->
    <div id="tab-allowlist" class="pa-tab-pane">
        <div class="pa-card">
            <div class="pa-card-header">
                <div>
                    <h2 class="pa-card-title">
                        <span class="pa-en">WordPress &amp; Plugin Settings Allowlist</span>
                        <span class="pa-fa">لیست سفید تنظیمات وردپرس و افزونه‌ها</span>
                    </h2>
                    <div class="pa-card-desc">
                        <span class="pa-en">For security, AI agents can only read or modify option keys that match these allowlisted patterns.</span>
                        <span class="pa-fa">برای جلوگیری از آسیب‌پذیری، ایجنت‌های AI فقط مجاز به خواندن یا نوشتن کلیدهای موجود در این لیست هستند.</span>
                    </div>
                </div>
            </div>

            <!-- Built-in Allowlist Presets -->
            <div style="margin-bottom: 24px;">
                <h3 style="font-size: 13px; font-weight: 700; margin-bottom: 10px;">
                    <span class="pa-en">Built-In Protected Presets:</span>
                    <span class="pa-fa">الگوهای پیش‌فرض حفاظت‌شده:</span>
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px;">
                    <div style="border: 1px solid var(--pa-border); border-radius: 8px; padding: 12px 16px; background: #fafafa;">
                        <strong style="display:block; font-size: 12.5px;">WP Rocket</strong>
                        <span style="font-size: 11px; color: var(--pa-text-muted);">wp_rocket_settings</span>
                    </div>
                    <div style="border: 1px solid var(--pa-border); border-radius: 8px; padding: 12px 16px; background: #fafafa;">
                        <strong style="display:block; font-size: 12.5px;">Wordfence Security</strong>
                        <span style="font-size: 11px; color: var(--pa-text-muted); font-family: var(--pa-font-mono);">wordfence*</span>
                    </div>
                    <div style="border: 1px solid var(--pa-border); border-radius: 8px; padding: 12px 16px; background: #fafafa;">
                        <strong style="display:block; font-size: 12.5px;">LiteSpeed Cache</strong>
                        <span style="font-size: 11px; color: var(--pa-text-muted); font-family: var(--pa-font-mono);">litespeed.conf.*</span>
                    </div>
                </div>
            </div>

            <!-- Custom Allowlist Form -->
            <form method="post">
                <?php wp_nonce_field( 'pressagent_save_allowlist_nonce' ); ?>
                <input type="hidden" name="pressagent_save_allowlist" value="1">

                <label for="allowlist_rules" style="display:block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px;">
                    <span class="pa-en">Custom Allowed Option Patterns (One per line):</span>
                    <span class="pa-fa">الگوهای مجاز سفارشی (هر خط یک الگو):</span>
                </label>
                <p style="font-size: 11.5px; color: var(--pa-text-muted); margin-top: 0;">
                    <span class="pa-en">Supports exact matches (e.g. <code>my_plugin_option</code>) or wildcard prefixes (e.g. <code>woocommerce_*</code>).</span>
                    <span class="pa-fa">از تطبیق دقیق (مثل <code>my_plugin_option</code>) و پیشوندهای ستاره‌دار (مثل <code>woocommerce_*</code>) پشتیبانی می‌کند.</span>
                </p>

                <textarea name="allowlist_rules" id="allowlist_rules" class="pa-input pa-textarea"><?php echo esc_textarea( implode( "\n", $custom_allowlist ) ); ?></textarea>

                <div style="margin-top: 14px;">
                    <button type="submit" class="pa-btn pa-btn-primary">
                        <span class="pa-en">Save Allowlist Rules</span>
                        <span class="pa-fa">ذخیره قوانین لیست سفید</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== TAB 5: DIAGNOSTICS & LOGS ==================== -->
    <div id="tab-diagnostics" class="pa-tab-pane">
        <!-- Security & Health Audit Card -->
        <div class="pa-card">
            <div class="pa-card-header">
                <div>
                    <h2 class="pa-card-title">
                        <span class="pa-en">System &amp; Security Health Audit</span>
                        <span class="pa-fa">ارزیابی وضعیت سلامت و امنیت سیستم</span>
                    </h2>
                    <div class="pa-card-desc">
                        <span class="pa-en">Automated environmental checks reported to AI diagnostics endpoints.</span>
                        <span class="pa-fa">بررسی‌های خودکار وضعیت سرور و فایل‌های حیاتی برای خطایابی هوش مصنوعی.</span>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">
                <div style="border: 1px solid var(--pa-border); border-radius: 8px; padding: 14px 16px;">
                    <div class="pa-stat-label">WordPress Version</div>
                    <div class="pa-mono" style="font-weight: 700; font-size: 14px;"><?php echo esc_html( $security_summary['wordpress_version'] ?? get_bloginfo('version') ); ?></div>
                </div>
                <div style="border: 1px solid var(--pa-border); border-radius: 8px; padding: 14px 16px;">
                    <div class="pa-stat-label">PHP Version</div>
                    <div class="pa-mono" style="font-weight: 700; font-size: 14px;"><?php echo esc_html( $security_summary['php_version'] ?? PHP_VERSION ); ?></div>
                </div>
                <div style="border: 1px solid var(--pa-border); border-radius: 8px; padding: 14px 16px;">
                    <div class="pa-stat-label">wp-config.php Writable</div>
                    <div>
                        <?php if ( ! empty( $security_summary['wp_config_writable'] ) ) : ?>
                            <span class="pa-chip pa-chip-warning">Writable (Review Recommended)</span>
                        <?php else : ?>
                            <span class="pa-chip pa-chip-success">Protected (Read-Only)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="border: 1px solid var(--pa-border); border-radius: 8px; padding: 14px 16px;">
                    <div class="pa-stat-label">.htaccess Exists</div>
                    <div>
                        <?php if ( ! empty( $security_summary['htaccess_exists'] ) ) : ?>
                            <span class="pa-chip pa-chip-success">Present</span>
                        <?php else : ?>
                            <span class="pa-chip pa-chip-neutral">Not Found</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Debug Log Viewer Card -->
        <div class="pa-card">
            <div class="pa-card-header">
                <div>
                    <h2 class="pa-card-title">
                        <span class="pa-en">Recent Debug Log Entries</span>
                        <span class="pa-fa">آخرین لاگ‌های خطای وردپرس (debug.log)</span>
                    </h2>
                    <div class="pa-card-desc">
                        <span class="pa-en">Real-time structured parser view of <code>debug.log</code> errors, notices, and warnings.</span>
                        <span class="pa-fa">نمایش ساختاریافته خطاهای اخیر ثبت‌شده در فایل دیباگ وردپرس.</span>
                    </div>
                </div>
            </div>

            <?php if ( is_wp_error( $debug_logs ) || empty( $debug_logs ) ) : ?>
                <div style="padding: 24px; text-align: center; color: var(--pa-text-muted); font-size: 12.5px;">
                    <span class="pa-en">
                        <?php echo is_wp_error( $debug_logs ) ? esc_html( $debug_logs->get_error_message() ) : 'No error log entries recorded. Your site is operating cleanly without reported PHP errors.'; ?>
                    </span>
                    <span class="pa-fa">
                        <?php echo is_wp_error( $debug_logs ) ? esc_html( $debug_logs->get_error_message() ) : 'هیچ خطایی در فایل دیباگ ثبت نشده است. سایت در شرایط پایدار قرار دارد.'; ?>
                    </span>
                </div>
            <?php else : ?>
                <div class="pa-table-wrapper">
                    <table class="pa-table">
                        <thead>
                            <tr>
                                <th style="width: 90px;"><span class="pa-en">Level</span><span class="pa-fa">سطح</span></th>
                                <th style="width: 170px;"><span class="pa-en">Time</span><span class="pa-fa">زمان</span></th>
                                <th><span class="pa-en">Message</span><span class="pa-fa">پیام خطا</span></th>
                                <th><span class="pa-en">File &amp; Line</span><span class="pa-fa">فایل و خط</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $debug_logs as $entry ) : 
                                if ( isset( $entry['raw'] ) ) : ?>
                                    <tr>
                                        <td colspan="4" class="pa-mono" style="font-size: 11px; color: var(--pa-text-muted);"><?php echo esc_html( $entry['raw'] ); ?></td>
                                    </tr>
                                <?php else : 
                                    $lvl_class = ( $entry['level'] === 'error' ) ? 'pa-chip-danger' : ( ( $entry['level'] === 'warning' ) ? 'pa-chip-warning' : 'pa-chip-neutral' );
                                ?>
                                    <tr>
                                        <td><span class="pa-chip <?php echo esc_attr( $lvl_class ); ?>"><?php echo esc_html( strtoupper( $entry['level'] ) ); ?></span></td>
                                        <td class="pa-mono" style="font-size: 11px; color: var(--pa-text-muted);"><?php echo esc_html( $entry['timestamp'] ); ?></td>
                                        <td style="font-weight: 500;"><?php echo esc_html( $entry['message'] ); ?></td>
                                        <td class="pa-mono" style="font-size: 11px; color: var(--pa-text-muted);">
                                            <?php echo esc_html( basename( $entry['file'] ) ); ?>:<?php echo esc_html( $entry['line'] ); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Language Switcher Engine
function setLanguage(lang) {
    var root = document.getElementById('pressagent-app');
    if (!root) return;

    root.setAttribute('data-lang', lang);
    root.setAttribute('dir', lang === 'fa' ? 'rtl' : 'ltr');

    var btnEn = document.getElementById('btn-lang-en');
    var btnFa = document.getElementById('btn-lang-fa');
    if (btnEn && btnFa) {
        if (lang === 'fa') {
            btnFa.classList.add('active');
            btnEn.classList.remove('active');
        } else {
            btnEn.classList.add('active');
            btnFa.classList.remove('active');
        }
    }

    try {
        localStorage.setItem('pressagent_lang', lang);
    } catch(e) {}
}

// Restore user language preference on load
(function() {
    try {
        var savedLang = localStorage.getItem('pressagent_lang');
        if (savedLang === 'fa' || savedLang === 'en') {
            setLanguage(savedLang);
        }
    } catch(e) {}
})();

// Tab Navigation Engine
function switchTab(tabId, tabEl) {
    document.querySelectorAll('.pa-tab-link').forEach(function(el) {
        el.classList.remove('active');
    });
    document.querySelectorAll('.pa-tab-pane').forEach(function(el) {
        el.classList.remove('active');
    });

    if (tabEl) {
        tabEl.classList.add('active');
    }
    var target = document.getElementById('tab-' + tabId);
    if (target) {
        target.classList.add('active');
    }

    try {
        localStorage.setItem('pressagent_tab', tabId);
    } catch(e) {}
}

// Restore user active tab on load
(function() {
    try {
        var savedTab = localStorage.getItem('pressagent_tab');
        if (savedTab) {
            var btn = document.querySelector('.pa-tab-link[onclick*="' + savedTab + '"]');
            if (btn) {
                switchTab(savedTab, btn);
            }
        }
    } catch(e) {}
})();

// MCP Client Selector Engine
function selectClient(clientId, btnEl) {
    document.querySelectorAll('.pa-client-btn').forEach(function(el) {
        el.classList.remove('active');
    });
    document.querySelectorAll('.pa-client-pane').forEach(function(el) {
        el.style.display = 'none';
    });

    if (btnEl) {
        btnEl.classList.add('active');
    }
    var pane = document.getElementById('pane-client-' + clientId);
    if (pane) {
        pane.style.display = 'block';
    }
}

// Copy Snippet Helper
function copySnippet(elementId, btnElement) {
    var el = document.getElementById(elementId);
    if (!el) return;
    var text = el.innerText;
    navigator.clipboard.writeText(text).then(function() {
        var origText = btnElement.innerText;
        btnElement.innerText = 'Copied! ✓';
        btnElement.style.borderColor = 'var(--pa-success)';
        btnElement.style.color = 'var(--pa-success)';
        setTimeout(function() {
            btnElement.innerText = origText;
            btnElement.style.borderColor = '';
            btnElement.style.color = '';
        }, 2000);
    }).catch(function() {
        alert('Please copy the text manually.');
    });
}

function copyRawToken(tokenText, btnElement) {
    navigator.clipboard.writeText(tokenText).then(function() {
        var origText = btnElement.innerText;
        btnElement.innerText = 'Copied! ✓';
        setTimeout(function() {
            btnElement.innerText = origText;
        }, 2000);
    });
}

// Scope Card Toggle
function toggleCard(cardEl) {
    var cb = cardEl.querySelector('input[type="checkbox"]');
    setTimeout(function() {
        if (cb.checked) {
            cardEl.classList.add('is-checked');
        } else {
            cardEl.classList.remove('is-checked');
        }
    }, 10);
}

// Scope Presets
function applyPreset(preset) {
    var allCbs = document.querySelectorAll('.pa-scopes-grid input[type="checkbox"]');
    allCbs.forEach(function(cb) {
        cb.checked = false;
    });

    if (preset === 'god') {
        allCbs.forEach(function(cb) { cb.checked = true; });
    } else if (preset === 'standard') {
        document.querySelectorAll('.cb-read, .cb-write').forEach(function(cb) { cb.checked = true; });
    } else if (preset === 'safe') {
        document.querySelectorAll('.cb-read').forEach(function(cb) { cb.checked = true; });
    }

    document.querySelectorAll('.pa-scope-card').forEach(function(card) {
        var cb = card.querySelector('input[type="checkbox"]');
        if (cb && cb.checked) {
            card.classList.add('is-checked');
        } else {
            card.classList.remove('is-checked');
        }
    });
}
</script>
