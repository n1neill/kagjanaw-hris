<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Http\Resources\Kiosk\KioskTemplateResource;
use App\Models\FingerprintTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Every enrolled finger of employees in service, for the kiosk to match
 * against locally. The route adds an ETag, so an unchanged list costs the
 * kiosk only a 304 Not Modified.
 */
class KioskTemplateController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $templates = FingerprintTemplate::query()
            ->whereHas('employee', fn (Builder $query) => $query->inService()->whereNotNull('biometric_user_id'))
            ->with('employee:id,first_name,middle_name,last_name,name_extension,biometric_user_id')
            ->orderBy('id')
            ->get();

        return KioskTemplateResource::collection($templates);
    }
}
