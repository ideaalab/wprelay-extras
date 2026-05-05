<?php
defined('ABSPATH') or exit;
use WPRelayExtras\Helper;
use RelayWp\Affiliate\Core\Models\CommissionEarning;
?>
<h2><?php printf(esc_html__('Editar comisión #%d', 'wprelay-extras'), $commission->id); ?></h2>

<p>
    <strong><?php esc_html_e('Afiliado:', 'wprelay-extras'); ?></strong> <?php echo esc_html(Helper::getAffiliateLabel($commission->affiliate_id)); ?><br>
    <strong><?php esc_html_e('Pedido:', 'wprelay-extras'); ?></strong> <?php echo $commission->order_id ? '#' . esc_html($commission->order_id) : '<em>—</em>'; ?>
</p>

<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . WPRELAY_EXTRAS_SLUG)); ?>" style="max-width:640px;">
    <?php wp_nonce_field('wprelay_extras_commission_update'); ?>
    <input type="hidden" name="wprelay_extras_action" value="update_commission">
    <input type="hidden" name="commission_id" value="<?php echo esc_attr($commission->id); ?>">

    <table class="form-table">
        <tr>
            <th><label><?php esc_html_e('Importe', 'wprelay-extras'); ?></label></th>
            <td>
                <input type="number" step="0.01" min="0.01" name="commission_amount" value="<?php echo esc_attr($commission->commission_amount); ?>" required>
                <span><?php echo esc_html($commission->commission_currency); ?></span>
            </td>
        </tr>
        <tr>
            <th><label><?php esc_html_e('Estado', 'wprelay-extras'); ?></label></th>
            <td>
                <select name="status">
                    <?php foreach ([CommissionEarning::PENDING, CommissionEarning::APPROVED, CommissionEarning::REJECTED] as $st): ?>
                        <option value="<?php echo esc_attr($st); ?>" <?php selected($commission->status, $st); ?>><?php echo esc_html(ucfirst($st)); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Al cambiar el estado se ajusta el balance del afiliado y se envía email de notificación.', 'wprelay-extras'); ?></p>
            </td>
        </tr>
        <tr>
            <th><label><?php esc_html_e('Motivo', 'wprelay-extras'); ?></label></th>
            <td><textarea name="reason" rows="3" style="width:100%;"><?php echo esc_textarea($commission->reason); ?></textarea></td>
        </tr>
    </table>

    <p class="submit">
        <button class="button button-primary"><?php esc_html_e('Guardar cambios', 'wprelay-extras'); ?></button>
        <a href="<?php echo esc_url(Helper::adminUrl('commissions')); ?>" class="button"><?php esc_html_e('Cancelar', 'wprelay-extras'); ?></a>
    </p>
</form>

<div class="wpre-card" style="margin-top:30px;background:#fff8e5;padding:15px;border-left:4px solid #f0b849;">
    <strong><?php esc_html_e('¿Devolución de pedido?', 'wprelay-extras'); ?></strong>
    <p><?php esc_html_e('Si el pedido original fue devuelto, cambia el estado a "Rejected". Si la comisión ya estaba aprobada, se generará automáticamente un débito compensatorio para descontar del saldo del afiliado.', 'wprelay-extras'); ?></p>
</div>
