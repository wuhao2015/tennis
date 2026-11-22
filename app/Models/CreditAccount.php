<?php

namespace App\Models;

use App\Services\CreditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditAccount extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:2', 'frozen' => 'decimal:2'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CreditEntry::class);
    }

    public function available(): string
    {
        return CreditService::decimal(CreditService::hundredths($this->balance) - CreditService::hundredths($this->frozen));
    }
}
