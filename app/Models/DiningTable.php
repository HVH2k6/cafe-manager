<?php

namespace App\Models;

use App\TableStatus;
use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A physical table in the cafe (table `tables`; named DiningTable to avoid confusion with DB tables).
 */
#[Fillable(['table_no', 'capacity', 'location', 'section', 'status', 'notes'])]
class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    protected $table = 'tables';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'table_no' => 'integer',
            'capacity' => 'integer',
            'status' => TableStatus::class,
        ];
    }

    /**
     * The code customers scan to order from this table (step 9).
     *
     * @return HasOne<QrCode, $this>
     */
    public function qrCode(): HasOne
    {
        return $this->hasOne(QrCode::class, 'table_id');
    }
}
