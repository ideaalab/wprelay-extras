<?php

namespace WPRelayExtras;

defined('ABSPATH') or exit;

use RelayWp\Affiliate\App\Helpers\Functions;
use RelayWp\Affiliate\App\Services\Database;
use RelayWp\Affiliate\Core\Models\Affiliate;
use RelayWp\Affiliate\Core\Models\CommissionEarning;
use RelayWp\Affiliate\Core\Models\Customer;
use RelayWp\Affiliate\Core\Models\Member;
use RelayWp\Affiliate\Core\Models\Order;
use RelayWp\Affiliate\Core\Models\Program;
use RelayWp\Affiliate\Core\Models\Transaction;

class Commissions
{
    /**
     * Crear comisión manual.
     * $data: affiliate_id, program_id, commission_amount, currency, order_id (opcional, WC order id), reason, status
     */
    public static function create(array $data)
    {
        Database::beginTransaction();
        try {
            $affiliateId = (int)$data['affiliate_id'];
            $programId   = (int)$data['program_id'];
            $amount      = (float)$data['commission_amount'];
            $currency    = $data['currency'] ?: Functions::getSelectedCurrency();
            $reason      = $data['reason'] ?? 'Comisión creada manualmente';
            $status      = $data['status'] ?? CommissionEarning::PENDING;
            $wcOrderId   = !empty($data['wc_order_id']) ? (int)$data['wc_order_id'] : null;

            // Validaciones
            if (!Affiliate::query()->find($affiliateId)) {
                throw new \Exception(__('Afiliado no encontrado', 'wprelay-extras'));
            }
            if (!Program::query()->find($programId)) {
                throw new \Exception(__('Programa no encontrado', 'wprelay-extras'));
            }
            if ($amount <= 0) {
                throw new \Exception(__('El importe debe ser mayor que 0', 'wprelay-extras'));
            }
            if (!in_array($status, [CommissionEarning::PENDING, CommissionEarning::APPROVED, CommissionEarning::REJECTED], true)) {
                throw new \Exception(__('Estado inválido', 'wprelay-extras'));
            }

            // Si se pasó un WC Order ID, mapear al order interno de WPRelay
            // Si no existe en WPRelay, lo creamos a partir del pedido WC
            $relayOrderId = null;
            if ($wcOrderId) {
                $relayOrder = Order::query()->where('woo_order_id = %d', [$wcOrderId])->first();
                if ($relayOrder) {
                    $relayOrderId = $relayOrder->id;
                } else {
                    // Intentar crear el order en WPRelay a partir del pedido WC
                    $wcOrder = function_exists('wc_get_order') ? wc_get_order($wcOrderId) : null;
                    if ($wcOrder && $wcOrder instanceof \WC_Order) {
                        // Buscar o crear el customer en WPRelay
                        $customerMember = Member::query()
                            ->where("email = %s", [$wcOrder->get_billing_email()])
                            ->where("type = %s", ['customer'])
                            ->first();

                        $customerId = null;
                        if ($customerMember) {
                            $customer = Customer::query()
                                ->where("member_id = %d", [$customerMember->id])
                                ->where("affiliate_id = %d", [$affiliateId])
                                ->first();
                            $customerId = $customer ? $customer->id : null;
                        }

                        $affiliate = Affiliate::query()->find($affiliateId);
                        Order::query()->create([
                            'woo_order_id'           => $wcOrder->get_id(),
                            'customer_id'            => $customerId,
                            'affiliate_id'           => $affiliateId,
                            'program_id'             => $affiliate->program_id ?? $programId,
                            'currency'               => $wcOrder->get_currency(),
                            'total_amount'           => $wcOrder->get_total(),
                            'calculated_total_amount' => $wcOrder->get_total(),
                            'medium'                 => 'manual',
                            'ordered_at'             => $wcOrder->get_date_created()
                                ? $wcOrder->get_date_created()->format('Y-m-d H:i:s')
                                : Functions::currentUTCTime(),
                            'order_status'           => $wcOrder->get_status(),
                            'created_at'             => Functions::currentUTCTime(),
                            'updated_at'             => Functions::currentUTCTime(),
                        ]);
                        $relayOrderId = Order::query()->lastInsertedId();
                        $reason .= sprintf(' [WC Order #%d - auto-registered]', $wcOrderId);
                    } else {
                        $reason .= sprintf(' [WC Order #%d - not found in WC]', $wcOrderId);
                    }
                }
            }

            CommissionEarning::query()->create([
                'affiliate_id'        => $affiliateId,
                'order_id'            => $relayOrderId,
                'program_id'          => $programId,
                'commission_amount'   => $amount,
                'commission_currency' => $currency,
                'show_commission'     => 1,
                'status'              => $status,
                'reason'              => $reason,
                'type'                => 'commission',
                'date_created'        => Functions::currentUTCTime(),
                'created_at'          => Functions::currentUTCTime(),
                'updated_at'          => Functions::currentUTCTime(),
            ]);

            $commissionId = CommissionEarning::query()->lastInsertedId();

            // Si se crea como aprobada, generar transacción CREDIT inmediatamente
            if ($status === CommissionEarning::APPROVED) {
                Transaction::query()->create([
                    'affiliate_id'         => $affiliateId,
                    'type'                 => Transaction::CREDIT,
                    'currency'             => $currency,
                    'amount'               => $amount,
                    'system_note'          => "Manual Commission Approved #{$commissionId}",
                    'transactionable_id'   => $commissionId,
                    'transactionable_type' => 'commission',
                    'created_at'           => Functions::currentUTCTime(),
                ]);
            }

            Database::commit();
            return [true, $commissionId, __('Comisión creada correctamente', 'wprelay-extras')];
        } catch (\Throwable $e) {
            Database::rollBack();
            return [false, null, $e->getMessage()];
        }
    }

    /**
     * Editar comisión existente: importe, estado, motivo.
     * Mantiene la integridad del balance ajustando transacciones.
     */
    public static function update($commissionId, array $data)
    {
        Database::beginTransaction();
        try {
            $commission = CommissionEarning::query()->where('id = %d', [$commissionId])->firstOrFail();

            $newAmount = isset($data['commission_amount']) ? (float)$data['commission_amount'] : (float)$commission->commission_amount;
            $newStatus = $data['status'] ?? $commission->status;
            $newReason = $data['reason'] ?? $commission->reason;

            if ($newAmount <= 0) {
                throw new \Exception(__('El importe debe ser mayor que 0', 'wprelay-extras'));
            }
            if (!in_array($newStatus, [CommissionEarning::PENDING, CommissionEarning::APPROVED, CommissionEarning::REJECTED], true)) {
                throw new \Exception(__('Estado inválido', 'wprelay-extras'));
            }

            $oldAmount = (float)$commission->commission_amount;
            $oldStatus = $commission->status;
            $currency  = $commission->commission_currency;
            $affId     = $commission->affiliate_id;

            // Actualizar la comisión
            CommissionEarning::query()->update([
                'commission_amount' => $newAmount,
                'status'            => $newStatus,
                'reason'            => $newReason,
                'updated_at'        => Functions::currentUTCTime(),
            ], ['id' => $commissionId]);

            /**
             * Lógica de balance:
             * - Si pasa pending/rejected -> approved : crear CREDIT por newAmount
             * - Si pasa approved -> rejected/pending : crear DEBIT por oldAmount
             * - Si era approved y sigue approved con cambio de importe :
             *     ajustar con CREDIT (si subió) o DEBIT (si bajó)
             */
            $wasApproved = ($oldStatus === CommissionEarning::APPROVED);
            $isApproved  = ($newStatus === CommissionEarning::APPROVED);

            if (!$wasApproved && $isApproved) {
                // Activación de balance
                Transaction::query()->create([
                    'affiliate_id'         => $affId,
                    'type'                 => Transaction::CREDIT,
                    'currency'             => $currency,
                    'amount'               => $newAmount,
                    'system_note'          => "Commission Approved (Edit) #{$commissionId}",
                    'transactionable_id'   => $commissionId,
                    'transactionable_type' => 'commission',
                    'created_at'           => Functions::currentUTCTime(),
                ]);
            } elseif ($wasApproved && !$isApproved) {
                // Reversión de balance
                Transaction::query()->create([
                    'affiliate_id'         => $affId,
                    'type'                 => Transaction::DEBIT,
                    'currency'             => $currency,
                    'amount'               => $oldAmount,
                    'system_note'          => "Commission {$newStatus} (Edit) #{$commissionId}",
                    'transactionable_id'   => $commissionId,
                    'transactionable_type' => 'commission',
                    'created_at'           => Functions::currentUTCTime(),
                ]);
            } elseif ($wasApproved && $isApproved && abs($newAmount - $oldAmount) > 0.001) {
                // Ajuste por cambio de importe estando aprobada
                $diff = $newAmount - $oldAmount;
                Transaction::query()->create([
                    'affiliate_id'         => $affId,
                    'type'                 => $diff > 0 ? Transaction::CREDIT : Transaction::DEBIT,
                    'currency'             => $currency,
                    'amount'               => abs($diff),
                    'system_note'          => "Commission Amount Adjusted #{$commissionId}",
                    'transactionable_id'   => $commissionId,
                    'transactionable_type' => 'commission',
                    'created_at'           => Functions::currentUTCTime(),
                ]);
            }

            // Email al afiliado si cambió de estado (solo si hay pedido vinculado,
            // porque sendCommissionStatusMail requiere order_id válido)
            if ($oldStatus !== $newStatus) {
                $reloaded = CommissionEarning::query()->where('id = %d', [$commissionId])->first();
                if ($reloaded && $reloaded->order_id) {
                    $affiliate = Affiliate::query()->find($affId);
                    $member = $affiliate ? Member::query()->find($affiliate->member_id) : null;
                    if ($affiliate && $member) {
                        CommissionEarning::sendCommissionStatusMail($newStatus, $reloaded, $affiliate, $member);
                    }
                }
            }

            Database::commit();
            return [true, __('Comisión actualizada', 'wprelay-extras')];
        } catch (\Throwable $e) {
            Database::rollBack();
            return [false, $e->getMessage()];
        }
    }

    public static function listCommissions($filters = [], $page = 1, $perPage = 30)
    {
        $table         = CommissionEarning::getTableName();
        $affiliateTbl  = Affiliate::getTableName();
        $memberTbl     = Member::getTableName();
        $programTbl    = Program::getTableName();

        $query = CommissionEarning::query()
            ->select("{$table}.*, {$memberTbl}.first_name, {$memberTbl}.last_name, {$memberTbl}.email, {$programTbl}.title as program_name")
            ->leftJoin("{$affiliateTbl}", "{$affiliateTbl}.id = {$table}.affiliate_id")
            ->leftJoin("{$memberTbl}", "{$memberTbl}.id = {$affiliateTbl}.member_id")
            ->leftJoin("{$programTbl}", "{$programTbl}.id = {$table}.program_id")
            ->orderBy("{$table}.id", 'DESC');

        if (!empty($filters['status'])) {
            $query->where("{$table}.status = %s", [$filters['status']]);
        }
        if (!empty($filters['affiliate_id'])) {
            $query->where("{$table}.affiliate_id = %d", [(int)$filters['affiliate_id']]);
        }
        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $query->where("({$memberTbl}.first_name LIKE %s OR {$memberTbl}.last_name LIKE %s OR {$memberTbl}.email LIKE %s)", [$s, $s, $s]);
        }

        $total = $query->count();
        $rows = $query->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return [$rows, $total];
    }

    public static function getById($id)
    {
        return CommissionEarning::query()->where('id = %d', [(int)$id])->first();
    }
}
