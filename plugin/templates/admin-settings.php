<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

$site_url = get_site_url();

// Handle quick default token generation
if ( isset( $_POST['pressagent_quick_setup'] ) && check_admin_referer( 'pressagent_quick_setup_nonce' ) ) {
    $default_scopes = array( 'pages:read', 'pages:write', 'elementor:read', 'elementor:write', 'settings:read', 'settings:write', 'debug:read', 'debug:write', 'security:read' );
    $new_token = PressAgent_Auth::generate_token( 'Default Agent Token (' . current_time( 'Y-m-d' ) . ')', $default_scopes );
}

if ( isset( $_POST['pressagent_generate_token'] ) && check_admin_referer( 'pressagent_generate_token_nonce' ) ) {
    $label = sanitize_text_field( $_POST['token_label'] );
    $scopes = isset( $_POST['token_scopes'] ) ? array_map( 'sanitize_text_field', $_POST['token_scopes'] ) : array();
    $new_token = PressAgent_Auth::generate_token( $label, $scopes );
}

if ( isset( $_POST['pressagent_revoke_token'] ) && check_admin_referer( 'pressagent_revoke_token_nonce' ) ) {
    $token_id = sanitize_text_field( $_POST['token_id'] );
    PressAgent_Auth::revoke_token( $token_id );
}

$tokens = get_option( 'pressagent_tokens', array() );
$available_scopes = array( 'pages:read', 'pages:write', 'elementor:read', 'elementor:write', 'settings:read', 'settings:write', 'debug:read', 'debug:write', 'security:read', 'code:execute' );

$display_token = isset( $new_token ) ? $new_token : ( ! empty( $tokens ) ? 'YOUR_SAVED_TOKEN' : 'GENERATE_TOKEN_FIRST' );
?>

<div class="wrap pressagent-admin-wrap">
    <style>
        .pressagent-admin-wrap { max-width: 1100px; margin-top: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
        .pressagent-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0; }
        .pressagent-title { display: flex; align-items: center; gap: 12px; margin: 0; font-size: 24px; font-weight: 700; color: #0f172a; }
        .pressagent-badge { background: #3b82f6; color: #fff; font-size: 12px; padding: 2px 10px; border-radius: 9999px; font-weight: 600; text-transform: uppercase; }
        
        .pressagent-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .pressagent-card h2 { margin-top: 0; font-size: 18px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px; }
        
        .pressagent-tabs-nav { display: flex; gap: 8px; margin-bottom: 16px; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; }
        .pressagent-tab-btn { background: #f8fafc; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; color: #475569; transition: all 0.2s ease; }
        .pressagent-tab-btn:hover { background: #e2e8f0; color: #0f172a; }
        .pressagent-tab-btn.active { background: #0f172a; color: #ffffff; border-color: #0f172a; }
        
        .pressagent-code-box { position: relative; background: #0f172a; border-radius: 8px; padding: 18px; margin: 12px 0; overflow-x: auto; }
        .pressagent-code-box pre { margin: 0; color: #38bdf8; font-family: "JetBrains Mono", Consolas, Monaco, "Courier New", monospace; font-size: 13px; line-height: 1.6; white-space: pre-wrap; word-break: break-all; }
        .pressagent-copy-btn { position: absolute; top: 12px; right: 12px; background: #334155; color: #ffffff; border: 1px solid #475569; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
        .pressagent-copy-btn:hover { background: #475569; }
        .pressagent-copy-btn.copied { background: #10b981; border-color: #059669; color: #ffffff; }

        .pressagent-tip { font-size: 13px; color: #64748b; margin-top: 8px; display: flex; align-items: center; gap: 6px; }
        .pressagent-quick-card { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff; border: none; }
        .pressagent-quick-card h2, .pressagent-quick-card p { color: #f8fafc; }
        .pressagent-btn-glow { background: #3b82f6; color: #fff; border: none; padding: 10px 20px; font-size: 14px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: background 0.2s; }
        .pressagent-btn-glow:hover { background: #2563eb; }
        
        .scope-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; margin: 12px 0; }
        .scope-label { background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 10px; border-radius: 6px; display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; }
    </style>

    <div class="pressagent-header">
        <h1 class="pressagent-title">
            <span>PressAgent</span>
            <span class="pressagent-badge">AI Ops Bridge v1.0</span>
        </h1>
        <a href="https://github.com/Noctis-Architect/press-agent" target="_blank" class="button">GitHub Repo</a>
    </div>

    <?php if ( isset( $new_token ) ) : ?>
        <div class="notice notice-success is-dismissible" style="padding: 12px 16px; border-left-color: #10b981;">
            <p style="margin: 0 0 6px 0; font-size: 15px;"><strong>🎉 New Token Generated:</strong></p>
            <div style="background: #f1f5f9; padding: 8px 12px; border-radius: 6px; font-family: monospace; font-size: 14px; display: inline-block;">
                <code><?php echo esc_html( $new_token ); ?></code>
            </div>
            <p style="margin: 8px 0 0 0; color: #b91c1c;"><em>⚠️ Copy this token now! It has been pre-filled into the connection snippets below.</em></p>
        </div>
    <?php endif; ?>

    <!-- ONE-CLICK AI CONNECTION BOX -->
    <div class="pressagent-card">
        <h2>⚡ Connect to AI Agent (1-Click Configuration)</h2>
        <p style="color: #475569; margin-top: 4px;">Choose your AI tool below, click copy, and paste it into your editor configuration:</p>

        <div class="pressagent-tabs-nav">
            <button type="button" class="pressagent-tab-btn active" onclick="switchPressAgentTab('cursor')">🟣 Cursor IDE</button>
            <button type="button" class="pressagent-tab-btn" onclick="switchPressAgentTab('claude-code')">⚡ Claude Code (CLI)</button>
            <button type="button" class="pressagent-tab-btn" onclick="switchPressAgentTab('claude-desktop')">🟠 Claude Desktop</button>
            <button type="button" class="pressagent-tab-btn" onclick="switchPressAgentTab('antigravity')">🟢 Antigravity / Windsurf</button>
        </div>

        <!-- TAB 1: CURSOR -->
        <div id="tab-cursor" class="pressagent-tab-content">
            <div class="pressagent-code-box">
                <button type="button" class="pressagent-copy-btn" onclick="copySnippet('snippet-cursor', this)">📋 Copy for Cursor</button>
                <pre id="snippet-cursor">{
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
            <div class="pressagent-tip">💡 <strong>How to use in Cursor:</strong> Open Cursor ➔ <code>Settings (Ctrl+Shift+J)</code> ➔ <code>Features</code> ➔ <code>MCP</code> ➔ Click <code>Open Config</code> and paste.</div>
        </div>

        <!-- TAB 2: CLAUDE CODE -->
        <div id="tab-claude-code" class="pressagent-tab-content" style="display: none;">
            <div class="pressagent-code-box">
                <button type="button" class="pressagent-copy-btn" onclick="copySnippet('snippet-claude-code', this)">📋 Copy CLI Command</button>
                <pre id="snippet-claude-code">claude mcp add pressagent -e PRESSAGENT_WP_URL="<?php echo esc_attr( $site_url ); ?>" -e PRESSAGENT_TOKEN="<?php echo esc_attr( $display_token ); ?>" -- npx -y pressagent</pre>
            </div>
            <div class="pressagent-tip">💡 <strong>How to use in Claude Code:</strong> Paste this one-liner directly into your terminal. Done!</div>
        </div>

        <!-- TAB 3: CLAUDE DESKTOP -->
        <div id="tab-claude-desktop" class="pressagent-tab-content" style="display: none;">
            <div class="pressagent-code-box">
                <button type="button" class="pressagent-copy-btn" onclick="copySnippet('snippet-claude-desktop', this)">📋 Copy for Claude Desktop</button>
                <pre id="snippet-claude-desktop">{
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
            <div class="pressagent-tip">💡 <strong>How to use in Claude Desktop:</strong> Add to <code>claude_desktop_config.json</code> in your Claude application data folder.</div>
        </div>

        <!-- TAB 4: ANTIGRAVITY -->
        <div id="tab-antigravity" class="pressagent-tab-content" style="display: none;">
            <div class="pressagent-code-box">
                <button type="button" class="pressagent-copy-btn" onclick="copySnippet('snippet-antigravity', this)">📋 Copy Config</button>
                <pre id="snippet-antigravity">{
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
            <div class="pressagent-tip">💡 <strong>How to use in Antigravity:</strong> Add into your MCP settings or workspace configuration.</div>
        </div>
    </div>

    <?php if ( empty( $tokens ) ) : ?>
        <!-- QUICK SETUP BANNER FOR FIRST TIME USERS -->
        <div class="pressagent-card pressagent-quick-card">
            <h2>🚀 Quick Setup</h2>
            <p>You have not generated any API tokens yet. Click the button below to generate a default token with all recommended permissions in 1 second:</p>
            <form method="post" style="margin-top: 12px;">
                <?php wp_nonce_field( 'pressagent_quick_setup_nonce' ); ?>
                <input type="hidden" name="pressagent_quick_setup" value="1">
                <button type="submit" class="pressagent-btn-glow">✨ Generate Quick Access Token</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- TOKEN MANAGEMENT TABLE -->
    <div class="pressagent-card">
        <h2>🔑 Active Tokens</h2>
        <table class="wp-list-table widefat fixed striped" style="border: 1px solid #e2e8f0; border-radius: 6px;">
            <thead>
                <tr>
                    <th style="font-weight: 600;">Label</th>
                    <th style="font-weight: 600;">Granted Scopes</th>
                    <th style="font-weight: 600;">Created At</th>
                    <th style="font-weight: 600;">Last Used</th>
                    <th style="font-weight: 600; width: 80px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $tokens ) ) : ?>
                    <tr><td colspan="5" style="text-align: center; color: #64748b; padding: 18px;">No active tokens. Generate one below to get started.</td></tr>
                <?php else : ?>
                    <?php foreach ( $tokens as $id => $token ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $token['label'] ); ?></strong></td>
                            <td><span style="font-size: 12px; color: #475569;"><?php echo esc_html( implode( ', ', $token['scopes'] ) ); ?></span></td>
                            <td><?php echo esc_html( $token['created_at'] ); ?></td>
                            <td><?php echo esc_html( $token['last_used'] ?: 'Never' ); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'pressagent_revoke_token_nonce' ); ?>
                                    <input type="hidden" name="token_id" value="<?php echo esc_attr( $id ); ?>">
                                    <input type="hidden" name="pressagent_revoke_token" value="1">
                                    <button type="submit" class="button button-link-delete" onclick="return confirm('Revoke this token permanently?');">Revoke</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <h3 style="margin-top: 24px; font-size: 15px;">Create Custom Scoped Token</h3>
        <form method="post" style="background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <?php wp_nonce_field( 'pressagent_generate_token_nonce' ); ?>
            <input type="hidden" name="pressagent_generate_token" value="1">
            <div style="margin-bottom: 12px;">
                <label for="token_label" style="font-weight: 600; display: block; margin-bottom: 4px;">Token Label:</label>
                <input type="text" name="token_label" id="token_label" class="regular-text" placeholder="e.g. My Laptop Cursor" required style="width: 100%; max-width: 400px;">
            </div>
            
            <div style="margin-bottom: 16px;">
                <div style="display: flex; align-items: center; justify-content: space-between; max-width: 600px; margin-bottom: 6px;">
                    <label style="font-weight: 600;">Permissions (RBAC Scopes):</label>
                    <a href="javascript:void(0)" onclick="toggleAllScopes(true)" style="font-size: 12px;">Select All</a>
                </div>
                <div class="scope-grid">
                    <?php foreach ( $available_scopes as $scope ) : ?>
                        <label class="scope-label">
                            <input type="checkbox" name="token_scopes[]" value="<?php echo esc_attr( $scope ); ?>" class="scope-cb" <?php echo $scope !== 'code:execute' ? 'checked' : ''; ?>>
                            <span><?php echo esc_html( $scope ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="button button-primary">Generate Custom Token</button>
        </form>
    </div>

    <!-- GOD MODE NOTICE -->
    <div class="pressagent-card" style="border-left: 4px solid #f59e0b;">
        <h2>🛡️ Safety & Developer (God) Mode</h2>
        <p style="color: #475569; margin-bottom: 0;">
            Action Guard is active. All reversible actions automatically create a rollback snapshot in <code>wp_pressagent_snapshots</code>.
            God Mode (Arbitrary PHP Plugin Execution) is currently <strong>disabled</strong> in production for security.
        </p>
    </div>
</div>

<script>
function switchPressAgentTab(tabName) {
    document.querySelectorAll('.pressagent-tab-content').forEach(function(el) {
        el.style.display = 'none';
    });
    document.querySelectorAll('.pressagent-tab-btn').forEach(function(el) {
        el.classList.remove('active');
    });
    
    var activeTab = document.getElementById('tab-' + tabName);
    if (activeTab) {
        activeTab.style.display = 'block';
    }
    
    event.target.classList.add('active');
}

function copySnippet(elementId, btnElement) {
    var text = document.getElementById(elementId).innerText;
    navigator.clipboard.writeText(text).then(function() {
        var originalText = btnElement.innerText;
        btnElement.innerText = 'Copied! ✓';
        btnElement.classList.add('copied');
        setTimeout(function() {
            btnElement.innerText = originalText;
            btnElement.classList.remove('copied');
        }, 2000);
    }).catch(function(err) {
        alert('Could not copy automatically. Please select the text and copy manually.');
    });
}

function toggleAllScopes(checked) {
    document.querySelectorAll('.scope-cb').forEach(function(cb) {
        cb.checked = checked;
    });
}
</script>
