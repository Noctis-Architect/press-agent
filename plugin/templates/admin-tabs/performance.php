<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="tab-performance" class="pa-tab-pane">
    <div class="pa-card">
        <div class="pa-card-header">
            <div>
                <h2 class="pa-card-title">
                    <span class="pa-en">Performance Audit</span>
                    <span class="pa-fa">آنالیز عملکرد</span>
                </h2>
                <div class="pa-card-desc">
                    <span class="pa-en">Server, database, cache, Elementor and LiteSpeed checks with safe fixes.</span>
                    <span class="pa-fa">بررسی سرور، دیتابیس، کش، المنتور و لایت‌اسپید همراه با fixهای امن.</span>
                </div>
            </div>
            <form method="post" style="margin:0;">
                <?php wp_nonce_field( 'pressagent_performance_nonce' ); ?>
                <input type="hidden" name="pressagent_run_performance_audit" value="1">
                <button type="submit" class="pa-btn pa-btn-primary pa-btn-sm">
                    <span class="pa-en">Run Audit</span>
                    <span class="pa-fa">اجرای آنالیز</span>
                </button>
            </form>
        </div>

        <?php if ( empty( $performance_audit ) ) : ?>
            <div style="padding: 22px; text-align:center; color: var(--pa-text-muted); font-size: 12.5px;">
                <span class="pa-en">Run the audit to inspect PHP/OPcache, object cache, page cache, autoload size, revisions, transients, debug.log and plugin/cache configuration.</span>
                <span class="pa-fa">برای بررسی PHP/OPcache، object cache، page cache، حجم autoload، ریبایژن‌ها، transientها، debug.log و تنظیمات کش/پلاگین، آنالیز را اجرا کنید.</span>
            </div>
        <?php else : ?>
            <?php
            $perf_env  = $performance_audit['environment'] ?? array();
            $perf_db   = $performance_audit['database'] ?? array();
            $perf_cache= $performance_audit['cache'] ?? array();
            $perf_page = $perf_cache['page_cache'] ?? array();
            ?>
            <div class="pa-metric-grid">
                <div class="pa-stat-card">
                    <div class="pa-stat-label">OPcache</div>
                    <div class="pa-stat-val" style="font-size:16px; color:<?php echo ! empty( $perf_env['opcache'] ) ? 'var(--pa-success)' : '#d97706'; ?>;">
                        <?php echo ! empty( $perf_env['opcache'] ) ? 'Enabled' : 'Disabled'; ?>
                    </div>
                </div>
                <div class="pa-stat-card">
                    <div class="pa-stat-label">Object Cache</div>
                    <div class="pa-stat-val" style="font-size:16px; color:<?php echo ! empty( $perf_env['object_cache'] ) ? 'var(--pa-success)' : '#d97706'; ?>;">
                        <?php echo ! empty( $perf_env['object_cache'] ) ? 'Persistent' : 'Missing'; ?>
                    </div>
                </div>
                <div class="pa-stat-card">
                    <div class="pa-stat-label">Page Cache</div>
                    <div class="pa-stat-val" style="font-size:16px; color:<?php echo ! empty( $perf_page['enabled'] ) ? 'var(--pa-success)' : '#d97706'; ?>;">
                        <?php echo esc_html( $perf_page['plugin'] ?: 'Not detected' ); ?>
                    </div>
                    <div class="pa-stat-sub"><?php echo ! empty( $perf_page['enabled'] ) ? 'Enabled' : 'Disabled / unknown'; ?></div>
                </div>
                <div class="pa-stat-card">
                    <div class="pa-stat-label">Autoload</div>
                    <div class="pa-stat-val" style="font-size:16px;"><?php echo esc_html( size_format( (int) ( $perf_db['autoload_bytes'] ?? 0 ), 2 ) ); ?></div>
                    <div class="pa-stat-sub"><?php echo (int) ( $perf_db['autoload_count'] ?? 0 ); ?> rows</div>
                </div>
                <div class="pa-stat-card">
                    <div class="pa-stat-label">Revisions</div>
                    <div class="pa-stat-val" style="font-size:16px;"><?php echo (int) ( $perf_db['revisions'] ?? 0 ); ?></div>
                    <div class="pa-stat-sub"><?php echo (int) ( $perf_db['expired_transients'] ?? 0 ); ?> expired transients</div>
                </div>
                <div class="pa-stat-card">
                    <div class="pa-stat-label">Database</div>
                    <div class="pa-stat-val" style="font-size:16px;"><?php echo null !== ( $perf_db['size_mb'] ?? null ) ? esc_html( $perf_db['size_mb'] . ' MB' ) : '—'; ?></div>
                    <div class="pa-stat-sub">PHP <?php echo esc_html( $performance_audit['site']['php'] ?? PHP_VERSION ); ?> / WP <?php echo esc_html( $performance_audit['site']['wp_version'] ?? get_bloginfo( 'version' ) ); ?></div>
                </div>
            </div>

            <div class="pa-section-label">
                <span class="pa-en">Prioritized Issues</span>
                <span class="pa-fa">مشکلات اولویت‌دار</span>
            </div>

            <?php if ( empty( $performance_audit['issues'] ) ) : ?>
                <div style="padding: 18px; border:1px solid var(--pa-border); border-radius:8px; color:var(--pa-success); font-size:12.5px;">
                    <span class="pa-en">No performance issues detected by the audit.</span>
                    <span class="pa-fa">مشکل عملکردی توسط آنالیز شناسایی نشد.</span>
                </div>
            <?php else : ?>
                <?php foreach ( $performance_audit['issues'] as $issue ) : ?>
                    <div class="pa-issue <?php echo esc_attr( $issue['severity'] ); ?>">
                        <div class="pa-issue-title">
                            <span class="pa-chip <?php echo 'high' === $issue['severity'] ? 'pa-chip-danger' : ( 'medium' === $issue['severity'] ? 'pa-chip-warning' : 'pa-chip-neutral' ); ?>"><?php echo esc_html( strtoupper( $issue['severity'] ) ); ?></span>
                            <?php echo esc_html( $issue['title'] ); ?>
                        </div>
                        <div class="pa-issue-detail"><?php echo esc_html( $issue['detail'] ); ?></div>
                        <?php if ( ! empty( $issue['fix_id'] ) ) : ?>
                            <form method="post" class="pa-issue-actions">
                                <?php wp_nonce_field( 'pressagent_performance_nonce' ); ?>
                                <input type="hidden" name="pressagent_performance_fix" value="1">
                                <input type="hidden" name="fix_id" value="<?php echo esc_attr( $issue['fix_id'] ); ?>">
                                <?php if ( 'delete_old_revisions' === $issue['fix_id'] ) : ?>
                                    <label class="pa-checkbox-line">
                                        <input type="number" name="age_days" value="30" min="1" max="3650" class="pa-input">
                                        <span class="pa-en">days old</span><span class="pa-fa">روز</span>
                                    </label>
                                <?php endif; ?>
                                <?php if ( in_array( $issue['fix_id'], array( 'delete_old_revisions', 'cleanup_database' ), true ) ) : ?>
                                    <label class="pa-checkbox-line">
                                        <input type="checkbox" name="confirm_destructive" value="1">
                                        <span class="pa-en">I understand this is destructive</span><span class="pa-fa">از مخرب بودن آگاهم</span>
                                    </label>
                                <?php endif; ?>
                                <button type="submit" class="pa-btn <?php echo in_array( $issue['fix_id'], array( 'delete_old_revisions', 'cleanup_database' ), true ) ? 'pa-btn-danger' : 'pa-btn-secondary'; ?> pa-btn-sm">
                                    <span class="pa-en">Apply Fix</span>
                                    <span class="pa-fa">اعمال راه‌حل</span>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ( ! empty( $perf_db['top_autoload'] ) ) : ?>
                <div class="pa-section-label"><span class="pa-en">Top Autoloaded Options</span><span class="pa-fa">بزرگ‌ترین گزینه‌های autoload</span></div>
                <div class="pa-table-wrapper">
                    <table class="pa-table">
                        <thead><tr><th>Option</th><th>Size</th></tr></thead>
                        <tbody>
                            <?php foreach ( $perf_db['top_autoload'] as $row ) : ?>
                                <tr><td class="pa-mono"><?php echo esc_html( $row['option_name'] ); ?></td><td><?php echo esc_html( size_format( (int) $row['size'], 2 ) ); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="pa-card">
        <div class="pa-card-header">
            <div>
                <h2 class="pa-card-title">
                    <span class="pa-en">Page Performance</span>
                    <span class="pa-fa">عملکرد یک صفحه</span>
                </h2>
                <div class="pa-card-desc">
                    <span class="pa-en">Fetch a same-origin page and measure response time, HTML, scripts, styles, images, compression and cache headers.</span>
                    <span class="pa-fa">یک صفحه same-origin را دریافت و زمان پاسخ، HTML، scriptها، styleها، تصاویر، فشرده‌سازی و هدر کش را اندازه‌گیری می‌کند.</span>
                </div>
            </div>
        </div>

        <form method="post">
            <?php wp_nonce_field( 'pressagent_performance_nonce' ); ?>
            <input type="hidden" name="pressagent_analyze_page" value="1">
            <div class="pa-form-grid">
                <div class="pa-field">
                    <label for="perf_page_id"><span class="pa-en">Page</span><span class="pa-fa">برگه</span></label>
                    <?php
                    wp_dropdown_pages( array(
                        'name'             => 'perf_page_id',
                        'id'               => 'perf_page_id',
                        'show_option_none' => __( '— Select a page —', 'pressagent' ),
                        'option_none_value'=> '0',
                        'selected'         => 0,
                        'class'            => 'pa-input',
                    ) );
                    ?>
                </div>
                <div class="pa-field">
                    <label for="perf_page_url"><span class="pa-en">Or URL / path</span><span class="pa-fa">یا آدرس / مسیر</span></label>
                    <input type="text" name="perf_page_url" id="perf_page_url" class="pa-input" placeholder="/shop/ or https://example.com/">
                    <small><span class="pa-en">Only URLs on this WordPress origin are allowed.</span><span class="pa-fa">فقط آدرس‌های همین دامنه مجاز هستند.</span></small>
                </div>
            </div>
            <div style="margin-top:14px;">
                <button type="submit" class="pa-btn pa-btn-primary">
                    <span class="pa-en">Analyze Page</span>
                    <span class="pa-fa">آنالیز صفحه</span>
                </button>
            </div>
        </form>

        <?php if ( ! empty( $page_audit ) ) : ?>
            <?php $metrics = $page_audit['metrics']; ?>
            <div class="pa-section-label"><span class="pa-en">Metrics</span><span class="pa-fa">متریک‌ها</span></div>
            <div class="pa-metric-grid">
                <div class="pa-stat-card"><div class="pa-stat-label">Response</div><div class="pa-stat-val" style="font-size:18px;"><?php echo (int) $metrics['time_ms']; ?> ms</div><div class="pa-stat-sub">HTTP <?php echo (int) $metrics['status']; ?></div></div>
                <div class="pa-stat-card"><div class="pa-stat-label">HTML</div><div class="pa-stat-val" style="font-size:18px;"><?php echo esc_html( size_format( (int) $metrics['html_bytes'], 2 ) ); ?></div></div>
                <div class="pa-stat-card"><div class="pa-stat-label">Scripts</div><div class="pa-stat-val" style="font-size:18px;"><?php echo (int) $metrics['scripts']; ?></div><div class="pa-stat-sub"><?php echo (int) $metrics['head_blocking_scripts']; ?> blocking in head</div></div>
                <div class="pa-stat-card"><div class="pa-stat-label">Stylesheets</div><div class="pa-stat-val" style="font-size:18px;"><?php echo (int) $metrics['stylesheets']; ?></div><div class="pa-stat-sub"><?php echo (int) $metrics['elementor_css']; ?> Elementor CSS</div></div>
                <div class="pa-stat-card"><div class="pa-stat-label">Images</div><div class="pa-stat-val" style="font-size:18px;"><?php echo (int) $metrics['images']; ?></div><div class="pa-stat-sub"><?php echo (int) $metrics['images_missing_dimensions']; ?> missing dimensions</div></div>
                <div class="pa-stat-card"><div class="pa-stat-label">Compression</div><div class="pa-stat-val" style="font-size:18px;"><?php echo esc_html( $metrics['compression'] ?: 'None' ); ?></div><div class="pa-stat-sub"><?php echo esc_html( $metrics['cache_header'] ?: 'No cache header' ); ?></div></div>
            </div>

            <?php if ( ! empty( $page_audit['issues'] ) ) : ?>
                <div class="pa-section-label"><span class="pa-en">Page Issues</span><span class="pa-fa">مشکلات صفحه</span></div>
                <?php foreach ( $page_audit['issues'] as $issue ) : ?>
                    <div class="pa-issue <?php echo esc_attr( $issue['severity'] ); ?>">
                        <div class="pa-issue-title"><?php echo esc_html( $issue['title'] ); ?></div>
                        <div class="pa-issue-detail"><?php echo esc_html( $issue['detail'] ); ?></div>
                        <?php if ( ! empty( $issue['fix_id'] ) ) : ?>
                            <div class="pa-help" style="margin-top:6px;">Fix: <code><?php echo esc_html( $issue['fix_id'] ); ?></code></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
