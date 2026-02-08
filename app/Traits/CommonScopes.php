<?php

namespace App\Traits;

trait CommonScopes
{
    /**
     * Scope для активных записей.
     */
    public function scopeActive($query)
    {
        if (property_exists($this, 'activeStatusField')) {
            return $query->where($this->activeStatusField, $this->activeStatusValue);
        }

        // По умолчанию ищем поле status со значением active/available
        if (in_array('status', $this->fillable)) {
            return $query->where('status', 'active');
        }

        return $query;
    }

    /**
     * Scope для выделенных записей.
     */
    public function scopeFeatured($query)
    {
        if (in_array('is_featured', $this->fillable)) {
            return $query->where('is_featured', true);
        }

        return $query;
    }

    /**
     * Scope для сортировки.
     */
    public function scopeOrdered($query)
    {
        if (in_array('sort_order', $this->fillable)) {
            return $query->orderBy('sort_order');
        } elseif (in_array('order', $this->fillable)) {
            return $query->orderBy('order');
        }

        return $query->orderBy('created_at', 'desc');
    }
}
