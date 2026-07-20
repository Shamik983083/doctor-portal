<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One company's EHR credentials and vendor-side identifiers.
 *
 * Holding the credential per company is what makes tenant segregation
 * structural: storefront A's approval is pushed with storefront A's key, so it
 * cannot reach storefront B's data even if a downstream bug asked it to.
 */
class PartnerEhrSetting extends Model
{
    protected $fillable = [
        'partner_id',
        'provider',
        'api_key',
        'authorization_shard',
        'endpoint',
        'organization_id',
        'default_provider_id',
        'note_form_id',
        'is_enabled',
        'sandbox_validated',
    ];

    /**
     * The API key is encrypted at rest. `hidden` keeps it out of any accidental
     * toArray()/toJson(), which is how credentials usually end up in a log line
     * or an admin JSON response.
     */
    protected $casts = [
        'api_key'           => 'encrypted',
        'is_enabled'        => 'boolean',
        'sandbox_validated' => 'boolean',
    ];

    protected $hidden = ['api_key'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Everything needed to push, and nothing missing.
     *
     * Used by the adapter as a precondition. A half-configured company must be
     * caught before a request is built, not discovered from a vendor error.
     */
    public function isPushable(): bool
    {
        return $this->is_enabled
            && $this->sandbox_validated
            && ! empty($this->api_key)
            && ! empty($this->endpoint);
    }

    /** Which required values are still missing, for the admin screen and errors. */
    public function missingValues(): array
    {
        $missing = [];

        if (empty($this->api_key))  $missing[] = 'api_key';
        if (empty($this->endpoint)) $missing[] = 'endpoint';
        if (empty($this->organization_id))     $missing[] = 'organization_id';
        if (empty($this->default_provider_id)) $missing[] = 'default_provider_id';

        return $missing;
    }

    /**
     * Healthie's required request headers, per their authentication guide.
     * AuthorizationShard is only sent when the account is sharded.
     */
    public function authHeaders(): array
    {
        $headers = [
            'Authorization'       => 'Basic ' . $this->api_key,
            'AuthorizationSource' => 'API',
        ];

        if (! empty($this->authorization_shard)) {
            $headers['AuthorizationShard'] = $this->authorization_shard;
        }

        return $headers;
    }
}
