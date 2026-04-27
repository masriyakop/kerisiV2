<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Portal > List of Letter (PAGEID 2330 / MENUID 2823).
 *
 * Source: legacy FIMS BL `IKA_LETTER_LIST_API`. The legacy page is
 * student-only and exposes three datatables / actions:
 *
 *   1. listSurat   — letter-type catalog from `lookup_details`
 *                    (lma_code_name = 'SPONSOR_LETTER').
 *   2. listHistory — student's downloaded letters from
 *                    `lv_sequence_letter`, scoped by lvs_matric_no.
 *   3. download    — generates a new lvs_sequence_letter row and a
 *                    PDF report URL.
 *   4. dt_payment_list — statement-of-account computation used inside
 *                        the payment-confirmation letter modal.
 *
 * # In scope (this migration)
 * Read-only listings (1) and (2). The `download` action depends on
 * mutating `lv_sequence_letter`, generating sequence numbers, and
 * rendering the legacy sponsor PDF templates (`upload/portal/sponsor/`).
 * That report pipeline does not exist in this codebase, so the download
 * endpoint returns 501 NOT_IMPLEMENTED with a clear message until the
 * sponsor-letter PDF service is ported. The dt_payment_list query is
 * also deferred (200+ lines of UNIONed SQL across deposit_master,
 * cust_invoice_*, receipt_master, sponsor_invoice_details) — the result
 * is only consumed inside the deferred download flow.
 *
 * # Scoping
 * The authenticated user's `name` is treated as the student matric / id
 * (FIMS convention — same mapping used by Tender / Vendor portal pages).
 */
class SponsorLetterController extends Controller
{
    use ApiResponse;

    private const CONN = 'mysql_secondary';

    public function listSurat(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'lde_description');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (! in_array($sortBy, ['lde_description', 'lde_value'], true)) {
            $sortBy = 'lde_description';
        }

        $base = DB::connection(self::CONN)
            ->table('lookup_details')
            ->where('lma_code_name', 'SPONSOR_LETTER')
            ->where('lde_status', 1);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw('LOWER(IFNULL(lde_description, \'\')) LIKE ?', [$like]);
        }

        $total = (clone $base)->count();
        $rows = (clone $base)
            ->select(['lde_id', 'lde_value', 'lde_description'])
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->map(function ($r, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'ldeId' => (int) $r->lde_id,
                'letterId' => (string) $r->lde_value,
                'letter' => (string) $r->lde_description,
            ];
        })->all();

        return $this->sendOk($data, [
            'page' => $page, 'limit' => $limit, 'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function listHistory(Request $request): JsonResponse
    {
        $studentId = $this->studentId($request);
        if ($studentId === null) {
            return $this->sendOk([], [
                'page' => 1, 'limit' => 0, 'total' => 0, 'totalPages' => 0,
            ]);
        }

        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'createddate');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sortBy, ['surat', 'ref_no', 'download_date', 'createddate'], true)) {
            $sortBy = 'createddate';
        }

        $letterName = trim((string) $request->input('letter_name', ''));
        $refNo = trim((string) $request->input('ref_no', ''));
        $year = trim((string) $request->input('year', ''));
        $startDate = trim((string) $request->input('start_date', ''));
        $endDate = trim((string) $request->input('end_date', ''));

        $base = DB::connection(self::CONN)
            ->table('lv_sequence_letter as lvs')
            ->join('lookup_details as ld', 'lvs.lvs_prefix_code', '=', 'ld.lde_value')
            ->where('ld.lma_code_name', 'SPONSOR_LETTER')
            ->where('lvs.lvs_matric_no', $studentId);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(lvs.lvs_prefix_code,''),
                    IFNULL(ld.lde_description,''),
                    IFNULL(JSON_UNQUOTE(JSON_EXTRACT(lvs.lvs_extended_field, '$.referenceNo')),''),
                    IFNULL(DATE_FORMAT(lvs.lvs_action_date,'%d/%m/%Y'),''),
                    IFNULL(DATE_FORMAT(STR_TO_DATE(lvs.lvs_action_date,'%Y-%m-%d'),'%Y'),'')
                )) LIKE ?",
                [$like]
            );
        }
        if ($letterName !== '') {
            $base->where('lvs.lvs_prefix_code', $letterName);
        }
        if ($refNo !== '') {
            $base->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(lvs.lvs_extended_field, '$.referenceNo')) LIKE ?",
                [$this->likeEscape(mb_strtolower($refNo, 'UTF-8'))]
            );
        }
        if ($year !== '') {
            $base->whereRaw("DATE_FORMAT(STR_TO_DATE(lvs.lvs_action_date,'%Y-%m-%d'),'%Y') = ?", [$year]);
        }
        if ($startDate !== '' && $endDate !== '') {
            $base->whereRaw(
                "DATE(lvs.lvs_action_date) >= STR_TO_DATE(?, '%d/%m/%Y') AND DATE(lvs.lvs_action_date) <= STR_TO_DATE(?, '%d/%m/%Y')",
                [$startDate, $endDate]
            );
        } elseif ($startDate !== '') {
            $base->whereRaw("DATE_FORMAT(lvs.lvs_action_date,'%d/%m/%Y') = ?", [$startDate]);
        } elseif ($endDate !== '') {
            $base->whereRaw("DATE_FORMAT(lvs.lvs_action_date,'%d/%m/%Y') = ?", [$endDate]);
        }

        $total = (clone $base)->count();

        $rawSort = match ($sortBy) {
            'surat' => 'ld.lde_description',
            'ref_no' => "JSON_UNQUOTE(JSON_EXTRACT(lvs.lvs_extended_field, '$.referenceNo'))",
            'download_date' => 'lvs.lvs_action_date',
            default => 'lvs.createddate',
        };

        $rows = (clone $base)
            ->select([
                'lvs.lvs_id',
                'lvs.lvs_prefix_code',
                'ld.lde_description as surat',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(lvs.lvs_extended_field, '$.referenceNo')) AS ref_no"),
                DB::raw("DATE_FORMAT(lvs.lvs_action_date,'%d/%m/%Y') AS download_date"),
            ])
            ->orderByRaw("$rawSort $sortDir")
            ->orderBy('lvs.lvs_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->map(function ($r, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'lvsId' => (int) $r->lvs_id,
                'letterId' => (string) $r->lvs_prefix_code,
                'letterName' => (string) $r->surat,
                'referenceNo' => $r->ref_no,
                'downloadDate' => $r->download_date,
            ];
        })->all();

        return $this->sendOk($data, [
            'page' => $page, 'limit' => $limit, 'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    /**
     * Download/generate is deferred — see class doc comment. Kept as a
     * documented stub so the SPA contract is explicit and any caller
     * gets a 501 (NOT_IMPLEMENTED) instead of a 404.
     */
    public function download(Request $request, string $letterId): JsonResponse
    {
        return $this->sendError(
            501,
            'NOT_IMPLEMENTED',
            'Sponsor letter generation is not available in this migration. The legacy report pipeline (PDF templates under upload/portal/sponsor/, eligibility checks against deposit_master / cust_invoice_master / receipt_master, and the lv_sequence_letter insert) has not been ported yet.',
            ['letterId' => $letterId]
        );
    }

    private function studentId(Request $request): ?string
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }
        $sid = trim((string) ($user->name ?? ''));

        return $sid === '' ? null : $sid;
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
