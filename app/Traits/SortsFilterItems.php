<?php

namespace App\Traits;

trait SortsFilterItems
{
    /**
     * Sorts filter items by active status, count, and value name.
     *
     * @param array $items
     * @return array
     */
    public function sortFilterItems(array $items): array
    {
        usort($items, function ($a, $b) {
            if ($a['active'] !== $b['active']) {
                return $a['active'] ? -1 : 1;
            }

            if (($a['count'] > 0) !== ($b['count'] > 0)) {
                return $a['count'] > 0 ? -1 : 1;
            }

            return strcmp((string) $a['value'], (string) $b['value']);
        });

        return $items;
    }
}
