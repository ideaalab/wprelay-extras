<?php
defined('ABSPATH') or exit;
use WPRelayExtras\Helper;
?>
<form method="get" style="margin:15px 0;">
    <input type="hidden" name="page" value="<?php echo esc_attr(WPRELAY_EXTRAS_SLUG); ?>">
    <input type="hidden" name="tab" value="payouts">
    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Buscar afiliado...', 'wprelay-extras'); ?>">
    <button class="button"><?php esc_html_e('Buscar', 'wprelay-extras'); ?></button>
</form>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th><?php esc_html_e('Afiliado', 'wprelay-extras'); ?></th>
            <th><?php esc_html_e('Email pago', 'wprelay-extras'); ?></th>
            <th><?php esc_html_e('Saldo pendiente', 'wprelay-extras'); ?></th>
            <th style="width:280px;"><?php esc_html_e('Registrar pago manual', 'wprelay-extras'); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
        <tr><td colspan="4"><?php esc_html_e('No hay afiliados con saldo pendiente.', 'wprelay-extras'); ?></td></tr>
    <?php else: foreach ($rows as $r): ?>
        <tr>
            <td>
                <strong><?php echo esc_html(trim($r->first_name . ' ' . $r->last_name)); ?></strong><br>
                <small><?php echo esc_html($r->email); ?></small>
            </td>
            <td><?php echo esc_html($r->payment_email ?: '—'); ?></td>
            <td><strong><?php echo esc_html(Helper::formatMoney($r->balance, $currency)); ?></strong></td>
            <td>
                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . WPRELAY_EXTRAS_SLUG)); ?>" class="wpre-inline-payout">
                    <?php wp_nonce_field('wprelay_extras_payout'); ?>
                    <input type="hidden" name="wprelay_extras_action" value="record_manual_payout">
                    <input type="hidden" name="affiliate_id" value="<?php echo esc_attr($r->affiliate_id); ?>">
                    <input type="hidden" name="currency" value="<?php echo esc_attr($currency); ?>">
                    <input type="number" step="0.01" min="0.01" max="<?php echo esc_attr($r->balance); ?>" name="amount" value="<?php echo esc_attr(number_format((float)$r->balance, 2, '.', '')); ?>" required style="width:90px">
                    <label style="display:block;margin:4px 0;font-size:12px;">
                        <input type="checkbox" name="send_email" value="1" checked> <?php esc_html_e('Notificar por email', 'wprelay-extras'); ?>
                    </label>
                    <textarea name="admin_note" rows="1" placeholder="<?php esc_attr_e('Nota interna', 'wprelay-extras'); ?>" style="width:100%;font-size:11px;"></textarea>
                    <textarea name="affiliate_note" rows="1" placeholder="<?php esc_attr_e('Nota visible al afiliado', 'wprelay-extras'); ?>" style="width:100%;font-size:11px;"></textarea>
                    <button class="button button-primary" onclick="return confirm('<?php echo esc_js(__('¿Confirmar pago?', 'wprelay-extras')); ?>');"><?php esc_html_e('Marcar como pagado', 'wprelay-extras'); ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<?php if ($total > 30): ?>
    <div class="tablenav"><div class="tablenav-pages">
        <?php
        echo paginate_links([
            'base'    => add_query_arg('paged', '%#%'),
            'format'  => '',
            'current' => $page,
            'total'   => ceil($total / 30),
        ]);
        ?>
    </div></div>
<?php endif; ?>
