<?php

namespace WPRelayExtras;

defined('ABSPATH') or exit;

use RelayWp\Affiliate\App\Helpers\Functions;
use RelayWp\Affiliate\App\Helpers\WC;
use RelayWp\Affiliate\App\Services\Database;
use RelayWp\Affiliate\App\Services\Settings;
use RelayWp\Affiliate\Core\Models\Affiliate;
use RelayWp\Affiliate\Core\Models\Member;
use RelayWp\Affiliate\Core\Models\Payout;
use RelayWp\Affiliate\Core\Models\Transaction;

class Payouts
{
    /**
     * Registra un pago manual marcado como completado y notifica al afiliado.
     * Crea transacción DEBIT para reducir el balance pendiente.
     */
    public static function recordManual(array $data)
    {
        Database::beginTransaction();
        try {
            $affiliateId = (int)$data['affiliate_id'];
            $amount      = (float)$data['amount'];
            $adminNote   = $data['admin_note'] ?? '';
            $affNote     = $data['affiliate_note'] ?? '';
            $currency    = $data['currency'] ?: Functions::getSelectedCurrency();
            $sendEmail   = !empty($data['send_email']);

            $affiliate = Affiliate::query()->find($affiliateId);
            if (!$affiliate) {
                throw new \Exception(__('Afiliado no encontrado', 'wprelay-extras'));
            }
            if ($amount <= 0) {
                throw new \Exception(__('El importe debe ser mayor que 0', 'wprelay-extras'));
            }

            $balance = Helper::getAffiliateBalance($affiliateId, $currency);
            if ($amount > $balance + 0.001) {
                throw new \Exception(sprintf(
                    __('El importe (%s) supera el saldo pendiente del afiliado (%s)', 'wprelay-extras'),
                    Helper::formatMoney($amount, $currency),
                    Helper::formatMoney($balance, $currency)
                ));
            }

            // Crear payout marcado directamente como success (es un pago manual)
            Payout::query()->create([
                'amount'         => $amount,
                'payment_source' => 'manual',
                'affiliate_note' => $affNote,
                'admin_note'     => $adminNote,
                'currency'       => $currency,
                'paid_at'        => Functions::currentUTCTime(),
                'affiliate_id'   => $affiliateId,
                'status'         => 'success',
                'paid_by'        => get_current_user_id(),
                'created_at'     => Functions::currentUTCTime(),
                'updated_at'     => Functions::currentUTCTime(),
            ]);

            $payoutId = Payout::query()->lastInsertedId();

            // Transacción DEBIT para descontar del balance
            Transaction::query()->create([
                'affiliate_id'         => $affiliateId,
                'type'                 => Transaction::DEBIT,
                'currency'             => $currency,
                'amount'               => $amount,
                'system_note'          => "Manual Payout #{$payoutId}",
                'transactionable_id'   => $payoutId,
                'transactionable_type' => 'payout',
                'created_at'           => Functions::currentUTCTime(),
            ]);

            // Email al afiliado (reusando el hook del plugin)
            if ($sendEmail && Settings::get('email_settings.affiliate_emails.payment_processed')) {
                $member = Member::query()->find($affiliate->member_id);
                if ($member) {
                    do_action('rwpa_payment_processed_email', [
                        'first_name'              => $member->first_name,
                        'last_name'               => $member->last_name,
                        'email'                   => $member->email,
                        'referral_code'           => $affiliate->referral_code,
                        'referral_link'           => Affiliate::getReferralCodeURL($affiliate),
                        'amount'                  => $amount,
                        'currency'                => WC::getWcCurrencySymbol(),
                        'payment_source'          => 'Manual',
                        'coupon_code'             => null,
                        'payout_date'             => Functions::utcToWPTime(Functions::currentUTCTime()),
                        'payout_affiliate_notes'  => $affNote,
                    ]);
                }
            }

            Database::commit();
            return [true, $payoutId, __('Pago manual registrado correctamente', 'wprelay-extras')];
        } catch (\Throwable $e) {
            Database::rollBack();
            return [false, null, $e->getMessage()];
        }
    }

    /**
     * Lista afiliados con saldo pendiente > 0
     */
    public static function listPending($search = '', $page = 1, $perPage = 30)
    {
        $affTbl  = Affiliate::getTableName();
        $memTbl  = Member::getTableName();
        $txTbl   = Transaction::getTableName();
        $payTbl  = Payout::getTableName();
        $currency = Functions::getSelectedCurrency();

        $query = Affiliate::query()
            ->select("{$affTbl}.id as affiliate_id, {$memTbl}.first_name, {$memTbl}.last_name, {$memTbl}.email,
                {$affTbl}.payment_email,
                (COALESCE(SUM(CASE WHEN {$txTbl}.type='credit' THEN {$txTbl}.amount END),0)
                 - COALESCE(SUM(CASE WHEN {$txTbl}.type='debit' THEN {$txTbl}.amount END),0)) as balance")
            ->leftJoin("{$memTbl}", "{$memTbl}.id = {$affTbl}.member_id")
            ->leftJoin("{$txTbl}", "{$txTbl}.affiliate_id = {$affTbl}.id AND {$txTbl}.currency = '" . esc_sql($currency) . "'")
            ->groupBy("{$affTbl}.id")
            ->having("balance > 0")
            ->orderBy('balance', 'DESC');

        if ($search) {
            $s = '%' . $search . '%';
            $query->where("({$memTbl}.first_name LIKE %s OR {$memTbl}.last_name LIKE %s OR {$memTbl}.email LIKE %s)", [$s, $s, $s]);
        }

        $total = $query->count();
        $rows = $query->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return [$rows, $total, $currency];
    }

    /**
     * Historial de pagos manuales registrados
     */
    public static function listHistory($page = 1, $perPage = 30)
    {
        $payTbl = Payout::getTableName();
        $affTbl = Affiliate::getTableName();
        $memTbl = Member::getTableName();

        $query = Payout::query()
            ->select("{$payTbl}.*, {$memTbl}.first_name, {$memTbl}.last_name, {$memTbl}.email")
            ->leftJoin("{$affTbl}", "{$affTbl}.id = {$payTbl}.affiliate_id")
            ->leftJoin("{$memTbl}", "{$memTbl}.id = {$affTbl}.member_id")
            ->where("{$payTbl}.payment_source = %s", ['manual'])
            ->orderBy("{$payTbl}.id", 'DESC');

        $total = $query->count();
        $rows = $query->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return [$rows, $total];
    }
}
