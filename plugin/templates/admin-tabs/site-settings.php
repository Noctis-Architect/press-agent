<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$general    = $wp_settings['general'] ?? array();
$reading    = $wp_settings['reading'] ?? array();
$discussion = $wp_settings['discussion'] ?? array();
$media      = $wp_settings['media'] ?? array();
$permalinks = $wp_settings['permalinks'] ?? array();
$privacy    = $wp_settings['privacy'] ?? array();
$current_lang = $general['WPLANG'] ?? '';
$languages    = array_values( array_unique( array_merge( array( '' ), (array) $available_languages, array( $current_lang ) ) ) );
$weekdays     = array();
for ( $i = 0; $i <= 6; $i++ ) {
    $weekdays[ $i ] = isset( $GLOBALS['wp_locale'] ) && is_object( $GLOBALS['wp_locale'] ) ? $GLOBALS['wp_locale']->get_weekday( $i ) : (string) $i;
}
?>
<div id="tab-site-settings" class="pa-tab-pane">
    <div class="pa-card">
        <div class="pa-card-header">
            <div>
                <h2 class="pa-card-title">
                    <span class="pa-en">WordPress Core Settings</span>
                    <span class="pa-fa">تنظیمات هسته وردپرس</span>
                </h2>
                <div class="pa-card-desc">
                    <span class="pa-en">Validated site settings with snapshots. Changing siteurl/home requires explicit confirmation.</span>
                    <span class="pa-fa">تنظیمات معتبرسازي‌شده سایت با اسنپ‌شات. تغییر siteurl/home نیازمند تایید صریح است.</span>
                </div>
            </div>
        </div>

        <form method="post">
            <?php wp_nonce_field( 'pressagent_wp_settings_nonce' ); ?>
            <input type="hidden" name="pressagent_save_wp_settings" value="1">

            <div class="pa-section-label"><span class="pa-en">General</span><span class="pa-fa">عمومی</span></div>
            <div class="pa-form-grid">
                <div class="pa-field">
                    <label for="wp_blogname"><?php esc_html_e( 'Site title', 'pressagent' ); ?></label>
                    <input type="text" class="pa-input" id="wp_blogname" name="wp_settings[general][blogname]" value="<?php echo esc_attr( $general['blogname'] ?? '' ); ?>">
                </div>
                <div class="pa-field">
                    <label for="wp_blogdescription"><?php esc_html_e( 'Tagline', 'pressagent' ); ?></label>
                    <input type="text" class="pa-input" id="wp_blogdescription" name="wp_settings[general][blogdescription]" value="<?php echo esc_attr( $general['blogdescription'] ?? '' ); ?>">
                </div>
                <div class="pa-field">
                    <label for="wp_admin_email"><?php esc_html_e( 'Administration email', 'pressagent' ); ?></label>
                    <input type="email" class="pa-input" id="wp_admin_email" name="wp_settings[general][admin_email]" value="<?php echo esc_attr( $general['admin_email'] ?? '' ); ?>">
                </div>
                <div class="pa-field">
                    <label for="wp_siteurl"><?php esc_html_e( 'WordPress address (siteurl)', 'pressagent' ); ?></label>
                    <input type="url" class="pa-input" id="wp_siteurl" name="wp_settings[general][siteurl]" value="<?php echo esc_attr( $general['siteurl'] ?? '' ); ?>">
                </div>
                <div class="pa-field">
                    <label for="wp_home"><?php esc_html_e( 'Site address (home)', 'pressagent' ); ?></label>
                    <input type="url" class="pa-input" id="wp_home" name="wp_settings[general][home]" value="<?php echo esc_attr( $general['home'] ?? '' ); ?>">
                </div>
                <div class="pa-field">
                    <label for="wp_default_role"><?php esc_html_e( 'New user default role', 'pressagent' ); ?></label>
                    <select class="pa-input" id="wp_default_role" name="wp_settings[general][default_role]">
                        <?php foreach ( $role_names as $role_key => $role_name ) : ?>
                            <option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $general['default_role'] ?? 'subscriber', $role_key ); ?>><?php echo esc_html( translate_user_role( $role_name ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pa-field">
                    <label for="wp_timezone"><?php esc_html_e( 'Timezone', 'pressagent' ); ?></label>
                    <select class="pa-input" id="wp_timezone" name="wp_settings[general][timezone_string]">
                        <?php wp_timezone_choice( $general['timezone_string'] ?? '' ); ?>
                    </select>
                    <small><?php esc_html_e( 'Leave empty to use a manual UTC offset below.', 'pressagent' ); ?></small>
                </div>
                <div class="pa-field">
                    <label for="wp_gmt_offset"><?php esc_html_e( 'Manual UTC offset', 'pressagent' ); ?></label>
                    <input type="number" step="0.5" min="-12" max="14" class="pa-input" id="wp_gmt_offset" name="wp_settings[general][gmt_offset]" value="<?php echo esc_attr( $general['gmt_offset'] ?? 0 ); ?>">
                </div>
                <div class="pa-field">
                    <label for="wp_date_format"><?php esc_html_e( 'Date format', 'pressagent' ); ?></label>
                    <input type="text" class="pa-input" id="wp_date_format" name="wp_settings[general][date_format]" value="<?php echo esc_attr( $general['date_format'] ?? '' ); ?>">
                </div>
                <div class="pa-field">
                    <label for="wp_time_format"><?php esc_html_e( 'Time format', 'pressagent' ); ?></label>
                    <input type="text" class="pa-input" id="wp_time_format" name="wp_settings[general][time_format]" value="<?php echo esc_attr( $general['time_format'] ?? '' ); ?>">
                </div>
                <div class="pa-field">
                    <label for="wp_start_of_week"><?php esc_html_e( 'Week starts on', 'pressagent' ); ?></label>
                    <select class="pa-input" id="wp_start_of_week" name="wp_settings[general][start_of_week]">
                        <?php foreach ( $weekdays as $day_index => $day_name ) : ?>
                            <option value="<?php echo esc_attr( $day_index ); ?>" <?php selected( (int) ( $general['start_of_week'] ?? 1 ), $day_index ); ?>><?php echo esc_html( $day_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pa-field">
                    <label for="wp_language"><?php esc_html_e( 'Site language', 'pressagent' ); ?></label>
                    <select class="pa-input" id="wp_language" name="wp_settings[general][WPLANG]">
                        <?php foreach ( $languages as $language_code ) : ?>
                            <option value="<?php echo esc_attr( $language_code ); ?>" <?php selected( $current_lang, $language_code ); ?>>
                                <?php echo esc_html( '' === $language_code ? 'English (United States)' : $language_code ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:12px; margin-top:16px;">
                <label class="pa-inline-check">
                    <input type="hidden" name="wp_settings[general][users_can_register]" value="0">
                    <input type="checkbox" name="wp_settings[general][users_can_register]" value="1" <?php checked( ! empty( $general['users_can_register'] ) ); ?>>
                    <span class="pa-en">Anyone can register</span><span class="pa-fa">امکان ثبت‌نام برای همه</span>
                </label>
                <label class="pa-inline-check">
                    <input type="hidden" name="wp_settings[general][blog_public]" value="0">
                    <input type="checkbox" name="wp_settings[general][blog_public]" value="1" <?php checked( ! empty( $general['blog_public'] ) ); ?>>
                    <span class="pa-en">Allow search engines to index this site</span><span class="pa-fa">اجازه ایندکس شدن سایت توسط موتورهای جستجو</span>
                </label>
            </div>

            <div class="pa-section-label"><span class="pa-en">Reading</span><span class="pa-fa">خواندن</span></div>
            <div class="pa-form-grid">
                <div class="pa-field">
                    <label for="wp_show_on_front"><?php esc_html_e( 'Front page displays', 'pressagent' ); ?></label>
                    <select class="pa-input" id="wp_show_on_front" name="wp_settings[reading][show_on_front]">
                        <option value="posts" <?php selected( $reading['show_on_front'] ?? 'posts', 'posts' ); ?>><?php esc_html_e( 'Latest posts', 'pressagent' ); ?></option>
                        <option value="page" <?php selected( $reading['show_on_front'] ?? 'posts', 'page' ); ?>><?php esc_html_e( 'A static page', 'pressagent' ); ?></option>
                    </select>
                </div>
                <div class="pa-field">
                    <label for="wp_page_on_front"><?php esc_html_e( 'Homepage', 'pressagent' ); ?></label>
                    <?php wp_dropdown_pages( array( 'name' => 'wp_settings[reading][page_on_front]', 'id' => 'wp_page_on_front', 'selected' => (int) ( $reading['page_on_front'] ?? 0 ), 'show_option_none' => __( '— Select —', 'pressagent' ), 'option_none_value' => '0', 'class' => 'pa-input' ) ); ?>
                </div>
                <div class="pa-field">
                    <label for="wp_page_for_posts"><?php esc_html_e( 'Posts page', 'pressagent' ); ?></label>
                    <?php wp_dropdown_pages( array( 'name' => 'wp_settings[reading][page_for_posts]', 'id' => 'wp_page_for_posts', 'selected' => (int) ( $reading['page_for_posts'] ?? 0 ), 'show_option_none' => __( '— Select —', 'pressagent' ), 'option_none_value' => '0', 'class' => 'pa-input' ) ); ?>
                </div>
                <div class="pa-field">
                    <label for="wp_posts_per_page"><?php esc_html_e( 'Blog pages show at most', 'pressagent' ); ?></label>
                    <input type="number" min="1" max="100" class="pa-input" id="wp_posts_per_page" name="wp_settings[reading][posts_per_page]" value="<?php echo esc_attr( $reading['posts_per_page'] ?? 10 ); ?>">
                </div>
            </div>

            <div class="pa-section-label"><span class="pa-en">Discussion</span><span class="pa-fa">بحث و دیدگاه‌ها</span></div>
            <div class="pa-form-grid">
                <div class="pa-field">
                    <label for="wp_default_comment_status"><?php esc_html_e( 'Default comment status', 'pressagent' ); ?></label>
                    <select class="pa-input" id="wp_default_comment_status" name="wp_settings[discussion][default_comment_status]">
                        <option value="open" <?php selected( $discussion['default_comment_status'] ?? 'open', 'open' ); ?>><?php esc_html_e( 'Open', 'pressagent' ); ?></option>
                        <option value="closed" <?php selected( $discussion['default_comment_status'] ?? 'open', 'closed' ); ?>><?php esc_html_e( 'Closed', 'pressagent' ); ?></option>
                    </select>
                </div>
                <div class="pa-field">
                    <label for="wp_default_ping_status"><?php esc_html_e( 'Default ping status', 'pressagent' ); ?></label>
                    <select class="pa-input" id="wp_default_ping_status" name="wp_settings[discussion][default_ping_status]">
                        <option value="open" <?php selected( $discussion['default_ping_status'] ?? 'open', 'open' ); ?>><?php esc_html_e( 'Open', 'pressagent' ); ?></option>
                        <option value="closed" <?php selected( $discussion['default_ping_status'] ?? 'open', 'closed' ); ?>><?php esc_html_e( 'Closed', 'pressagent' ); ?></option>
                    </select>
                </div>
                <div class="pa-field">
                    <label for="wp_comment_max_links"><?php esc_html_e( 'Comment max links', 'pressagent' ); ?></label>
                    <input type="number" min="0" class="pa-input" id="wp_comment_max_links" name="wp_settings[discussion][comment_max_links]" value="<?php echo esc_attr( $discussion['comment_max_links'] ?? 2 ); ?>">
                </div>
                <div class="pa-field">
                    <label for="wp_comments_per_page"><?php esc_html_e( 'Comments per page', 'pressagent' ); ?></label>
                    <input type="number" min="0" class="pa-input" id="wp_comments_per_page" name="wp_settings[discussion][comments_per_page]" value="<?php echo esc_attr( $discussion['comments_per_page'] ?? 50 ); ?>">
                </div>
            </div>
            <div style="display:flex; flex-direction:column; gap:12px; margin-top:16px;">
                <?php
                $discussion_toggles = array(
                    'comment_registration'          => array( 'Users must be registered to comment', 'کاربران برای دیدگاه باید ثبت‌نام کنند' ),
                    'close_comments_for_old_posts'  => array( 'Close comments on old posts', 'بستن دیدگاه‌های نوشته‌های قدیمی' ),
                    'thread_comments'               => array( 'Enable threaded comments', 'فعال‌سازی دیدگاه‌های درختی' ),
                    'page_comments'                 => array( 'Break comments into pages', 'صفحه‌بندی دیدگاه‌ها' ),
                );
                foreach ( $discussion_toggles as $toggle_key => $toggle_label ) :
                    ?>
                    <label class="pa-inline-check">
                        <input type="hidden" name="wp_settings[discussion][<?php echo esc_attr( $toggle_key ); ?>]" value="0">
                        <input type="checkbox" name="wp_settings[discussion][<?php echo esc_attr( $toggle_key ); ?>]" value="1" <?php checked( ! empty( $discussion[ $toggle_key ] ) ); ?>>
                        <span class="pa-en"><?php echo esc_html( $toggle_label[0] ); ?></span><span class="pa-fa"><?php echo esc_html( $toggle_label[1] ); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="pa-section-label"><span class="pa-en">Media</span><span class="pa-fa">رسانه</span></div>
            <div class="pa-form-grid">
                <?php
                $media_sizes = array(
                    'thumbnail' => 'Thumbnail',
                    'medium'    => 'Medium',
                    'large'     => 'Large',
                );
                foreach ( $media_sizes as $size_key => $size_label ) :
                    ?>
                    <div class="pa-field">
                        <label><?php echo esc_html( $size_label ); ?></label>
                        <div style="display:flex; gap:8px;">
                            <input type="number" min="0" max="5000" class="pa-input" name="wp_settings[media][<?php echo esc_attr( $size_key ); ?>_size_w]" value="<?php echo esc_attr( $media[ $size_key . '_size_w' ] ?? 0 ); ?>" placeholder="Width">
                            <input type="number" min="0" max="5000" class="pa-input" name="wp_settings[media][<?php echo esc_attr( $size_key ); ?>_size_h]" value="<?php echo esc_attr( $media[ $size_key . '_size_h' ] ?? 0 ); ?>" placeholder="Height">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex; flex-direction:column; gap:12px; margin-top:16px;">
                <label class="pa-inline-check">
                    <input type="hidden" name="wp_settings[media][thumbnail_crop]" value="0">
                    <input type="checkbox" name="wp_settings[media][thumbnail_crop]" value="1" <?php checked( ! empty( $media['thumbnail_crop'] ) ); ?>>
                    <span class="pa-en">Crop thumbnail to exact dimensions</span><span class="pa-fa">برش تصویر بندانگشتی به ابعاد دقیق</span>
                </label>
                <label class="pa-inline-check">
                    <input type="hidden" name="wp_settings[media][uploads_use_yearmonth_folders]" value="0">
                    <input type="checkbox" name="wp_settings[media][uploads_use_yearmonth_folders]" value="1" <?php checked( ! empty( $media['uploads_use_yearmonth_folders'] ) ); ?>>
                    <span class="pa-en">Organize uploads into month/year folders</span><span class="pa-fa">مرتب‌سازی فایل‌های رسانه در پوشه ماه/سال</span>
                </label>
            </div>

            <div class="pa-section-label"><span class="pa-en">Permalinks</span><span class="pa-fa">پیوندهای یکتا</span></div>
            <div class="pa-form-grid">
                <div class="pa-field">
                    <label for="wp_permalink_structure"><?php esc_html_e( 'Permalink structure', 'pressagent' ); ?></label>
                    <input type="text" class="pa-input" id="wp_permalink_structure" name="wp_settings[permalinks][permalink_structure]" value="<?php echo esc_attr( $permalinks['permalink_structure'] ?? '' ); ?>" placeholder="/%postname%/">
                    <small><?php esc_html_e( 'Saving flushes rewrite rules automatically.', 'pressagent' ); ?></small>
                </div>
                <div class="pa-field">
                    <label for="wp_category_base"><?php esc_html_e( 'Category base', 'pressagent' ); ?></label>
                    <input type="text" class="pa-input" id="wp_category_base" name="wp_settings[permalinks][category_base]" value="<?php echo esc_attr( $permalinks['category_base'] ?? '' ); ?>">
                </div>
                <div class="pa-field">
                    <label for="wp_tag_base"><?php esc_html_e( 'Tag base', 'pressagent' ); ?></label>
                    <input type="text" class="pa-input" id="wp_tag_base" name="wp_settings[permalinks][tag_base]" value="<?php echo esc_attr( $permalinks['tag_base'] ?? '' ); ?>">
                </div>
            </div>

            <div class="pa-section-label"><span class="pa-en">Privacy &amp; Identity</span><span class="pa-fa">حریم خصوصی و هویت</span></div>
            <div class="pa-form-grid">
                <div class="pa-field">
                    <label for="wp_privacy_page"><?php esc_html_e( 'Privacy policy page', 'pressagent' ); ?></label>
                    <?php wp_dropdown_pages( array( 'name' => 'wp_settings[privacy][wp_page_for_privacy_policy]', 'id' => 'wp_privacy_page', 'selected' => (int) ( $privacy['wp_page_for_privacy_policy'] ?? 0 ), 'show_option_none' => __( '— Select —', 'pressagent' ), 'option_none_value' => '0', 'class' => 'pa-input' ) ); ?>
                </div>
                <div class="pa-field">
                    <label for="wp_site_icon"><?php esc_html_e( 'Site icon attachment ID', 'pressagent' ); ?></label>
                    <input type="number" min="0" class="pa-input" id="wp_site_icon" name="wp_settings[privacy][site_icon]" value="<?php echo esc_attr( $privacy['site_icon'] ?? 0 ); ?>">
                    <small><?php esc_html_e( 'Must be an image attachment ID.', 'pressagent' ); ?></small>
                </div>
            </div>

            <div style="margin-top: 22px; padding-top: 16px; border-top: 1px solid var(--pa-border-subtle); display:flex; flex-direction:column; gap:12px;">
                <label class="pa-inline-check">
                    <input type="checkbox" name="confirm_siteurl" value="1">
                    <span class="pa-en">I understand changing siteurl/home can break the site</span>
                    <span class="pa-fa">می‌دانم تغییر siteurl/home می‌تواند سایت را از دسترس خارج کند</span>
                </label>
                <div>
                    <button type="submit" class="pa-btn pa-btn-primary">
                        <span class="pa-en">Save Site Settings</span>
                        <span class="pa-fa">ذخیره تنظیمات سایت</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
