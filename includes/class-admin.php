<?php

namespace WPRelayExtras;

defined('ABSPATH') or exit;

use RelayWp\Affiliate\App\Helpers\Functions;
use RelayWp\Affiliate\Core\Models\Program;

class Admin
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'registerMenu']);
        add_action('admin_init', [__CLASS__, 'handlePost']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
    }

    public static function registerMenu()
    {
        add_menu_page(
            __('WPRelay Extras', 'wprelay-extras'),
            __('WPRelay Extras', 'wprelay-extras'),
            Helper::CAPABILITY,
            WPRELAY_EXTRAS_SLUG,
            [__CLASS__, 'render'],
            'dashicons-money-alt',
            58
        );
    }

    public static function enqueue($hook)
    {
        if (strpos($hook, WPRELAY_EXTRAS_SLUG) === false) return;
        wp_enqueue_style('wprelay-extras', WPRELAY_EXTRAS_URL . 'assets/admin.css', [], WPRELAY_EXTRAS_VERSION);
    }

    public static function render()
    {
        Helper::checkCap();
        $tab = $_GET['tab'] ?? 'payouts';
        $valid = ['payouts', 'payout-history', 'commissions', 'commission-create', 'commission-edit'];
        if (!in_array($tab, $valid, true)) $tab = 'payouts';

        echo '<div class="wrap wprelay-extras-wrap">';
        echo '<h1>' . esc_html__('WPRelay Extras', 'wprelay-extras') . '</h1>';
        Helper::renderNotices();

        // Tabs
        $tabs = [
            'payouts'           => __('Pagos pendientes', 'wprelay-extras'),
            'payout-history'    => __('Historial pagos manuales', 'wprelay-extras'),
            'commissions'       => __('Comisiones', 'wprelay-extras'),
            'commission-create' => __('Crear comisión', 'wprelay-extras'),
        ];
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $active = $tab === $key || ($tab === 'commission-edit' && $key === 'commissions') ? ' nav-tab-active' : '';
            printf(
                '<a href="%s" class="nav-tab%s">%s</a>',
                esc_url(Helper::adminUrl($key)),
                esc_attr($active),
                esc_html($label)
            );
        }
        echo '</h2>';

        switch ($tab) {
            case 'payouts':           self::viewPayoutsPending(); break;
            case 'payout-history':    self::viewPayoutHistory(); break;
            case 'commissions':       self::viewCommissions(); break;
            case 'commission-create': self::viewCommissionCreate(); break;
            case 'commission-edit':   self::viewCommissionEdit(); break;
        }

        echo '</div>';
    }

    // ============= Vistas =============

    private static function viewPayoutsPending()
    {
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $page = max(1, (int)($_GET['paged'] ?? 1));
        [$rows, $total, $currency] = Payouts::listPending($search, $page, 30);
        include WPRELAY_EXTRAS_PATH . 'views/payouts-pending.php';
    }

    private static function viewPayoutHistory()
    {
        $page = max(1, (int)($_GET['paged'] ?? 1));
        [$rows, $total] = Payouts::listHistory($page, 30);
        include WPRELAY_EXTRAS_PATH . 'views/payouts-history.php';
    }

    private static function viewCommissions()
    {
        $filters = [
            'status'       => $_GET['status']       ?? '',
            'affiliate_id' => $_GET['affiliate_id'] ?? '',
            'search'       => $_GET['s']            ?? '',
        ];
        $page = max(1, (int)($_GET['paged'] ?? 1));
        [$rows, $total] = Commissions::listCommissions($filters, $page, 30);
        include WPRELAY_EXTRAS_PATH . 'views/commissions-list.php';
    }

    private static function viewCommissionCreate()
    {
        $affiliates = Helper::getAllAffiliates();
        $programs = Program::query()->select('*')->orderBy('title', 'ASC')->get();
        $defaultCurrency = Functions::getSelectedCurrency();
        include WPRELAY_EXTRAS_PATH . 'views/commission-create.php';
    }

    private static function viewCommissionEdit()
    {
        $id = (int)($_GET['id'] ?? 0);
        $commission = Commissions::getById($id);
        if (!$commission) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Comisión no encontrada', 'wprelay-extras') . '</p></div>';
            return;
        }
        include WPRELAY_EXTRAS_PATH . 'views/commission-edit.php';
    }

    // ============= POST handlers =============

    public static function handlePost()
    {
        if (!isset($_POST['wprelay_extras_action'])) return;
        Helper::checkCap();

        $action = sanitize_text_field($_POST['wprelay_extras_action']);

        switch ($action) {
            case 'record_manual_payout':
                self::handleRecordPayout();
                break;
            case 'create_commission':
                self::handleCreateCommission();
                break;
            case 'update_commission':
                self::handleUpdateCommission();
                break;
        }
    }

    private static function handleRecordPayout()
    {
        Helper::checkNonce('wprelay_extras_payout');
        [$ok, $id, $msg] = Payouts::recordManual([
            'affiliate_id'   => (int)($_POST['affiliate_id'] ?? 0),
            'amount'         => (float)str_replace(',', '.', $_POST['amount'] ?? 0),
            'admin_note'     => sanitize_textarea_field($_POST['admin_note'] ?? ''),
            'affiliate_note' => sanitize_textarea_field($_POST['affiliate_note'] ?? ''),
            'currency'       => sanitize_text_field($_POST['currency'] ?? ''),
            'send_email'     => !empty($_POST['send_email']),
        ]);
        Helper::notice($msg, $ok ? 'success' : 'error');
        wp_safe_redirect(Helper::adminUrl($ok ? 'payout-history' : 'payouts'));
        exit;
    }

    private static function handleCreateCommission()
    {
        Helper::checkNonce('wprelay_extras_commission_create');
        [$ok, $id, $msg] = Commissions::create([
            'affiliate_id'       => (int)($_POST['affiliate_id'] ?? 0),
            'program_id'         => (int)($_POST['program_id'] ?? 0),
            'commission_amount'  => (float)str_replace(',', '.', $_POST['commission_amount'] ?? 0),
            'currency'           => sanitize_text_field($_POST['currency'] ?? ''),
            'wc_order_id'        => (int)($_POST['wc_order_id'] ?? 0),
            'reason'             => sanitize_textarea_field($_POST['reason'] ?? ''),
            'status'             => sanitize_text_field($_POST['status'] ?? 'pending'),
        ]);
        Helper::notice($msg, $ok ? 'success' : 'error');
        wp_safe_redirect(Helper::adminUrl($ok ? 'commissions' : 'commission-create'));
        exit;
    }

    private static function handleUpdateCommission()
    {
        Helper::checkNonce('wprelay_extras_commission_update');
        $id = (int)($_POST['commission_id'] ?? 0);
        [$ok, $msg] = Commissions::update($id, [
            'commission_amount' => (float)str_replace(',', '.', $_POST['commission_amount'] ?? 0),
            'status'            => sanitize_text_field($_POST['status'] ?? ''),
            'reason'            => sanitize_textarea_field($_POST['reason'] ?? ''),
        ]);
        Helper::notice($msg, $ok ? 'success' : 'error');
        wp_safe_redirect(Helper::adminUrl($ok ? 'commissions' : 'commission-edit', ['id' => $id]));
        exit;
    }
}
