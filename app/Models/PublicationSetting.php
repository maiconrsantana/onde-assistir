<?php

namespace App\Models;

use Database\Factories\PublicationSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublicationSetting extends Model
{
    /** @use HasFactory<PublicationSettingFactory> */
    use HasFactory;

    public const MODE_MANUAL = 'manual';

    public const MODE_AUTOMATIC = 'automatic';

    protected $guarded = [];
}
