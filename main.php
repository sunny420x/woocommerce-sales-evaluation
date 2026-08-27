<?php
/**
 * Plugin Name: Sales Evaluation For WooCommerce
 * Description: ปลั้กอินช่วยวิเคราะห์การขายและที่มาของลูกค้า โดยแบ่งเป็นสัดส่วน
 * Author: Jirakit Pawnsakunrungrot
 * Author URI: https://www.linkedin.com/in/sunny-jirakit
 * Plugin URI: https://github.com/sunny420x/woocommerce-sales-evaluation
 */

if (!defined('ABSPATH'))
    exit;

add_action('admin_menu', 'woocommerce_sales_evaluation_menu');

function woocommerce_sales_evaluation_menu()
{
    add_menu_page(
        'Sales Evaluation', // Title ของหน้า
        'Sales Evaluation', // ชื่อเมนูที่โชว์ในแถบข้าง
        'manage_options', //สิทธิ์การเข้าถึง (Admin)
        'woocommerce-sales-evaluation', // Slug ของหน้า
        'woocommerce_sales_evaluation_page', // ฟังก์ชันที่ใช้พ่น HTML หน้า Setting
        'dashicons-performance', // ไอคอน
        '80' // ตำแหน่งเมนู
    );
}

function woocommerce_sales_evaluation_sanitize_date_param($key, $default)
{
    if (!isset($_GET[$key]) || !is_scalar($_GET[$key])) {
        return $default;
    }

    $value = sanitize_text_field($_GET[$key]);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $default;
    }

    return $value;
}

function woocommerce_sales_evaluation_get_referral_source_config($option)
{
    $sources = array(
        'google' => array(
            'key' => 'google',
            'title' => 'การขายจาก Google',
            'label' => 'Google',
            'stats_label' => 'คำสั่งซื้อจาก Google',
            'keywords' => array('google'),
        ),
        'chatgpt' => array(
            'key' => 'chatgpt',
            'title' => 'การขายจาก ChatGPT',
            'label' => 'ChatGPT',
            'stats_label' => 'คำสั่งซื้อจาก ChatGPT',
            'keywords' => array('chatgpt', 'chatgpt.com'),
        ),
        'bing' => array(
            'key' => 'bing',
            'title' => 'การขายจาก Bing',
            'label' => 'Bing',
            'stats_label' => 'คำสั่งซื้อจาก Bing',
            'keywords' => array('bing', 'bing.com'),
        ),
        'line' => array(
            'key' => 'line',
            'title' => 'การขายจาก LINE',
            'label' => 'LINE',
            'stats_label' => 'คำสั่งซื้อจาก LINE',
            'keywords' => array('Safari Line', 'naver.line.android'),
        ),
        'facebook' => array(
            'key' => 'facebook',
            'title' => 'การขายจาก Facebook',
            'label' => 'Facebook',
            'stats_label' => 'คำสั่งซื้อจาก Facebook',
            'keywords' => array('facebook'),
        ),
    );

    return isset($sources[$option]) ? $sources[$option] : $sources['google'];
}

function woocommerce_sales_evaluation_get_total_order_count($start_date, $end_date)
{
    global $wpdb;

    $statuses = array(
        'wc-completed',
        'wc-processing',
        'wc-on-hold',
        'wc-pending',
        'wc-failed',
        'wc-cancelled',
        'wc-refunded',
    );

    $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
    $from = $start_date . ' 00:00:00';
    $to = $end_date . ' 23:59:59';

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status IN ($placeholders) AND post_date BETWEEN %s AND %s",
        array_merge($statuses, array($from, $to))
    ));
}

function woocommerce_sales_evaluation_get_referral_order_ids($start_date, $end_date, $option = 'google')
{
    global $wpdb;

    $config = woocommerce_sales_evaluation_get_referral_source_config($option);
    $statuses = array(
        'wc-completed',
        'wc-processing',
        'wc-on-hold',
        'wc-pending',
        'wc-failed',
        'wc-cancelled',
        'wc-refunded',
    );

    $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
    $from = $start_date . ' 00:00:00';
    $to = $end_date . ' 23:59:59';
    $keyword_placeholders = array();

    foreach ($config['keywords'] as $keyword) {
        $keyword_placeholders[] = '%' . $wpdb->esc_like($keyword) . '%';
        $keyword_placeholders[] = '%' . $wpdb->esc_like($keyword) . '%';
    }

    $like_conditions = array_fill(0, count($config['keywords']), '(LOWER(pm.meta_key) LIKE %s OR LOWER(pm.meta_value) LIKE %s)');
    $sql = "SELECT DISTINCT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
        WHERE p.post_type = 'shop_order'
          AND p.post_status IN ($placeholders)
          AND p.post_date BETWEEN %s AND %s
          AND pm.meta_key != ' '
          AND TRIM(COALESCE(pm.meta_value, '')) != ''
          AND (" . implode(' OR ', $like_conditions) . ")";

    return $wpdb->get_col($wpdb->prepare($sql, array_merge($statuses, array($from, $to), $keyword_placeholders)));
}

function woocommerce_sales_evaluation_get_referral_totals($order_ids, $completed_only = false)
{
    global $wpdb;

    if (empty($order_ids)) {
        return 0.0;
    }

    $placeholders = implode(', ', array_fill(0, count($order_ids), '%d'));
    $sql = "SELECT SUM(CAST(pm.meta_value AS DECIMAL(20,4)))
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = '_order_total'
          AND p.ID IN ($placeholders)";

    $args = $order_ids;
    if ($completed_only) {
        $sql .= " AND p.post_status = 'wc-completed'";
    }

    return (float) $wpdb->get_var($wpdb->prepare($sql, $args));
}

function woocommerce_sales_evaluation_get_referral_stats($start_date, $end_date, $option = 'google')
{
    if (!function_exists('wc_get_order')) {
        return array('error' => 'WooCommerce ไม่ถูกติดตั้งหรือไม่พร้อมใช้งาน');
    }

    if (!class_exists('DateTime')) {
        return array('error' => 'ไม่พบคลาส DateTime ในระบบ');
    }

    $start = DateTime::createFromFormat('Y-m-d', $start_date);
    $end = DateTime::createFromFormat('Y-m-d', $end_date);

    if (!$start || !$end) {
        return array('error' => 'รูปแบบวันที่ไม่ถูกต้อง โปรดใช้ YYYY-MM-DD');
    }

    $config = woocommerce_sales_evaluation_get_referral_source_config($option);
    $total_orders = woocommerce_sales_evaluation_get_total_order_count($start_date, $end_date);
    $referral_order_ids = woocommerce_sales_evaluation_get_referral_order_ids($start_date, $end_date, $option);
    $referral_orders_count = count($referral_order_ids);
    $referral_total_amount = woocommerce_sales_evaluation_get_referral_totals($referral_order_ids, false);
    $referral_completed_total = woocommerce_sales_evaluation_get_referral_totals($referral_order_ids, true);
    $percentage = $total_orders > 0 ? round(($referral_orders_count / $total_orders) * 100, 2) : 0;

    return array(
        'total_orders' => $total_orders,
        'orders' => $referral_orders_count,
        'data_total' => $referral_total_amount,
        'data_completed_total' => $referral_completed_total,
        'percentage' => $percentage,
        'source_title' => $config['title'],
        'source_label' => $config['label'],
        'stats_label' => $config['stats_label'],
    );
}

function woocommerce_sales_evaluation_get_referral_value($order, $option = 'google')
{
    global $wpdb;

    if (!is_object($order) || !method_exists($order, 'get_id')) {
        return '';
    }

    $config = woocommerce_sales_evaluation_get_referral_source_config($option);
    $order_id = $order->get_id();
    $like_values = array();

    foreach ($config['keywords'] as $keyword) {
        $like_values[] = '%' . $wpdb->esc_like($keyword) . '%';
    }

    $like_condition = implode(' OR ', array_fill(0, count($like_values), 'LOWER(meta_value) LIKE %s'));
    $sql = "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND TRIM(COALESCE(meta_value, '')) != '' AND ($like_condition) ORDER BY meta_id ASC LIMIT 1";

    $query = $wpdb->prepare($sql, array_merge(array($order_id), $like_values));
    $value = $wpdb->get_var($query);
    return is_string($value) ? trim($value) : '';
}

function woocommerce_sales_evaluation_get_referral_orders($start_date, $end_date, $option = 'google')
{
    if (!function_exists('wc_get_order')) {
        return array();
    }

    $order_ids = woocommerce_sales_evaluation_get_referral_order_ids($start_date, $end_date, $option);
    $orders = array();

    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $referral = woocommerce_sales_evaluation_get_referral_value($order, $option);
            if ($referral !== '') {
                $orders[] = $order;
            }
        }
    }

    return $orders;
}

function woocommerce_sales_evaluation_page()
{
    ?>
    <style>
        .leftside {
            width: 350px;
            background: #f8f8f8;
            height: max-content;
        }
        .leftside h1 {
            background: #009FE3;
            color: #fff;
            font-size: 16px;
            padding: 10px 20px;
            margin: 0;
        }
        .leftside a {
            padding: 10px 20px;
            font-size: 14px;
            background: #f8f8f8;
            color: #000;
            transition: .2s ease-in-out;
            display: block;
            width: 100%;
            text-decoration: none;
        }
        .leftside a.active {
            background: #fff;
        }
        .leftside a:hover {
            background: #fff;
            cursor: pointer;
        }
        .container {
            width: 1200px;
            background: #fff; 
        }
        .container h1 {
            background: #555;
            color: #fff;
            font-size: 16px;
            padding: 10px 20px;
            margin: 0;
        }
        .container p {
            padding: 0;
        }
        .white-label-zone {
            width: calc(100% + 20px);
            height: auto;
            background: #fff;
            display: flex;
            margin: 0 0 0 -20px;
        }
        .white-label-zone h1,p {
            padding: 0 20px;
        }
    </style>
    <div class="white-label-zone no-print">
        <span style="padding: 40px 10px 40px 40px;float: left;font-size: 60px;">🛍️</span>
        <div style="padding: 20px 0;">
            <h1>Sales Evaluation</h1>
            <p>ปลั้กอินช่วยวิเคราะห์การขายและที่มาของลูกค้า โดยแบ่งเป็นสัดส่วน
                <br>
                <strong>Github Repository:</strong> <a href="https://github.com/sunny420x/woocommerce-sales-evaluation" target="_blank">https://github.com/sunny420x/woocommerce-sales-evaluation</a>
            </p>
        </div>
    </div>
    <div class="wrap">
        <div style="display: flex;">
            <div class="leftside no-print">
                <h1>Sales Evaluation</h1>
                <a href="/wp-admin/admin.php?page=woocommerce-sales-evaluation" <?php if(!isset($_GET['option'])) { echo "class='active'"; } ?>>📊 Dashboard</a>
                <h1>Search Engine & LLM</h1>
                <a href="/wp-admin/admin.php?page=woocommerce-sales-evaluation&option=google" <?php if(isset($_GET['option']) && $_GET['option'] == "google") { echo "class='active'"; } ?>>📕 Google</a>
                <a href="/wp-admin/admin.php?page=woocommerce-sales-evaluation&option=bing" <?php if(isset($_GET['option']) && $_GET['option'] == "bing") { echo "class='active'"; } ?>>📘 Bing</a>
                <a href="/wp-admin/admin.php?page=woocommerce-sales-evaluation&option=chatgpt" <?php if(isset($_GET['option']) && $_GET['option'] == "chatgpt") { echo "class='active'"; } ?>>🤖 ChatGPT</a>
                <h1>Social Media</h1>
                <a href="/wp-admin/admin.php?page=woocommerce-sales-evaluation&option=line" <?php if(isset($_GET['option']) && $_GET['option'] == "line") { echo "class='active'"; } ?>>📗 LINE</a>
                <a href="/wp-admin/admin.php?page=woocommerce-sales-evaluation&option=facebook" <?php if(isset($_GET['option']) && $_GET['option'] == "facebook") { echo "class='active'"; } ?>>📘 Facebook</a>
            </div>
            <div class="container">
                <?php
                $option = isset($_GET['option']) ? sanitize_text_field($_GET['option']) : null;
                if (in_array($option, array('google', 'chatgpt', 'bing', 'line', 'facebook'), true)) {
                    $source_config = woocommerce_sales_evaluation_get_referral_source_config($option);
                    $start_date = woocommerce_sales_evaluation_sanitize_date_param('start_date', date('Y-m-01'));
                    $end_date = woocommerce_sales_evaluation_sanitize_date_param('end_date', date('Y-m-t'));
                    if (function_exists('wc_get_orders')) {
                        $stats = woocommerce_sales_evaluation_get_referral_stats($start_date, $end_date, $option);
                    } else {
                        $stats = array('error' => 'WooCommerce ไม่ถูกติดตั้งหรือไม่พร้อมใช้งาน');
                    }
                ?>
                <h1><?php echo esc_html($source_config['title']); ?></h1>
                <div style="padding: 0 25px 25px 25px;">
                    <p><strong>ออกรายงานเมื่อวันที่:</strong> <?=date("d/m/Y")?></p>
                    <form method="get" action="">
                        <input type="hidden" name="page" value="woocommerce-sales-evaluation" />
                        <input type="hidden" name="option" value="<?php echo esc_attr($option); ?>" />
                        <div style="display: grid; grid-template-columns: 1fr 1fr 100px 100px; gap: 10px;">
                            <div>
                                <label for="start_date">วันที่เริ่มต้น: </label><br>
                                <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>" style="width: 100%;" />
                            </div>
                            <div>
                                <label for="end_date">วันที่สิ้นสุด: </label><br>
                                <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>" style="width: 100%;" />
                            </div>
                            <div>
                                <button type="submit" class="button button-outline-primary no-print" style="width: 100%; margin-top: 20px;">กรอง</button>
                            </div>
                            <div>
                                <a onclick="window.print();" class="button button-outline-primary no-print" style="width: 100%; margin-top: 20px;">ออกรายงาน</a>
                            </div>
                        </div>
                    </form>

                    <?php if (isset($stats['error'])) : ?>
                        <div style="margin-top:24px;padding:16px;background:#ffe5e5;border:1px solid #ffcccc;color:#900;">
                            <?php echo esc_html($stats['error']); ?>
                        </div>
                    <?php else : ?>
                        <div class="stat_card_container">
                            <div class="stat_card" style="border: 1.5px solid #5E244E; color: #5E244E;">
                                <strong>🗃️ คำสั่งซื้อทั้งหมด</strong><br>
                                <span><?php echo esc_html($stats['total_orders']); ?> รายการ</span>
                            </div>
                            <div class="stat_card" style="border: 1.5px solid #AA1C41; color: #AA1C41;">
                                <strong>🗂️ <?php echo esc_html($stats['stats_label']); ?></strong><br>
                                <span><?php echo esc_html($stats['orders']); ?> รายการ (<?php echo esc_html($stats['percentage']); ?>%)</span>
                            </div>
                            <div class="stat_card" style="border: 1.5px solid #4274D9; color: #4274D9;">
                                <strong>💰 ยอดจาก <?php echo esc_html($stats['source_label']); ?></strong><br>
                                <span><?php echo function_exists('wc_price') ? wc_price($stats['data_total']) : number_format($stats['data_total'], 2); ?></span>
                            </div>
                            <div class="stat_card" style="background: #293681; color: #fff;">
                                <strong>✅ ปิดการขายได้</strong><br>
                                <span>
                                    <?php echo function_exists('wc_price') ? wc_price($stats['data_completed_total']) : number_format($stats['data_completed_total'], 2); ?>
                                    (<?php echo $stats['data_total'] > 0 ? number_format(($stats['data_completed_total'] / $stats['data_total']) * 100, 2) : '0.00'; ?>%)
                                </span>
                            </div>
                        </div>
                        <?php
                        $orders = woocommerce_sales_evaluation_get_referral_orders($start_date, $end_date, $option);
                        if (!empty($orders)) :
                        ?>
                        <div style="margin-top:24px; overflow:auto;">
                            <table class="wp-list-table widefat fixed striped" style="width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th>สั่งซื้อเมื่อ</th>
                                        <th style="width: 80px;">คำสั่งซื้อ</th>
                                        <th style="width: 400px;">รายการสินค้า</th>
                                        <th>สถานะ</th>
                                        <th style="width: 80px;">ยอดทั้งหมด</th>
                                        <th>Referral</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order) : ?>
                                        <tr>
                                            <td>
                                                <?php echo esc_html($order->get_date_created() ? $order->get_date_created()->date_i18n('Y-m-d H:i') : 'N/A'); ?>
                                            </td>
                                            <td><a href="<?php echo esc_url(admin_url('post.php?post=' . intval($order->get_id()) . '&action=edit')); ?>" target="_blank"><?php echo esc_html($order->get_order_number()); ?></a></td>
                                            <td>
                                                <?php echo implode( ', ', array_map( fn( $item ) => $item->get_name(), $order->get_items() ) ) ?>
                                            </td>
                                            <td>
                                                <span class="badge
                                                <?php if($order->get_status() == "cancelled" || $order->get_status() == "failed" || $order->get_status() == "refunded") { echo "danger"; } ?> 
                                                <?php if($order->get_status() == "processing") { echo "primary"; } ?> 
                                                <?php if($order->get_status() == "completed") { echo "success"; } ?> 
                                                <?php if($order->get_status() == "on-hold") { echo "pending"; } ?>
                                                ">
                                                <?= esc_html(wc_get_order_status_name($order->get_status())); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo function_exists('wc_price') ? wc_price($order->get_total()) : number_format($order->get_total(), 2); ?>
                                            </td>
                                            <td>
                                                <?php echo esc_html(woocommerce_sales_evaluation_get_referral_value($order, $option)); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php } else { ?>
                <h1>Dashboard</h1>
                <div style="padding: 0 25px 25px 25px;">
                    <?php
                    $start_date = woocommerce_sales_evaluation_sanitize_date_param('start_date', date('Y-m-01'));
                    $end_date = woocommerce_sales_evaluation_sanitize_date_param('end_date', date('Y-m-t'));
                    if (function_exists('wc_get_orders')) {
                        $facebook_stats = woocommerce_sales_evaluation_get_referral_stats($start_date, $end_date, 'facebook');
                        $line_stats = woocommerce_sales_evaluation_get_referral_stats($start_date, $end_date, 'line');
                        $google_stats = woocommerce_sales_evaluation_get_referral_stats($start_date, $end_date, 'google');
                    } else {
                        die("WooCommerce ไม่พร้อมใช้งาน");
                    }
                    ?>
                    <h2>📊 ยอดขายจาก Social Media เดือนนี้</h2>
                    <div class="stat_card_container">
                        <div class="stat_card" style="background: #293681; color: #fff;">
                            <strong>ปิดการขาย Google ได้</strong><br>
                            <span>
                                <?php echo function_exists('wc_price') ? wc_price($google_stats['data_completed_total']) : number_format($google_stats['data_completed_total'], 2); ?>
                                (<?php echo $google_stats['data_total'] > 0 ? number_format(($google_stats['data_completed_total'] / $google_stats['data_total']) * 100, 2) : '0.00'; ?>%)
                            </span>
                        </div>
                        <div class="stat_card" style="background: #293681; color: #fff;">
                            <strong>ปิดการขาย LINE ได้</strong><br>
                            <span>
                                <?php echo function_exists('wc_price') ? wc_price($line_stats['data_completed_total']) : number_format($line_stats['data_completed_total'], 2); ?>
                                (<?php echo $line_stats['data_total'] > 0 ? number_format(($line_stats['data_completed_total'] / $line_stats['data_total']) * 100, 2) : '0.00'; ?>%)
                            </span>
                        </div>
                        <div class="stat_card" style="background: #293681; color: #fff;">
                            <strong>ปิดการขาย Facebook ได้</strong><br>
                            <span>
                                <?php echo function_exists('wc_price') ? wc_price($facebook_stats['data_completed_total']) : number_format($facebook_stats['data_completed_total'], 2); ?>
                                (<?php echo $facebook_stats['data_total'] > 0 ? number_format(($facebook_stats['data_completed_total'] / $facebook_stats['data_total']) * 100, 2) : '0.00'; ?>%)
                            </span>
                        </div>
                    </div>
                    <h2>ปลั้กอินนี้คืออะไร ?</h2>
                    <p>
                        ปลั้กอิน Sales Evaluation ถูกออกแบบขึ้นมาเพื่อประเมินสัดส่วนคำสั่งซื้อที่มาจาก Google ออกมาจากคำสั่งซื้อทั้งหมด โดยสามารถช่วยประเมินประสิทธิภาพของการใช้ Google Ads และการทำบทความสำหรับ SEO ได้
                        รวมถึงสามารถประเมินสินค้ายอดนิยมจากคำสั่งซื้อที่มาจาก Google ได้
                    </p>
                    <h2>วิธีการติดตั้ง</h2>
                    <p>
                        สามารถติดตั้งปลั้กอินนี้ได้โดยการดาวน์โหลดไฟล์นี้จาก Github หน้านี้ และอัพโหลดลงในหน้า /wp-admin/plugin-install.php หลังจากอัพโหลด 
                        และเปิดใช้งาน (Activate) ระบบจะทำการสร้างตารางและคอลัมน์ใหม่จากตารางเดิมโดยอัตโนมัติ
                    </p>
                </div>
                <?php } ?>
            </div>
            <style>
                .stat_card_container {
                    display: grid;
                    grid-template-columns: 1fr 1fr 1fr 1fr;
                    gap: 10px;
                    padding-top: 20px;
                }
                .stat_card {
                    padding: 20px;
                    line-height: 30px;
                    height: max-content;
                }
                .stat_card strong {
                    font-size: 20px;
                    font-weight: 600;
                    line-height: 25px;
                    margin-bottom: 20px;
                }
                .stat_card span {
                    font-size: 18px;
                    font-weight: 500;
                }
                @media screen and (max-width: 1024px) {
                    .stat_card_container {
                        display: block !important;
                    }
                    .stat_card {
                        margin-bottom: 20px;   
                    }
                }
                @media print {
                    .no-print {
                        display: none !important;
                    }
                    table th {
                        width: unset !important;
                    }
                    h1 {
                        color: #000 !important;
                        font-size: 22px !important;
                        font-weight: 600 !important;
                    }
                    p {
                        border-bottom: 1px solid #555;
                        padding-bottom: 10px !important;
                    }
                    .stat_card_container {
                        grid-template-columns: 1fr 1fr !important;
                        border: 1px solid #333;
                        margin-top: 20px;
                        padding-top: 10px;
                    }
                    .stat_card {
                        border: 0 !important;
                    }
                }
            </style>
        </div>
    </div>
<?php
}