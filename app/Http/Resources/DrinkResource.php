<?php

namespace App\Http\Resources;

use App\Models\Drink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Drink
 */
class DrinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'size' => $this->size->value,
            'size_label' => $this->size->label(),
            'cost_cents' => $this->cost_cents,
        ];
    }
}
