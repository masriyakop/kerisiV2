<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CcontrollerReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Debtor Portal > Financial Information > Reminder" endpoint
 * (PAGEID for MENUID 2584).
 *
 * Source: FIMS BL `NF_BL_DEBTOR_PORTAL_REMINDER` — joins
 * `ccontroller_reminder` to `ccontroller_master` and filters to
 * cm_business_type='INVOICE' + cm_debtor_creditor='DEBTOR', scoped to the
 * logged-in debtor (legacy uses `$_GET['username']` populated from
 * `$_USER['USERNAME']`; here we use auth()->user()->name by default,
 * but also honour an explicit ?debtor_id= override for admin preview).
 *
 * The legacy response shape is a flat array of reminder rows with an
 * attached `cim_cust_invoice_id` subquery; we keep those fields and add
 * pagination + a case-insensitive global `q` filter for parity with
 * the other Portal listings.
 */
class DebtorReminderController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'crm_reminder_date');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortable = [
            'crm_reminder_date', 'crm_invoice_no', 'crm_amt_outstanding',
            'crm_reminder_bil', 'crm_email_addr', 'crm_notification_methd',
        ];
        if (!in_array($sortBy, $sortable, true)) {
            $sortBy = 'crm_reminder_date';
        }

        $debtorId = $this->debtorId($request);
        if ($debtorId === null) {
            // Match legacy behaviour: if we can't identify the debtor we
            // return an empty set instead of 400, so the UI renders
            // gracefully for unmapped users.
            return $this->sendOk([], [
                'page' => $page, 'limit' => $limit,
                'total' => 0, 'totalPages' => 0,
                'debtorId' => null,
            ]);
        }

        $query = CcontrollerReminder::query()
            ->from('ccontroller_reminder as cr')
            ->join('ccontroller_master as cm', 'cm.cm_id', '=', 'cr.cm_id')
            ->where('cm.cm_business_type', 'INVOICE')
            ->where('cm.cm_debtor_creditor', 'DEBTOR')
            ->where('cr.crm_debtor_id', $debtorId);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->where(function ($b) use ($like) {
                foreach ([
                    'cr.crm_invoice_no', 'cr.crm_amt_outstanding',
                    'cr.crm_email_addr', 'cr.crm_notification_methd',
                ] as $col) {
                    $b->orWhereRaw("LOWER(IFNULL($col, '')) LIKE ?", [$like]);
                }
                $b->orWhereRaw("DATE_FORMAT(cr.crm_reminder_date, '%d/%m/%Y') LIKE ?", [$like]);
                $b->orWhereRaw("CAST(cr.crm_reminder_bil AS CHAR) LIKE ?", [$like]);
            });
        }

        $total = (clone $query)->count();

        $rows = $query
            ->select([
                'cr.crm_id',
                'cr.crm_invoice_no',
                'cr.crm_amt_outstanding',
                'cr.crm_reminder_date',
                'cr.crm_reminder_bil',
                'cr.crm_email_addr',
                'cr.crm_notification_methd',
            ])
            ->selectSub(
                fn ($b) => $b->from('cust_invoice_master as cim')
                    ->whereColumn('cim.cim_invoice_no', 'cr.crm_invoice_no')
                    ->selectRaw('cim.cim_cust_invoice_id')
                    ->limit(1),
                'cim_cust_invoice_id'
            )
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'id' => (int) $r->crm_id,
                'invoiceNo' => $r->crm_invoice_no,
                'amountOutstanding' => $r->crm_amt_outstanding,
                'reminderDate' => $r->crm_reminder_date,
                'reminderBil' => $r->crm_reminder_bil !== null ? (int) $r->crm_reminder_bil : null,
                'emailAddress' => $r->crm_email_addr,
                'notificationMethod' => $r->crm_notification_methd,
                'custInvoiceId' => $r->cim_cust_invoice_id !== null ? (int) $r->cim_cust_invoice_id : null,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'debtorId' => $debtorId,
        ]);
    }

    private function debtorId(Request $request): ?string
    {
        $override = trim((string) $request->input('debtor_id', ''));
        if ($override !== '') {
            return $override;
        }
        $user = $request->user();
        if ($user === null) {
            return null;
        }
        $name = trim((string) ($user->name ?? ''));
        return $name === '' ? null : $name;
    }

    private function likeEscape(string $needle): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
    }
}
