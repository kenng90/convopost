<?php

namespace Modules\Contacts\Models;

use App\Models\Company;
use App\Models\Messaging\ChannelIdentity;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes;

    protected $table = 'contacts';

    public $guarded = [];

    public function groups()
    {
        return $this->belongsToMany(
            Group::class,
            'groups_contacts',
            'contact_id',
            'group_id'
        );
    }

    public function getCompany()
    {
        return Company::findOrFail($this->company_id);
    }

    public function fields()
    {
        return $this->belongsToMany(
            Field::class,
            'custom_contacts_fields_contacts',
            'contact_id',
            'custom_contacts_field_id'
        )->withPivot('value');
    }

    public function country()
    {
        return $this->belongsTo(
            Country::class
        );
    }

    public function channelIdentities(): HasMany
    {
        return $this->hasMany(ChannelIdentity::class, 'contact_id');
    }

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $company_id = session('company_id', null);
            if ($company_id && ! $model->company_id) {
                $model->company_id = $company_id;
            }
        });

        static::created(function ($model) {
            if (! filled($model->phone)) {
                return;
            }

            $country_id = $model->getCountryByPhoneNumber($model->phone);
            if ($country_id) {
                $model->country_id = $country_id;
                $model->update();
            }
        });
    }

    private function getCountryByPhoneNumber($phoneNumber)
    {
        if (! filled($phoneNumber)) {
            return null;
        }

        if (strpos($phoneNumber, '+') !== 0) {
            $phoneNumber = '+'.$phoneNumber;
        }

        $prefixes = Country::pluck('id', 'phone_code');

        // Use regular expression to extract the prefix
        if (preg_match('/^\+(\d{4})/', $phoneNumber, $matches)) {
            $prefix = $matches[1];

            // Check if the prefix exists in the array
            if (isset($prefixes[$prefix])) {
                return $prefixes[$prefix];
            } elseif (preg_match('/^\+(\d{3})/', $phoneNumber, $matches)) {
                $prefix = $matches[1];

                // Check if the prefix exists in the array
                if (isset($prefixes[$prefix])) {
                    return $prefixes[$prefix];
                } elseif (preg_match('/^\+(\d{2})/', $phoneNumber, $matches)) {
                    $prefix = $matches[1];

                    // Check if the prefix exists in the array
                    if (isset($prefixes[$prefix])) {
                        return $prefixes[$prefix];
                    } elseif (preg_match('/^\+(\d{1})/', $phoneNumber, $matches)) {
                        $prefix = $matches[1];

                        // Check if the prefix exists in the array
                        if (isset($prefixes[$prefix])) {
                            return $prefixes[$prefix];
                        }
                    }
                }
            }
        }

        return null;
    }
}
