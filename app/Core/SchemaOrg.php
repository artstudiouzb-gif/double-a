<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Нативная генерация микроразметки Schema.org (JSON-LD) для госсайта:
 * Organization, NewsArticle, Event, BreadcrumbList. Чистые функции — сборка
 * массивов тестируется без вывода; render() печатает <script type=ld+json>.
 */
final class SchemaOrg
{
    /** @return array<string, mixed> */
    public static function organization(string $name, string $url, string $phone = '', string $email = '', string $address = '', string $logo = ''): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'GovernmentOrganization',
            'name' => $name,
            'url' => $url,
        ];
        if ($logo !== '') {
            $data['logo'] = $logo;
        }
        if ($phone !== '') {
            $data['telephone'] = $phone;
        }
        if ($email !== '') {
            $data['email'] = $email;
        }
        if ($address !== '') {
            $data['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $address];
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public static function newsArticle(string $title, string $url, string $datePublished, string $description = '', string $image = '', string $publisher = ''): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => mb_substr($title, 0, 110),
            'url' => $url,
            'mainEntityOfPage' => $url,
        ];
        if ($datePublished !== '') {
            $data['datePublished'] = date('c', (int) strtotime($datePublished));
        }
        if ($description !== '') {
            $data['description'] = mb_substr($description, 0, 300);
        }
        if ($image !== '') {
            $data['image'] = [$image];
        }
        if ($publisher !== '') {
            $data['publisher'] = ['@type' => 'Organization', 'name' => $publisher];
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public static function event(string $title, string $url, string $startDate, string $location = '', string $description = ''): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $title,
            'url' => $url,
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        ];
        if ($startDate !== '') {
            $data['startDate'] = $startDate;
        }
        if ($location !== '') {
            $data['location'] = ['@type' => 'Place', 'name' => $location];
        }
        if ($description !== '') {
            $data['description'] = mb_substr($description, 0, 300);
        }

        return $data;
    }

    /**
     * @param array<int, array{0: string, 1: string}> $items [[название, url], ...]
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $items): array
    {
        $list = [];
        foreach (array_values($items) as $i => [$name, $url]) {
            $item = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $name,
            ];
            if ($url !== '') {
                $item['item'] = $url;
            }
            $list[] = $item;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ];
    }

    /** Печатает готовый JSON-LD блок. @param array<string, mixed> $data */
    public static function render(array $data): string
    {
        return '<script type="application/ld+json">'
            . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
            . '</script>';
    }
}
