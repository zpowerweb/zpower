<?php
/**
 * @wordpress-plugin
 * Plugin Name:       ZPower Slider
 * Plugin URI:        https://zpower.tw
 * Description:       Standalone responsive slider editor with desktop/mobile images, shortcode output, autoplay, article sliders, and bundled Swiper assets.
 * Version:           1.5.15
 * Author:            立平方網頁設計有限公司
 * Author URI:        https://zpower.tw
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       zpower-slider
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Tested up to:      6.4
 * Requires PHP:      7.4
 */

// 防止直接訪問文件
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define('ZPOWER_SLIDER_VERSION', '1.5.15');
define('ZPOWER_SLIDER_PLUGIN_FILE', __FILE__);
define('ZPOWER_SLIDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZPOWER_SLIDER_PLUGIN_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', function() {
    load_plugin_textdomain('zpower-slider', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

if (!function_exists('zpower_slider_uses_english_locale')) {
    function zpower_slider_uses_english_locale() {
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        return is_string($locale) && stripos($locale, 'en') === 0;
    }
}

if (!function_exists('zpower_slider_t')) {
    function zpower_slider_t($zh_tw, $en_us) {
        if (zpower_slider_uses_english_locale()) {
            return $en_us;
        }

        $translated = __($zh_tw, 'zpower-slider');
        if ($translated !== $zh_tw) {
            return $translated;
        }

        return zpower_slider_uses_english_locale() ? $en_us : $zh_tw;
    }
}

// == ZPower 輪播圖（桌機/手機圖+RWD+自動輪播+權限開關+共用連結+文章輪播+響應式固定高度+響應式標題設定+高斯模糊+文字淡入淡出+進度條+輪播樣式切換）==

// 自訂顏色清理函數
if (!function_exists('zpower_sanitize_color_alpha')) {
    function zpower_sanitize_color_alpha( $color_value ) {
        if ( empty( $color_value ) ) {
            return '';
        }
        if ( preg_match( '/^rgba\(\s*(?:\d{1,3}\s*,\s*){2}\d{1,3}\s*,\s*(?:0|1|0?\.\d+)\s*\)$/i', $color_value ) ) {
            return $color_value;
        }
        if ( preg_match( '/^#([a-fA-F0-9]{8})$/i', $color_value ) ) { 
            return $color_value;
        }
        if ( sanitize_hex_color( $color_value ) ) { 
            return sanitize_hex_color( $color_value );
        }
        return '';
    }
}

if (!function_exists('zpower_slider_current_user_can_manage')) {
    function zpower_slider_current_user_can_manage() {
        if (current_user_can('manage_options')) {
            return true;
        }

        $user = wp_get_current_user();
        return $user && in_array('editor', (array) $user->roles, true);
    }
}

if (!function_exists('zpower_slider_clamp_int')) {
    function zpower_slider_clamp_int($value, $min, $max, $fallback) {
        if (is_array($value) || $value === '' || $value === null) {
            return $fallback;
        }

        $value = intval($value);
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}

if (!function_exists('zpower_slider_post_value')) {
    function zpower_slider_post_value($key, $default = '') {
        if (!isset($_POST[$key])) {
            return $default;
        }

        $value = wp_unslash($_POST[$key]);

        if (is_array($value)) {
            return $default;
        }

        return $value;
    }
}


// 註冊自訂文章類型 'zpower_slider'
add_action('init', function() {
    $labels = array(
        'name' => zpower_slider_t('輪播圖', 'Sliders'),
        'singular_name' => zpower_slider_t('輪播圖', 'Slider'),
        'add_new' => zpower_slider_t('新增輪播圖', 'Add New Slider'),
        'add_new_item' => zpower_slider_t('新增輪播圖', 'Add New Slider'),
        'edit_item' => zpower_slider_t('編輯輪播圖', 'Edit Slider'),
        'new_item' => zpower_slider_t('新輪播圖', 'New Slider'),
        'all_items' => zpower_slider_t('所有輪播圖', 'All Sliders'),
        'view_item' => zpower_slider_t('檢視輪播圖', 'View Slider'),
        'search_items' => zpower_slider_t('搜尋輪播圖', 'Search Sliders'),
        'not_found' => zpower_slider_t('找不到輪播圖', 'No sliders found'),
        'not_found_in_trash' => zpower_slider_t('垃圾桶內無輪播圖', 'No sliders found in Trash'),
        'menu_name' => zpower_slider_t('輪播圖', 'Sliders')
    );
    $args = array(
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-images-alt2',
        'supports' => array('title'),
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    );
    register_post_type('zpower_slider', $args);
});

// 新增設定子選單頁面
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=zpower_slider',
        zpower_slider_t('輪播圖設定', 'Slider Settings'),
        zpower_slider_t('輪播圖設定', 'Slider Settings'),
        'manage_options',
        'zpower_slider_settings',
        'zpower_slider_settings_page_callback'
    );
});

// 在輪播圖列表頁標題旁注入「輪播圖設定」按鈕
add_action('admin_footer', function() {
    $screen = get_current_screen();
    if ( $screen && $screen->post_type === 'zpower_slider' && $screen->base === 'edit' ) {
        $settings_url = esc_url_raw(admin_url('admin.php?page=zpower_slider_settings'));
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var heading = document.querySelector('.wp-heading-inline');
            if (heading) {
                var btn = document.createElement('a');
                btn.href  = '<?php echo esc_js($settings_url); ?>';
                btn.className = 'page-title-action';
                btn.textContent = '<?php echo esc_js(zpower_slider_t('輪播圖設定', 'Slider Settings')); ?>';
                heading.parentNode.insertBefore(btn, heading.nextSibling);
            }
        });
        </script>
        <?php
    }
});

// 設定頁面回呼
function zpower_slider_settings_page_callback() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html(zpower_slider_t('您沒有權限進行此操作', 'You do not have permission to perform this action.')));
    }

    if (isset($_POST['zpower_slider_admin_only_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zpower_slider_admin_only_nonce'])), 'zpower_slider_admin_only_save')) {
        $val = zpower_slider_post_value('zpower_slider_admin_only') === 'yes' ? 'yes' : '';
        update_option('zpower_slider_admin_only', $val);
        echo '<div class="updated notice"><p>' . esc_html(zpower_slider_t('設定已儲存。', 'Settings saved.')) . '</p></div>';
    }

    $checked = 'checked';
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(zpower_slider_t('輪播圖權限設定', 'Slider Permission Settings')); ?></h1>
        <form method="post">
            <?php wp_nonce_field('zpower_slider_admin_only_save', 'zpower_slider_admin_only_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html(zpower_slider_t('僅限管理員/編輯可編輯輪播圖', 'Only administrators and editors can edit sliders')); ?></th>
                    <td>
                        <label>
                            <input type="hidden" name="zpower_slider_admin_only" value="yes">
                            <input type="checkbox" name="zpower_slider_admin_only" value="yes" <?php echo $checked; ?> disabled>
                            <?php echo esc_html(zpower_slider_t('為了安全性，輪播圖固定只有管理員與編輯可以新增、編輯、刪除。', 'For security, only administrators and editors can add, edit, and delete sliders.')); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <?php submit_button(zpower_slider_t('儲存設定', 'Save Settings')); ?>
        </form>
    </div>
    <?php
}

// 過濾用戶權限
add_filter('user_has_cap', function($allcaps, $caps, $args, $user){
    global $pagenow, $typenow;

    $is_slider_context = false;

    if (isset($_GET['post_type']) && sanitize_key(wp_unslash($_GET['post_type'])) === 'zpower_slider') {
        $is_slider_context = true;
    } elseif (isset($_GET['post'])) {
        $post_id = intval(wp_unslash($_GET['post']));
        if ($post_id > 0) {
            $post_obj = get_post($post_id);
            if ($post_obj && $post_obj->post_type === 'zpower_slider') {
                $is_slider_context = true;
            }
        }
    } elseif ($typenow === 'zpower_slider') {
        $is_slider_context = true;
    }

    if ($is_slider_context && ($pagenow === 'post.php' || $pagenow === 'post-new.php' || $pagenow === 'edit.php')) {
        $roles = isset($user->roles) ? (array) $user->roles : array();
        $can_manage_slider = !empty($allcaps['manage_options']) || in_array('administrator', $roles, true) || in_array('editor', $roles, true);

        if (!$can_manage_slider) {
            $block_caps = array(
                'edit_post', 'edit_posts', 'edit_others_posts', 'publish_posts',
                'delete_post', 'delete_posts', 'delete_others_posts',
                'delete_published_posts', 'delete_private_posts',
                'edit_published_posts', 'edit_private_posts',
            );

            if (isset($args[0]) && in_array($args[0], $block_caps, true)) {
                 $allcaps[$args[0]] = false;
            }

            $cpt_specific_caps = array(
                'edit_zpower_slider', 'edit_zpower_sliders',
                'edit_others_zpower_sliders', 'publish_zpower_sliders',
                'delete_zpower_slider', 'delete_zpower_sliders',
                'delete_others_zpower_sliders', 'delete_published_zpower_sliders',
                'delete_private_zpower_sliders', 'edit_published_zpower_sliders',
                'edit_private_zpower_sliders',
            );
            foreach($cpt_specific_caps as $cap_to_block){
                if (isset($allcaps[$cap_to_block])) {
                    $allcaps[$cap_to_block] = false;
                }
            }
            if (isset($args[0]) && $args[0] === 'edit_post' && isset($args[2])) {
                $post_id_check = $args[2];
                if (get_post_type($post_id_check) === 'zpower_slider') {
                    $allcaps['edit_post'] = false;
                }
            }
            if (isset($args[0]) && $args[0] === 'delete_post' && isset($args[2])) {
                $post_id_check = $args[2];
                if (get_post_type($post_id_check) === 'zpower_slider') {
                    $allcaps['delete_post'] = false;
                }
            }
        }
    }
    return $allcaps;
}, 10, 4);


// 後台通知
add_action('admin_notices', function(){
    global $pagenow, $typenow, $post;
    $is_slider_page = false;

    if ( (isset($_GET['post_type']) && sanitize_key(wp_unslash($_GET['post_type'])) === 'zpower_slider') ||
         (isset($post) && is_object($post) && $post->post_type === 'zpower_slider') ||
         $typenow === 'zpower_slider'
       ) {
        $is_slider_page = true;
    }

    if($is_slider_page && ($pagenow === 'post.php' || $pagenow === 'post-new.php') ) {
        $post_id_to_check = ($pagenow === 'post.php' && isset($_GET['post'])) ? intval(wp_unslash($_GET['post'])) : null;

        if ($post_id_to_check) {
            if (!current_user_can('edit_post', $post_id_to_check)) {
                 echo '<div class="notice notice-error"><p>' . esc_html(zpower_slider_t('目前僅限管理員與編輯人員可以編輯輪播圖。', 'Only administrators and editors can edit sliders.')) . '</p></div>';
            }
        } elseif ($pagenow === 'post-new.php') {
            if (!current_user_can('edit_posts') && !current_user_can('publish_posts')) {
                 echo '<div class="notice notice-error"><p>' . esc_html(zpower_slider_t('目前僅限管理員與編輯人員可以新增輪播圖。', 'Only administrators and editors can add sliders.')) . '</p></div>';
            }
        }
    }
});

// Meta Box 的 JS 和 CSS
function zpower_slider_admin_scripts_and_styles($hook) {
    global $post_type;
    if (('post.php' == $hook || 'post-new.php' == $hook) && 'zpower_slider' === $post_type) {
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_style('wp-color-picker');
        $alpha_picker_url = ZPOWER_SLIDER_PLUGIN_URL . 'assets/js/wp-color-picker-alpha.min.js';
        wp_enqueue_script('wp-color-picker-alpha', $alpha_picker_url, array('jquery', 'wp-color-picker'), '3.0.2', true);
    }
}
add_action('admin_enqueue_scripts', 'zpower_slider_admin_scripts_and_styles');


// 新增 Meta Box
add_action('add_meta_boxes', function() {
    add_meta_box(
        'zpower_slider_images',
        zpower_slider_t('輪播圖片與設定', 'Slider Images and Settings'),
        'zpower_slider_images_metabox_callback',
        'zpower_slider',
        'normal',
        'high'
    );
});

// Meta Box 回呼函數
function zpower_slider_images_metabox_callback($post) {
    $can_edit = true;

    if (!zpower_slider_current_user_can_manage()) {
        $can_edit = false;
    }
    if (!current_user_can('edit_post', $post->ID)) {
       $can_edit = false;
    }

    wp_nonce_field('zpower_slider_images_metabox_save', 'zpower_slider_images_metabox_nonce');

    $default_manual = array(
        'arrow_size' => 44, 'arrow_margin' => 16, 'dot_size' => 12, 'dot_gap' => 8,
        'dot_bottom' => 16, 'arrow_color' => '#ffffff', 'dot_color' => '#ffffff',
        'display_style' => 'style_1',
        'style_two_radius' => 22,
        'autoplay' => 'no', 'autoplay_delay' => 3000,
        'enable_arrows' => 'yes', 'enable_dots' => 'yes'
    );

    $default_auto = array(
        'article_title_font_size_desktop' => 16,
        'article_title_font_size_tablet' => 14,
        'article_title_font_size_mobile' => 12,
        'article_title_bg_color' => 'rgba(0,0,0,0.5)',
        'article_title_bg_opacity' => 50,
        'article_title_text_color' => '#ffffff',
        'article_title_text_opacity' => 100,
        'article_title_bg_height_desktop' => 40, 
        'article_title_bg_height_tablet' => 35,  
        'article_title_bg_height_mobile' => 30,  
        'article_title_padding' => 15, 
        'article_title_center_align' => 'no',
        'article_fixed_height_enable' => 'no', 
        'article_fixed_height_value_desktop' => 300,
        'article_fixed_height_value_tablet' => 250,
        'article_fixed_height_value_mobile' => 200,   
    );

    $slides = get_post_meta($post->ID, '_zpower_slider_slides', true);
    if (!is_array($slides)) $slides = array();

    // 手動輪播設定
    $arrow_color = get_post_meta($post->ID, '_zpower_slider_arrow_color', true) ?: $default_manual['arrow_color'];
    $dot_color = get_post_meta($post->ID, '_zpower_slider_dot_color', true) ?: $default_manual['dot_color'];
    $arrow_size = get_post_meta($post->ID, '_zpower_slider_arrow_size', true);
    $arrow_size = ($arrow_size !== '' && $arrow_size !== false) ? intval($arrow_size) : $default_manual['arrow_size'];
    $arrow_margin = get_post_meta($post->ID, '_zpower_slider_arrow_margin', true);
    $arrow_margin = ($arrow_margin !== '' && $arrow_margin !== false) ? intval($arrow_margin) : $default_manual['arrow_margin'];
    $dot_size = get_post_meta($post->ID, '_zpower_slider_dot_size', true);
    $dot_size = ($dot_size !== '' && $dot_size !== false) ? intval($dot_size) : $default_manual['dot_size'];
    $dot_gap = get_post_meta($post->ID, '_zpower_slider_dot_gap', true);
    $dot_gap = ($dot_gap !== '' && $dot_gap !== false) ? intval($dot_gap) : $default_manual['dot_gap'];
    $dot_bottom = get_post_meta($post->ID, '_zpower_slider_dot_bottom', true);
    $dot_bottom = ($dot_bottom !== '' && $dot_bottom !== false) ? intval($dot_bottom) : $default_manual['dot_bottom'];
    $autoplay_enabled = get_post_meta($post->ID, '_zpower_slider_autoplay', true) === 'yes';
    $autoplay_delay = get_post_meta($post->ID, '_zpower_slider_autoplay_delay', true);
    $autoplay_delay = ($autoplay_delay !== '' && $autoplay_delay !== false) ? intval($autoplay_delay) : $default_manual['autoplay_delay'];
    $enable_arrows_option = get_post_meta($post->ID, '_zpower_slider_enable_arrows', true);
    $enable_arrows = ($enable_arrows_option === '' || $enable_arrows_option === 'yes') ? 'yes' : 'no';
    $enable_dots_option = get_post_meta($post->ID, '_zpower_slider_enable_dots', true);
    $enable_dots = ($enable_dots_option === '' || $enable_dots_option === 'yes') ? 'yes' : 'no';
    $display_style_value = get_post_meta($post->ID, '_zpower_slider_display_style', true);
    $display_style = in_array($display_style_value, array('style_1', 'style_2'), true) ? $display_style_value : $default_manual['display_style'];
    $style_two_radius_value = get_post_meta($post->ID, '_zpower_slider_style_two_radius', true);
    $style_two_radius = ($style_two_radius_value !== '' && $style_two_radius_value !== false) ? intval($style_two_radius_value) : $default_manual['style_two_radius'];
    $style_two_radius = max(0, min(80, $style_two_radius));

    // 自動文章輪播設定
    $enable_article_slider = get_post_meta($post->ID, '_zpower_slider_enable_article_slider', true) === 'yes';
    $article_slider_category = get_post_meta($post->ID, '_zpower_slider_article_category', true);
    
    $article_title_font_size_desktop = get_post_meta($post->ID, '_zpower_slider_article_title_font_size_desktop', true);
    $article_title_font_size_desktop = ($article_title_font_size_desktop !== '' && $article_title_font_size_desktop !== false) ? intval($article_title_font_size_desktop) : $default_auto['article_title_font_size_desktop'];
    $article_title_font_size_tablet = get_post_meta($post->ID, '_zpower_slider_article_title_font_size_tablet', true);
    $article_title_font_size_tablet = ($article_title_font_size_tablet !== '' && $article_title_font_size_tablet !== false) ? intval($article_title_font_size_tablet) : $default_auto['article_title_font_size_tablet'];
    $article_title_font_size_mobile = get_post_meta($post->ID, '_zpower_slider_article_title_font_size_mobile', true);
    $article_title_font_size_mobile = ($article_title_font_size_mobile !== '' && $article_title_font_size_mobile !== false) ? intval($article_title_font_size_mobile) : $default_auto['article_title_font_size_mobile'];

    $article_title_bg_color_hex = get_post_meta($post->ID, '_zpower_slider_article_title_bg_color_hex', true) ?: substr($default_auto['article_title_bg_color'], 0, 7);
    $article_title_bg_opacity = get_post_meta($post->ID, '_zpower_slider_article_title_bg_opacity', true);
    $article_title_bg_opacity = ($article_title_bg_opacity !== '' && $article_title_bg_opacity !== false) ? intval($article_title_bg_opacity) : $default_auto['article_title_bg_opacity'];

    $article_title_text_color_hex = get_post_meta($post->ID, '_zpower_slider_article_title_text_color_hex', true) ?: $default_auto['article_title_text_color'];
    $article_title_text_opacity = get_post_meta($post->ID, '_zpower_slider_article_title_text_opacity', true);
    $article_title_text_opacity = ($article_title_text_opacity !== '' && $article_title_text_opacity !== false) ? intval($article_title_text_opacity) : $default_auto['article_title_text_opacity'];

    $article_title_bg_height_desktop = get_post_meta($post->ID, '_zpower_slider_article_title_bg_height_desktop', true);
    $article_title_bg_height_desktop = ($article_title_bg_height_desktop !== '' && $article_title_bg_height_desktop !== false) ? intval($article_title_bg_height_desktop) : $default_auto['article_title_bg_height_desktop'];
    $article_title_bg_height_tablet = get_post_meta($post->ID, '_zpower_slider_article_title_bg_height_tablet', true);
    $article_title_bg_height_tablet = ($article_title_bg_height_tablet !== '' && $article_title_bg_height_tablet !== false) ? intval($article_title_bg_height_tablet) : $default_auto['article_title_bg_height_tablet'];
    $article_title_bg_height_mobile = get_post_meta($post->ID, '_zpower_slider_article_title_bg_height_mobile', true);
    $article_title_bg_height_mobile = ($article_title_bg_height_mobile !== '' && $article_title_bg_height_mobile !== false) ? intval($article_title_bg_height_mobile) : $default_auto['article_title_bg_height_mobile'];
    
    $article_title_padding = get_post_meta($post->ID, '_zpower_slider_article_title_padding', true);
    $article_title_padding = ($article_title_padding !== '' && $article_title_padding !== false) ? intval($article_title_padding) : $default_auto['article_title_padding'];

    $article_title_center_align_val = get_post_meta($post->ID, '_zpower_slider_article_title_center_align', true);
    $article_title_center_align = $article_title_center_align_val === 'yes' ? 'yes' : 'no';

    $article_fixed_height_enable_val = get_post_meta($post->ID, '_zpower_slider_article_fixed_height_enable', true);
    $article_fixed_height_enable = $article_fixed_height_enable_val === 'yes' ? 'yes' : 'no';
    
    $article_fixed_height_value_desktop = get_post_meta($post->ID, '_zpower_slider_article_fixed_height_value_desktop', true);
    $article_fixed_height_value_desktop = ($article_fixed_height_value_desktop !== '' && $article_fixed_height_value_desktop !== false) ? intval($article_fixed_height_value_desktop) : $default_auto['article_fixed_height_value_desktop'];
    $article_fixed_height_value_tablet = get_post_meta($post->ID, '_zpower_slider_article_fixed_height_value_tablet', true);
    $article_fixed_height_value_tablet = ($article_fixed_height_value_tablet !== '' && $article_fixed_height_value_tablet !== false) ? intval($article_fixed_height_value_tablet) : $default_auto['article_fixed_height_value_tablet'];
    $article_fixed_height_value_mobile = get_post_meta($post->ID, '_zpower_slider_article_fixed_height_value_mobile', true);
    $article_fixed_height_value_mobile = ($article_fixed_height_value_mobile !== '' && $article_fixed_height_value_mobile !== false) ? intval($article_fixed_height_value_mobile) : $default_auto['article_fixed_height_value_mobile'];

    $categories = get_categories(array('hide_empty' => 0));
    ?>
    <style>
        .zpower-metabox-wrapper,
        .zpower-metabox-wrapper * {
            box-sizing: border-box;
        }
        .zpower-metabox-wrapper {
            max-width: 100%;
        }
        .zpower-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 25px;
            max-width: 100%;
        }
        .zpower-section-title {
            font-size: 1.3em; 
            font-weight: 600;
            padding-bottom: 10px;
            margin-top: 0; 
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            color: #2c3338;
        }
        .zpower-subsection-title { 
            font-size: 1.1em; 
            font-weight: 600; 
            color: #3c434a; 
            margin-top: 25px; 
            margin-bottom: 15px; 
            padding-bottom: 8px; 
            border-bottom: 1px dotted #ddd; 
        }
        .zpower-subsection-title:first-child { margin-top: 0; }

        .zpower-slider-slide-item {display:flex; flex-wrap:wrap; align-items:flex-start; gap:16px; background:#fdfdfd; border:1px solid #ddd; padding:12px; border-radius:4px; margin-bottom:12px; max-width:100%;}
        .zpower-slider-slide-item:hover {border-color:#999;}
        .zpower-slider-slide-item.ui-sortable-helper {box-shadow:0 6px 18px rgba(0,0,0,.12);}
        .zpower-slide-drag-handle {flex:0 0 30px; align-self:stretch; min-height:88px; display:flex; align-items:center; justify-content:center; color:#646970; background:#f0f0f1; border:1px solid #dcdcde; border-radius:4px; cursor:grab; user-select:none; touch-action:none; line-height:1;}
        .zpower-slide-drag-handle::before {content:""; width:14px; height:28px; background:repeating-linear-gradient(to bottom, currentColor 0 2px, transparent 2px 6px); opacity:.85;}
        .zpower-slide-drag-handle:hover, .zpower-slide-drag-handle:focus {background:#e5f5fa; border-color:#72aee6; color:#0073aa; outline:none;}
        .zpower-slide-drag-handle:active {cursor:grabbing;}
        .zpower-slider-slide-item.is-sorting {opacity:.92;}
        .zpower-remove-slide {display:inline-flex; flex:0 0 28px; align-items:center; justify-content:center; width:28px;height:28px;color:#fff;background:#d63638;border-radius:50%;font-weight:bold;font-size:18px;cursor:pointer;margin-right:10px;border:none;transition:background .2s;}
        .zpower-remove-slide:hover {background:#a00;}
        .zpower-slide-img-preview {display:flex; flex:0 1 160px; flex-direction:column; align-items:flex-start; gap:6px; min-width:140px; max-width:190px;}
        .zpower-slide-link-fields {display:flex; flex:1 1 220px; flex-direction:column; gap:6px; min-width:200px; max-width:100%;}
        .zpower-slide-link-fields .regular-text,
        .zpower-slide-link-fields select {width:100%; max-width:100%;}
        .zpower-autoplay-row, .zpower-enable-controls-row {display:flex;flex-wrap:wrap;align-items:center;gap:12px 18px;margin-bottom:12px;}
        .zpower-slide-img-btn {
            margin-top:4px;font-size:12px;padding: 4px 12px;border:1px solid #ccc;background:#f6f7f7;border-radius:3px;cursor:pointer;
            box-shadow: 0 1px 0 #ccc; color: #0071a1; text-decoration: none;
        }
        .zpower-slide-img-btn:hover { background:#f0f0f1; border-color: #0071a1; color: #0071a1; }
        .zpower-slide-img-preview img {background: #f0f0f0;display: block;min-height: 70px;min-width: 120px;max-width: 140px;max-height: 90px;object-fit: cover; border-radius:3px; border: 1px solid #ddd;}
        .zpower-slide-img-preview img[data-id=""], .zpower-slide-img-preview img:not([src]) {display: none !important;}
        .zpower-slide-placeholder { border: 2px dashed #ccd0d4; background-color: #f9f9f9; height: 100px; margin-bottom:12px; border-radius:4px;}
        .disabled-overlay { position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(255,255,255,0.8); z-index:100; display:flex; align-items:center; justify-content:center; font-weight:bold; color:#555; border-radius:4px;}
        .zpower-section.is-disabled { position:relative; opacity: 0.7; pointer-events:none; }
        
        .zpower-grid-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px 25px; }
        .zpower-grid-container > div { margin-bottom: 10px; min-width:0; }
        
        .zpower-field-row { margin-bottom: 20px; }
        .zpower-field-row label { display: block; margin-bottom: 8px; font-weight: 600; color: #3c434a; }
        .zpower-field-row input[type="checkbox"] + label, .zpower-field-row input[type="checkbox"] { font-weight: normal; margin-right: 5px;}
        .zpower-field-row .description {font-size: 13px; color: #50575e; font-style: italic; margin-top: 5px; font-weight:normal;}
        .zpower-field-row input[type="range"] { width: 100%; max-width: 280px; margin-top: 5px; }
        .zpower-field-row input[type="text"],
        .zpower-field-row select { max-width: 100%; }
        .zpower-field-row .range-value-display { margin-left: 10px; font-weight: 500; color: #0073aa; }
        .zpower-style-options { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; margin-top:10px; }
        .zpower-style-option { display:block; border:1px solid #dcdcde; border-radius:4px; background:#fff; padding:14px 16px; cursor:pointer; }
        .zpower-style-option:hover { border-color:#72aee6; }
        .zpower-style-option input { margin-right:8px; }
        .zpower-style-option-title { display:inline-block; font-weight:600; color:#1d2327; }
        .zpower-style-option-desc { display:block; margin-top:6px; color:#646970; font-size:13px; line-height:1.5; }

        #zpower_article_slider_options_wrapper.hidden, 
        #zpower_manual_slider_options_wrapper.hidden,
        #zpower_manual_carousel_settings_section.hidden, 
        #zpower_manual_slider_images_section.hidden,
        #zpower_slider_article_fixed_height_values_wrapper.hidden { display: none !important; } 
        
        .wp-picker-container .wp-color-result.button { height: auto; min-height: 30px; padding: 0 10px; }
        .zpower-color-opacity-wrap { display: flex; flex-wrap:wrap; align-items: center; gap: 10px; margin-top: 5px; }
        .zpower-color-opacity-wrap .wp-picker-container { margin-bottom: 0 !important; }
        .zpower-color-opacity-wrap input[type="range"] { flex-grow: 1; max-width: 180px; }
        .zpower-color-opacity-wrap .range-value-display { font-size: 12px; }

        @media (max-width: 782px) {
            .zpower-section {
                padding: 16px;
                margin-bottom: 18px;
            }
            .zpower-section .zpower-section {
                padding: 14px;
            }
            .zpower-grid-container,
            .zpower-style-options {
                grid-template-columns: minmax(0, 1fr);
                gap: 16px;
            }
            .zpower-slider-slide-item {
                gap: 10px;
                padding: 10px;
            }
            .zpower-slide-drag-handle {
                flex: 0 0 36px;
                min-height: 44px;
            }
            .zpower-remove-slide {
                flex-basis: 32px;
                width: 32px;
                height: 32px;
                margin-right: 0;
            }
            .zpower-slide-img-preview {
                flex: 1 1 calc(50% - 10px);
                max-width: none;
            }
            .zpower-slide-link-fields {
                flex-basis: 100%;
                min-width: 0;
            }
            .zpower-color-opacity-wrap {
                align-items: flex-start;
            }
            .zpower-color-opacity-wrap input[type="range"] {
                flex: 1 1 180px;
                max-width: 100%;
            }
            .zpower-autoplay-row .button {
                margin-left: 0 !important;
            }
        }

        @media (max-width: 480px) {
            .zpower-section {
                padding: 14px;
            }
            .zpower-slider-slide-item {
                display: flex;
                flex-direction: row;
                align-items: stretch;
            }
            .zpower-slide-drag-handle {
                flex: 1 1 calc(100% - 42px);
                min-height: 38px;
            }
            .zpower-remove-slide {
                margin-left: auto;
            }
            .zpower-slide-img-preview,
            .zpower-slide-link-fields {
                flex-basis: 100%;
                min-width: 0;
            }
            .zpower-slide-img-preview img {
                width: 100%;
                min-width: 0;
                max-width: 100%;
                max-height: 160px;
            }
            .zpower-slide-img-btn,
            .zpower-slide-link-fields input,
            .zpower-slide-link-fields select,
            #zpower_add_slide_btn {
                width: 100%;
                text-align: center;
            }
            .zpower-field-row input[type="range"] {
                max-width: 100%;
            }
            .zpower-field-row .range-value-display,
            .zpower-color-opacity-wrap .range-value-display {
                display: block;
                margin-left: 0;
                margin-top: 6px;
                width: 100%;
            }
        }

        .button-primary { background: #007cba; border-color: #007cba; color: white; text-decoration: none; text-shadow: none; }
        .button-primary:hover { background: #0071a1; border-color: #0071a1; }
        .button-secondary { background: #f6f7f7; border-color: #ccc; color: #0071a1; }
        .button-secondary:hover { background: #f0f0f1; border-color: #0071a1; color: #0071a1; }
    </style>
    <div class="zpower-metabox-wrapper <?php if(!$can_edit) echo 'is-disabled'; ?>" >
        <?php if(!$can_edit): ?>
            <div class="disabled-overlay"><?php echo esc_html(zpower_slider_t('您沒有編輯此輪播圖的權限。', 'You do not have permission to edit this slider.')); ?></div>
        <?php endif; ?>

        <div class="zpower-section">
            <h2 class="zpower-section-title"><?php echo esc_html(zpower_slider_t('輪播模式設定', 'Slider Mode Settings')); ?></h2>
            <div class="zpower-field-row">
                <label>
                    <input type="checkbox" id="zpower_slider_enable_article_slider" name="zpower_slider_enable_article_slider" value="yes" <?php checked($enable_article_slider, true); ?> <?php if(!$can_edit) echo 'disabled'; ?>>
                    <?php echo esc_html(zpower_slider_t('啟用自動抓取文章輪播', 'Enable automatic post slider')); ?>
                </label>
                <p class="description"><?php echo esc_html(zpower_slider_t('啟用後，將自動抓取文章作為輪播內容。停用則使用手動輪播設定。', 'When enabled, posts are automatically used as slider content. When disabled, manual slider settings are used.')); ?></p>
            </div>
        </div>

        <div id="zpower_article_slider_options_wrapper" class="zpower-section <?php if (!$enable_article_slider) echo 'hidden'; ?>">
            <h2 class="zpower-section-title"><?php echo esc_html(zpower_slider_t('自動文章輪播設定', 'Automatic Post Slider Settings')); ?></h2>
            <div class="zpower-field-row">
                <label for="zpower_slider_article_category"><?php echo esc_html(zpower_slider_t('選擇文章分類', 'Select Post Category')); ?></label>
                <select id="zpower_slider_article_category" name="zpower_slider_article_category" <?php if(!$can_edit) echo 'disabled'; ?> style="min-width: 200px;">
                    <option value=""><?php echo esc_html(zpower_slider_t('所有分類', 'All Categories')); ?></option>
                    <?php foreach ($categories as $category) : ?>
                        <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected($article_slider_category, $category->term_id); ?>>
                            <?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php echo esc_html(zpower_slider_t('選擇要輪播的文章分類，預設顯示最新 10 篇文章。', 'Select the post category to display. By default, the latest 10 posts are shown.')); ?></p>
            </div>

            <h3 class="zpower-subsection-title"><?php echo esc_html(zpower_slider_t('文章標題樣式設定', 'Post Title Style Settings')); ?></h3>
            <div class="zpower-grid-container">
                 <div>
                    <label for="zpower_slider_article_title_font_size_desktop"><?php echo esc_html(zpower_slider_t('標題文字大小 (桌面)', 'Title Font Size (Desktop)')); ?></label>
                    <input type="range" id="zpower_slider_article_title_font_size_desktop" name="zpower_slider_article_title_font_size_desktop" min="10" max="40" value="<?php echo esc_attr($article_title_font_size_desktop); ?>" oninput="document.getElementById('zpower_slider_article_title_font_size_desktop_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_article_title_font_size_desktop_val" class="range-value-display"><?php echo esc_html($article_title_font_size_desktop); ?>px</span>
                </div>
                <div>
                    <label for="zpower_slider_article_title_font_size_tablet"><?php echo esc_html(zpower_slider_t('標題文字大小 (平板)', 'Title Font Size (Tablet)')); ?></label>
                    <input type="range" id="zpower_slider_article_title_font_size_tablet" name="zpower_slider_article_title_font_size_tablet" min="10" max="36" value="<?php echo esc_attr($article_title_font_size_tablet); ?>" oninput="document.getElementById('zpower_slider_article_title_font_size_tablet_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_article_title_font_size_tablet_val" class="range-value-display"><?php echo esc_html($article_title_font_size_tablet); ?>px</span>
                    <p class="description"><?php echo esc_html(zpower_slider_t('建議寬度 <= 1024px', 'Recommended width <= 1024px')); ?></p>
                </div>
                <div>
                    <label for="zpower_slider_article_title_font_size_mobile"><?php echo esc_html(zpower_slider_t('標題文字大小 (手機)', 'Title Font Size (Mobile)')); ?></label>
                    <input type="range" id="zpower_slider_article_title_font_size_mobile" name="zpower_slider_article_title_font_size_mobile" min="10" max="32" value="<?php echo esc_attr($article_title_font_size_mobile); ?>" oninput="document.getElementById('zpower_slider_article_title_font_size_mobile_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_article_title_font_size_mobile_val" class="range-value-display"><?php echo esc_html($article_title_font_size_mobile); ?>px</span>
                    <p class="description"><?php echo esc_html(zpower_slider_t('建議寬度 <= 768px', 'Recommended width <= 768px')); ?></p>
                </div>
                <div>
                    <label for="zpower_slider_article_title_bg_height_desktop"><?php echo esc_html(zpower_slider_t('標題背景高度 (桌面)', 'Title Background Height (Desktop)')); ?></label>
                    <input type="range" id="zpower_slider_article_title_bg_height_desktop" name="zpower_slider_article_title_bg_height_desktop" min="20" max="100" value="<?php echo esc_attr($article_title_bg_height_desktop); ?>" oninput="document.getElementById('zpower_slider_article_title_bg_height_desktop_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_article_title_bg_height_desktop_val" class="range-value-display"><?php echo esc_html($article_title_bg_height_desktop); ?>px</span>
                </div>
                 <div>
                    <label for="zpower_slider_article_title_bg_height_tablet"><?php echo esc_html(zpower_slider_t('標題背景高度 (平板)', 'Title Background Height (Tablet)')); ?></label>
                    <input type="range" id="zpower_slider_article_title_bg_height_tablet" name="zpower_slider_article_title_bg_height_tablet" min="20" max="100" value="<?php echo esc_attr($article_title_bg_height_tablet); ?>" oninput="document.getElementById('zpower_slider_article_title_bg_height_tablet_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_article_title_bg_height_tablet_val" class="range-value-display"><?php echo esc_html($article_title_bg_height_tablet); ?>px</span>
                     <p class="description"><?php echo esc_html(zpower_slider_t('建議寬度 <= 1024px', 'Recommended width <= 1024px')); ?></p>
                </div>
                 <div>
                    <label for="zpower_slider_article_title_bg_height_mobile"><?php echo esc_html(zpower_slider_t('標題背景高度 (手機)', 'Title Background Height (Mobile)')); ?></label>
                    <input type="range" id="zpower_slider_article_title_bg_height_mobile" name="zpower_slider_article_title_bg_height_mobile" min="20" max="100" value="<?php echo esc_attr($article_title_bg_height_mobile); ?>" oninput="document.getElementById('zpower_slider_article_title_bg_height_mobile_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_article_title_bg_height_mobile_val" class="range-value-display"><?php echo esc_html($article_title_bg_height_mobile); ?>px</span>
                    <p class="description"><?php echo esc_html(zpower_slider_t('建議寬度 <= 768px', 'Recommended width <= 768px')); ?></p>
                </div>
                <div> <label for="zpower_slider_article_title_padding"><?php echo esc_html(zpower_slider_t('標題文字左右內距', 'Title Horizontal Padding')); ?></label>
                    <input type="range" id="zpower_slider_article_title_padding" name="zpower_slider_article_title_padding" min="0" max="100" value="<?php echo esc_attr($article_title_padding); ?>" oninput="document.getElementById('zpower_slider_article_title_padding_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_article_title_padding_val" class="range-value-display"><?php echo esc_html($article_title_padding); ?>px</span>
                </div>
                <div style="grid-column: span 1 / span 1;"> <label>
                        <input type="checkbox" id="zpower_slider_article_title_center_align" name="zpower_slider_article_title_center_align" value="yes" <?php checked($article_title_center_align, 'yes'); ?> <?php if(!$can_edit) echo 'disabled'; ?>>
                        <?php echo esc_html(zpower_slider_t('標題文字置中對齊', 'Center align title text')); ?>
                    </label>
                </div>
            </div>
            <div class="zpower-field-row" style="margin-top:20px;">
                <label for="zpower_slider_article_title_text_color_hex"><?php echo esc_html(zpower_slider_t('標題文字顏色', 'Title Text Color')); ?></label>
                <div class="zpower-color-opacity-wrap">
                    <input type="text" id="zpower_slider_article_title_text_color_hex" name="zpower_slider_article_title_text_color_hex" value="<?php echo esc_attr($article_title_text_color_hex); ?>" class="zpower-color-field" data-default-color="<?php echo esc_attr($default_auto['article_title_text_color']); ?>" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <input type="range" id="zpower_slider_article_title_text_opacity" name="zpower_slider_article_title_text_opacity" min="0" max="100" value="<?php echo esc_attr($article_title_text_opacity); ?>" oninput="document.getElementById('zpower_slider_article_title_text_opacity_val').innerText=this.value+'%';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_article_title_text_opacity_val" class="range-value-display"><?php echo esc_html($article_title_text_opacity); ?>% <?php echo esc_html(zpower_slider_t('不透明度', 'opacity')); ?></span>
                </div>
            </div>
            <div class="zpower-field-row">
                <label for="zpower_slider_article_title_bg_color_hex"><?php echo esc_html(zpower_slider_t('標題背景顏色', 'Title Background Color')); ?></label>
                 <div class="zpower-color-opacity-wrap">
                    <input type="text" id="zpower_slider_article_title_bg_color_hex" name="zpower_slider_article_title_bg_color_hex" value="<?php echo esc_attr($article_title_bg_color_hex); ?>" class="zpower-color-field" data-default-color="<?php echo esc_attr(substr($default_auto['article_title_bg_color'],0,7)); ?>" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <input type="range" id="zpower_slider_article_title_bg_opacity" name="zpower_slider_article_title_bg_opacity" min="0" max="100" value="<?php echo esc_attr($article_title_bg_opacity); ?>" oninput="document.getElementById('zpower_slider_article_title_bg_opacity_val').innerText=this.value+'%';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_article_title_bg_opacity_val" class="range-value-display"><?php echo esc_html($article_title_bg_opacity); ?>% <?php echo esc_html(zpower_slider_t('不透明度', 'opacity')); ?></span>
                </div>
            </div>
            <?php if($can_edit): ?>
            <button type="button" class="button button-secondary" id="zpower_slider_article_defaults_btn" style="margin-top:12px;"><?php echo esc_html(zpower_slider_t('標題樣式預設值', 'Reset Title Style')); ?></button>
            <?php endif; ?>

            <h3 class="zpower-subsection-title"><?php echo esc_html(zpower_slider_t('輪播區塊固定高度設定', 'Fixed Slider Height Settings')); ?></h3>
             <div class="zpower-field-row">
                <label>
                    <input type="checkbox" id="zpower_slider_article_fixed_height_enable" name="zpower_slider_article_fixed_height_enable" value="yes" <?php checked($article_fixed_height_enable, 'yes'); ?> <?php if(!$can_edit) echo 'disabled'; ?>>
                    <?php echo esc_html(zpower_slider_t('啟用固定輪播高度', 'Enable fixed slider height')); ?>
                </label>
                <p class="description"><?php echo esc_html(zpower_slider_t('啟用後，下方設定的高度將會生效，圖片將填滿寬度並置中顯示，超出高度的部分會被裁切。', 'When enabled, the height settings below take effect. Images fill the width, stay centered, and overflow is cropped.')); ?></p>
            </div>
            <div id="zpower_slider_article_fixed_height_values_wrapper" class="zpower-grid-container <?php if ($article_fixed_height_enable !== 'yes') echo 'hidden'; ?>">
                <div>
                    <label for="zpower_slider_article_fixed_height_value_desktop"><?php echo esc_html(zpower_slider_t('輪播區塊高度 (桌面)', 'Slider Height (Desktop)')); ?></label>
                    <input type="range" id="zpower_slider_article_fixed_height_value_desktop" name="zpower_slider_article_fixed_height_value_desktop" min="100" max="1200" step="10" value="<?php echo esc_attr($article_fixed_height_value_desktop); ?>" oninput="document.getElementById('zpower_slider_article_fixed_height_value_desktop_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_article_fixed_height_value_desktop_val" class="range-value-display"><?php echo esc_html($article_fixed_height_value_desktop); ?>px</span>
                </div>
                 <div>
                    <label for="zpower_slider_article_fixed_height_value_tablet"><?php echo esc_html(zpower_slider_t('輪播區塊高度 (平板)', 'Slider Height (Tablet)')); ?></label>
                    <input type="range" id="zpower_slider_article_fixed_height_value_tablet" name="zpower_slider_article_fixed_height_value_tablet" min="100" max="1000" step="10" value="<?php echo esc_attr($article_fixed_height_value_tablet); ?>" oninput="document.getElementById('zpower_slider_article_fixed_height_value_tablet_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_article_fixed_height_value_tablet_val" class="range-value-display"><?php echo esc_html($article_fixed_height_value_tablet); ?>px</span>
                    <p class="description"><?php echo esc_html(zpower_slider_t('建議寬度 <= 1024px', 'Recommended width <= 1024px')); ?></p>
                </div>
                 <div>
                    <label for="zpower_slider_article_fixed_height_value_mobile"><?php echo esc_html(zpower_slider_t('輪播區塊高度 (手機)', 'Slider Height (Mobile)')); ?></label>
                    <input type="range" id="zpower_slider_article_fixed_height_value_mobile" name="zpower_slider_article_fixed_height_value_mobile" min="100" max="800" step="10" value="<?php echo esc_attr($article_fixed_height_value_mobile); ?>" oninput="document.getElementById('zpower_slider_article_fixed_height_value_mobile_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_article_fixed_height_value_mobile_val" class="range-value-display"><?php echo esc_html($article_fixed_height_value_mobile); ?>px</span>
                    <p class="description"><?php echo esc_html(zpower_slider_t('建議寬度 <= 768px', 'Recommended width <= 768px')); ?></p>
                </div>
            </div>
        </div>

    <div id="zpower_manual_slider_options_wrapper" class="zpower-section <?php if ($enable_article_slider && !$can_edit) echo 'is-disabled'; ?>">
        <div id="zpower_manual_carousel_settings_section" class="zpower-section" style="background-color: #fcfcfc;"> <h2 class="zpower-section-title"><?php echo esc_html(zpower_slider_t('輪播通用設定', 'General Slider Settings')); ?></h2>
            <p class="description" style="margin-bottom:20px;"><?php echo esc_html(zpower_slider_t('此處設定適用於手動輪播及自動抓取文章輪播的通用外觀與行為。', 'These settings apply to the shared appearance and behavior of manual sliders and automatic post sliders.')); ?></p>

            <h3 class="zpower-subsection-title"><?php echo esc_html(zpower_slider_t('輪播樣式', 'Slider Style')); ?></h3>
            <p class="description"><?php echo esc_html(zpower_slider_t('選擇前台輪播版型。樣式一為滿版輪播；樣式二會顯示白色背景、中央圓角圖片與左右露出的前後張。', 'Select the frontend slider layout. Style 1 is full-width. Style 2 uses a white background, rounded center image, and partially visible previous/next slides.')); ?></p>
            <div class="zpower-style-options">
                <label class="zpower-style-option">
                    <input type="radio" name="zpower_slider_display_style" value="style_1" <?php checked($display_style, 'style_1'); ?> <?php if(!$can_edit) echo 'disabled'; ?>>
                    <span class="zpower-style-option-title"><?php echo esc_html(zpower_slider_t('樣式一', 'Style 1')); ?></span>
                    <span class="zpower-style-option-desc"><?php echo esc_html(zpower_slider_t('滿版輪播圖顯示方式。', 'Full-width slider display.')); ?></span>
                </label>
                <label class="zpower-style-option">
                    <input type="radio" name="zpower_slider_display_style" value="style_2" <?php checked($display_style, 'style_2'); ?> <?php if(!$can_edit) echo 'disabled'; ?>>
                    <span class="zpower-style-option-title"><?php echo esc_html(zpower_slider_t('樣式二', 'Style 2')); ?></span>
                    <span class="zpower-style-option-desc"><?php echo esc_html(zpower_slider_t('中央主圖放大，進入頁面即可看到左中右三張，外層使用白色背景與圓角卡片效果。', 'The center image is emphasized, with left and right slides visible on page load and a white rounded-card effect.')); ?></span>
                </label>
            </div>
            <div class="zpower-field-row" style="margin-top:18px;">
                <label for="zpower_slider_style_two_radius"><?php echo esc_html(zpower_slider_t('樣式二圖片圓角', 'Style 2 Image Radius')); ?></label>
                <input type="range" id="zpower_slider_style_two_radius" name="zpower_slider_style_two_radius" min="0" max="80" value="<?php echo esc_attr($style_two_radius); ?>" oninput="document.getElementById('zpower_slider_style_two_radius_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                <span id="zpower_slider_style_two_radius_val" class="range-value-display"><?php echo esc_html($style_two_radius); ?>px</span>
                <p class="description"><?php echo esc_html(zpower_slider_t('此設定只會套用於樣式二；設為 0px 可取消圖片圓角。', 'This setting only applies to Style 2. Set it to 0px to remove image rounding.')); ?></p>
            </div>

            <h3 class="zpower-subsection-title"><?php echo esc_html(zpower_slider_t('顏色設定', 'Color Settings')); ?></h3>
            <p class="description"><?php echo esc_html(zpower_slider_t('這些顏色設定將應用於輪播的導覽箭頭與分頁點。', 'These color settings apply to the slider arrows and pagination dots.')); ?></p>
            <div class="zpower-grid-container" style="margin-top:10px; margin-bottom: 20px;">
                <div>
                    <label for="zpower_slider_arrow_color"><?php echo esc_html(zpower_slider_t('箭頭顏色', 'Arrow Color')); ?></label>
                    <input type="text" id="zpower_slider_arrow_color" name="zpower_slider_arrow_color" value="<?php echo esc_attr($arrow_color); ?>" class="zpower-color-field" data-default-color="<?php echo esc_attr($default_manual['arrow_color']); ?>" <?php if(!$can_edit) echo 'disabled'; ?> />
                </div>
                <div>
                    <label for="zpower_slider_dot_color"><?php echo esc_html(zpower_slider_t('點的顏色', 'Dot Color')); ?></label>
                    <input type="text" id="zpower_slider_dot_color" name="zpower_slider_dot_color" value="<?php echo esc_attr($dot_color); ?>" class="zpower-color-field" data-default-color="<?php echo esc_attr($default_manual['dot_color']); ?>" <?php if(!$can_edit) echo 'disabled'; ?> />
                </div>
            </div>

            <h3 class="zpower-subsection-title"><?php echo esc_html(zpower_slider_t('圖示設定（點及箭頭）', 'Icon Settings (Dots and Arrows)')); ?></h3>
            <p class="description"><?php echo esc_html(zpower_slider_t('調整輪播導覽箭頭與分頁點的尺寸、邊距等外觀設定。', 'Adjust size, spacing, and other appearance settings for slider arrows and pagination dots.')); ?></p>
            <div class="zpower-grid-container zpower-icon-settings-grid" style="margin-top:10px;">
                <div>
                    <label for="zpower_slider_arrow_size"><?php echo esc_html(zpower_slider_t('箭頭大小', 'Arrow Size')); ?></label>
                    <input type="range" id="zpower_slider_arrow_size" name="zpower_slider_arrow_size" min="20" max="100" value="<?php echo esc_attr($arrow_size); ?>" oninput="document.getElementById('zpower_slider_arrow_size_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_arrow_size_val" class="range-value-display"><?php echo esc_html($arrow_size); ?>px</span>
                </div>
                <div>
                    <label for="zpower_slider_arrow_margin"><?php echo esc_html(zpower_slider_t('箭頭左右邊距', 'Arrow Side Margin')); ?></label>
                    <input type="range" id="zpower_slider_arrow_margin" name="zpower_slider_arrow_margin" min="0" max="100" value="<?php echo esc_attr($arrow_margin); ?>" oninput="document.getElementById('zpower_slider_arrow_margin_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_arrow_margin_val" class="range-value-display"><?php echo esc_html($arrow_margin); ?>px</span>
                </div>
                <div>
                    <label for="zpower_slider_dot_size"><?php echo esc_html(zpower_slider_t('點的大小', 'Dot Size')); ?></label>
                    <input type="range" id="zpower_slider_dot_size" name="zpower_slider_dot_size" min="6" max="32" value="<?php echo esc_attr($dot_size); ?>" oninput="document.getElementById('zpower_slider_dot_size_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_dot_size_val" class="range-value-display"><?php echo esc_html($dot_size); ?>px</span>
                </div>
                <div>
                    <label for="zpower_slider_dot_gap"><?php echo esc_html(zpower_slider_t('點與點之間的距離', 'Dot Gap')); ?></label>
                    <input type="range" id="zpower_slider_dot_gap" name="zpower_slider_dot_gap" min="0" max="32" value="<?php echo esc_attr($dot_gap); ?>" oninput="document.getElementById('zpower_slider_dot_gap_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_dot_gap_val" class="range-value-display"><?php echo esc_html($dot_gap); ?>px</span>
                </div>
                <div>
                    <label for="zpower_slider_dot_bottom"><?php echo esc_html(zpower_slider_t('點與底部的距離', 'Dot Bottom Offset')); ?></label>
                    <input type="range" id="zpower_slider_dot_bottom" name="zpower_slider_dot_bottom" min="0" max="100" value="<?php echo esc_attr($dot_bottom); ?>" oninput="document.getElementById('zpower_slider_dot_bottom_val').innerText=this.value+'px';" <?php if(!$can_edit) echo 'disabled'; ?> />
                    <span id="zpower_slider_dot_bottom_val" class="range-value-display"><?php echo esc_html($dot_bottom); ?>px</span>
                </div>
            </div>
             <div class="zpower-enable-controls-row" style="margin-top: 20px;">
                <label>
                    <input type="checkbox" name="zpower_slider_enable_arrows" value="yes" <?php checked($enable_arrows, 'yes'); ?> <?php if(!$can_edit) echo 'disabled'; ?>> <?php echo esc_html(zpower_slider_t('啟用箭頭導覽', 'Enable arrow navigation')); ?>
                </label>
            </div>
            <div class="zpower-enable-controls-row">
                <label>
                    <input type="checkbox" name="zpower_slider_enable_dots" value="yes" <?php checked($enable_dots, 'yes'); ?> <?php if(!$can_edit) echo 'disabled'; ?>> <?php echo esc_html(zpower_slider_t('啟用分頁點導覽', 'Enable pagination dots')); ?>
                </label>
            </div>
            <?php if($can_edit): ?>
            <button type="button" class="button button-secondary" id="zpower_slider_reset_btn" style="margin-top:12px;"><?php echo esc_html(zpower_slider_t('圖示預設值', 'Reset Icons')); ?></button>
            <?php endif; ?>


            <h3 class="zpower-subsection-title" style="margin-top:30px;"><?php echo esc_html(zpower_slider_t('自動輪播設定', 'Autoplay Settings')); ?></h3>
            <p class="description"><?php echo esc_html(zpower_slider_t('設定輪播是否自動播放以及播放的間隔時間。', 'Set whether the slider plays automatically and how long each slide stays visible.')); ?></p>
            <div class="zpower-field-row" style="margin-top:10px;">
                <label for="zpower_slider_autoplay_delay"><?php echo esc_html(zpower_slider_t('自動輪播間隔（毫秒）', 'Autoplay Interval (ms)')); ?></label>
                <input type="range" id="zpower_slider_autoplay_delay" name="zpower_slider_autoplay_delay" min="1000" max="20000" step="100" value="<?php echo esc_attr($autoplay_delay); ?>" oninput="document.getElementById('zpower_slider_autoplay_delay_val').innerText=this.value+' ms';" <?php if(!$can_edit) echo 'disabled'; ?> />
                <span id="zpower_slider_autoplay_delay_val" class="range-value-display"><?php echo esc_html($autoplay_delay); ?> ms</span>
            </div>
            <div class="zpower-autoplay-row">
                <label>
                    <input type="checkbox" name="zpower_slider_autoplay" id="zpower_slider_autoplay" value="yes" <?php checked($autoplay_enabled, true); ?> <?php if(!$can_edit) echo 'disabled'; ?> > <?php echo esc_html(zpower_slider_t('開啟自動輪播', 'Enable autoplay')); ?>
                </label>
                <?php if($can_edit): ?>
                <button type="button" class="button button-secondary" id="zpower_slider_autoplay_reset_btn" style="margin-left:16px;"><?php echo esc_html(zpower_slider_t('自動輪播預設值', 'Reset Autoplay')); ?></button>
                <?php endif; ?>
            </div>
        </div>

        <div id="zpower_manual_slider_images_section" class="zpower-section <?php if(!$can_edit) echo 'is-disabled'; ?> <?php if ($enable_article_slider) echo 'hidden'; ?>">
            <h2 class="zpower-section-title"><?php echo esc_html(zpower_slider_t('手動輪播 - 圖片選擇與排序', 'Manual Slider - Image Selection and Sorting')); ?></h2>
             <p class="description"><?php echo esc_html(zpower_slider_t('此區域僅在「停用」自動抓取文章輪播時作用。用於手動新增、排序及設定輪播圖片。', 'This area is used only when the automatic post slider is disabled. Add, sort, and configure slider images manually here.')); ?></p>
            <?php if($can_edit): ?>
            <button type="button" class="button button-primary" id="zpower_add_slide_btn" style="margin-top:10px; margin-bottom:15px;"><?php echo esc_html(zpower_slider_t('新增一組圖片', 'Add Image Set')); ?></button>
            <?php endif; ?>
            <input type="hidden" id="zpower_slider_slides_input" name="zpower_slider_slides" value="<?php echo esc_attr(wp_json_encode($slides)); ?>">
            <div id="zpower_slider_slides_preview" style="margin-top:10px;">
                <?php foreach($slides as $i => $slide){
                    $pc_id = isset($slide['pc']) ? intval($slide['pc']) : 0;
                    $mobile_id = isset($slide['mobile']) ? intval($slide['mobile']) : 0;
                    $url = isset($slide['url']) ? esc_url($slide['url']) : '';
                    $target = isset($slide['target']) ? esc_attr($slide['target']) : '_self';
                ?>
                <div class="zpower-slider-slide-item" data-index="<?php echo $i; ?>">
                    <?php if($can_edit): ?>
                    <span class="zpower-slide-drag-handle" title="<?php echo esc_attr(zpower_slider_t('拖曳排序', 'Drag to Sort')); ?>" aria-label="<?php echo esc_attr(zpower_slider_t('拖曳排序', 'Drag to Sort')); ?>"></span>
                    <span class="zpower-remove-slide" title="<?php echo esc_attr(zpower_slider_t('移除此組', 'Remove This Set')); ?>">×</span>
                    <?php endif; ?>
                    <div class="zpower-slide-img-preview">
                        <img class="zpower-slide-pc-img" src="<?php echo $pc_id ? esc_url(wp_get_attachment_image_url($pc_id, 'medium')) : ''; ?>" data-id="<?php echo $pc_id ? esc_attr($pc_id) : ''; ?>" alt="<?php echo esc_attr(zpower_slider_t('桌機圖片預覽', 'Desktop image preview')); ?>">
                        <?php if($can_edit): ?>
                        <button type="button" class="zpower-slide-img-btn zpower-select-pc-img"><?php echo esc_html(zpower_slider_t('選擇桌機圖片', 'Select Desktop Image')); ?></button>
                        <?php endif; ?>
                    </div>
                    <div class="zpower-slide-img-preview">
                        <img class="zpower-slide-mobile-img" src="<?php echo $mobile_id ? esc_url(wp_get_attachment_image_url($mobile_id, 'medium')) : ''; ?>" data-id="<?php echo $mobile_id ? esc_attr($mobile_id) : ''; ?>" alt="<?php echo esc_attr(zpower_slider_t('手機圖片預覽', 'Mobile image preview')); ?>">
                        <?php if($can_edit): ?>
                        <button type="button" class="zpower-slide-img-btn zpower-select-mobile-img"><?php echo esc_html(zpower_slider_t('選擇手機圖片', 'Select Mobile Image')); ?></button>
                        <button type="button" class="zpower-slide-img-btn zpower-remove-mobile-img" style="color:#d63638;"><?php echo esc_html(zpower_slider_t('移除手機圖', 'Remove Mobile Image')); ?></button>
                        <?php endif; ?>
                    </div>
                    <div class="zpower-slide-link-fields">
                        <input type="text" class="zpower-slide-url regular-text" value="<?php echo $url; ?>" placeholder="<?php echo esc_attr(zpower_slider_t('連結網址', 'Link URL')); ?>" <?php if(!$can_edit) echo 'disabled'; ?> >
                        <select class="zpower-slide-target" <?php if(!$can_edit) echo 'disabled'; ?> >
                            <option value="_self" <?php selected($target, '_self'); ?>><?php echo esc_html(zpower_slider_t('原視窗', 'Same Window')); ?></option>
                            <option value="_blank" <?php selected($target, '_blank'); ?>><?php echo esc_html(zpower_slider_t('新視窗', 'New Window')); ?></option>
                        </select>
                    </div>
                </div>
                <?php } ?>
            </div>
            <p style="color:#50575e; margin-top:15px; font-style:italic;">
                <?php echo esc_html(zpower_slider_t('*建議圖片寬度在 2000 px 以上', '*Recommended image width is 2000 px or wider')); ?><br>
                &nbsp;<?php echo esc_html(zpower_slider_t('每組圖片可分別設定桌機與手機圖，連結與開啟方式共用', 'Each image set can have separate desktop and mobile images while sharing the same link and target.')); ?>
            </p>
        </div>
    </div> 
</div> <script>
    jQuery(document).ready(function($){
        var canEdit = <?php echo $can_edit ? 'true' : 'false'; ?>;
        var zpowerSliderAdminI18n = {
            opacity: <?php echo wp_json_encode(zpower_slider_t('不透明度', 'opacity')); ?>,
            dragToSort: <?php echo wp_json_encode(zpower_slider_t('拖曳排序', 'Drag to Sort')); ?>,
            removeSet: <?php echo wp_json_encode(zpower_slider_t('移除此組', 'Remove This Set')); ?>,
            desktopPreview: <?php echo wp_json_encode(zpower_slider_t('桌機圖片預覽', 'Desktop image preview')); ?>,
            selectDesktop: <?php echo wp_json_encode(zpower_slider_t('選擇桌機圖片', 'Select Desktop Image')); ?>,
            mobilePreview: <?php echo wp_json_encode(zpower_slider_t('手機圖片預覽', 'Mobile image preview')); ?>,
            selectMobile: <?php echo wp_json_encode(zpower_slider_t('選擇手機圖片', 'Select Mobile Image')); ?>,
            removeMobile: <?php echo wp_json_encode(zpower_slider_t('移除手機圖', 'Remove Mobile Image')); ?>,
            linkUrl: <?php echo wp_json_encode(zpower_slider_t('連結網址', 'Link URL')); ?>,
            sameWindow: <?php echo wp_json_encode(zpower_slider_t('原視窗', 'Same Window')); ?>,
            newWindow: <?php echo wp_json_encode(zpower_slider_t('新視窗', 'New Window')); ?>,
            mediaError: <?php echo wp_json_encode(zpower_slider_t('WordPress 媒體庫功能未載入，請檢查是否有其他外掛衝突或 JavaScript 錯誤。', 'The WordPress media library did not load. Please check for plugin conflicts or JavaScript errors.')); ?>,
            useImage: <?php echo wp_json_encode(zpower_slider_t('選擇此圖片', 'Use This Image')); ?>
        };

        function initializeColorPickers() {
            if (canEdit && typeof $.fn.wpColorPicker === 'function') {
                $('.zpower-color-field').each(function() {
                     if (!$(this).data('wpWpColorPicker')) {
                        $(this).wpColorPicker();
                     }
                });
                $('.zpower-color-picker-alpha-enabled').each(function() { 
                    if (!$(this).data('wpWpColorPicker')) {
                        $(this).wpColorPicker();
                    }
                });
            }
        }
        initializeColorPickers(); 


        $('#zpower_slider_enable_article_slider').on('change', function(){
            if ($(this).is(':checked')) {
                $('#zpower_article_slider_options_wrapper').removeClass('hidden');
                $('#zpower_manual_slider_options_wrapper').removeClass('hidden'); 
                $('#zpower_manual_carousel_settings_section').removeClass('hidden'); 
                $('#zpower_manual_slider_images_section').addClass('hidden'); 
            } else {
                $('#zpower_article_slider_options_wrapper').addClass('hidden');
                $('#zpower_manual_slider_options_wrapper').removeClass('hidden'); 
                $('#zpower_manual_carousel_settings_section').removeClass('hidden'); 
                $('#zpower_manual_slider_images_section').removeClass('hidden'); 
            }
        }).trigger('change'); 

        $('#zpower_slider_article_fixed_height_enable').on('change', function(){
            if ($(this).is(':checked')) {
                $('#zpower_slider_article_fixed_height_values_wrapper').removeClass('hidden'); 
            } else {
                $('#zpower_slider_article_fixed_height_values_wrapper').addClass('hidden'); 
            }
        }).trigger('change'); 


        $('#zpower_slider_article_defaults_btn').on('click', function(){
            if (!canEdit) return;
            $('#zpower_slider_article_title_font_size_desktop').val(<?php echo $default_auto['article_title_font_size_desktop']; ?>).trigger('input');
            $('#zpower_slider_article_title_font_size_tablet').val(<?php echo $default_auto['article_title_font_size_tablet']; ?>).trigger('input');
            $('#zpower_slider_article_title_font_size_mobile').val(<?php echo $default_auto['article_title_font_size_mobile']; ?>).trigger('input');
            
            $('#zpower_slider_article_title_bg_height_desktop').val(<?php echo $default_auto['article_title_bg_height_desktop']; ?>).trigger('input');
            $('#zpower_slider_article_title_bg_height_tablet').val(<?php echo $default_auto['article_title_bg_height_tablet']; ?>).trigger('input');
            $('#zpower_slider_article_title_bg_height_mobile').val(<?php echo $default_auto['article_title_bg_height_mobile']; ?>).trigger('input');

            $('#zpower_slider_article_title_padding').val(<?php echo $default_auto['article_title_padding']; ?>).trigger('input'); 
            $('#zpower_slider_article_title_center_align').prop('checked', <?php echo $default_auto['article_title_center_align'] === 'yes' ? 'true' : 'false'; ?>);


            $('#zpower_slider_article_title_text_color_hex').val('<?php echo esc_js($default_auto['article_title_text_color']); ?>').trigger('change');
            if ($('#zpower_slider_article_title_text_color_hex').data('wpWpColorPicker')) {
                $('#zpower_slider_article_title_text_color_hex').wpColorPicker('color', '<?php echo esc_js($default_auto['article_title_text_color']); ?>');
            }
            $('#zpower_slider_article_title_text_opacity').val(<?php echo $default_auto['article_title_text_opacity']; ?>).trigger('input');

            $('#zpower_slider_article_title_bg_color_hex').val('<?php echo esc_js(substr($default_auto['article_title_bg_color'],0,7)); ?>').trigger('change');
             if ($('#zpower_slider_article_title_bg_color_hex').data('wpWpColorPicker')) {
                $('#zpower_slider_article_title_bg_color_hex').wpColorPicker('color', '<?php echo esc_js(substr($default_auto['article_title_bg_color'],0,7)); ?>');
            }
            $('#zpower_slider_article_title_bg_opacity').val(<?php echo $default_auto['article_title_bg_opacity']; ?>).trigger('input');

            $('#zpower_slider_article_fixed_height_enable').prop('checked', <?php echo $default_auto['article_fixed_height_enable'] === 'yes' ? 'true' : 'false'; ?>).trigger('change');
            $('#zpower_slider_article_fixed_height_value_desktop').val(<?php echo $default_auto['article_fixed_height_value_desktop']; ?>).trigger('input');
            $('#zpower_slider_article_fixed_height_value_tablet').val(<?php echo $default_auto['article_fixed_height_value_tablet']; ?>).trigger('input');
            $('#zpower_slider_article_fixed_height_value_mobile').val(<?php echo $default_auto['article_fixed_height_value_mobile']; ?>).trigger('input');
        });


        if (!canEdit) {
            $('#zpower_slider_images input, #zpower_slider_images select, #zpower_slider_images button').prop('disabled', true);
            if (typeof $.fn.wpColorPicker === 'function') {
                 $('#zpower_slider_images .zpower-color-field, #zpower_slider_images .zpower-color-picker-alpha-enabled').each(function(){
                    if ($(this).data('wpWpColorPicker')) {
                        $(this).wpColorPicker('option', 'disabled', true);
                    }
                 });
            }
            if (typeof $.fn.sortable === 'function' && $('#zpower_slider_slides_preview').data('uiSortable')) {
                $('#zpower_slider_slides_preview').sortable('disable');
            }
            $('#zpower_add_slide_btn, .zpower-slide-drag-handle, .zpower-remove-slide, .zpower-select-pc-img, .zpower-select-mobile-img, .zpower-remove-mobile-img, #zpower_slider_reset_btn, #zpower_slider_autoplay_reset_btn, #zpower_slider_article_defaults_btn').hide();
        }

        // Range slider value updates
        $('#zpower_slider_arrow_size').on('input', function(){ $('#zpower_slider_arrow_size_val').text(this.value + 'px'); });
        $('#zpower_slider_arrow_margin').on('input', function(){ $('#zpower_slider_arrow_margin_val').text(this.value + 'px'); });
        $('#zpower_slider_dot_size').on('input', function(){ $('#zpower_slider_dot_size_val').text(this.value + 'px'); });
        $('#zpower_slider_dot_gap').on('input', function(){ $('#zpower_slider_dot_gap_val').text(this.value + 'px'); });
        $('#zpower_slider_dot_bottom').on('input', function(){ $('#zpower_slider_dot_bottom_val').text(this.value + 'px'); });
        $('#zpower_slider_style_two_radius').on('input', function(){ $('#zpower_slider_style_two_radius_val').text(this.value + 'px'); });
        $('#zpower_slider_autoplay_delay').on('input', function(){ $('#zpower_slider_autoplay_delay_val').text(this.value + ' ms'); });
        
        $('#zpower_slider_article_title_font_size_desktop').on('input', function(){ $('#zpower_slider_article_title_font_size_desktop_val').text(this.value + 'px'); });
        $('#zpower_slider_article_title_font_size_tablet').on('input', function(){ $('#zpower_slider_article_title_font_size_tablet_val').text(this.value + 'px'); });
        $('#zpower_slider_article_title_font_size_mobile').on('input', function(){ $('#zpower_slider_article_title_font_size_mobile_val').text(this.value + 'px'); });
        
        $('#zpower_slider_article_title_bg_height_desktop').on('input', function(){ $('#zpower_slider_article_title_bg_height_desktop_val').text(this.value + 'px'); });
        $('#zpower_slider_article_title_bg_height_tablet').on('input', function(){ $('#zpower_slider_article_title_bg_height_tablet_val').text(this.value + 'px'); });
        $('#zpower_slider_article_title_bg_height_mobile').on('input', function(){ $('#zpower_slider_article_title_bg_height_mobile_val').text(this.value + 'px'); });

        $('#zpower_slider_article_title_padding').on('input', function(){ $('#zpower_slider_article_title_padding_val').text(this.value + 'px'); }); 
        $('#zpower_slider_article_title_text_opacity').on('input', function(){ $('#zpower_slider_article_title_text_opacity_val').text(this.value + '% ' + zpowerSliderAdminI18n.opacity); }); 
        $('#zpower_slider_article_title_bg_opacity').on('input', function(){ $('#zpower_slider_article_title_bg_opacity_val').text(this.value + '% ' + zpowerSliderAdminI18n.opacity); });   
        
        $('#zpower_slider_article_fixed_height_value_desktop').on('input', function(){ $('#zpower_slider_article_fixed_height_value_desktop_val').text(this.value + 'px'); }); 
        $('#zpower_slider_article_fixed_height_value_tablet').on('input', function(){ $('#zpower_slider_article_fixed_height_value_tablet_val').text(this.value + 'px'); }); 
        $('#zpower_slider_article_fixed_height_value_mobile').on('input', function(){ $('#zpower_slider_article_fixed_height_value_mobile_val').text(this.value + 'px'); }); 


        $('#zpower_slider_reset_btn').on('click', function(){
            if (!canEdit) return;
            $('#zpower_slider_arrow_size').val(<?php echo $default_manual['arrow_size']; ?>).trigger('input');
            $('#zpower_slider_arrow_margin').val(<?php echo $default_manual['arrow_margin']; ?>).trigger('input');
            $('#zpower_slider_dot_size').val(<?php echo $default_manual['dot_size']; ?>).trigger('input');
            $('#zpower_slider_dot_gap').val(<?php echo $default_manual['dot_gap']; ?>).trigger('input');
            $('#zpower_slider_dot_bottom').val(<?php echo $default_manual['dot_bottom']; ?>).trigger('input');
            $('#zpower_slider_style_two_radius').val(<?php echo $default_manual['style_two_radius']; ?>).trigger('input');
            if (typeof $.fn.wpColorPicker === 'function') {
                $('#zpower_slider_arrow_color').val('<?php echo esc_js($default_manual['arrow_color']); ?>').trigger('change').wpColorPicker('color', '<?php echo esc_js($default_manual['arrow_color']); ?>');
                $('#zpower_slider_dot_color').val('<?php echo esc_js($default_manual['dot_color']); ?>').trigger('change').wpColorPicker('color', '<?php echo esc_js($default_manual['dot_color']); ?>');
            }
            $('input[name="zpower_slider_enable_arrows"]').prop('checked', <?php echo $default_manual['enable_arrows'] === 'yes' ? 'true' : 'false'; ?>);
            $('input[name="zpower_slider_enable_dots"]').prop('checked', <?php echo $default_manual['enable_dots'] === 'yes' ? 'true' : 'false'; ?>);
        });

        $('#zpower_slider_autoplay_reset_btn').on('click', function(){
            if (!canEdit) return;
            $('#zpower_slider_autoplay_delay').val(<?php echo $default_manual['autoplay_delay']; ?>).trigger('input');
            $('#zpower_slider_autoplay').prop('checked', <?php echo $default_manual['autoplay'] === 'yes' ? 'true' : 'false'; ?>);
        });

        function updateSlidesInput() {
            if (!canEdit) return;
            var slides = [];
            $('#zpower_slider_slides_preview .zpower-slider-slide-item').each(function(idx){
                var $item = $(this);
                $item.attr('data-index', idx);
                slides.push({
                    pc: $item.find('.zpower-slide-pc-img').attr('data-id') || '',
                    mobile: $item.find('.zpower-slide-mobile-img').attr('data-id') || '',
                    url: $item.find('.zpower-slide-url').val() || '',
                    target: $item.find('.zpower-slide-target').val() || '_self'
                });
            });
            $('#zpower_slider_slides_input').val(JSON.stringify(slides));
        }

        $('#zpower_add_slide_btn').on('click', function(){
            if (!canEdit) return;
            var idx = $('#zpower_slider_slides_preview .zpower-slider-slide-item').length;
            var html = `
                <div class="zpower-slider-slide-item" data-index="${idx}">
                    <span class="zpower-slide-drag-handle" title="${zpowerSliderAdminI18n.dragToSort}" aria-label="${zpowerSliderAdminI18n.dragToSort}"></span>
                    <span class="zpower-remove-slide" title="${zpowerSliderAdminI18n.removeSet}">×</span>
                    <div class="zpower-slide-img-preview">
                        <img class="zpower-slide-pc-img" src="" data-id="" alt="${zpowerSliderAdminI18n.desktopPreview}">
                        <button type="button" class="zpower-slide-img-btn zpower-select-pc-img">${zpowerSliderAdminI18n.selectDesktop}</button>
                    </div>
                    <div class="zpower-slide-img-preview">
                        <img class="zpower-slide-mobile-img" src="" data-id="" alt="${zpowerSliderAdminI18n.mobilePreview}">
                        <button type="button" class="zpower-slide-img-btn zpower-select-mobile-img">${zpowerSliderAdminI18n.selectMobile}</button>
                        <button type="button" class="zpower-slide-img-btn zpower-remove-mobile-img" style="color:#d63638;">${zpowerSliderAdminI18n.removeMobile}</button>
                    </div>
                    <div class="zpower-slide-link-fields">
                        <input type="text" class="zpower-slide-url regular-text" value="" placeholder="${zpowerSliderAdminI18n.linkUrl}">
                        <select class="zpower-slide-target">
                            <option value="_self">${zpowerSliderAdminI18n.sameWindow}</option>
                            <option value="_blank">${zpowerSliderAdminI18n.newWindow}</option>
                        </select>
                    </div>
                </div>`;
            $('#zpower_slider_slides_preview').append(html);
            enableSlideEventsForLastItem();
            refreshSlidesSortable();
            updateSlidesInput();
        });

        function enableSlideEventsForLastItem() {
            if (!canEdit) return;
            var $newItem = $('#zpower_slider_slides_preview .zpower-slider-slide-item:last-child');
            attachMediaUploader($newItem.find('.zpower-select-pc-img'), $newItem.find('.zpower-slide-pc-img'));
            attachMediaUploader($newItem.find('.zpower-select-mobile-img'), $newItem.find('.zpower-slide-mobile-img'));
            $newItem.find('.zpower-remove-mobile-img').on('click', handleRemoveMobileImage);
            $newItem.find('.zpower-remove-slide').on('click', handleRemoveSlide);
            $newItem.find('.zpower-slide-url, .zpower-slide-target').on('change', updateSlidesInput);
        }

        function attachMediaUploader($button, $imgElement) {
            $button.on('click', function(e) {
                if (!canEdit) return;
                e.preventDefault();
                if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                    alert(zpowerSliderAdminI18n.mediaError);
                    return;
                }
                var frame = wp.media({
                    title: $button.text(),
                    button: { text: zpowerSliderAdminI18n.useImage },
                    library: { type: 'image' },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $imgElement.attr('src', attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url)
                               .attr('data-id', attachment.id);
                    updateSlidesInput();
                });
                frame.open();
            });
        }

        function handleRemoveMobileImage() {
            if (!canEdit) return;
            $(this).closest('.zpower-slide-img-preview').find('.zpower-slide-mobile-img').attr('src','').attr('data-id','');
            updateSlidesInput();
        }

        function handleRemoveSlide() {
            if (!canEdit) return;
            $(this).closest('.zpower-slider-slide-item').remove();
            refreshSlidesSortable();
            updateSlidesInput();
        }

        function refreshSlidesSortable() {
            setupSlidesSortable(0);
        }

        function setupSlidesSortable(attempt) {
            if (!canEdit) return;

            var $slidesList = $('#zpower_slider_slides_preview');
            if (!$slidesList.length) return;

            if (typeof $.fn.sortable !== 'function') {
                if (attempt < 10) {
                    setTimeout(function() {
                        setupSlidesSortable(attempt + 1);
                    }, 150);
                }
                return;
            }

            if (!$slidesList.data('uiSortable')) {
                $slidesList.sortable({
                    items: '.zpower-slider-slide-item',
                    handle: '.zpower-slide-drag-handle',
                    cancel: 'input, textarea, button, select, option, .zpower-remove-slide, .zpower-slide-img-btn',
                    placeholder: 'zpower-slide-placeholder',
                    forcePlaceholderSize: true,
                    tolerance: 'pointer',
                    distance: 3,
                    start: function(event, ui) {
                        ui.item.addClass('is-sorting');
                        ui.placeholder.height(ui.item.outerHeight());
                    },
                    stop: function(event, ui) {
                        ui.item.removeClass('is-sorting');
                        updateSlidesInput();
                    },
                    update: function(event, ui) {
                        updateSlidesInput();
                    }
                });
            } else {
                $slidesList.sortable('option', 'disabled', false);
                $slidesList.sortable('refresh');
            }
        }

        function enableSlideEvents() {
            $('#zpower_slider_slides_preview .zpower-slider-slide-item').each(function(){
                var $item = $(this);
                $item.find('.zpower-select-pc-img, .zpower-select-mobile-img, .zpower-remove-mobile-img, .zpower-remove-slide, .zpower-slide-url, .zpower-slide-target').off();

                if (canEdit) {
                    attachMediaUploader($item.find('.zpower-select-pc-img'), $item.find('.zpower-slide-pc-img'));
                    attachMediaUploader($item.find('.zpower-select-mobile-img'), $item.find('.zpower-slide-mobile-img'));
                    $item.find('.zpower-remove-mobile-img').on('click', handleRemoveMobileImage);
                    $item.find('.zpower-remove-slide').on('click', handleRemoveSlide);
                    $item.find('.zpower-slide-url, .zpower-slide-target').on('change', updateSlidesInput);
                }
            });

            refreshSlidesSortable();
        }

        enableSlideEvents();
        if (canEdit) {
            $('#post').on('submit', updateSlidesInput);
            updateSlidesInput();
        }
    });
    </script>
    <?php
} // End of zpower_slider_images_metabox_callback

// 儲存 Meta Box 資料
add_action('save_post_zpower_slider', function($post_id){
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $post_id;

    if (!isset($_POST['zpower_slider_images_metabox_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zpower_slider_images_metabox_nonce'])), 'zpower_slider_images_metabox_save')) {
        return $post_id;
    }

    if (!zpower_slider_current_user_can_manage()) {
        return $post_id;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }

    // 輪播模式設定
    $enable_article_slider = zpower_slider_post_value('zpower_slider_enable_article_slider') === 'yes' ? 'yes' : 'no';
    update_post_meta($post_id, '_zpower_slider_enable_article_slider', $enable_article_slider);

    // 自動文章輪播設定
    if (isset($_POST['zpower_slider_article_category'])) {
        update_post_meta($post_id, '_zpower_slider_article_category', absint(zpower_slider_post_value('zpower_slider_article_category')));
    }
    if (isset($_POST['zpower_slider_article_title_font_size_desktop'])) {
        update_post_meta($post_id, '_zpower_slider_article_title_font_size_desktop', zpower_slider_clamp_int(zpower_slider_post_value('zpower_slider_article_title_font_size_desktop'), 10, 40, 16));
    }
    if (isset($_POST['zpower_slider_article_title_font_size_tablet'])) {
        update_post_meta($post_id, '_zpower_slider_article_title_font_size_tablet', zpower_slider_clamp_int(zpower_slider_post_value('zpower_slider_article_title_font_size_tablet'), 10, 36, 14));
    }
    if (isset($_POST['zpower_slider_article_title_font_size_mobile'])) {
        update_post_meta($post_id, '_zpower_slider_article_title_font_size_mobile', zpower_slider_clamp_int(zpower_slider_post_value('zpower_slider_article_title_font_size_mobile'), 10, 32, 12));
    }

    if (isset($_POST['zpower_slider_article_title_bg_color_hex'])) {
        update_post_meta($post_id, '_zpower_slider_article_title_bg_color_hex', sanitize_hex_color(zpower_slider_post_value('zpower_slider_article_title_bg_color_hex')));
    }
    if (isset($_POST['zpower_slider_article_title_bg_opacity'])) {
        update_post_meta($post_id, '_zpower_slider_article_title_bg_opacity', zpower_slider_clamp_int(zpower_slider_post_value('zpower_slider_article_title_bg_opacity'), 0, 100, 50));
    }
    if (isset($_POST['zpower_slider_article_title_text_color_hex'])) {
        update_post_meta($post_id, '_zpower_slider_article_title_text_color_hex', sanitize_hex_color(zpower_slider_post_value('zpower_slider_article_title_text_color_hex')));
    }
    if (isset($_POST['zpower_slider_article_title_text_opacity'])) {
        update_post_meta($post_id, '_zpower_slider_article_title_text_opacity', zpower_slider_clamp_int(zpower_slider_post_value('zpower_slider_article_title_text_opacity'), 0, 100, 100));
    }
    
    // 儲存響應式標題背景高度
    if (isset($_POST['zpower_slider_article_title_bg_height_desktop'])) {
        update_post_meta($post_id, '_zpower_slider_article_title_bg_height_desktop', zpower_slider_clamp_int(zpower_slider_post_value('zpower_slider_article_title_bg_height_desktop'), 20, 100, 40));
    }
    if (isset($_POST['zpower_slider_article_title_bg_height_tablet'])) {
        update_post_meta($post_id, '_zpower_slider_article_title_bg_height_tablet', zpower_slider_clamp_int(zpower_slider_post_value('zpower_slider_article_title_bg_height_tablet'), 20, 100, 35));
    }
    if (isset($_POST['zpower_slider_article_title_bg_height_mobile'])) {
        update_post_meta($post_id, '_zpower_slider_article_title_bg_height_mobile', zpower_slider_clamp_int(zpower_slider_post_value('zpower_slider_article_title_bg_height_mobile'), 20, 100, 30));
    }

    if (isset($_POST['zpower_slider_article_title_padding'])) { 
        update_post_meta($post_id, '_zpower_slider_article_title_padding', zpower_slider_clamp_int(zpower_slider_post_value('zpower_slider_article_title_padding'), 0, 100, 15));
    }
    $article_title_center_align_save = zpower_slider_post_value('zpower_slider_article_title_center_align') === 'yes' ? 'yes' : 'no';
    update_post_meta($post_id, '_zpower_slider_article_title_center_align', $article_title_center_align_save);
    
    $article_fixed_height_enable_save = zpower_slider_post_value('zpower_slider_article_fixed_height_enable') === 'yes' ? 'yes' : 'no';
    update_post_meta($post_id, '_zpower_slider_article_fixed_height_enable', $article_fixed_height_enable_save);
    
    if (isset($_POST['zpower_slider_article_fixed_height_value_desktop'])) {
        update_post_meta($post_id, '_zpower_slider_article_fixed_height_value_desktop', zpower_slider_clamp_int(zpower_slider_post_value('zpower_slider_article_fixed_height_value_desktop'), 100, 1200, 300));
    }
    if (isset($_POST['zpower_slider_article_fixed_height_value_tablet'])) {
        update_post_meta($post_id, '_zpower_slider_article_fixed_height_value_tablet', zpower_slider_clamp_int(zpower_slider_post_value('zpower_slider_article_fixed_height_value_tablet'), 100, 1000, 250));
    }
    if (isset($_POST['zpower_slider_article_fixed_height_value_mobile'])) {
        update_post_meta($post_id, '_zpower_slider_article_fixed_height_value_mobile', zpower_slider_clamp_int(zpower_slider_post_value('zpower_slider_article_fixed_height_value_mobile'), 100, 800, 200));
    }


    // 手動輪播 - 圖片選擇
    if(isset($_POST['zpower_slider_slides'])){
        $slides_json = zpower_slider_post_value('zpower_slider_slides', '');
        $slides = json_decode($slides_json, true);
        if(is_array($slides)){
            $sanitized_slides = array();
            foreach ($slides as $slide) {
                $sanitized_slides[] = array(
                    'pc' => isset($slide['pc']) ? intval($slide['pc']) : 0,
                    'mobile' => isset($slide['mobile']) ? intval($slide['mobile']) : 0,
                    'url' => isset($slide['url']) && !is_array($slide['url']) ? esc_url_raw(trim((string) $slide['url'])) : '',
                    'target' => (isset($slide['target']) && in_array($slide['target'], array('_self', '_blank'), true)) ? $slide['target'] : '_self',
                );
            }
            update_post_meta($post_id, '_zpower_slider_slides', $sanitized_slides);
        } else {
             delete_post_meta($post_id, '_zpower_slider_slides');
        }
    }

    // 手動輪播 - 通用設定
    $manual_meta_fields_callbacks = [
        '_zpower_slider_arrow_color' => function($value) { return sanitize_hex_color($value); },
        '_zpower_slider_dot_color'   => function($value) { return sanitize_hex_color($value); },
        '_zpower_slider_arrow_size'  => function($value) { return zpower_slider_clamp_int($value, 20, 100, 44); },
        '_zpower_slider_arrow_margin'=> function($value) { return zpower_slider_clamp_int($value, 0, 100, 16); },
        '_zpower_slider_dot_size'    => function($value) { return zpower_slider_clamp_int($value, 6, 32, 12); },
        '_zpower_slider_dot_gap'     => function($value) { return zpower_slider_clamp_int($value, 0, 32, 8); },
        '_zpower_slider_dot_bottom'  => function($value) { return zpower_slider_clamp_int($value, 0, 100, 16); },
        '_zpower_slider_autoplay_delay' => function($value) { return zpower_slider_clamp_int($value, 1000, 20000, 3000); },
    ];
    foreach ($manual_meta_fields_callbacks as $meta_key_prefixed => $sanitize_callback) {
        $post_key = substr($meta_key_prefixed, 1);
        if (isset($_POST[$post_key])) {
            update_post_meta($post_id, $meta_key_prefixed, call_user_func($sanitize_callback, zpower_slider_post_value($post_key)));
        }
    }
    $autoplay_value_to_save = zpower_slider_post_value('zpower_slider_autoplay') === 'yes' ? 'yes' : 'no';
    update_post_meta($post_id, '_zpower_slider_autoplay', $autoplay_value_to_save);
    $enable_arrows_save = zpower_slider_post_value('zpower_slider_enable_arrows') === 'yes' ? 'yes' : 'no';
    update_post_meta($post_id, '_zpower_slider_enable_arrows', $enable_arrows_save);
    $enable_dots_save = zpower_slider_post_value('zpower_slider_enable_dots') === 'yes' ? 'yes' : 'no';
    update_post_meta($post_id, '_zpower_slider_enable_dots', $enable_dots_save);
    $display_style_save = zpower_slider_post_value('zpower_slider_display_style') === 'style_2' ? 'style_2' : 'style_1';
    update_post_meta($post_id, '_zpower_slider_display_style', $display_style_save);
    if (isset($_POST['zpower_slider_style_two_radius'])) {
        $style_two_radius_save = zpower_slider_clamp_int(zpower_slider_post_value('zpower_slider_style_two_radius'), 0, 80, 22);
        if ($style_two_radius_save === 22) {
            delete_post_meta($post_id, '_zpower_slider_style_two_radius');
        } else {
            update_post_meta($post_id, '_zpower_slider_style_two_radius', $style_two_radius_save);
        }
    }
});


// 前端載入 Swiper
add_action('wp_enqueue_scripts', function() {
    if (!wp_style_is('swiper-bundle-css', 'enqueued') && !wp_style_is('swiper', 'enqueued')) {
        wp_enqueue_style(
            'zpower-swiper-style',
            ZPOWER_SLIDER_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.css',
            array(),
            '11.0.5'
        );
    }
    if (!wp_script_is('swiper-bundle-js', 'enqueued') && !wp_script_is('swiper', 'enqueued')) {
        wp_enqueue_script(
            'zpower-swiper-script',
            ZPOWER_SLIDER_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.js',
            array(),
            '11.0.5',
            true
        );
        $capture_script = "
            if (typeof Swiper === 'function' && typeof window.ZPowerSwiperV11 === 'undefined') {
                window.ZPowerSwiperV11 = Swiper;
            }
            if (typeof window.ZPowerSliderGlobalInit !== 'undefined' && window.ZPowerSliderGlobalInit.queue.length > 0 && typeof window.ZPowerSwiperV11 === 'function') {
                window.ZPowerSliderGlobalInit.tryInitializeQueue();
            }
        ";
        wp_add_inline_script('zpower-swiper-script', $capture_script, 'after');
    }
});

// HEX 與透明度轉 RGBA
if (!function_exists('zpower_hex_opacity_to_rgba')) {
    function zpower_hex_opacity_to_rgba($hex, $opacity_percent) {
        $sanitized_hex = sanitize_hex_color($hex);
        if (!$sanitized_hex) {
            $sanitized_hex = '#000000';
        }
        $hex = $sanitized_hex;
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        $opacity = max(0, min(1, $opacity_percent / 100));
        return "rgba({$r},{$g},{$b},{$opacity})";
    }
}

// 避免 Elementor / wpautop 將短代碼內的 style/script 換行轉成 <p>，導致輪播 JS 語法錯誤。
if (!function_exists('zpower_slider_protect_inline_assets')) {
    function zpower_slider_protect_inline_assets($html) {
        if (strpos($html, '<style') === false && strpos($html, '<script') === false) {
            return $html;
        }

        return preg_replace_callback('/<(style|script)(\b[^>]*)>(.*?)<\/\1>/is', function($matches) {
            $content = str_replace(array("\r\n", "\r", "\n", "\t"), ' ', $matches[3]);
            return '<' . $matches[1] . $matches[2] . '>' . trim($content) . '</' . $matches[1] . '>';
        }, $html);
    }
}


// 短代碼 [zpower_slider id=""]
add_shortcode('zpower_slider', function($atts) {
    $atts = shortcode_atts(array('id' => ''), $atts, 'zpower_slider');
    $post_id = intval($atts['id']);

    if(!$post_id) return '';

    $slider_post = get_post($post_id);
    if (!$slider_post || $slider_post->post_type !== 'zpower_slider') {
        return '';
    }
    if ($slider_post->post_status !== 'publish' && !current_user_can('edit_post', $post_id)) {
        return '';
    }

    $enable_article_slider = get_post_meta($post_id, '_zpower_slider_enable_article_slider', true) === 'yes';

    $default_display = array(
        'arrow_color' => '#ffffff', 'dot_color' => '#ffffff', 'arrow_size' => 44,
        'arrow_margin' => 16, 'dot_size' => 12, 'dot_gap' => 8,
        'dot_bottom' => 16, 'autoplay_delay' => 3000,
        'enable_arrows' => 'yes', 'enable_dots' => 'yes',
        'display_style' => 'style_1',
        'style_two_radius' => 22
    );
    $default_auto_frontend = array(
        'article_title_font_size_desktop' => 16,
        'article_title_font_size_tablet' => 14,
        'article_title_font_size_mobile' => 12,
        'article_title_bg_color_hex' => '#000000',
        'article_title_bg_opacity' => 50,
        'article_title_text_color_hex' => '#ffffff',
        'article_title_text_opacity' => 100,
        'article_title_bg_height_desktop' => 40,
        'article_title_bg_height_tablet' => 35,
        'article_title_bg_height_mobile' => 30,
        'article_title_padding' => 15, 
        'article_title_center_align' => 'no',
        'article_fixed_height_enable' => 'no', 
        'article_fixed_height_value_desktop' => 300,   
        'article_fixed_height_value_tablet' => 250,   
        'article_fixed_height_value_mobile' => 200,   
    );

    // 通用顯示設定
    $arrow_color = sanitize_hex_color(get_post_meta($post_id, '_zpower_slider_arrow_color', true)) ?: $default_display['arrow_color'];
    $dot_color = sanitize_hex_color(get_post_meta($post_id, '_zpower_slider_dot_color', true)) ?: $default_display['dot_color'];
    $arrow_size = get_post_meta($post_id, '_zpower_slider_arrow_size', true);
    $arrow_size = zpower_slider_clamp_int($arrow_size, 20, 100, $default_display['arrow_size']);
    $arrow_margin = get_post_meta($post_id, '_zpower_slider_arrow_margin', true);
    $arrow_margin = zpower_slider_clamp_int($arrow_margin, 0, 100, $default_display['arrow_margin']);
    $dot_size = get_post_meta($post_id, '_zpower_slider_dot_size', true);
    $dot_size = zpower_slider_clamp_int($dot_size, 6, 32, $default_display['dot_size']);
    $dot_gap = get_post_meta($post_id, '_zpower_slider_dot_gap', true);
    $dot_gap = zpower_slider_clamp_int($dot_gap, 0, 32, $default_display['dot_gap']);
    $dot_bottom = get_post_meta($post_id, '_zpower_slider_dot_bottom', true); 
    $dot_bottom = zpower_slider_clamp_int($dot_bottom, 0, 100, $default_display['dot_bottom']);
    $autoplay_active = get_post_meta($post_id, '_zpower_slider_autoplay', true) === 'yes';
    $autoplay_delay_time = get_post_meta($post_id, '_zpower_slider_autoplay_delay', true);
    $autoplay_delay_time = zpower_slider_clamp_int($autoplay_delay_time, 1000, 20000, $default_display['autoplay_delay']);
    $arrows_enabled_setting = get_post_meta($post_id, '_zpower_slider_enable_arrows', true);
    $render_arrows = ($arrows_enabled_setting === '' || $arrows_enabled_setting === 'yes');
    $dots_enabled_setting = get_post_meta($post_id, '_zpower_slider_enable_dots', true);
    $render_dots = ($dots_enabled_setting === '' || $dots_enabled_setting === 'yes');
    $display_style_value = get_post_meta($post_id, '_zpower_slider_display_style', true);
    $display_style = in_array($display_style_value, array('style_1', 'style_2'), true) ? $display_style_value : $default_display['display_style'];
    $display_style_class = $display_style === 'style_2' ? 'zpower-slider-style-two' : 'zpower-slider-style-one';
    $style_two_radius_meta = get_post_meta($post_id, '_zpower_slider_style_two_radius', true);
    $style_two_radius_has_custom = ($style_two_radius_meta !== '' && $style_two_radius_meta !== false);
    $style_two_radius = zpower_slider_clamp_int($style_two_radius_meta, 0, 80, $default_display['style_two_radius']);
    $style_two_radius_css = $style_two_radius_has_custom ? $style_two_radius . 'px' : 'clamp(12px, 1.7vw, 22px)';

    $slider_unique = 'zpower-swiper-' . $post_id . '-' . wp_rand(1000,9999);
    $slides_to_render = array();

    // 文章輪播特定設定
    $article_title_font_size_desktop_val = zpower_slider_clamp_int(get_post_meta($post_id, '_zpower_slider_article_title_font_size_desktop', true), 10, 40, $default_auto_frontend['article_title_font_size_desktop']);
    $article_title_font_size_tablet_val = zpower_slider_clamp_int(get_post_meta($post_id, '_zpower_slider_article_title_font_size_tablet', true), 10, 36, $default_auto_frontend['article_title_font_size_tablet']);
    $article_title_font_size_mobile_val = zpower_slider_clamp_int(get_post_meta($post_id, '_zpower_slider_article_title_font_size_mobile', true), 10, 32, $default_auto_frontend['article_title_font_size_mobile']);

    $article_title_bg_color_hex = sanitize_hex_color(get_post_meta($post_id, '_zpower_slider_article_title_bg_color_hex', true)) ?: $default_auto_frontend['article_title_bg_color_hex'];
    $article_title_bg_opacity = get_post_meta($post_id, '_zpower_slider_article_title_bg_opacity', true);
    $article_title_bg_opacity = zpower_slider_clamp_int($article_title_bg_opacity, 0, 100, $default_auto_frontend['article_title_bg_opacity']);
    $final_article_title_bg_color = zpower_hex_opacity_to_rgba($article_title_bg_color_hex, $article_title_bg_opacity);

    $article_title_text_color_hex = sanitize_hex_color(get_post_meta($post_id, '_zpower_slider_article_title_text_color_hex', true)) ?: $default_auto_frontend['article_title_text_color_hex'];
    $article_title_text_opacity = get_post_meta($post_id, '_zpower_slider_article_title_text_opacity', true);
    $article_title_text_opacity = zpower_slider_clamp_int($article_title_text_opacity, 0, 100, $default_auto_frontend['article_title_text_opacity']);
    $final_article_title_text_color = zpower_hex_opacity_to_rgba($article_title_text_color_hex, $article_title_text_opacity);

    $article_title_bg_height_desktop_val = zpower_slider_clamp_int(get_post_meta($post_id, '_zpower_slider_article_title_bg_height_desktop', true), 20, 100, $default_auto_frontend['article_title_bg_height_desktop']);
    $article_title_bg_height_tablet_val = zpower_slider_clamp_int(get_post_meta($post_id, '_zpower_slider_article_title_bg_height_tablet', true), 20, 100, $default_auto_frontend['article_title_bg_height_tablet']);
    $article_title_bg_height_mobile_val = zpower_slider_clamp_int(get_post_meta($post_id, '_zpower_slider_article_title_bg_height_mobile', true), 20, 100, $default_auto_frontend['article_title_bg_height_mobile']);
    
    $article_title_padding_val = get_post_meta($post_id, '_zpower_slider_article_title_padding', true);
    $article_title_padding_val = zpower_slider_clamp_int($article_title_padding_val, 0, 100, $default_auto_frontend['article_title_padding']);
    $article_title_center_align_frontend = get_post_meta($post_id, '_zpower_slider_article_title_center_align', true) === 'yes';


    $article_fixed_height_enable_frontend = get_post_meta($post_id, '_zpower_slider_article_fixed_height_enable', true) === 'yes';
    $article_fixed_height_value_desktop_frontend = zpower_slider_clamp_int(get_post_meta($post_id, '_zpower_slider_article_fixed_height_value_desktop', true), 100, 1200, $default_auto_frontend['article_fixed_height_value_desktop']);
    $article_fixed_height_value_tablet_frontend = zpower_slider_clamp_int(get_post_meta($post_id, '_zpower_slider_article_fixed_height_value_tablet', true), 100, 1000, $default_auto_frontend['article_fixed_height_value_tablet']);
    $article_fixed_height_value_mobile_frontend = zpower_slider_clamp_int(get_post_meta($post_id, '_zpower_slider_article_fixed_height_value_mobile', true), 100, 800, $default_auto_frontend['article_fixed_height_value_mobile']);


    if ($enable_article_slider) {
        $article_slider_category_id = absint(get_post_meta($post_id, '_zpower_slider_article_category', true));
        $args = array(
            'post_type' => 'post', 'posts_per_page' => 10, 'orderby' => 'date',
            'order' => 'DESC', 'post_status' => 'publish',
        );
        if (!empty($article_slider_category_id)) {
            $args['cat'] = $article_slider_category_id;
        }
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                if (has_post_thumbnail()) {
                    $thumbnail_id = get_post_thumbnail_id();
                    $pc_image_src_array = wp_get_attachment_image_src($thumbnail_id, 'full');
                    $mobile_image_src_array = $pc_image_src_array; 

                    $pc_url = $pc_image_src_array ? $pc_image_src_array[0] : '';
                    $mobile_url = $mobile_image_src_array ? $mobile_image_src_array[0] : '';

                    if ($pc_url) {
                         $slides_to_render[] = array(
                            'pc_image_to_render' => $pc_url,
                            'mobile_image_to_render' => $mobile_url,
                            'pc_alt_text' => get_the_title(),
                            'mobile_alt_text' => get_the_title(),
                            'url' => get_permalink(),
                            'target' => '_self', 
                            'title' => get_the_title()
                        );
                    }
                }
            }
            wp_reset_postdata();
        }
        if (empty($slides_to_render)) {
            return '<p style="text-align:center; padding: 20px; color: #777;">' . esc_html(zpower_slider_t('輪播圖', 'Slider')) . ' (ID: ' . esc_attr($post_id) . ') ' . esc_html(zpower_slider_t('在指定分類下找不到符合條件的文章或文章沒有精選圖片。', 'could not find matching posts with featured images in the selected category.')) . '</p>';
        }
    } else { 
        $slides_meta = get_post_meta($post_id, '_zpower_slider_slides', true);
        if(!is_array($slides_meta) || empty($slides_meta)) return '<p style="text-align:center; padding: 20px; color: #777;">' . esc_html(zpower_slider_t('輪播圖', 'Slider')) . ' (ID: ' . esc_attr($post_id) . ') ' . esc_html(zpower_slider_t('尚未設定圖片，或沒有有效的圖片。', 'has no configured images or no valid images.')) . '</p>';
        
        $valid_slides = array_filter($slides_meta, function($slide_item) {
            return !empty($slide_item['pc']) || !empty($slide_item['mobile']);
        });

        if (empty($valid_slides)) {
            return '<p style="text-align:center; padding: 20px; color: #777;">' . esc_html(zpower_slider_t('輪播圖', 'Slider')) . ' (ID: ' . esc_attr($post_id) . ') ' . esc_html(zpower_slider_t('沒有有效的圖片可供顯示。', 'has no valid images to display.')) . '</p>';
        }

        foreach($valid_slides as $slide_data) {
            $pc_id = !empty($slide_data['pc']) ? intval($slide_data['pc']) : 0;
            $mobile_id = !empty($slide_data['mobile']) ? intval($slide_data['mobile']) : 0;
            $url = !empty($slide_data['url']) ? esc_url($slide_data['url']) : '';
            $target = !empty($slide_data['target']) ? esc_attr($slide_data['target']) : '_self';

            $pc_image_src_array = $pc_id ? wp_get_attachment_image_src($pc_id, 'full') : null;
            $mobile_image_src_array = $mobile_id ? wp_get_attachment_image_src($mobile_id, 'full') : null;
            
            $pc_url = $pc_image_src_array ? $pc_image_src_array[0] : '';
            $mobile_url = $mobile_image_src_array ? $mobile_image_src_array[0] : '';

            $pc_image_to_render = $pc_url;
            $mobile_image_to_render = $mobile_url;

            $pc_alt_text = $pc_id ? (get_post_meta($pc_id, '_wp_attachment_image_alt', true) ?: get_the_title($pc_id)) : zpower_slider_t('桌機輪播圖片', 'Desktop slider image');
            if (empty(trim($pc_alt_text))) $pc_alt_text = zpower_slider_t('桌機輪播圖片', 'Desktop slider image');
            
            $mobile_alt_text = $mobile_id ? (get_post_meta($mobile_id, '_wp_attachment_image_alt', true) ?: get_the_title($mobile_id)) : ($pc_id ? $pc_alt_text : zpower_slider_t('手機輪播圖片', 'Mobile slider image'));
            if (empty(trim($mobile_alt_text))) $mobile_alt_text = ($pc_id ? $pc_alt_text : zpower_slider_t('手機輪播圖片', 'Mobile slider image'));

            if (empty($mobile_image_to_render) && !empty($pc_image_to_render)) {
                $mobile_image_to_render = $pc_image_to_render;
                if ($mobile_id === 0 || empty(trim(get_post_meta($mobile_id, '_wp_attachment_image_alt', true)))) { $mobile_alt_text = $pc_alt_text; }
            }
            if (empty($pc_image_to_render) && !empty($mobile_image_to_render)) {
                $pc_image_to_render = $mobile_image_to_render;
                 if ($pc_id === 0 || empty(trim(get_post_meta($pc_id, '_wp_attachment_image_alt', true)))) { $pc_alt_text = $mobile_alt_text; }
            }

            $slides_to_render[] = array(
                'pc_image_to_render' => $pc_image_to_render,
                'mobile_image_to_render' => $mobile_image_to_render,
                'pc_alt_text' => $pc_alt_text,
                'mobile_alt_text' => $mobile_alt_text,
                'url' => $url, 'target' => $target, 'title' => null
            );
        }
    }
    
    $slides_to_render = array_values($slides_to_render);
    foreach ($slides_to_render as $slide_index => &$slide_to_render_item) {
        $slide_to_render_item['zpower_source_index'] = $slide_index;
    }
    unset($slide_to_render_item);

    $source_slide_count = count($slides_to_render);
    $frontend_slides_to_render = $slides_to_render;
    $style_two_uses_virtual_loop = $display_style === 'style_2' && $source_slide_count > 1 && $source_slide_count < 6;

    if ($style_two_uses_virtual_loop) {
        $frontend_slides_to_render = array();
        for ($i = 0; $i < ($source_slide_count * 3); $i++) {
            $frontend_slides_to_render[] = $slides_to_render[$i % $source_slide_count];
        }
    }

    $enable_swiper_loop_parameter = $source_slide_count > 1;
    $frontend_slide_count = count($frontend_slides_to_render);

    ob_start();
    ?>
    <div class="zpower-swiper-container <?php echo esc_attr($display_style_class); ?>">
        <?php if ($enable_article_slider && $autoplay_active && $enable_swiper_loop_parameter): ?>
        <div class="zpower-autoplay-progress <?php echo esc_attr($slider_unique); ?>-progress">
            <div class="zpower-autoplay-progress-bar"></div>
        </div>
        <?php endif; ?>
        <div class="swiper <?php echo esc_attr($slider_unique); ?>">
            <div class="swiper-wrapper">
                <?php foreach($frontend_slides_to_render as $slide_output_data): ?>
                <div class="swiper-slide" data-zpower-source-index="<?php echo esc_attr($slide_output_data['zpower_source_index']); ?>">
                    <?php if($slide_output_data['url']): ?><a href="<?php echo esc_url($slide_output_data['url']); ?>" target="<?php echo esc_attr($slide_output_data['target']); ?>" class="zpower-slide-link" aria-label="<?php echo esc_attr(zpower_slider_t('輪播項目連結', 'Slider item link')); ?>" draggable="false"><?php endif; ?>
                    <div class="zpower-slide-content">
                        <?php if(!empty($slide_output_data['pc_image_to_render'])): ?>
                        <div class="zpower-pc-image">
                            <img src="<?php echo esc_url($slide_output_data['pc_image_to_render']); ?>" alt="<?php echo esc_attr($slide_output_data['pc_alt_text']); ?>" loading="<?php echo $display_style === 'style_2' ? 'eager' : 'lazy'; ?>" decoding="async" draggable="false">
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($slide_output_data['mobile_image_to_render'])): ?>
                        <div class="zpower-mobile-image">
                            <img src="<?php echo esc_url($slide_output_data['mobile_image_to_render']); ?>" alt="<?php echo esc_attr($slide_output_data['mobile_alt_text']); ?>" loading="<?php echo $display_style === 'style_2' ? 'eager' : 'lazy'; ?>" decoding="async" draggable="false">
                        </div>
                        <?php endif; ?>

                        <?php if ($enable_article_slider && !empty($slide_output_data['title'])): ?>
                        <div class="zpower-article-title-overlay" style="
                            background-color: <?php echo esc_attr($final_article_title_bg_color); ?>;
                            padding-left: <?php echo esc_attr($article_title_padding_val); ?>px;
                            padding-right: <?php echo esc_attr($article_title_padding_val); ?>px;
                            justify-content: <?php echo $article_title_center_align_frontend ? 'center' : 'flex-start'; ?>;
                            <?php if (strpos($final_article_title_bg_color, 'rgba') === 0 && $article_title_bg_opacity < 100): ?>
                            -webkit-backdrop-filter: blur(5px);
                            backdrop-filter: blur(5px);
                            <?php endif; ?>
                        ">
                            <span class="zpower-article-title-text" style="
                                color: <?php echo esc_attr($final_article_title_text_color); ?>;
                                <?php if ($article_title_center_align_frontend): ?>
                                text-align: center; 
                                <?php endif; ?>
                            ">
                                <?php echo esc_html($slide_output_data['title']); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if($slide_output_data['url']): ?></a><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($enable_swiper_loop_parameter && $render_arrows): ?>
            <div class="swiper-button-next zpower-swiper-arrow" role="button" aria-label="<?php echo esc_attr(zpower_slider_t('下一張', 'Next slide')); ?>">
                <svg viewBox="0 0 40 40" width="100%" height="100%" style="display:block;" xmlns="http://www.w3.org/2000/svg"><polyline points="15,10 25,20 15,30" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="swiper-button-prev zpower-swiper-arrow" role="button" aria-label="<?php echo esc_attr(zpower_slider_t('上一張', 'Previous slide')); ?>">
                 <svg viewBox="0 0 40 40" width="100%" height="100%" style="display:block;transform:scaleX(-1);" xmlns="http://www.w3.org/2000/svg"><polyline points="15,10 25,20 15,30" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <?php endif; ?>
            <?php if ($enable_swiper_loop_parameter && $render_dots): ?>
            <div class="swiper-pagination" role="tablist" aria-label="<?php echo esc_attr(zpower_slider_t('輪播導覽點', 'Slider pagination')); ?>"></div>
            <?php endif; ?>
        </div>
    </div>
    <style>
    .zpower-swiper-container { width: 100%; position: relative; overflow: hidden; }
    <?php if ($display_style === 'style_2'): ?>
    .zpower-slider-style-two {
        background: #ffffff;
        box-sizing: border-box;
        padding: 30px;
    }
    .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> {
        overflow: visible !important;
    }
    .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .swiper-wrapper {
        align-items: center;
    }
    .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .swiper-slide {
        width: calc(83.33% - 50px);
        max-width: calc(100% - 160px);
        box-sizing: border-box;
        opacity: 0.48;
        transform: scale(0.94);
        transform-origin: center center;
        transition: none;
    }
    .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?>.zpower-slider-ready .swiper-slide {
        transition: transform 0.35s ease, opacity 0.35s ease;
    }
    .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?>.zpower-slider-jump .swiper-slide {
        transition: none !important;
    }
    @media (min-width: 769px) {
        .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .swiper-slide-prev {
            transform-origin: right center;
        }
        .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .swiper-slide-next {
            transform-origin: left center;
        }
    }
    .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .swiper-slide-active {
        opacity: 1;
        transform: scale(1);
        z-index: 2;
    }
    .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .zpower-slide-content,
    .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .zpower-pc-image,
    .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .zpower-mobile-image,
    .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .zpower-pc-image img,
    .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .zpower-mobile-image img {
        border-radius: <?php echo esc_attr($style_two_radius_css); ?>;
    }
    .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .zpower-slide-content {
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.18);
    }
    .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .swiper-slide:not(.swiper-slide-active) .zpower-slide-content::after {
        content: "";
        position: absolute;
        inset: 0;
        z-index: 5;
        border-radius: inherit;
        background: rgba(0, 0, 0, 0.46);
        pointer-events: none;
    }
    .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .zpower-article-title-overlay {
        border-radius: 0 0 <?php echo esc_attr($style_two_radius_css); ?> <?php echo esc_attr($style_two_radius_css); ?>;
    }
    @media (max-width: 768px) {
        .zpower-slider-style-two {
            padding: 20px;
        }
        .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .swiper-slide {
            width: calc(83.33% - 17px);
            max-width: calc(100% - 40px);
        }
        .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .zpower-slide-content {
            box-shadow: 0 4px 9px rgba(0, 0, 0, 0.18);
        }
    }
    @media (max-width: 480px) {
        .zpower-slider-style-two .<?php echo esc_attr($slider_unique); ?> .swiper-slide {
            width: calc(83.33% - 17px);
            max-width: calc(100% - 40px);
        }
    }
    <?php endif; ?>
    .<?php echo esc_attr($slider_unique); ?> { 
        position: relative; 
        <?php if ($enable_article_slider && $article_fixed_height_enable_frontend): ?>
        height: <?php echo esc_attr($article_fixed_height_value_desktop_frontend); ?>px; 
        overflow: hidden;
        <?php endif; ?>
    }
    <?php if ($enable_article_slider && $article_fixed_height_enable_frontend): ?>
    @media (max-width: 1024px) { 
        .<?php echo esc_attr($slider_unique); ?> {
            height: <?php echo esc_attr($article_fixed_height_value_tablet_frontend); ?>px !important;
        }
    }
    @media (max-width: 768px) { 
        .<?php echo esc_attr($slider_unique); ?> {
            height: <?php echo esc_attr($article_fixed_height_value_mobile_frontend); ?>px !important;
        }
    }
    <?php endif; ?>
    .<?php echo esc_attr($slider_unique); ?> .zpower-pc-image,
    .<?php echo esc_attr($slider_unique); ?> .zpower-mobile-image,
    .<?php echo esc_attr($slider_unique); ?> img {
        -webkit-user-select: none;
        user-select: none;
        -webkit-user-drag: none;
        pointer-events: none;
    }
    .<?php echo esc_attr($slider_unique); ?> .zpower-slide-link {
        -webkit-user-drag: none;
    }

    .<?php echo esc_attr($slider_unique); ?> .swiper-wrapper { align-items: flex-start; 
        <?php if ($enable_article_slider && $article_fixed_height_enable_frontend): ?>
        height: 100%;
        <?php endif; ?>
    }
     .<?php echo esc_attr($slider_unique); ?> .swiper-slide {
        <?php if ($enable_article_slider && $article_fixed_height_enable_frontend): ?>
        height: 100%; 
        overflow: hidden;
        <?php endif; ?>
    }

    .zpower-slide-content { position: relative; width: 100%; overflow: hidden; 
        <?php if ($enable_article_slider && $article_fixed_height_enable_frontend): ?>
        height: 100%;
        display: flex; 
        align-items: center;
        justify-content: center;
        <?php endif; ?>
    }
    .zpower-pc-image, .zpower-mobile-image { width: 100%; position: relative; overflow: hidden; background-color: #f0f0f0; line-height: 0; 
        <?php if ($enable_article_slider && $article_fixed_height_enable_frontend): ?>
        height: 100%; 
        display: flex;
        align-items: center;
        justify-content: center;
        <?php endif; ?>
    }
    .zpower-pc-image img, .zpower-mobile-image img { display: block; width: 100%; 
        <?php if ($enable_article_slider && $article_fixed_height_enable_frontend): ?>
        height: 100%; 
        object-fit: cover;
        object-position: center;
        <?php else: ?>
        height: auto;
        max-width: 100%; 
        object-fit: cover;
        <?php endif; ?>
    }
    .zpower-mobile-image { display: none; }
    @media (max-width: 768px) { 
        .zpower-pc-image { display: none; } .zpower-mobile-image { display: block; } 
    }
    @media (min-width: 769px) { 
        .zpower-pc-image { display: block; } 
    }
    .zpower-slide-link { display: block; width: 100%; height: 100%; text-decoration: none; }

    .zpower-article-title-overlay {
        position: absolute; bottom: 0; left: 0; width: 100%;
        box-sizing: border-box;
        display: flex; align-items: center; /* justify-content is now inline */
        z-index: 10; 
        height: <?php echo esc_attr($article_title_bg_height_desktop_val); ?>px; 
        <?php if ($enable_article_slider): ?>
        opacity: 0;
        transition: opacity 0.5s ease-in-out; 
        <?php endif; ?>
    }
    .<?php echo esc_attr($slider_unique); ?> .swiper-slide-active .zpower-article-title-overlay {
        <?php if ($enable_article_slider): ?>
        opacity: 1;
        <?php endif; ?>
    }

    .zpower-article-title-text {
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;
        font-size: <?php echo esc_attr($article_title_font_size_desktop_val); ?>px; 
        line-height: <?php echo esc_attr($article_title_bg_height_desktop_val); ?>px; 
    }
    <?php if ($enable_article_slider): ?>
    @media (max-width: 1024px) { 
        .<?php echo esc_attr($slider_unique); ?> .zpower-article-title-overlay {
            height: <?php echo esc_attr($article_title_bg_height_tablet_val); ?>px !important;
        }
        .<?php echo esc_attr($slider_unique); ?> .zpower-article-title-text {
            font-size: <?php echo esc_attr($article_title_font_size_tablet_val); ?>px !important;
            line-height: <?php echo esc_attr($article_title_bg_height_tablet_val); ?>px !important;
        }
    }
    @media (max-width: 768px) { 
        .<?php echo esc_attr($slider_unique); ?> .zpower-article-title-overlay {
            height: <?php echo esc_attr($article_title_bg_height_mobile_val); ?>px !important;
        }
        .<?php echo esc_attr($slider_unique); ?> .zpower-article-title-text {
            font-size: <?php echo esc_attr($article_title_font_size_mobile_val); ?>px !important;
            line-height: <?php echo esc_attr($article_title_bg_height_mobile_val); ?>px !important;
        }
    }
    <?php endif; ?>

    .<?php echo esc_attr($slider_unique); ?> .swiper-button-next,
    .<?php echo esc_attr($slider_unique); ?> .swiper-button-prev {
        color: <?php echo esc_attr($arrow_color); ?> !important; background: none !important;
        width: <?php echo esc_attr($arrow_size); ?>px !important; height: <?php echo esc_attr($arrow_size); ?>px !important;
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        top: 50%; transform: translateY(-50%); padding: 0; opacity: 1; z-index: 20; 
        transition: opacity 0.3s ease, color 0.3s ease; cursor: pointer;
    }
    .<?php echo esc_attr($slider_unique); ?> .swiper-button-next:hover,
    .<?php echo esc_attr($slider_unique); ?> .swiper-button-prev:hover { opacity: 0.7; }
    .<?php echo esc_attr($slider_unique); ?> .swiper-button-disabled { opacity: 0.35 !important; cursor: auto !important; pointer-events: none; }
    .<?php echo esc_attr($slider_unique); ?> .swiper-button-next { right: <?php echo esc_attr($arrow_margin); ?>px !important; }
    .<?php echo esc_attr($slider_unique); ?> .swiper-button-prev { left: <?php echo esc_attr($arrow_margin); ?>px !important; }
    .<?php echo esc_attr($slider_unique); ?> .zpower-swiper-arrow svg { width: 100% !important; height: 100% !important; display: block; }
    .<?php echo esc_attr($slider_unique); ?> .swiper-pagination {
        bottom: <?php echo esc_attr($dot_bottom); ?>px !important; width: 100%; left: 0; text-align: center; z-index: 20; 
        position: absolute; display: flex; justify-content: center; align-items: center;
    }
    .<?php echo esc_attr($slider_unique); ?> .swiper-pagination-bullet {
        background: <?php echo esc_attr( substr($dot_color,0,7) . '99'); ?> !important;
        width: <?php echo esc_attr($dot_size); ?>px !important; height: <?php echo esc_attr($dot_size); ?>px !important;
        margin: 0 <?php echo esc_attr(max(1, $dot_gap/2)); ?>px !important;
        opacity: 1 !important; border-radius: 50%;
        transition: background-color 0.3s ease; cursor: pointer;
    }
    .<?php echo esc_attr($slider_unique); ?> .swiper-pagination-bullet-active { background: <?php echo esc_attr($dot_color); ?> !important; }
    .<?php echo esc_attr($slider_unique); ?> .swiper-pagination-bullet.zpower-pagination-duplicate { display: none !important; }
    .<?php echo esc_attr($slider_unique); ?> .swiper-button-next::after,
    .<?php echo esc_attr($slider_unique); ?> .swiper-button-prev::after { display: none !important; content: '' !important; }

    /* 進度條樣式 */
    .zpower-autoplay-progress {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px; 
        background-color: transparent; 
        z-index: 25; 
    }
    .zpower-autoplay-progress-bar {
        width: 0%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.11); 
        transition: width 0.05s linear; 
    }
    </style>
    <script>
    if (typeof window.ZPowerSliderGlobalInit === 'undefined') {
        window.ZPowerSliderGlobalInit = {
            queue: [],
            initializedSliders: {},
            instances: {},
            swiperCheckInterval: null,
            swiperCheckAttempts: 0,
            maxSwiperCheckAttempts: 30,

            addInstanceToQueue: function(selector, options) {
                if (!this.initializedSliders[selector] && !this.queue.find(item => item.selector === selector)) {
                    this.queue.push({ selector: selector, options: options });
                }
                if (typeof window.ZPowerSwiperV11 === 'function') {
                    this.tryInitializeQueue();
                } else {
                    this.scheduleInitializationCheck();
                }
            },

            scheduleInitializationCheck: function() {
                if (this.swiperCheckInterval) return; 
                this.swiperCheckAttempts = 0;
                this.swiperCheckInterval = setInterval(() => {
                    if (typeof window.ZPowerSwiperV11 === 'function' && window.ZPowerSwiperV11.prototype && window.ZPowerSwiperV11.prototype.constructor === window.ZPowerSwiperV11) {
                        clearInterval(this.swiperCheckInterval);
                        this.swiperCheckInterval = null;
                        this.tryInitializeQueue();
                        this.runFinalAdjustmentsForAll(); 
                    } else {
                        this.swiperCheckAttempts++;
                        if (this.swiperCheckAttempts >= this.maxSwiperCheckAttempts) {
                            clearInterval(this.swiperCheckInterval); 
                            this.swiperCheckInterval = null;
                        }
                    }
                }, 100);
            },

            tryInitializeQueue: function() {
                const SwiperConstructor = window.ZPowerSwiperV11;
                if (typeof SwiperConstructor === 'undefined' || typeof SwiperConstructor.prototype === 'undefined' || SwiperConstructor.prototype.constructor !== SwiperConstructor) {
                    if (!this.swiperCheckInterval && this.queue.length > 0) {
                         this.scheduleInitializationCheck();
                    }
                    return;
                }

                let stillInQueue = [];
                this.queue.forEach(item => {
                    if (this.initializedSliders[item.selector]) return; 

                    const sliderElement = document.querySelector(item.selector);
                    if (sliderElement) {
                        if (sliderElement.swiper || sliderElement.dataset.zpowerInitialized === 'true') {
                            this.initializedSliders[item.selector] = true;
                            if(sliderElement.swiper) this.instances[item.selector] = sliderElement.swiper;
                            sliderElement.classList.add('zpower-slider-ready');
                            if(typeof window.adjustZPowerSliderHeight === "function" && sliderElement.swiper && !item.options.articleFixedHeightEnabled) { 
                                window.adjustZPowerSliderHeight(sliderElement.swiper);
                            }
                            return;
                        }
                        try {
                            const instance = new SwiperConstructor(sliderElement, item.options);
                            sliderElement.dataset.zpowerInitialized = 'true'; 
                            sliderElement.classList.add('zpower-slider-ready');
                            this.instances[item.selector] = instance;
                            this.initializedSliders[item.selector] = true;
                            if(typeof window.adjustZPowerSliderHeight === "function" && !instance.params.articleFixedHeightEnabled) { 
                                adjustZPowerSliderHeight(instance); 
                            }
                        } catch (e) {
                            stillInQueue.push(item); 
                        }
                    } else {
                        stillInQueue.push(item);
                    }
                });
                this.queue = stillInQueue; 
            },

            runFinalAdjustmentsForAll: function() {
                for (const selector in this.instances) {
                    if (this.instances.hasOwnProperty(selector)) {
                        const instance = this.instances[selector];
                        if (instance && instance.el && typeof adjustZPowerSliderHeight === "function" && !instance.params.articleFixedHeightEnabled) { 
                             adjustZPowerSliderHeight(instance);
                        }
                    }
                }
            }
        };

        window.adjustZPowerSliderHeight = function(swiperInstance) {
            if (!swiperInstance || !swiperInstance.el || !swiperInstance.slides || swiperInstance.slides.length === 0 || typeof swiperInstance.activeIndex === 'undefined' || !swiperInstance.slides[swiperInstance.activeIndex]) {
                return;
            }
            if (swiperInstance.params && swiperInstance.params.articleFixedHeightEnabled) {
                return;
            }
            try {
                const activeSlide = swiperInstance.slides[swiperInstance.activeIndex];
                if (!activeSlide) return;

                const pcImageDiv = activeSlide.querySelector('.zpower-pc-image');
                const mobileImageDiv = activeSlide.querySelector('.zpower-mobile-image');
                let visibleImageContainer = null;
                let imageElement = null;

                if (pcImageDiv && window.getComputedStyle(pcImageDiv).display !== 'none') {
                    visibleImageContainer = pcImageDiv;
                    imageElement = pcImageDiv.querySelector('img');
                } else if (mobileImageDiv && window.getComputedStyle(mobileImageDiv).display !== 'none') {
                    visibleImageContainer = mobileImageDiv;
                    imageElement = mobileImageDiv.querySelector('img');
                }

                if (visibleImageContainer) {
                    const setHeight = () => {
                        if (visibleImageContainer && visibleImageContainer.offsetHeight > 0) {
                            if (swiperInstance.el) {
                                swiperInstance.el.style.height = visibleImageContainer.offsetHeight + 'px';
                            }
                        }
                        if (swiperInstance.update) swiperInstance.update();
                    };

                    if (imageElement) {
                        if (imageElement.complete && imageElement.naturalHeight > 0) {
                            setHeight();
                        } else {
                            imageElement.onload = null; imageElement.onerror = null; 
                            imageElement.onload = setHeight;
                            imageElement.onerror = setHeight; 
                            if (!imageElement.src || (imageElement.complete && imageElement.naturalHeight === 0 && imageElement.naturalWidth === 0)) {
                                setHeight(); 
                            }
                        }
                    } else { setHeight(); }
                }
            } catch (e) { /* Error handling */ }
        };

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.ZPowerSliderGlobalInit !== 'undefined') {
                if (typeof window.ZPowerSwiperV11 === 'function') {
                    window.ZPowerSliderGlobalInit.tryInitializeQueue();
                } else {
                    window.ZPowerSliderGlobalInit.scheduleInitializationCheck();
                }
            }
        });
        window.addEventListener('load', function() {
             if (typeof window.ZPowerSliderGlobalInit !== 'undefined') {
                window.ZPowerSliderGlobalInit.tryInitializeQueue(); 
                setTimeout(function() {
                    window.ZPowerSliderGlobalInit.runFinalAdjustmentsForAll();
                }, 300); 
            }
        });
    }

    (function() {
        const sliderSelector = '.<?php echo esc_js($slider_unique); ?>';
        const currentSliderElement = document.querySelector(sliderSelector);
        const progressContainer = document.querySelector('.<?php echo esc_js($slider_unique); ?>-progress'); 
        const progressBar = progressContainer ? progressContainer.querySelector('.zpower-autoplay-progress-bar') : null;
        const zpowerSourceSlideCount = <?php echo intval($source_slide_count); ?>;
        const zpowerFrontendSlideCount = <?php echo intval($frontend_slide_count); ?>;
        const zpowerUsesVirtualLoop = <?php echo wp_json_encode($style_two_uses_virtual_loop); ?>;

        if (currentSliderElement) {
            currentSliderElement.addEventListener('dragstart', function(event) {
                if (!event.target) return;
                if (typeof event.target.closest !== 'function') return;
                if (event.target.closest('.zpower-pc-image, .zpower-mobile-image')) {
                    event.preventDefault();
                }
            });
        }

        const syncZPowerSourcePagination = function(swiper) {
            if (!swiper || !currentSliderElement || zpowerSourceSlideCount <= 0) return;
            const paginationEl = currentSliderElement.querySelector('.swiper-pagination');
            if (!paginationEl) return;
            let activeSlide = null;
            if (swiper.slides) {
                if (typeof swiper.activeIndex !== 'undefined') {
                    activeSlide = swiper.slides[swiper.activeIndex];
                }
            }
            const sourceIndexAttr = activeSlide ? activeSlide.getAttribute('data-zpower-source-index') : null;
            const sourceIndex = sourceIndexAttr !== null ? parseInt(sourceIndexAttr, 10) : (swiper.realIndex % zpowerSourceSlideCount);
            const normalizedSourceIndex = Number.isNaN(sourceIndex) ? 0 : sourceIndex;

            paginationEl.querySelectorAll('.swiper-pagination-bullet').forEach(function(bullet) {
                bullet.classList.remove('swiper-pagination-bullet-active');
                bullet.removeAttribute('aria-current');
            });

            const activeBullet = paginationEl.querySelector('.swiper-pagination-bullet[data-zpower-source-index="' + normalizedSourceIndex + '"]');
            if (activeBullet) {
                activeBullet.classList.add('swiper-pagination-bullet-active');
                activeBullet.setAttribute('aria-current', 'true');
            }
        };

        const releaseZPowerJumpLock = function() {
            if (!currentSliderElement) return;
            const removeJumpClass = function() {
                currentSliderElement.classList.remove('zpower-slider-jump');
            };
            if (typeof window.requestAnimationFrame === 'function') {
                window.requestAnimationFrame(function() {
                    window.requestAnimationFrame(removeJumpClass);
                });
                return;
            }
            window.setTimeout(removeJumpClass, 0);
        };

        const normalizeZPowerVirtualLoop = function(swiper) {
            if (!zpowerUsesVirtualLoop || !swiper || typeof swiper.activeIndex === 'undefined' || zpowerSourceSlideCount <= 0) return;
            const firstMiddleIndex = zpowerSourceSlideCount;
            const firstEndIndex = zpowerFrontendSlideCount - zpowerSourceSlideCount;
            let targetIndex = null;

            if (typeof swiper.slideTo !== 'function') return;
            if (swiper.activeIndex < firstMiddleIndex) {
                targetIndex = swiper.activeIndex + zpowerSourceSlideCount;
            } else if (swiper.activeIndex >= firstEndIndex) {
                targetIndex = swiper.activeIndex - zpowerSourceSlideCount;
            }

            if (targetIndex === null) return;
            if (currentSliderElement) {
                currentSliderElement.classList.add('zpower-slider-jump');
            }
            swiper.slideTo(targetIndex, 0, false);
            if (typeof swiper.updateSlidesClasses === 'function') {
                swiper.updateSlidesClasses();
            }
            syncZPowerSourcePagination(swiper);
            releaseZPowerJumpLock();
        };


        if (currentSliderElement && !currentSliderElement.dataset.zpowerQueued) {
            currentSliderElement.dataset.zpowerQueued = 'true'; 

            let swiperOptions = {
                observer: true, observeParents: true, observeSlideChildren: true,
                slidesPerView: <?php echo $display_style === 'style_2' ? "'auto'" : '1'; ?>,
                spaceBetween: <?php echo $display_style === 'style_2' ? '10' : '0'; ?>,
                watchOverflow: true,
                updateOnImagesReady: true, 
                isArticleSlider: <?php echo wp_json_encode($enable_article_slider); ?>, 
                articleFixedHeightEnabled: <?php echo wp_json_encode($enable_article_slider && $article_fixed_height_enable_frontend); ?>, 
                <?php if ($display_style === 'style_2'): ?>
                initialSlide: <?php echo $style_two_uses_virtual_loop ? intval($source_slide_count) : 0; ?>,
                centeredSlides: true,
                slideToClickedSlide: true,
                loopAdditionalSlides: <?php echo max(3, $frontend_slide_count); ?>,
                loopedSlides: <?php echo max(3, $frontend_slide_count); ?>,
                breakpoints: {
                    769: {
                        spaceBetween: 30,
                    },
                },
                <?php endif; ?>
                a11y: { 
                    enabled: true,
                    prevSlideMessage: <?php echo wp_json_encode(zpower_slider_t('上一張投影片', 'Previous slide')); ?>,
                    nextSlideMessage: <?php echo wp_json_encode(zpower_slider_t('下一張投影片', 'Next slide')); ?>,
                    paginationBulletMessage: <?php echo wp_json_encode(zpower_slider_t('跳至投影片 {{index}}', 'Go to slide {{index}}')); ?>,
                    firstSlideMessage: <?php echo wp_json_encode(zpower_slider_t('這是第一張投影片', 'This is the first slide')); ?>,
                    lastSlideMessage: <?php echo wp_json_encode(zpower_slider_t('這是最後一張投影片', 'This is the last slide')); ?>,
                },
                on: {
                    init: function () {
                        normalizeZPowerVirtualLoop(this);
                        if (!this.params.articleFixedHeightEnabled) {
                            if(typeof window.adjustZPowerSliderHeight === "function") window.adjustZPowerSliderHeight(this);
                        }
                        this.update(); 
                        if (this.navigation) { this.navigation.update(); } 
                        syncZPowerSourcePagination(this);
                    },
                    imagesReady: function () { 
                        if (!this.params.articleFixedHeightEnabled) {
                           if(typeof window.adjustZPowerSliderHeight === "function") window.adjustZPowerSliderHeight(this);
                        }
                        this.update(); 
                    }, 
                    resize: function () { 
                        if (!this.params.articleFixedHeightEnabled) {
                            if(typeof window.adjustZPowerSliderHeight === "function") window.adjustZPowerSliderHeight(this);
                        }
                        this.update(); 
                        if (this.navigation) { this.navigation.update(); } 
                    },
                    slideChangeTransitionStart: function () { 
                        syncZPowerSourcePagination(this);
                        if (!this.params.articleFixedHeightEnabled) {
                            if(typeof window.adjustZPowerSliderHeight === "function") window.adjustZPowerSliderHeight(this);
                        }
                        if (progressBar && this.params.isArticleSlider && this.autoplay && this.autoplay.running) {
                           progressBar.style.width = '0%'; 
                           progressBar.style.transition = 'none'; 
                           void progressBar.offsetWidth; 
                           progressBar.style.transition = 'width 0.05s linear';
                        }
                    },
                    slideChangeTransitionEnd: function () {
                        normalizeZPowerVirtualLoop(this);
                        syncZPowerSourcePagination(this);
                        if (this.navigation) { this.navigation.update(); }
                    },
                    autoplayTimeLeft: function(swiper, timeLeft, percentage){
                        if (progressBar && swiper.params.isArticleSlider) {
                            const progress = (1 - percentage) * 100;
                            progressBar.style.width = progress + '%';
                        }
                    },
                    autoplayStop: function(swiper){
                         if (progressBar && swiper.params.isArticleSlider) {
                            progressBar.style.width = '0%';
                        }
                    },
                    autoplayStart: function(swiper){
                         if (progressBar && swiper.params.isArticleSlider) {
                            progressBar.style.width = '0%'; 
                            progressBar.style.transition = 'none'; 
                            void progressBar.offsetWidth; 
                            progressBar.style.transition = 'width 0.05s linear';
                        }
                    }
                }
            };

            const slideCount = <?php echo $source_slide_count; ?>;

            if (slideCount > 1) {
                swiperOptions.loop = <?php echo wp_json_encode($enable_swiper_loop_parameter && !$style_two_uses_virtual_loop); ?>;
                if (<?php echo wp_json_encode($autoplay_active); ?>) {
                    swiperOptions.autoplay = {
                        delay: <?php echo intval($autoplay_delay_time); ?>,
                        disableOnInteraction: false, 
                        pauseOnMouseEnter: true,    
                    };
                }
                if (<?php echo wp_json_encode($render_arrows); ?>) {
                    swiperOptions.navigation = {
                        nextEl: sliderSelector + ' .swiper-button-next',
                        prevEl: sliderSelector + ' .swiper-button-prev',
                    };
                } else {
                     swiperOptions.navigation = false; 
                }
                if (<?php echo wp_json_encode($render_dots); ?>) {
                    swiperOptions.pagination = {
                        el: sliderSelector + ' .swiper-pagination',
                        clickable: true, bulletElement: 'span', 
                        bulletClass: 'swiper-pagination-bullet', 
                        bulletActiveClass: 'swiper-pagination-bullet-active', 
                        renderBullet: function(index, className) {
                            const sourceIndex = zpowerSourceSlideCount > 0 ? index % zpowerSourceSlideCount : index;
                            const duplicateClass = index >= zpowerSourceSlideCount ? ' zpower-pagination-duplicate' : '';
                            const hiddenAttrs = index >= zpowerSourceSlideCount ? ' aria-hidden="true" tabindex="-1"' : '';
                            return '<span class="' + className + duplicateClass + '" data-zpower-source-index="' + sourceIndex + '"' + hiddenAttrs + '></span>';
                        },
                    };
                } else {
                    swiperOptions.pagination = false; 
                }
            } else { 
                swiperOptions.loop = false;
                swiperOptions.autoplay = false;
                swiperOptions.navigation = false;
                swiperOptions.pagination = false;
                if (currentSliderElement) {
                    const nextBtn = currentSliderElement.querySelector('.swiper-button-next');
                    const prevBtn = currentSliderElement.querySelector('.swiper-button-prev');
                    const paginationEl = currentSliderElement.querySelector('.swiper-pagination');
                    if (nextBtn) nextBtn.style.display = 'none';
                    if (prevBtn) prevBtn.style.display = 'none';
                    if (paginationEl) paginationEl.style.display = 'none';
                }
                 if (progressContainer) { 
                    progressContainer.style.display = 'none';
                }
            }

            if (typeof window.ZPowerSliderGlobalInit !== 'undefined') {
                window.ZPowerSliderGlobalInit.addInstanceToQueue(sliderSelector, swiperOptions);
            } else {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() { 
                        const SwiperConstructor = typeof window.ZPowerSwiperV11 === 'function' ? window.ZPowerSwiperV11 : (typeof Swiper === 'function' ? Swiper : null);
                        if (SwiperConstructor) {
                            const el = document.querySelector(sliderSelector);
                            if (el && !el.swiper && !el.dataset.zpowerInitialized) { 
                                try {
                                    new SwiperConstructor(el, swiperOptions);
                                    el.dataset.zpowerInitialized = 'true';
                                    el.classList.add('zpower-slider-ready');
                                } catch (e) { /* Error handling */ }
                            }
                        }
                    }, 1500); 
                });
            }
        }
    })();
    </script>
    <?php
    return zpower_slider_protect_inline_assets(ob_get_clean());
});

// CPT 列表頁新增 '短代碼' 欄位
add_filter('manage_zpower_slider_posts_columns', function($columns){
    $new_columns = array();
    foreach($columns as $key => $value) {
        $new_columns[$key] = $value;
        if($key === 'title') { 
            $new_columns['zpower_shortcode'] = zpower_slider_t('短代碼', 'Shortcode');
        }
    }
    if(!isset($new_columns['zpower_shortcode'])) {
         $new_columns['zpower_shortcode'] = zpower_slider_t('短代碼', 'Shortcode');
    }
    return $new_columns;
});


// 顯示 '短代碼' 欄位內容
add_action('manage_zpower_slider_posts_custom_column', function($column, $post_id){
    if($column === 'zpower_shortcode'){
        $shortcode = '[zpower_slider id="' . $post_id . '"]';
        echo '<span class="zpower-shortcode" style="display:inline-block;background:#f6f6f6;border:1px solid #ddd;padding:2px 8px;border-radius:4px;cursor:pointer;" data-shortcode="'.esc_attr($shortcode).'" title="'.esc_attr(zpower_slider_t('點擊以複製', 'Click to copy')).'">'.esc_html($shortcode).'</span> ';
        echo '<button type="button" class="button zpower-copy-btn" style="margin-left:6px;" data-shortcode="'.esc_attr($shortcode).'">'.esc_html(zpower_slider_t('複製', 'Copy')).'</button>';
    }
}, 10, 2);

// CPT 列表頁底部加入複製短代碼 JS
add_action('admin_footer-edit.php', function(){
    $current_screen = get_current_screen();
    if($current_screen && $current_screen->post_type === 'zpower_slider') { 
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const zpowerSliderCopySuccessText = <?php echo wp_json_encode(zpower_slider_t('已複製！', 'Copied!')); ?>;
        const zpowerSliderCopyFailText = <?php echo wp_json_encode(zpower_slider_t('複製失敗', 'Copy failed')); ?>;
        const zpowerSliderCopyText = <?php echo wp_json_encode(zpower_slider_t('複製', 'Copy')); ?>;

        function copyToClipboard(text, btnElement, successText = zpowerSliderCopySuccessText, originalText = zpowerSliderCopyText) {
            if(window.navigator.clipboard && window.isSecureContext){
                navigator.clipboard.writeText(text).then(function(){
                    if(btnElement) {
                        const oldText = btnElement.textContent; 
                        btnElement.textContent = successText;
                        setTimeout(function(){ btnElement.textContent = originalText; }, 1500); 
                    }
                }).catch(function(err) {
                    fallbackCopyTextToClipboard(text, btnElement, successText, originalText); 
                });
            } else {
                fallbackCopyTextToClipboard(text, btnElement, successText, originalText);
            }
        }

        function fallbackCopyTextToClipboard(text, btnElement, successText, originalText) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed'; 
            textarea.style.left = '-9999px'; 
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            try {
                var successful = document.execCommand('copy');
                if (successful && btnElement) {
                    const oldText = btnElement.textContent;
                    btnElement.textContent = successText;
                    setTimeout(function(){ btnElement.textContent = originalText; }, 1500);
                } else if (!successful && btnElement) { 
                     const oldText = btnElement.textContent;
                     btnElement.textContent = zpowerSliderCopyFailText; 
                     setTimeout(function(){ btnElement.textContent = originalText; }, 1500);
                }
            } catch (err) {
                if (btnElement) { 
                    const oldText = btnElement.textContent;
                    btnElement.textContent = zpowerSliderCopyFailText;
                    setTimeout(function(){ btnElement.textContent = originalText; }, 1500);
                }
            }
            document.body.removeChild(textarea);
        }

        document.querySelectorAll('.zpower-copy-btn').forEach(function(btn){
            const originalButtonText = btn.textContent; 
            btn.addEventListener('click', function(){
                var shortcode = this.getAttribute('data-shortcode');
                copyToClipboard(shortcode, btn, zpowerSliderCopySuccessText, originalButtonText);
            });
        });

        document.querySelectorAll('.zpower-shortcode').forEach(function(span){
            span.addEventListener('click', function(){
                var shortcode = this.getAttribute('data-shortcode');
                copyToClipboard(shortcode, null); 
                const originalOutline = this.style.outline;
                this.style.outline = '2px solid #4CAF50'; 
                setTimeout(() => { this.style.outline = originalOutline; }, 1000); 
            });
        });
    });
    </script>
    <?php
    }
});
?>
