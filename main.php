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

function woocommerce_sales_evaluation_get_google_referral_order_ids($start_date, $end_date)
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
    $like = '%' . $wpdb->esc_like('google') . '%';

    $sql = "SELECT DISTINCT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
        WHERE p.post_type = 'shop_order'
          AND p.post_status IN ($placeholders)
          AND p.post_date BETWEEN %s AND %s
          AND pm.meta_key != ' '
          AND TRIM(COALESCE(pm.meta_value, '')) != ''
          AND (LOWER(pm.meta_key) LIKE %s OR LOWER(pm.meta_value) LIKE %s)";

    return $wpdb->get_col($wpdb->prepare($sql, array_merge($statuses, array($from, $to, $like, $like))));
}

function woocommerce_sales_evaluation_get_google_referral_totals($order_ids, $completed_only = false)
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

function woocommerce_sales_evaluation_get_google_referral_stats($start_date, $end_date)
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

    $total_orders = woocommerce_sales_evaluation_get_total_order_count($start_date, $end_date);
    $google_order_ids = woocommerce_sales_evaluation_get_google_referral_order_ids($start_date, $end_date);
    $google_orders_count = count($google_order_ids);
    $google_total_amount = woocommerce_sales_evaluation_get_google_referral_totals($google_order_ids, false);
    $google_completed_total = woocommerce_sales_evaluation_get_google_referral_totals($google_order_ids, true);
    $percentage = $total_orders > 0 ? round(($google_orders_count / $total_orders) * 100, 2) : 0;

    return array(
        'total_orders' => $total_orders,
        'google_orders' => $google_orders_count,
        'google_total' => $google_total_amount,
        'google_completed_total' => $google_completed_total,
        'percentage' => $percentage,
    );
}

function woocommerce_sales_evaluation_get_google_referral_value($order)
{
    global $wpdb;

    if (!is_object($order) || !method_exists($order, 'get_id')) {
        return '';
    }

    $order_id = $order->get_id();
    $like = '%' . $wpdb->esc_like('Google') . '%';

    $sql = $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND TRIM(COALESCE(meta_value, '')) != '' AND meta_value LIKE %s ORDER BY meta_id ASC LIMIT 1",
        $order_id,
        $like
    );

    $value = $wpdb->get_var($sql);
    return is_string($value) ? trim($value) : '';
}

function woocommerce_sales_evaluation_get_google_referral_orders($start_date, $end_date)
{
    if (!function_exists('wc_get_order')) {
        return array();
    }

    $order_ids = woocommerce_sales_evaluation_get_google_referral_order_ids($start_date, $end_date);
    $orders = array();

    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $referral = woocommerce_sales_evaluation_get_google_referral_value($order);
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
                <h1>Sale Evaluation</h1>
                <a href="/wp-admin/admin.php?page=woocommerce-sales-evaluation&option=google-ads" <?php if(isset($_GET['option']) && $_GET['option'] == "google-ads") { echo "class='active'"; } ?>>Google Ads</a>
            </div>
            <div class="container">
                <?php if(isset($_GET['option']) && $_GET['option'] == "google-ads") {
                    $start_date = woocommerce_sales_evaluation_sanitize_date_param('start_date', date('Y-m-01'));
                    $end_date = woocommerce_sales_evaluation_sanitize_date_param('end_date', date('Y-m-t'));
                    if (function_exists('wc_get_orders')) {
                        $stats = woocommerce_sales_evaluation_get_google_referral_stats($start_date, $end_date);
                    } else {
                        $stats = array('error' => 'WooCommerce ไม่ถูกติดตั้งหรือไม่พร้อมใช้งาน');
                    }
                ?>
                <h1>การขายจาก Google</h1>
                <div style="padding: 0 25px 25px 25px;">
                    <p><strong>ออกรายงานเมื่อวันที่:</strong> <?=date("d/m/Y")?></p>
                    <form method="get" action="">
                        <input type="hidden" name="page" value="woocommerce-sales-evaluation" />
                        <input type="hidden" name="option" value="google-ads" />
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
                                <strong>🗂️ คำสั่งซื้อจาก Google</strong><br>
                                <span><?php echo esc_html($stats['google_orders']); ?> รายการ (<?php echo esc_html($stats['percentage']); ?>%)</span>
                            </div>
                            <div class="stat_card" style="border: 1.5px solid #4274D9; color: #4274D9;">
                                <strong>💰 ยอดจาก Google</strong><br>
                                <span><?php echo function_exists('wc_price') ? wc_price($stats['google_total']) : number_format($stats['google_total'], 2); ?></span>
                            </div>
                            <div class="stat_card" style="border: 1.5px solid #293681; color: #293681;">
                                <strong>✅ ปิดการขายได้</strong><br>
                                <span>
                                    <?php echo function_exists('wc_price') ? wc_price($stats['google_completed_total']) : number_format($stats['google_completed_total'], 2); ?>
                                    (<?php echo $stats['google_total'] > 0 ? number_format(($stats['google_completed_total'] / $stats['google_total']) * 100, 2) : '0.00'; ?>%)
                                </span>
                            </div>
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
                                border-radius: 20px;
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
                        <?php
                        $google_orders = woocommerce_sales_evaluation_get_google_referral_orders($start_date, $end_date);
                        if (!empty($google_orders)) :
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
                                    <?php foreach ($google_orders as $order) : ?>
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
                                                <?php if($order->get_status() == "cancelled" || $order->get_status() == "failed") { echo "danger"; } ?> 
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
                                                <?php echo esc_html(woocommerce_sales_evaluation_get_google_referral_value($order)); ?>
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
                <h1>คู่มือการใช้งาน</h1>
                <div style="padding: 0 25px 25px 25px;">
                    <h2>วิธีการติดตั้ง</h2>
                    <p>
                        สามารถติดตั้งปลั้กอินนี้ได้โดยการดาวน์โหลดไฟล์นี้จาก Github หน้านี้ และอัพโหลดลงในหน้า /wp-admin/plugin-install.php หลังจากอัพโหลด 
                        และเปิดใช้งาน (Activate) ระบบจะทำการสร้างตารางและคอลัมน์ใหม่จากตารางเดิมโดยอัตโนมัติ
                    </p>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
<?php
}