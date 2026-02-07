<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group,
            'key' => $this->key,

            // Значение (с учетом типа)
            'value' => $this->value,
            'type' => $this->type,

            // Метаданные
            'description' => $this->description,
            'options' => $this->options,
            'order' => $this->order,

            // Видимость
            'is_public' => $this->is_public,
            'is_required' => $this->is_required,

            // Валидация
            'validation_rules' => $this->validation_rules,

            // Временные метки
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Форматированное значение для форм
            'form_value' => $this->getFormValue(),

            // Тип поля для форм
            'input_type' => $this->getInputType(),

            // Дополнительные параметры для фронтенда
            'field_props' => $this->getFieldProps(),
        ];
    }

    private function getFormValue()
    {
        return match($this->type) {
            'boolean' => (bool) $this->value,
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'json', 'array' => is_array($this->value) ? $this->value : json_decode($this->value, true),
            default => $this->value,
        };
    }

    private function getInputType(): string
    {
        if ($this->options && is_array($this->options)) {
            return 'select';
        }

        return match($this->type) {
            'boolean' => 'checkbox',
            'integer', 'float' => 'number',
            'text' => 'textarea',
            'json', 'array' => 'json',
            default => 'text',
        };
    }

    private function getFieldProps(): array
    {
        $props = [];

        if ($this->type === 'integer' || $this->type === 'float') {
            $props['min'] = 0;
            if ($this->type === 'integer') {
                $props['step'] = 1;
            } else {
                $props['step'] = '0.01';
            }
        }

        if ($this->options && is_array($this->options)) {
            $props['options'] = $this->options;
        }

        if ($this->description) {
            $props['help'] = $this->description;
        }

        return $props;
    }
}
