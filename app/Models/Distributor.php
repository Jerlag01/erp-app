<?php

namespace App\Models;

use App\Models\Concerns\DeletesStoredFiles;
use App\Models\Concerns\HasAddress;
use App\Services\Distributors\DistributorClient;
use App\Services\Distributors\DistributorClients;
use Database\Factories\DistributorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A wholesaler the items are bought from, e.g. Cebeo or Rexel, with its contact people
 * and, optionally, the credentials for its price/catalogue connection.
 */
#[Fillable([
    'name', 'website', 'store_url', 'email', 'phone', 'street', 'postal_code', 'city', 'country', 'logo', 'notes',
    'price_provider', 'api_username', 'api_password', 'api_customer_number',
])]
#[Hidden(['api_password'])]
class Distributor extends Model
{
    use DeletesStoredFiles, HasAddress;

    /** @use HasFactory<DistributorFactory> */
    use HasFactory;

    public function storedFiles(): array
    {
        return ['logo'];
    }

    /**
     * @return HasMany<DistributorContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(DistributorContact::class);
    }

    /**
     * @return BelongsToMany<Manufacturer, $this>
     */
    public function manufacturers(): BelongsToMany
    {
        return $this->belongsToMany(Manufacturer::class);
    }

    /**
     * @return HasMany<StockItem, $this>
     */
    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    /**
     * The connection to this distributor's catalogue, if one is chosen.
     */
    public function client(): ?DistributorClient
    {
        return app(DistributorClients::class)->for($this);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_password' => 'encrypted',
        ];
    }
}
