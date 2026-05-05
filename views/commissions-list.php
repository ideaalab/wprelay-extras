<?php
defined('ABSPATH') or exit;
use WPRelayExtras\Helper;
use RelayWp\Affiliate\App\Helpers\Functions;
use RelayWp\Affiliate\Core\Models\CommissionEarning;
?>
<form method="get" style="margin:15px 0;">
    <input type="hidden" name="page" value="<?php echo esc_attr(WPRELAY_EXTRAS_SLUG); ?>">
    <input type="hidden" name="tab" value="commissions">
    <input type="search" name="s" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php esc_attr_e('Buscar afiliado...', 'wprelay-extras'); ?>">
    <select name="status">
        <option value=""><?php esc_html_e('Todos los estados', 'wprelay-extras'); ?></option>
        <?php foreach ([CommissionEarning::PENDING, CommissionEarning::APPROVED, CommissionEarning::REJECTED] as $st): ?>
            <option value="<?php echo esc_attr($st); ?>" <?php selected($filters['status'], $st); ?>><?php echo esc_html(ucfirst($st)); ?></option>
        <?php endforeach; ?>
    </select>
    <button class="button"><?php esc_html_e('Filtrar', 'wprelay-extras'); ?></button>
    <a href="<?php echo esc_url(Helper::adminUrl('commission-create')); ?>" class="button button-primary" style="float:right;"><?php esc_html_e('+ Nueva comisión', 'wprelay-extras'); ?></a>
</form>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th>#</th>
            <th><?php esc_html_e('Afiliado', 'wprelay-extras'); ?></th>
            <th><?php esc_html_e('Programa', 'wprelay-extras'); ?></th>
            <th><?php esc_html_e('Importe', 'wprelay-extras'); ?></th>
            <th><?php esc_html_e('Estado', 'wprelay-extras'); ?></th>
            <th><?php esc_html_e('Pedido', 'wprelay-extras'); ?></th>
            <th><?php esc_html_e('Motivo', 'wprelay-extras'); ?></th>
            <th><?php esc_html_e('Fecha', 'wprelay-extras'); ?></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
        <tr><td colspan="9"><?php esc_html_e('Sin resultados.', 'wprelay-extras'); ?></td></tr>
    <?php else: foreach ($rows as $r): ?>
        <tr>
            <td><?php echo esc_html($r->id); ?></td>
            <td><?php echo esc_html(trim($r->first_name . ' ' . $r->last_name)); ?><br><small><?php echo esc_html($r->email); ?></small></td>
            <td><?php echo esc_html($r->program_name ?: '—'); ?></td>
            <td><strong><?php echo esc_html(Helper::formatMoney($r->commission_amount, $r->commission_currency)); ?></strong></td>
            <td><span class="wpre-badge wpre-<?php echo esc_attr($r->status); ?>"><?php echo esc_html(ucfirst($r->status)); ?></span></td>
            <td><?php echo $r->order_id ? '#' . esc_html($r->order_id) : '<em>—</em>'; ?></td>
            <td><small><?php echo esc_html(wp_trim_words($r->reason, 10)); ?></small></td>
            <td><small><?php echo esc_html(Functions::utcToWPTime($r->created_at)); ?></small></td>
            <td><a href="<?php echo esc_url(Helper::adminUrl('commission-edit', ['id' => $r->id])); ?>" class="button button-small"><?php esc_html_e('Editar', 'wprelay-extras'); ?></a></td>
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
