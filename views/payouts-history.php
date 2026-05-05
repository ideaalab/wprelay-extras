<?php
defined('ABSPATH') or exit;
use WPRelayExtras\Helper;
use RelayWp\Affiliate\App\Helpers\Functions;
?>
<table class="wp-list-table widefat fixed striped" style="margin-top:15px;">
    <thead>
        <tr>
            <th>#</th>
            <th><?php esc_html_e('Fecha', 'wprelay-extras'); ?></th>
            <th><?php esc_html_e('Afiliado', 'wprelay-extras'); ?></th>
            <th><?php esc_html_e('Importe', 'wprelay-extras'); ?></th>
            <th><?php esc_html_e('Estado', 'wprelay-extras'); ?></th>
            <th><?php esc_html_e('Nota interna', 'wprelay-extras'); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
        <tr><td colspan="6"><?php esc_html_e('Aún no se ha registrado ningún pago manual.', 'wprelay-extras'); ?></td></tr>
    <?php else: foreach ($rows as $r): ?>
        <tr>
            <td><?php echo esc_html($r->id); ?></td>
            <td><?php echo esc_html(Functions::utcToWPTime($r->paid_at)); ?></td>
            <td><?php echo esc_html(trim($r->first_name . ' ' . $r->last_name)); ?><br><small><?php echo esc_html($r->email); ?></small></td>
            <td><strong><?php echo esc_html(Helper::formatMoney($r->amount, $r->currency)); ?></strong></td>
            <td><span class="wpre-badge wpre-<?php echo esc_attr($r->status); ?>"><?php echo esc_html(ucfirst($r->status)); ?></span></td>
            <td><?php echo esc_html($r->admin_note ?: '—'); ?></td>
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
