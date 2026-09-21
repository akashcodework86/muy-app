<?php

namespace App\Support;

use App\Models\Service;
use Illuminate\Support\Collection;

final class IncubateeServiceCatalog
{
    /**
     * Active services grouped by root category slug.
     *
     * @return Collection<string, Collection<int, Service>>
     */
    public static function grouped(): Collection
    {
        return Service::query()
            ->where('is_active', true)
            ->with(['category.parent'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy(function (Service $service): string {
                $category = $service->category;
                $root = $category?->parent ?? $category;

                return (string) ($root?->slug ?: 'other');
            });
    }

    public static function categoryLabel(string $slug): string
    {
        $key = 'incubatee.service_categories.'.$slug;
        $translated = __($key);

        return $translated === $key
            ? str_replace('_', ' ', $slug)
            : $translated;
    }

    public static function serviceLabel(Service $service): string
    {
        $key = 'incubatee.service_names.'.$service->code;
        $translated = __($key);

        return $translated === $key ? (string) $service->name : $translated;
    }
}
