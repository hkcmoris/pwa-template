<?php

declare(strict_types=1);

namespace Configuration;

final class Formatter
{
    /**
     * @param array{
     *     id: int,
     *     user_id: int,
     *     created_at: string,
     *     updated_at: string
     * } $meta
     * @param list<array{id: int,configuration_id: int,component_id: int,position: int}> $rows
     * @return array{
     *     id: int,
     *     user_id: int,
     *     created_at: string,
     *     updated_at: string,
     *     children: list<array{id: int,configuration_id: int,component_id: int,position: int}>
     * }
     */
    public function buildConfiguration(array $meta, array $rows): array
    {
        $children = [];
        foreach ($rows as $row) {
            $children[] = [
                'id' => $row['id'],
                'configuration_id' => $row['configuration_id'],
                'component_id' => $row['component_id'],
                'position' => $row['position']
            ];
        }

        $result = [
            'id' => $meta['id'],
            'user_id' => $meta['user_id'],
            'created_at' => $meta['created_at'],
            'updated_at' => $meta['updated_at'],
            'children' => $children
        ];

        return $result;
    }
}
