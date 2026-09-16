<?php

namespace App\Models;

use Database\Factories\MigraineExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A CSV export of a user's migraine diary over a date range. The file is
 * generated from the user's data on each download, so an export only needs
 * to remember what was asked for.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property bool $include_ad_hoc
 * @property bool $include_regular
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['from_date', 'to_date', 'include_ad_hoc', 'include_regular'])]
class MigraineExport extends Model
{
    /** @use HasFactory<MigraineExportFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The name the CSV file is downloaded as, e.g. "migraine-map-2026-01-01-to-2026-09-16.csv".
     */
    public function fileName(): string
    {
        return sprintf('migraine-map-%s-to-%s.csv', $this->from_date->toDateString(), $this->to_date->toDateString());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_date' => 'date:Y-m-d',
            'to_date' => 'date:Y-m-d',
            'include_ad_hoc' => 'boolean',
            'include_regular' => 'boolean',
        ];
    }
}
