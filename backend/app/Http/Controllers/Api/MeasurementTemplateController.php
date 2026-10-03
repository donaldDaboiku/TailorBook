<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeasurementTemplateResource;
use App\Models\MeasurementTemplate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MeasurementTemplateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $templates = MeasurementTemplate::query()
            ->whereNull('business_id')
            ->with('fields')
            ->orderBy('sort_order')
            ->get();

        return MeasurementTemplateResource::collection($templates);
    }
}
