<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\ManualJournalDetl;
use App\Models\ManualJournalMaster;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * General Ledger > Journal Listing (PAGEID 1700 / MENUID 2056).
 *
 * Legacy BL: `SNA_API_GLREPORT_JOURNAL_LISTING` with endpoints
 * `dt_listJL` (master datatable), `masterData`, `dt_debitJL` (DR rows),
 * `dt_creditJL` (CR rows) and `deleteMaster`.
 *
 * Filters applied on the master list (legacy):
 *   - exclude `mjm_system_id = 'VOT_TRANSFER'`
 *   - exclude `mjm_status = 'ERROR'`
 *   - smart filter: year-of-createddate, type, description, date (journal),
 *     status, system_id
 *
 * The legacy datatable deep-links the Journal No column to MENUID 2057
 * (Journal Listing Detail). 2057 is not in the current PAGE_SECOND_LEVEL_MENU
 * migration scope, so the View action is served by the in-page modal that
 * consumes this controller's `show` endpoint — no url_view is emitted.
 */
class JournalListingController extends Controller
{
    use ApiResponse;

    private const SORTABLE = [
        'mjm_journal_no',
        'mjm_journal_desc',
        'mjm_typeofjournal',
        'mjm_total_amt',
        'mjm_status',
        'mjm_system_id',
        'mjm_enterdate',
        'createdby',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'mjm_journal_no');
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'mjm_journal_no';
        }
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $year = trim((string) $request->input('years', ''));
        $type = trim((string) $request->input('type_of_journal', ''));
        $desc = trim((string) $request->input('description', ''));
        $dateJournal = trim((string) $request->input('date_journal', ''));
        $status = trim((string) $request->input('status', ''));
        $systemId = trim((string) $request->input('system_id', ''));

        $query = ManualJournalMaster::query()
            ->where(function (QueryBuilder|\Illuminate\Database\Eloquent\Builder $b) {
                $b->whereNull('mjm_system_id')
                    ->orWhere('mjm_system_id', '!=', 'VOT_TRANSFER');
            })
            ->where(function (QueryBuilder|\Illuminate\Database\Eloquent\Builder $b) {
                $b->whereNull('mjm_status')
                    ->orWhere('mjm_status', '!=', 'ERROR');
            });

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(mjm_journal_no, ''),
                    IFNULL(mjm_journal_desc, ''),
                    IFNULL(mjm_typeofjournal, ''),
                    IFNULL(FORMAT(mjm_total_amt, 2), ''),
                    IFNULL(mjm_status, ''),
                    IFNULL(mjm_system_id, '')
                )) LIKE ?",
                [$like]
            );
        }

        if ($year !== '') {
            $query->whereRaw(
                "IFNULL(DATE_FORMAT(createddate, '%Y'), '') LIKE ?",
                ['%'.$this->bareEscape($year).'%']
            );
        }
        if ($type !== '') {
            $query->whereRaw(
                "LOWER(IFNULL(mjm_typeofjournal, '')) LIKE ?",
                [$this->likeEscape(mb_strtolower($type, 'UTF-8'))]
            );
        }
        if ($desc !== '') {
            $query->whereRaw(
                "LOWER(IFNULL(mjm_journal_desc, '')) LIKE ?",
                [$this->likeEscape(mb_strtolower($desc, 'UTF-8'))]
            );
        }
        if ($dateJournal !== '') {
            // Legacy filter compares dd/mm/YYYY strings; keep parity.
            $query->whereRaw(
                "IFNULL(DATE_FORMAT(mjm_enterdate, '%d/%m/%Y'), '') LIKE ?",
                ['%'.$this->bareEscape($dateJournal).'%']
            );
        }
        if ($status !== '') {
            $query->whereRaw(
                "LOWER(IFNULL(mjm_status, '')) LIKE ?",
                [$this->likeEscape(mb_strtolower($status, 'UTF-8'))]
            );
        }
        if ($systemId !== '') {
            $query->whereRaw(
                "LOWER(IFNULL(mjm_system_id, '')) LIKE ?",
                [$this->likeEscape(mb_strtolower($systemId, 'UTF-8'))]
            );
        }

        $total = (clone $query)->count();

        $rows = (clone $query)
            ->orderBy($sortBy, $sortDir)
            ->orderBy('mjm_journal_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function (ManualJournalMaster $r, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'mjmJournalId' => (int) $r->mjm_journal_id,
                'journalNo' => $r->mjm_journal_no,
                'description' => $r->mjm_journal_desc,
                'typeOfJournal' => $r->mjm_typeofjournal,
                'amount' => $r->mjm_total_amt !== null ? (float) $r->mjm_total_amt : null,
                'status' => $r->mjm_status,
                'systemId' => $r->mjm_system_id,
                'dateJournal' => $r->mjm_enterdate
                    ? Carbon::parse($r->mjm_enterdate)->format('d/m/Y')
                    : null,
                'createdBy' => $r->createdby,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $master = ManualJournalMaster::query()->find($id);
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Journal record not found');
        }

        $lines = ManualJournalDetl::query()
            ->where('mjm_journal_no', $id)
            ->orderBy('mjd_trans_type')
            ->orderBy('mjd_journal_detl_id')
            ->get();

        $mapLine = fn (ManualJournalDetl $d): array => [
            'id' => (int) $d->mjd_journal_detl_id,
            'ounCode' => $d->oun_code,
            'fundType' => $d->fty_fund_type,
            'activityCode' => $d->at_activity_code,
            'costCentre' => $d->ccr_costcentre,
            'acctCode' => $d->acm_acct_code,
            'documentNo' => $d->mjd_document_no !== null ? trim($d->mjd_document_no) : null,
            'amount' => $d->mjd_trans_amt !== null ? (float) $d->mjd_trans_amt : null,
            'codeSo' => $d->code_so,
            'projectNo' => $d->cpa_project_no,
            'taxcode' => $d->mjd_taxcode,
            'status' => $d->mjd_status,
            'reference' => $d->mjd_reference !== null ? trim($d->mjd_reference) : null,
            'paytoId' => $d->mjd_payto_id,
            'paytoType' => $d->mjd_payto_type,
            'paytoName' => $d->mjd_payto_name,
            'source' => $d->mjd_source,
        ];

        $debitLines = $lines->where('mjd_trans_type', 'DT')->values()->map($mapLine);
        $creditLines = $lines->where('mjd_trans_type', 'CR')->values()->map($mapLine);

        $sumDebit = $lines->where('mjd_trans_type', 'DT')
            ->sum(fn (ManualJournalDetl $d) => (float) ($d->mjd_trans_amt ?? 0));
        $sumCredit = $lines->where('mjd_trans_type', 'CR')
            ->sum(fn (ManualJournalDetl $d) => (float) ($d->mjd_trans_amt ?? 0));

        return $this->sendOk([
            'header' => [
                'mjmJournalId' => (int) $master->mjm_journal_id,
                'journalNo' => $master->mjm_journal_no,
                'description' => $master->mjm_journal_desc,
                'typeOfJournal' => $master->mjm_typeofjournal,
                'amount' => $master->mjm_total_amt !== null ? (float) $master->mjm_total_amt : null,
                'status' => $master->mjm_status,
                'systemId' => $master->mjm_system_id,
                'dateJournal' => $master->mjm_enterdate
                    ? Carbon::parse($master->mjm_enterdate)->format('d/m/Y')
                    : null,
                'createdBy' => $master->createdby,
                'sumDebit' => (float) $sumDebit,
                'sumCredit' => (float) $sumCredit,
            ],
            'debit' => $debitLines,
            'credit' => $creditLines,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $master = ManualJournalMaster::query()->find($id);
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'Journal record not found');
        }

        // Legacy BL allows delete unconditionally but the business obviously
        // should not destroy already-posted or cancelled journals. Apply a
        // conservative status gate (only drafts / unprocessed journals).
        $status = strtoupper((string) ($master->mjm_status ?? ''));
        if (in_array($status, ['POSTED', 'CANCEL', 'CANCELLED'], true)) {
            return $this->sendError(
                409,
                'JOURNAL_NOT_DELETABLE',
                'Journal has already been '.strtolower($status).' and cannot be deleted.',
            );
        }

        DB::connection('mysql_secondary')->transaction(function () use ($master) {
            // Mirror legacy: if CREDIT_CARD system, also purge temp rows.
            if (($master->mjm_system_id ?? null) === 'CREDIT_CARD'
                && ! empty($master->mjm_journal_no)
            ) {
                DB::connection('mysql_secondary')
                    ->table('temp_process_creditcard')
                    ->where('tcp_journal_no', $master->mjm_journal_no)
                    ->delete();
            }
            ManualJournalDetl::query()
                ->where('mjm_journal_no', $master->mjm_journal_id)
                ->delete();
            $master->delete();
        });

        return $this->sendOk(['success' => true]);
    }

    public function options(): JsonResponse
    {
        $base = ManualJournalMaster::query()
            ->where(function ($b) {
                $b->whereNull('mjm_system_id')
                    ->orWhere('mjm_system_id', '!=', 'VOT_TRANSFER');
            })
            ->where(function ($b) {
                $b->whereNull('mjm_status')
                    ->orWhere('mjm_status', '!=', 'ERROR');
            });

        $types = (clone $base)
            ->whereNotNull('mjm_typeofjournal')
            ->where('mjm_typeofjournal', '!=', '')
            ->distinct()
            ->orderBy('mjm_typeofjournal')
            ->pluck('mjm_typeofjournal')
            ->values()
            ->all();

        $statuses = (clone $base)
            ->whereNotNull('mjm_status')
            ->where('mjm_status', '!=', '')
            ->distinct()
            ->orderBy('mjm_status')
            ->pluck('mjm_status')
            ->values()
            ->all();

        $systems = (clone $base)
            ->whereNotNull('mjm_system_id')
            ->where('mjm_system_id', '!=', '')
            ->distinct()
            ->orderBy('mjm_system_id')
            ->pluck('mjm_system_id')
            ->values()
            ->all();

        return $this->sendOk([
            'types' => array_values(array_map('strval', $types)),
            'statuses' => array_values(array_map('strval', $statuses)),
            'systemIds' => array_values(array_map('strval', $systems)),
        ]);
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }

    private function bareEscape(string $needle): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle);
    }
}
