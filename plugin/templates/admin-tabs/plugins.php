<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="tab-plugins" class="pa-tab-pane">
    <div class="pa-card">
        <div class="pa-card-header">
            <div>
                <h2 class="pa-card-title">
                    <span class="pa-en">Plugin Intelligence Hub</span>
                    <span class="pa-fa">مرکز هوشمند پلاگین‌ها</span>
                </h2>
                <div class="pa-card-desc">
                    <span class="pa-en">Verified integrations with the exact settings and actions exposed to the AI agent.</span>
                    <span class="pa-fa">یکپارچه‌سازی‌های تأییدشده همراه با تنظیمات و اکشن‌های دقیقی که به ایجنت ارائه می‌شوند.</span>
                </div>
            </div>
            <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="pa-btn pa-btn-secondary pa-btn-sm">
                <span class="pa-en">Open WordPress Plugins</span>
                <span class="pa-fa">صفحه افزونه‌های وردپرس</span>
            </a>
        </div>

        <div class="pa-stats-grid" style="margin-bottom: 0;">
            <div class="pa-stat-card">
                <div class="pa-stat-label"><span class="pa-en">Supported Integrations</span><span class="pa-fa">یکپارچه‌سازی‌های پشتیبانی‌شده</span></div>
                <div class="pa-stat-val"><?php echo count( $supported_plugins ); ?></div>
                <div class="pa-stat-sub"><span class="pa-en">Free plugins with validated schemas</span><span class="pa-fa">پلاگین‌های رایگان با اسکیمای معتبر</span></div>
            </div>
            <div class="pa-stat-card">
                <div class="pa-stat-label"><span class="pa-en">Active Supported</span><span class="pa-fa">فعال از میان پشتیبانی‌شده‌ها</span></div>
                <div class="pa-stat-val" style="color: var(--pa-success);"><?php echo (int) $active_supported_count; ?></div>
                <div class="pa-stat-sub"><span class="pa-en">Available to the AI agent now</span><span class="pa-fa">در دسترس ایجنت در حال حاضر</span></div>
            </div>
            <div class="pa-stat-card">
                <div class="pa-stat-label"><span class="pa-en">Installed Plugins</span><span class="pa-fa">کل افزونه‌های نصب‌شده</span></div>
                <div class="pa-stat-val"><?php echo (int) $installed_plugin_count; ?></div>
                <div class="pa-stat-sub"><span class="pa-en">All plugins on this site</span><span class="pa-fa">همه افزونه‌های این سایت</span></div>
            </div>
        </div>
    </div>

    <div class="pa-card">
        <div class="pa-card-header">
            <div>
                <h2 class="pa-card-title">
                    <span class="pa-en">Supported Plugins</span>
                    <span class="pa-fa">پلاگین‌های پشتیبانی‌شده</span>
                </h2>
                <div class="pa-card-desc">
                    <span class="pa-en">Use the PressAgent agent for configuration. Unknown options are rejected; every write is snapshotted.</span>
                    <span class="pa-fa">برای تنظیمات از ایجنت PressAgent استفاده کنید. گزینه ناشناخته رد می‌شود و هر نوشتن اسنپ‌شات دارد.</span>
                </div>
            </div>
        </div>

        <div class="pa-table-wrapper">
            <table class="pa-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Plugin', 'pressagent' ); ?></th>
                        <th><?php esc_html_e( 'Version', 'pressagent' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'pressagent' ); ?></th>
                        <th><?php esc_html_e( 'Settings', 'pressagent' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'pressagent' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $supported_plugins as $plugin_slug => $catalog ) : ?>
                        <?php
                        $installed = $installed_plugins_by_slug[ $plugin_slug ] ?? null;
                        $is_active = ! empty( $catalog['active'] );
                        $version   = $catalog['version'] ?: ( $installed['version'] ?? '—' );
                        $new_version = $installed['new_version'] ?? null;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $catalog['name'] ); ?></strong>
                                <div class="pa-help" style="font-family: var(--pa-font-mono);"><?php echo esc_html( $plugin_slug ); ?></div>
                                <?php if ( ! empty( $catalog['notes'][0] ) ) : ?>
                                    <div class="pa-help"><?php echo esc_html( $catalog['notes'][0] ); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="pa-mono"><?php echo esc_html( $version ); ?></span>
                                <?php if ( $new_version ) : ?>
                                    <div><span class="pa-chip pa-chip-warning"><?php echo esc_html( $new_version ); ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $is_active ) : ?>
                                    <span class="pa-chip pa-chip-success"><?php esc_html_e( 'Active', 'pressagent' ); ?></span>
                                <?php else : ?>
                                    <span class="pa-chip pa-chip-neutral"><?php esc_html_e( 'Inactive', 'pressagent' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="pa-mono"><?php echo count( $catalog['settings'] ?? array() ); ?></span></td>
                            <td><span class="pa-mono"><?php echo count( $catalog['actions'] ?? array() ); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="pa-help" style="margin-top: 14px;">
            <span class="pa-en">Premium plugins are never downloaded by PressAgent. If a licensed Pro copy is installed, its shared settings are detected automatically; Pro-only modules remain documented but are not bundled.</span>
            <span class="pa-fa">PressAgent نسخه Pro را دانلود نمی‌کند. اگر نسخه Pro دارای لایسنس نصب باشد، تنظیمات مشترک آن شناسایی می‌شود؛ ماژول‌های اختصاصی Pro مستند می‌شوند ولی همراه افزونه عرضه نمی‌شوند.</span>
        </p>
    </div>
</div>
