<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

$site_url = get_site_url();

// Handle Rollback action
$alert_message = null;
$alert_type = 'success';

if ( isset( $_POST['pressagent_rollback_snapshot'] ) && check_admin_referer( 'pressagent_rollback_snapshot_nonce' ) ) {
    $snapshot_id = (int) $_POST['snapshot_id'];
    $res = PressAgent_Action_Guard::rollback( $snapshot_id );
    if ( is_wp_error( $res ) ) {
        $alert_message = 'خطا در بازگردانی: ' . $res->get_error_message();
        $alert_type = 'error';
    } else {
        $alert_message = '✅ اسنپ‌شات #' . $snapshot_id . ' با موفقیت بازگردانی شد و کش سایت تخلیه گردید.';
        $alert_type = 'success';
    }
}

// Handle quick default token generation
if ( isset( $_POST['pressagent_quick_setup'] ) && check_admin_referer( 'pressagent_quick_setup_nonce' ) ) {
    $default_scopes = array( 'pages:read', 'pages:write', 'elementor:read', 'elementor:write', 'settings:read', 'settings:write', 'cache:purge', 'debug:read', 'debug:write', 'security:read' );
    $new_token = PressAgent_Auth::generate_token( 'کلید پیش‌فرض (' . current_time( 'Y/m/d H:i' ) . ')', $default_scopes );
    $alert_message = '🎉 کلید دسترسی استاندارد با موفقیت ساخته شد و کدهای اتصال IDE فعال شدند!';
    $alert_type = 'success';
}

// Handle custom token generation
if ( isset( $_POST['pressagent_generate_token'] ) && check_admin_referer( 'pressagent_generate_token_nonce' ) ) {
    $label = sanitize_text_field( $_POST['token_label'] );
    $scopes = isset( $_POST['token_scopes'] ) ? array_map( 'sanitize_text_field', $_POST['token_scopes'] ) : array();
    if ( empty( $scopes ) ) {
        $alert_message = 'لطفاً حداقل یک دسترسی را برای کلید انتخاب کنید.';
        $alert_type = 'error';
    } else {
        $new_token = PressAgent_Auth::generate_token( $label, $scopes );
        $alert_message = '🎉 کلید دسترسی اختصاصی با موفقیت ساخته شد و کدهای کانفیگ فعال شدند!';
        $alert_type = 'success';
    }
}

// Handle token revoke
if ( isset( $_POST['pressagent_revoke_token'] ) && check_admin_referer( 'pressagent_revoke_token_nonce' ) ) {
    $token_id = sanitize_text_field( $_POST['token_id'] );
    PressAgent_Auth::revoke_token( $token_id );
    $alert_message = 'کلید دسترسی با موفقیت باطل شد.';
    $alert_type = 'warning';
}

$tokens = get_option( 'pressagent_tokens', array() );
$snapshots = PressAgent_Action_Guard::get_snapshots( 25 );
$total_snapshots = PressAgent_Action_Guard::count_snapshots();

// Active raw token for MCP config — only shown immediately after generation.
// The raw token is never persisted in the database.
$active_token = isset( $new_token ) ? $new_token : '';
$has_active_token = ! empty( $active_token );
?>

<!-- Load Persian Google Font Vazirmatn -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<div class="wrap pressagent-admin-wrap" dir="rtl">
    <style>
        :root {
            --pa-primary: #2563eb;
            --pa-primary-dark: #1d4ed8;
            --pa-dark: #0f172a;
            --pa-card: #ffffff;
            --pa-border: #e2e8f0;
            --pa-text: #1e293b;
            --pa-muted: #64748b;
            --pa-danger: #ef4444;
            --pa-warning: #f59e0b;
            --pa-success: #10b981;
            --pa-font: 'Vazirmatn', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Tahoma, sans-serif;
        }

        .pressagent-admin-wrap,
        .pressagent-admin-wrap * {
            box-sizing: border-box;
            font-family: var(--pa-font) !important;
        }

        .pressagent-admin-wrap {
            max-width: 1180px;
            margin: 25px auto 40px auto;
            color: var(--pa-text);
            line-height: 1.6;
        }

        /* Top Header */
        .pa-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #fff;
            padding: 24px 30px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2);
        }
        .pa-brand { display: flex; align-items: center; gap: 16px; }
        .pa-logo-icon {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        .pa-brand h1 { margin: 0; font-size: 24px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 10px; }
        .pa-brand p { margin: 4px 0 0 0; color: #94a3b8; font-size: 13px; font-weight: 400; }
        .pa-badge { background: rgba(59, 130, 246, 0.25); border: 1px solid rgba(59, 130, 246, 0.4); color: #bfdbfe; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }

        /* Stats Bar */
        .pa-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .pa-stat-card {
            background: #fff;
            border: 1px solid var(--pa-border);
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .pa-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .pa-stat-num { font-size: 22px; font-weight: 800; color: var(--pa-dark); line-height: 1.2; }
        .pa-stat-label { font-size: 12px; color: var(--pa-muted); margin-top: 2px; font-weight: 500; }

        /* General Card */
        .pa-card {
            background: #ffffff;
            border: 1px solid var(--pa-border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px -2px rgba(0,0,0,0.05);
        }
        .pa-card-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 0;
            margin-bottom: 16px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 18px;
            font-weight: 700;
            color: var(--pa-dark);
        }

        /* Preset Buttons */
        .pa-presets-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            padding: 14px 18px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            align-items: center;
        }
        .pa-preset-btn {
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .pa-preset-btn:hover { background: #f1f5f9; border-color: #94a3b8; transform: translateY(-1px); }
        .pa-preset-btn.btn-god {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #b91c1c;
        }
        .pa-preset-btn.btn-god:hover {
            background: #dc2626;
            color: #fff;
            border-color: #b91c1c;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
        }

        /* Scopes Categories */
        .pa-category-section {
            margin-bottom: 22px;
        }
        .pa-category-title {
            font-size: 14px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pa-scopes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
        }
        .pa-scope-card {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
            user-select: none;
        }
        .pa-scope-card:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        .pa-scope-card.is-checked {
            background: #eff6ff;
            border-color: #93c5fd;
        }
        .pa-scope-card.is-danger.is-checked {
            background: #fef2f2;
            border-color: #fca5a5;
        }
        .pa-scope-card input[type="checkbox"] {
            margin-top: 3px;
            cursor: pointer;
            width: 17px;
            height: 17px;
        }
        .pa-scope-info { flex: 1; }
        .pa-scope-name { font-size: 13px; font-weight: 700; color: #0f172a; display: block; font-family: monospace !important; }
        .pa-scope-desc { font-size: 12px; color: #64748b; margin-top: 3px; line-height: 1.5; display: block; font-weight: 400; }

        /* MCP Tools Interactive Grid */
        .pa-mcp-tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .pa-tool-btn {
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px;
            cursor: pointer;
            text-align: right;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .pa-tool-btn:hover { border-color: #93c5fd; background: #f8fafc; transform: translateY(-1px); }
        .pa-tool-btn.active {
            border-color: var(--pa-primary);
            background: #eff6ff;
            box-shadow: 0 0 0 1px var(--pa-primary);
        }
        .pa-tool-btn .pa-tool-icon { font-size: 24px; }
        .pa-tool-btn .pa-tool-title { font-weight: 700; font-size: 14px; color: #0f172a; }
        .pa-tool-btn .pa-tool-sub { font-size: 11px; color: #64748b; margin-top: 2px; }

        /* Empty state for MCP when no tool or key */
        .pa-mcp-empty-state {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 32px 20px;
            text-align: center;
            color: #475569;
            margin-top: 10px;
        }
        .pa-mcp-empty-state h3 { margin: 0 0 6px 0; font-size: 16px; font-weight: 700; color: #1e293b; }
        .pa-mcp-empty-state p { margin: 0; font-size: 13px; color: #64748b; }

        .pa-code-wrapper {
            background: #0f172a;
            border-radius: 12px;
            padding: 18px 20px;
            position: relative;
            margin-top: 14px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.2);
        }
        .pa-code-wrapper pre {
            margin: 0;
            color: #38bdf8;
            font-family: Consolas, Monaco, "Courier New", monospace !important;
            font-size: 13px;
            direction: ltr;
            text-align: left;
            overflow-x: auto;
            white-space: pre-wrap;
            line-height: 1.6;
        }
        .pa-copy-btn {
            position: absolute;
            top: 12px;
            left: 12px;
            background: #334155;
            color: #fff;
            border: 1px solid #475569;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .pa-copy-btn:hover { background: #475569; }
        .pa-copy-btn.copied { background: var(--pa-success); border-color: var(--pa-success); }

        /* Tables */
        .pa-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .pa-table th { background: #f8fafc; padding: 12px 14px; font-size: 13px; font-weight: 700; color: #475569; text-align: right; border-bottom: 2px solid #e2e8f0; }
        .pa-table td { padding: 12px 14px; font-size: 13px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .pa-table tr:hover td { background: #fbfcfe; }
        .pa-tag { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; font-family: var(--pa-font); }
        .pa-tag-active { background: #dcfce7; color: #166534; }
        .pa-tag-rolled { background: #f1f5f9; color: #64748b; }
        .pa-tag-scope { background: #e0f2fe; color: #0369a1; margin: 2px; font-family: monospace !important; }

        .pa-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .pa-btn-primary { background: var(--pa-primary); color: #fff; }
        .pa-btn-primary:hover { background: var(--pa-primary-dark); box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3); }
        .pa-btn-rollback { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; padding: 5px 12px; font-size: 12px; border-radius: 6px; }
        .pa-btn-rollback:hover { background: #fde68a; color: #78350f; }
        .pa-btn-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; padding: 4px 10px; font-size: 12px; border-radius: 6px; }
        .pa-btn-danger:hover { background: #ef4444; color: #fff; }
    </style>

    <!-- HEADER -->
    <div class="pa-header">
        <div class="pa-brand">
            <div class="pa-logo-icon">🤖</div>
            <div>
                <h1>PressAgent <span class="pa-badge">نسخه ۱.۰.۰</span></h1>
                <p>پل ارتباطی هوشمند و امن میان ایجنت‌های هوش مصنوعی (MCP) و وب‌سایت وردپرسی شما</p>
            </div>
        </div>
        <div>
            <a href="https://github.com/Noctis-Architect/press-agent" target="_blank" class="pa-btn" style="background: rgba(255,255,255,0.15); color: #fff;">
                ⭐ مشاهده در گیت‌هاب
            </a>
        </div>
    </div>

    <?php if ( $alert_message ) : ?>
        <div class="notice notice-<?php echo esc_attr( $alert_type ); ?> is-dismissible" style="padding: 14px 18px; border-radius: 10px; margin-bottom: 24px;">
            <p style="margin: 0; font-size: 14px; font-weight: 700;"><?php echo esc_html( $alert_message ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $new_token ) ) : ?>
        <div class="pa-card" style="border: 2px solid var(--pa-success); background: #f0fdf4;">
            <h2 style="color: #166534; margin-top: 0; display: flex; align-items: center; gap: 8px; font-size: 18px; font-weight: 800;">
                🎉 کلید دسترسی اصلی شما صادر شد:
            </h2>
            <div style="background: #ffffff; border: 1px dashed #86efac; padding: 14px 18px; border-radius: 10px; margin: 14px 0; display: flex; align-items: center; justify-content: space-between; direction: ltr;">
                <code style="font-size: 16px; font-weight: 800; color: #15803d; letter-spacing: 0.5px;"><?php echo esc_html( $new_token ); ?></code>
                <button type="button" class="pa-btn pa-btn-primary" style="font-size: 13px;" onclick="copyRawToken('<?php echo esc_js( $new_token ); ?>', this)">📋 کپی کلید</button>
            </div>
            <p style="margin: 0; font-size: 13px; color: #166534;">
                ✅ <strong>تبریک!</strong> اکنون می‌توانید در بخش زیر روی دکمه هر ویرایشگر (مانند Cursor، Claude و...) کلیک کنید تا کدهای کانفیگ دقیقاً با این کلید و آدرس سایت نمایش داده شوند.
            </p>
        </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="pa-stats-grid">
        <div class="pa-stat-card">
            <div class="pa-stat-icon" style="background: #eff6ff; color: #3b82f6;">🔑</div>
            <div>
                <div class="pa-stat-num"><?php echo count( $tokens ); ?></div>
                <div class="pa-stat-label">کلیدهای دسترسی معتبر</div>
            </div>
        </div>
        <div class="pa-stat-card">
            <div class="pa-stat-icon" style="background: #fef3c7; color: #d97706;">📸</div>
            <div>
                <div class="pa-stat-num"><?php echo $total_snapshots; ?></div>
                <div class="pa-stat-label">اسنپ‌شات‌های ثبت‌شده</div>
            </div>
        </div>
        <div class="pa-stat-card">
            <div class="pa-stat-icon" style="background: #dcfce7; color: #16a34a;">🛡️</div>
            <div>
                <div class="pa-stat-num" style="font-size: 16px; color: #16a34a; font-weight: 800;">آماده بازگردانی</div>
                <div class="pa-stat-label">Action Guard (ضدخرابی)</div>
            </div>
        </div>
        <div class="pa-stat-card">
            <div class="pa-stat-icon" style="background: #fee2e2; color: #dc2626;">🔥</div>
            <div>
                <div class="pa-stat-num" style="font-size: 16px; color: #dc2626; font-weight: 800;">God Mode</div>
                <div class="pa-stat-label">پشتیبانی از دسترسی نامحدود</div>
            </div>
        </div>
    </div>

    <!-- 1. MCP INSTALLATION SECTION (CLICK TO REVEAL EXACT CODE) -->
    <div class="pa-card" id="pa-mcp-section">
        <div class="pa-card-title">
            <span>⚡ اتصال به ویرایشگرها و ابزارهای هوش مصنوعی (MCP)</span>
            <?php if ( $has_active_token ) : ?>
                <span style="font-size: 12px; font-weight: normal; color: var(--pa-success);">
                    🟢 کلید فعال: <code><?php echo esc_html( substr( $active_token, 0, 8 ) . '...' ); ?></code>
                </span>
            <?php else : ?>
                <span style="font-size: 12px; font-weight: normal; color: var(--pa-danger);">
                    🔴 نیازمند ایجاد کلید
                </span>
            <?php endif; ?>
        </div>

        <p style="color: #475569; margin-top: 0; font-size: 13px;">
            روی هر ویرایشگری که استفاده می‌کنید کلیک کنید تا کدهای کانفیگ اختصاصی سایت همراه با کلید دسترسی فعال به شما داده شود:
        </p>

        <!-- IDE BUTTONS -->
        <div class="pa-mcp-tools-grid">
            <div class="pa-tool-btn" id="btn-mcp-cursor" onclick="handleToolClick('cursor', this)">
                <span class="pa-tool-icon">🟣</span>
                <div>
                    <div class="pa-tool-title">Cursor IDE</div>
                    <div class="pa-tool-sub">کانفیگ JSON محیط Cursor</div>
                </div>
            </div>
            <div class="pa-tool-btn" id="btn-mcp-claude-code" onclick="handleToolClick('claude-code', this)">
                <span class="pa-tool-icon">⚡</span>
                <div>
                    <div class="pa-tool-title">Claude Code (CLI)</div>
                    <div class="pa-tool-sub">دستور تک‌خطی ترمینال</div>
                </div>
            </div>
            <div class="pa-tool-btn" id="btn-mcp-claude-desktop" onclick="handleToolClick('claude-desktop', this)">
                <span class="pa-tool-icon">🟠</span>
                <div>
                    <div class="pa-tool-title">Claude Desktop</div>
                    <div class="pa-tool-sub">کانفیگ برنامه دسکتاپ</div>
                </div>
            </div>
            <div class="pa-tool-btn" id="btn-mcp-antigravity" onclick="handleToolClick('antigravity', this)">
                <span class="pa-tool-icon">🟢</span>
                <div>
                    <div class="pa-tool-title">Antigravity / Windsurf</div>
                    <div class="pa-tool-sub">محیط‌های هوشمند کدنویسی</div>
                </div>
            </div>
            <div class="pa-tool-btn" id="btn-mcp-cline" onclick="handleToolClick('cline', this)">
                <span class="pa-tool-icon">🤖</span>
                <div>
                    <div class="pa-tool-title">Cline / Roo-Code</div>
                    <div class="pa-tool-sub">افزونه VS Code</div>
                </div>
            </div>
        </div>

        <!-- INITIAL STATE: NO CODE DISPLAYED UNTIL CLICKED -->
        <div id="mcp-empty-state" class="pa-mcp-empty-state">
            <?php if ( $has_active_token ) : ?>
                <h3>👆 یک ابزار را از گزینه‌های بالا انتخاب کنید</h3>
                <p>به محض کلیک روی دکمه ویرایشگر مورد نظر، فایل پیکربندی دقیقاً با کلید اصلی شما باز می‌شود.</p>
            <?php else : ?>
                <h3 style="color: #b91c1c;">⚠️ ابتدا کلید دسترسی بسازید</h3>
                <p>برای دریافت کدهای کانفیگ، لطفاً ابتدا از فرم زیر یک کلید صادر کنید یا دکمه ساخت کلید سریع را بزنید.</p>
                <div style="margin-top: 14px;">
                    <button type="button" class="pa-btn pa-btn-primary" onclick="scrollToGenerator()">
                        👇 رفتن به بخش ساخت کلید
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ( $has_active_token ) : ?>
            <!-- CODE PANELS (HIDDEN INITIALLY) -->
            <div id="mcp-panel-cursor" class="pa-mcp-panel" style="display: none;">
                <div class="pa-code-wrapper">
                    <button type="button" class="pa-copy-btn" onclick="copySnippetText('code-cursor', this)">📋 کپی کانفیگ Cursor</button>
                    <pre id="code-cursor">{
  "mcpServers": {
    "pressagent": {
      "command": "npx",
      "args": ["-y", "pressagent"],
      "env": {
        "PRESSAGENT_WP_URL": "<?php echo esc_js( $site_url ); ?>",
        "PRESSAGENT_TOKEN": "<?php echo esc_js( $active_token ); ?>"
      }
    }
  }
}</pre>
                </div>
                <p style="font-size: 13px; color: var(--pa-muted); margin: 8px 4px 0 4px;">
                    💡 <strong>راهنما:</strong> در محیط Cursor کلیدهای <code>Ctrl + Shift + J</code> را بزنید ➔ <strong>Features ➔ MCP</strong> ➔ کانفیگ بالا را پیست کنید.
                </p>
            </div>

            <div id="mcp-panel-claude-code" class="pa-mcp-panel" style="display: none;">
                <div class="pa-code-wrapper">
                    <button type="button" class="pa-copy-btn" onclick="copySnippetText('code-claude-code', this)">📋 کپی دستور ترمینال</button>
                    <pre id="code-claude-code">claude mcp add pressagent -e PRESSAGENT_WP_URL="<?php echo esc_attr( $site_url ); ?>" -e PRESSAGENT_TOKEN="<?php echo esc_attr( $active_token ); ?>" -- npx -y pressagent</pre>
                </div>
                <p style="font-size: 13px; color: var(--pa-muted); margin: 8px 4px 0 4px;">
                    💡 <strong>راهنما:</strong> این دستور را در ترمینال یا خط فرمان اجرا کنید تا دستیار به وردپرس شما متصل شود.
                </p>
            </div>

            <div id="mcp-panel-claude-desktop" class="pa-mcp-panel" style="display: none;">
                <div class="pa-code-wrapper">
                    <button type="button" class="pa-copy-btn" onclick="copySnippetText('code-claude-desktop', this)">📋 کپی کانفیگ Claude Desktop</button>
                    <pre id="code-claude-desktop">{
  "mcpServers": {
    "pressagent": {
      "command": "npx",
      "args": ["-y", "pressagent"],
      "env": {
        "PRESSAGENT_WP_URL": "<?php echo esc_js( $site_url ); ?>",
        "PRESSAGENT_TOKEN": "<?php echo esc_js( $active_token ); ?>"
      }
    }
  }
}</pre>
                </div>
                <p style="font-size: 13px; color: var(--pa-muted); margin: 8px 4px 0 4px;">
                    💡 <strong>راهنما:</strong> در فایل <code>claude_desktop_config.json</code> در پوشه داده‌های Claude قرار دهید.
                </p>
            </div>

            <div id="mcp-panel-antigravity" class="pa-mcp-panel" style="display: none;">
                <div class="pa-code-wrapper">
                    <button type="button" class="pa-copy-btn" onclick="copySnippetText('code-antigravity', this)">📋 کپی کانفیگ Antigravity</button>
                    <pre id="code-antigravity">{
  "mcpServers": {
    "pressagent": {
      "command": "npx",
      "args": ["-y", "pressagent"],
      "env": {
        "PRESSAGENT_WP_URL": "<?php echo esc_js( $site_url ); ?>",
        "PRESSAGENT_TOKEN": "<?php echo esc_js( $active_token ); ?>"
      }
    }
  }
}</pre>
                </div>
                <p style="font-size: 13px; color: var(--pa-muted); margin: 8px 4px 0 4px;">
                    💡 <strong>راهنما:</strong> در فایل تنظیمات <code>mcp_config.json</code> محیط Antigravity قرار دهید.
                </p>
            </div>

            <div id="mcp-panel-cline" class="pa-mcp-panel" style="display: none;">
                <div class="pa-code-wrapper">
                    <button type="button" class="pa-copy-btn" onclick="copySnippetText('code-cline', this)">📋 کپی کانفیگ Cline</button>
                    <pre id="code-cline">{
  "mcpServers": {
    "pressagent": {
      "command": "npx",
      "args": ["-y", "pressagent"],
      "env": {
        "PRESSAGENT_WP_URL": "<?php echo esc_js( $site_url ); ?>",
        "PRESSAGENT_TOKEN": "<?php echo esc_js( $active_token ); ?>"
      },
      "disabled": false,
      "autoApprove": []
    }
  }
}</pre>
                </div>
                <p style="font-size: 13px; color: var(--pa-muted); margin: 8px 4px 0 4px;">
                    💡 <strong>راهنما:</strong> در تنظیمات افزونه Cline در بخش MCP Servers اضافه فرمایید.
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- 2. CREATE TOKEN WITH 3 PRESETS (SAFE, STANDARD, GOD MODE) -->
    <div class="pa-card" id="pa-generator-section">
        <div class="pa-card-title">
            <span>🔑 ساخت کلید دسترسی جدید با سطوح اختیارات (RBAC)</span>
        </div>

        <!-- PRESET BUTTONS (SAFE, STANDARD, GOD MODE) -->
        <div class="pa-presets-bar">
            <span style="font-size: 13px; font-weight: 800; color: #475569; margin-left: 8px;">حالت‌های آماده:</span>
            <button type="button" class="pa-preset-btn" onclick="applyPreset('safe')">
                🛡️ حالت امن (فقط خواندن)
            </button>
            <button type="button" class="pa-preset-btn" onclick="applyPreset('standard')">
                ⚡ حالت استاندارد (پیشنهادی)
            </button>
            <button type="button" class="pa-preset-btn btn-god" onclick="applyPreset('god')">
                🔥 گاد مود (God Mode - تمام تیک‌ها)
            </button>
            <button type="button" class="pa-preset-btn" onclick="applyPreset('none')" style="margin-right: auto; font-size: 12px; color: #64748b;">
                پاک کردن همه
            </button>
        </div>

        <form method="post">
            <?php wp_nonce_field( 'pressagent_generate_token_nonce' ); ?>
            <input type="hidden" name="pressagent_generate_token" value="1">

            <div style="margin-bottom: 20px;">
                <label for="token_label" style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 14px;">نام یا برچسب کلید:</label>
                <input type="text" name="token_label" id="token_label" class="regular-text" placeholder="مثلاً: لپ‌تاپ من، سرور ایجنت ۲، Cursor IDE" required style="width: 100%; max-width: 450px; padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px;">
            </div>

            <!-- CATEGORY 1: REST API -->
            <div class="pa-category-section">
                <div class="pa-category-title">
                    <span>🌐 ۱. دسترسی REST API و مدیریت محتوا</span>
                </div>
                <div class="pa-scopes-grid">
                    <label class="pa-scope-card is-checked" onclick="updateCardState(this)">
                        <input type="checkbox" name="token_scopes[]" value="pages:read" class="scope-cb cb-read" checked>
                        <div class="pa-scope-info">
                            <span class="pa-scope-name">pages:read</span>
                            <span class="pa-scope-desc">مشاهده برگه‌ها، نوشته‌ها و محصولات ووکامرس</span>
                        </div>
                    </label>
                    <label class="pa-scope-card is-checked" onclick="updateCardState(this)">
                        <input type="checkbox" name="token_scopes[]" value="pages:write" class="scope-cb cb-write" checked>
                        <div class="pa-scope-info">
                            <span class="pa-scope-name">pages:write</span>
                            <span class="pa-scope-desc">ایجاد، ویرایش، انتشار و حذف برگه‌ها و محصولات</span>
                        </div>
                    </label>
                    <label class="pa-scope-card" onclick="updateCardState(this)">
                        <input type="checkbox" name="token_scopes[]" value="rest:general" class="scope-cb cb-write">
                        <div class="pa-scope-info">
                            <span class="pa-scope-name">rest:general</span>
                            <span class="pa-scope-desc">ارسال درخواست به سایر اندپوینت‌های استاندارد REST API</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- CATEGORY 2: APPEARANCE & ELEMENTOR -->
            <div class="pa-category-section">
                <div class="pa-category-title">
                    <span>🎨 ۲. طراحی، قالب و المنتور (Appearance)</span>
                </div>
                <div class="pa-scopes-grid">
                    <label class="pa-scope-card is-checked" onclick="updateCardState(this)">
                        <input type="checkbox" name="token_scopes[]" value="elementor:read" class="scope-cb cb-read" checked>
                        <div class="pa-scope-info">
                            <span class="pa-scope-name">elementor:read</span>
                            <span class="pa-scope-desc">خواندن ساختار کانتینرها، ستون‌ها و ابزارک‌های المنتور</span>
                        </div>
                    </label>
                    <label class="pa-scope-card is-checked" onclick="updateCardState(this)">
                        <input type="checkbox" name="token_scopes[]" value="elementor:write" class="scope-cb cb-write" checked>
                        <div class="pa-scope-info">
                            <span class="pa-scope-name">elementor:write</span>
                            <span class="pa-scope-desc">طراحی، افزودن سکشن و تغییر استایل ابزارک‌های المنتور</span>
                        </div>
                    </label>
                    <label class="pa-scope-card" onclick="updateCardState(this)">
                        <input type="checkbox" name="token_scopes[]" value="appearance:css" class="scope-cb cb-write">
                        <div class="pa-scope-info">
                            <span class="pa-scope-name">appearance:css</span>
                            <span class="pa-scope-desc">تنظیم قالب برگه (تمام‌صفحه) و اعمال استایل‌های CSS اختصاصی</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- CATEGORY 3: CACHE & SECURITY -->
            <div class="pa-category-section">
                <div class="pa-category-title">
                    <span>🚀 ۳. بهینه‌سازی، کش و پلاگین‌های امنیتی</span>
                </div>
                <div class="pa-scopes-grid">
                    <label class="pa-scope-card is-checked" onclick="updateCardState(this)">
                        <input type="checkbox" name="token_scopes[]" value="cache:purge" class="scope-cb cb-write" checked>
                        <div class="pa-scope-info">
                            <span class="pa-scope-name">cache:purge</span>
                            <span class="pa-scope-desc">تخلیه فوری کش سایت (LiteSpeed، WP Rocket و Object Cache)</span>
                        </div>
                    </label>
                    <label class="pa-scope-card is-checked" onclick="updateCardState(this)">
                        <input type="checkbox" name="token_scopes[]" value="cache:status" class="scope-cb cb-read" checked>
                        <div class="pa-scope-info">
                            <span class="pa-scope-name">cache:status</span>
                            <span class="pa-scope-desc">مشاهده وضعیت اتصال و سلامت افزونه‌های کشینگ</span>
                        </div>
                    </label>
                    <label class="pa-scope-card is-checked" onclick="updateCardState(this)">
                        <input type="checkbox" name="token_scopes[]" value="security:read" class="scope-cb cb-read" checked>
                        <div class="pa-scope-info">
                            <span class="pa-scope-name">security:read</span>
                            <span class="pa-scope-desc">مشاهده خلاصه وضعیت امنیتی و گزارش اسکن Wordfence</span>
                        </div>
                    </label>
                    <label class="pa-scope-card is-checked" onclick="updateCardState(this)">
                        <input type="checkbox" name="token_scopes[]" value="settings:read" class="scope-cb cb-read" checked>
                        <div class="pa-scope-info">
                            <span class="pa-scope-name">settings:read</span>
                            <span class="pa-scope-desc">خواندن تنظیمات مجاز افزونه‌ها و پیکربندی‌های وردپرس</span>
                        </div>
                    </label>
                    <label class="pa-scope-card is-checked" onclick="updateCardState(this)">
                        <input type="checkbox" name="token_scopes[]" value="settings:write" class="scope-cb cb-write" checked>
                        <div class="pa-scope-info">
                            <span class="pa-scope-name">settings:write</span>
                            <span class="pa-scope-desc">ویرایش تنظیمات مجاز (مانند برگه اصلی، کش و افزونه‌ها)</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- CATEGORY 4: SYSTEM, DEBUG & GOD MODE -->
            <div class="pa-category-section">
                <div class="pa-category-title">
                    <span>🛠️ ۴. سیستم، دیباگ، دیتابیس و گاد مود</span>
                </div>
                <div class="pa-scopes-grid">
                    <label class="pa-scope-card is-checked" onclick="updateCardState(this)">
                        <input type="checkbox" name="token_scopes[]" value="debug:read" class="scope-cb cb-read" checked>
                        <div class="pa-scope-info">
                            <span class="pa-scope-name">debug:read</span>
                            <span class="pa-scope-desc">خواندن فایل خطاهای سیستم (debug.log)</span>
                        </div>
                    </label>
                    <label class="pa-scope-card is-checked" onclick="updateCardState(this)">
                        <input type="checkbox" name="token_scopes[]" value="debug:write" class="scope-cb cb-write" checked>
                        <div class="pa-scope-info">
                            <span class="pa-scope-name">debug:write</span>
                            <span class="pa-scope-desc">تحلیل هوشمند پیام‌های خطا با کمک ایجنت AI</span>
                        </div>
                    </label>
                    <label class="pa-scope-card" onclick="updateCardState(this)">
                        <input type="checkbox" name="token_scopes[]" value="database:backup" class="scope-cb cb-write">
                        <div class="pa-scope-info">
                            <span class="pa-scope-name">database:backup</span>
                            <span class="pa-scope-desc">ایجاد و بازگردانی اسنپ‌شات‌های جداول دیتابیس</span>
                        </div>
                    </label>
                    <label class="pa-scope-card is-danger" onclick="updateCardState(this)">
                        <input type="checkbox" name="token_scopes[]" value="code:execute" class="scope-cb cb-god">
                        <div class="pa-scope-info">
                            <span class="pa-scope-name" style="color: #dc2626;">🔥 code:execute (گاد مود)</span>
                            <span class="pa-scope-desc" style="color: #b91c1c;">اجرای کدهای سیستمی، ساخت پلاگین و اختیارات کامل هسته</span>
                        </div>
                    </label>
                </div>
            </div>

            <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #f1f5f9;">
                <button type="submit" class="pa-btn pa-btn-primary" style="padding: 10px 24px; font-size: 14px;">
                    ✨ صدور کلید دسترسی با دسترسی‌های انتخابی
                </button>
            </div>
        </form>
    </div>

    <!-- 3. SNAPSHOTS & ROLLBACK SECTION -->
    <div class="pa-card">
        <div class="pa-card-title">
            <span>📸 تاریخچه اسنپ‌شات‌ها و قابلیت بازگردانی (Action Guard)</span>
            <span style="font-size: 12px; font-weight: normal; color: var(--pa-muted);">
                قبل از هر تغییر اساسی در المنتور یا تنظیمات، وضعیت قبلی ذخیره می‌شود
            </span>
        </div>

        <?php if ( empty( $snapshots ) ) : ?>
            <p style="text-align: center; color: var(--pa-muted); padding: 24px 0;">
                هنوز اسنپ‌شاتی ثبت نشده است. هر زمان ایجنت هوش مصنوعی تغییری ایجاد کند، اینجا ثبت می‌شود.
            </p>
        <?php else : ?>
            <div style="overflow-x: auto;">
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">شناسه</th>
                            <th style="width: 140px;">نوع عملیات</th>
                            <th>جزئیات و محتوای تغییر</th>
                            <th style="width: 160px;">تاریخ و زمان</th>
                            <th style="width: 110px;">وضعیت</th>
                            <th style="width: 110px; text-align: center;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $snapshots as $snap ) : 
                            $ctx = json_decode( $snap['context'], true );
                            $is_active = ( $snap['status'] === 'active' );
                        ?>
                            <tr>
                                <td><strong>#<?php echo esc_html( $snap['id'] ); ?></strong></td>
                                <td>
                                    <?php if ( $snap['action_type'] === 'update_elementor' ) : ?>
                                        <span class="pa-tag" style="background: #ede9fe; color: #6d28d9;">🎨 لایوت المنتور</span>
                                    <?php elseif ( $snap['action_type'] === 'update_option' ) : ?>
                                        <span class="pa-tag" style="background: #fef3c7; color: #b45309;">⚙️ تنظیمات</span>
                                    <?php else : ?>
                                        <span class="pa-tag" style="background: #f1f5f9; color: #475569;"><?php echo esc_html( $snap['action_type'] ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( isset( $ctx['page_id'] ) ) : ?>
                                        برگه شناسه: <code>#<?php echo esc_html( $ctx['page_id'] ); ?></code> (<?php echo esc_html( get_the_title( $ctx['page_id'] ) ?: 'بدون عنوان' ); ?>)
                                    <?php elseif ( isset( $ctx['option_name'] ) ) : ?>
                                        آپشن وردپرس: <code><?php echo esc_html( $ctx['option_name'] ); ?></code>
                                    <?php else : ?>
                                        <code><?php echo esc_html( $snap['context'] ); ?></code>
                                    <?php endif; ?>
                                </td>
                                <td style="direction: ltr; text-align: right; color: var(--pa-muted); font-size: 12px;">
                                    <?php echo esc_html( $snap['created_at'] ); ?>
                                </td>
                                <td>
                                    <?php if ( $is_active ) : ?>
                                        <span class="pa-tag pa-tag-active">قابل بازگردانی</span>
                                    <?php else : ?>
                                        <span class="pa-tag pa-tag-rolled">بازگردانی‌شده</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ( $is_active ) : ?>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('آیا از بازگردانی این اسنپ‌شات به حالت قبل اطمینان دارید؟');">
                                            <?php wp_nonce_field( 'pressagent_rollback_snapshot_nonce' ); ?>
                                            <input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $snap['id'] ); ?>">
                                            <input type="hidden" name="pressagent_rollback_snapshot" value="1">
                                            <button type="submit" class="pa-btn-rollback">⏪ بازگردانی</button>
                                        </form>
                                    <?php else : ?>
                                        <span style="color: #94a3b8; font-size: 12px;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- 4. ACTIVE TOKENS TABLE -->
    <div class="pa-card">
        <div class="pa-card-title">
            <span>🔑 کلیدهای دسترسی فعال سیستم</span>
            <span style="font-size: 12px; font-weight: normal; color: var(--pa-muted);"><?php echo count( $tokens ); ?> کلید معتبر</span>
        </div>

        <?php if ( empty( $tokens ) ) : ?>
            <p style="text-align: center; color: var(--pa-muted); padding: 20px 0;">
                هیچ کلید فعالی ثبت نشده است. با استفاده از فرم بالا می‌توانید کلید جدید ایجاد کنید.
            </p>
        <?php else : ?>
            <div style="overflow-x: auto;">
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>برچسب کلید</th>
                            <th>دسترسی‌های اعطا شده (Scopes)</th>
                            <th style="width: 150px;">تاریخ ساخت</th>
                            <th style="width: 150px;">آخرین استفاده</th>
                            <th style="width: 80px; text-align: center;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $tokens as $id => $tok ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $tok['label'] ); ?></strong>
                                    <?php if ( in_array( 'code:execute', $tok['scopes'], true ) || in_array( '*', $tok['scopes'], true ) ) : ?>
                                        <span class="pa-tag" style="background: #fee2e2; color: #dc2626; margin-right: 4px;">🔥 God Mode</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php foreach ( $tok['scopes'] as $sc ) : ?>
                                        <span class="pa-tag pa-tag-scope"><?php echo esc_html( $sc ); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td style="direction: ltr; text-align: right; color: var(--pa-muted); font-size: 12px;">
                                    <?php echo esc_html( $tok['created_at'] ); ?>
                                </td>
                                <td style="direction: ltr; text-align: right; color: var(--pa-muted); font-size: 12px;">
                                    <?php echo esc_html( $tok['last_used'] ?: 'استفاده نشده' ); ?>
                                </td>
                                <td style="text-align: center;">
                                    <form method="post" style="display:inline;" onsubmit="return confirm('آیا از ابطال همیشگی این کلید مطمئن هستید؟');">
                                        <?php wp_nonce_field( 'pressagent_revoke_token_nonce' ); ?>
                                        <input type="hidden" name="token_id" value="<?php echo esc_attr( $id ); ?>">
                                        <input type="hidden" name="pressagent_revoke_token" value="1">
                                        <button type="submit" class="pa-btn-danger">ابطال</button>
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

<script>
var hasToken = <?php echo $has_active_token ? 'true' : 'false'; ?>;

// Click handler for IDE Tool buttons
function handleToolClick(toolId, btnEl) {
    if (!hasToken) {
        alert('لطفاً ابتدا از بخش زیر یک کلید دسترسی (Token) صادر کنید تا کدهای کانفیگ ساخته شوند.');
        scrollToGenerator();
        return;
    }

    // Hide empty state
    var emptyState = document.getElementById('mcp-empty-state');
    if (emptyState) {
        emptyState.style.display = 'none';
    }

    // Update active button state
    document.querySelectorAll('.pa-tool-btn').forEach(function(el) {
        el.classList.remove('active');
    });
    btnEl.classList.add('active');

    // Hide all panels, then reveal target
    document.querySelectorAll('.pa-mcp-panel').forEach(function(el) {
        el.style.display = 'none';
    });

    var targetPanel = document.getElementById('mcp-panel-' + toolId);
    if (targetPanel) {
        targetPanel.style.display = 'block';
    }
}

function scrollToGenerator() {
    var gen = document.getElementById('pa-generator-section');
    if (gen) {
        gen.scrollIntoView({ behavior: 'smooth' });
        var labelInput = document.getElementById('token_label');
        if (labelInput) {
            setTimeout(function() { labelInput.focus(); }, 400);
        }
    }
}

// Copy Snippet Code
function copySnippetText(elementId, btnElement) {
    var text = document.getElementById(elementId).innerText;
    navigator.clipboard.writeText(text).then(function() {
        var orig = btnElement.innerText;
        btnElement.innerText = 'کپی شد! ✓';
        btnElement.classList.add('copied');
        setTimeout(function() {
            btnElement.innerText = orig;
            btnElement.classList.remove('copied');
        }, 2000);
    }).catch(function() {
        alert('لطفاً متن کد را به صورت دستی کپی نمایید.');
    });
}

function copyRawToken(tokenText, btnElement) {
    navigator.clipboard.writeText(tokenText).then(function() {
        var orig = btnElement.innerText;
        btnElement.innerText = 'کپی شد! ✓';
        setTimeout(function() {
            btnElement.innerText = orig;
        }, 2000);
    });
}

// Sync Visual Card State with Checkbox
function updateCardState(cardEl) {
    var cb = cardEl.querySelector('input[type="checkbox"]');
    setTimeout(function() {
        if (cb.checked) {
            cardEl.classList.add('is-checked');
        } else {
            cardEl.classList.remove('is-checked');
        }
    }, 10);
}

// Apply 3 Presets (Safe, Standard, God Mode)
function applyPreset(type) {
    var allCbs = document.querySelectorAll('.scope-cb');
    allCbs.forEach(function(cb) {
        cb.checked = false;
    });

    if (type === 'god') {
        // All checkboxes checked!
        allCbs.forEach(function(cb) {
            cb.checked = true;
        });
    } else if (type === 'standard') {
        // Standard AI Ops (everything except code:execute)
        document.querySelectorAll('.cb-read, .cb-write').forEach(function(cb) {
            cb.checked = true;
        });
    } else if (type === 'safe') {
        // Safe Read-only
        document.querySelectorAll('.cb-read').forEach(function(cb) {
            cb.checked = true;
        });
    }

    // Refresh visual card styles
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
