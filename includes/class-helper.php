<?php

namespace WPRelayExtras;

defined('ABSPATH') or exit;

use RelayWp\Affiliate\App\Helpers\Functions;
use RelayWp\Affiliate\Core\Models\Affiliate;
use RelayWp\Affiliate\Core\Models\Member;
use RelayWp\Affiliate\Core\Models\Transaction;

class Helper
{
    const CAPABILITY = 'manage_woocommerce';

    public static function checkCap()
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'wprelay-extras'));
        }
    }

    public static function checkNonce($action)
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], $action)) {
            wp_die(__('Verificación de seguridad fallida.', 'wprelay-extras'));
        }
    }

    public static function notice($message, $type = 'success')
    {
        $key = 'wprelay_extras_notice';
        set_transient($key . '_' . get_current_user_id(), [
            'message' => $message,
            'type' => $type,
        ], 30);
    }

    public static function renderNotices()
    {
        $key = 'wprelay_extras_notice_' . get_current_user_id();
        $notice = get_transient($key);
        if ($notice) {
            delete_transient($key);
            $class = $notice['type'] === 'error' ? 'notice-error' : 'notice-success';
            printf(
                '<div class="notice %s is-dismissible"><p>%s</p></div>',
                esc_attr($class),
                esc_html($notice['message'])
            );
        }
    }

    public static function getAffiliateBalance($affiliateId, $currency = null)
    {
        $currency = $currency ?: Functions::getSelectedCurrency();

        $row = Transaction::query()
            ->select("COALESCE(SUM(CASE WHEN type = 'credit' THEN amount END), 0) - COALESCE(SUM(CASE WHEN type = 'debit' THEN amount END), 0) as balance")
            ->where("affiliate_id = %d", [$affiliateId])
            ->where("currency = %s", [$currency])
            ->first();

        return $row ? (float)$row->balance : 0.0;
    }

    public static function getAffiliateLabel($affiliateId)
    {
        $affiliate = Affiliate::query()->find($affiliateId);
        if (!$affiliate) return '#' . $affiliateId;

        $member = Member::query()->find($affiliate->member_id);
        if (!$member) return '#' . $affiliateId;

        $name = trim($member->first_name . ' ' . $member->last_name);
        return $name ? sprintf('%s (%s)', $name, $member->email) : $member->email;
    }

    public static function getAllAffiliates()
    {
        $affiliateTable = Affiliate::getTableName();
        $memberTable = Member::getTableName();

        return Affiliate::query()
            ->select("{$affiliateTable}.id, {$memberTable}.first_name, {$memberTable}.last_name, {$memberTable}.email")
            ->leftJoin("{$memberTable}", "{$memberTable}.id = {$affiliateTable}.member_id")
            ->orderBy("{$memberTable}.first_name", 'ASC')
            ->get();
    }

    public static function adminUrl($tab = '', $args = [])
    {
        $base = admin_url('admin.php?page=' . WPRELAY_EXTRAS_SLUG);
        if ($tab) {
            $base .= '&tab=' . urlencode($tab);
        }
        if (!empty($args)) {
            $base = add_query_arg($args, $base);
        }
        return $base;
    }

    public static function formatMoney($amount, $currency = null)
    {
        $currency = $currency ?: Functions::getSelectedCurrency();
        if (function_exists('wc_price')) {
            return wp_strip_all_tags(wc_price($amount, ['currency' => $currency]));
        }
        return number_format((float)$amount, 2) . ' ' . $currency;
    }
}
