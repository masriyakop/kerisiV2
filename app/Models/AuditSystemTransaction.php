<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit Trail / System Transaction (PAGEID 3 / MENUID 5).
 *
 * Source: legacy FIMS schema `fims_audit.system_transaction`. The table is
 * read cross-database via the configured `mysql_secondary` connection — the
 * same connection used by the rest of the migrated FIMS pages — by giving
 * Eloquent the fully-qualified table name. Auditable trait is intentionally
 * NOT applied: this model represents the LEGACY audit ledger and is strictly
 * read-only; new audit events flow into `audit_logs` via the existing
 * Auditable trait elsewhere in the codebase.
 *
 * Reference legacy BL: V2_AUDIT_SYSTEM_TRANSACTION_API.
 */
class AuditSystemTransaction extends Model
{
    use HasFactory;

    protected $connection = 'mysql_secondary';

    /**
     * Cross-database fully-qualified name. MySQL resolves this on the same
     * server provided the connection user has SELECT on `fims_audit`.
     */
    protected $table = 'fims_audit.system_transaction';

    protected $primaryKey = 'AUDIT_ID';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'AUDIT_ID' => 'integer',
            'USER_ID' => 'integer',
            'AUDIT_REQUEST_MENU_ID' => 'integer',
        ];
    }
}
