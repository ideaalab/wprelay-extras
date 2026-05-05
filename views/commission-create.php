<?php
defined('ABSPATH') or exit;
use RelayWp\Affiliate\Core\Models\CommissionEarning;
?>
<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . WPRELAY_EXTRAS_SLUG)); ?>" style="max-width:640px;margin-top:20px;">
    <?php wp_nonce_field('wprelay_extras_commission_create'); ?>
    <input type="hidden" name="wprelay_extras_action" value="create_commission">

    <table class="form-table">
        <tr>
            <th><label for="affiliate_id"><?php esc_html_e('Afiliado', 'wprelay-extras'); ?> *</label></th>
            <td>
                <select name="affiliate_id" id="affiliate_id" required style="width:100%;">
                    <option value=""><?php esc_html_e('— Seleccionar —', 'wprelay-extras'); ?></option>
                    <?php foreach ($affiliates as $a):
                        $name = trim($a->first_name . ' ' . $a->last_name);
                        $label = $name ? "$name ({$a->email})" : $a->email;
                        ?>
                        <option value="<?php echo esc_attr($a->id); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="program_id"><?php esc_html_e('Programa', 'wprelay-extras'); ?> *</label></th>
            <td>
                <select name="program_id" id="program_id" required style="width:100%;">
                    <option value=""><?php esc_html_e('— Seleccionar —', 'wprelay-extras'); ?></option>
                    <?php foreach ($programs as $p): ?>
                        <option value="<?php echo esc_attr($p->id); ?>"><?php echo esc_html($p->title); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="commission_amount"><?php esc_html_e('Importe', 'wprelay-extras'); ?> *</label></th>
            <td>
                <input type="number" step="0.01" min="0.01" name="commission_amount" id="commission_amount" required>
                <input type="text" name="currency" value="<?php echo esc_attr($defaultCurrency); ?>" maxlength="3" style="width:60px;">
            </td>
        </tr>
        <tr>
            <th><label for="wc_order_id"><?php esc_html_e('Pedido WC (opcional)', 'wprelay-extras'); ?></label></th>
            <td>
                <input type="number" name="wc_order_id" id="wc_order_id" min="0" placeholder="<?php esc_attr_e('ID del pedido en WooCommerce', 'wprelay-extras'); ?>">
                <p class="description"><?php esc_html_e('Déjalo vacío si la comisión no está vinculada a un pedido.', 'wprelay-extras'); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="status"><?php esc_html_e('Estado inicial', 'wprelay-extras'); ?></label></th>
            <td>
                <select name="status" id="status">
                    <option value="<?php echo CommissionEarning::PENDING; ?>"><?php esc_html_e('Pendiente', 'wprelay-extras'); ?></option>
                    <option value="<?php echo CommissionEarning::APPROVED; ?>"><?php esc_html_e('Aprobada (suma al saldo inmediatamente)', 'wprelay-extras'); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="reason"><?php esc_html_e('Motivo / Nota', 'wprelay-extras'); ?></label></th>
            <td><textarea name="reason" id="reason" rows="3" style="width:100%;" placeholder="<?php esc_attr_e('Por qué creas esta comisión manual', 'wprelay-extras'); ?>"></textarea></td>
        </tr>
    </table>

    <p class="submit">
        <button class="button button-primary"><?php esc_html_e('Crear comisión', 'wprelay-extras'); ?></button>
    </p>
</form>
