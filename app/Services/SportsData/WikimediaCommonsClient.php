<?php

namespace App\Services\SportsData;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class WikimediaCommonsClient
{
    /** @return array<string, mixed>|null */
    public function file(string $fileName): ?array
    {
        $title = str_starts_with($fileName, 'File:') ? $fileName : 'File:'.$fileName;

        $response = $this->request()->get((string) config('retro_catalog.api_url'), [
            'action' => 'query',
            'format' => 'json',
            'formatversion' => 2,
            'redirects' => 1,
            'prop' => 'imageinfo',
            'titles' => $title,
            'iiprop' => 'url|mime|size|extmetadata',
            'iiurlwidth' => 1200,
        ])->throw();

        $page = $response->json('query.pages.0');
        if (! is_array($page) || isset($page['missing'])) {
            return null;
        }

        $info = $page['imageinfo'][0] ?? null;
        if (! is_array($info) || empty($info['url'])) {
            return null;
        }

        $metadata = is_array($info['extmetadata'] ?? null) ? $info['extmetadata'] : [];
        $value = static fn (string $key): ?string => isset($metadata[$key]['value'])
            ? trim(strip_tags((string) $metadata[$key]['value']))
            : null;

        return [
            'title' => (string) ($page['title'] ?? $title),
            'image_url' => (string) ($info['thumburl'] ?? $info['url']),
            'original_url' => (string) $info['url'],
            'description_url' => (string) ($info['descriptionurl'] ?? ''),
            'mime' => (string) ($info['mime'] ?? ''),
            'width' => (int) ($info['width'] ?? 0),
            'height' => (int) ($info['height'] ?? 0),
            'author' => $value('Artist') ?: $value('Credit'),
            'license' => $value('LicenseShortName'),
            'license_url' => isset($metadata['LicenseUrl']['value'])
                ? (string) $metadata['LicenseUrl']['value']
                : null,
            'description' => $value('ImageDescription'),
        ];
    }

    /** @return array{body: string, extension: string, content_type: string} */
    public function download(string $url): array
    {
        $this->assertAllowedImageUrl($url);

        $response = Http::withUserAgent((string) config('retro_catalog.user_agent'))
            ->accept('*/*')
            ->timeout((int) config('retro_catalog.timeout', 20))
            ->retry(2, 500)
            ->get($url)
            ->throw();

        $body = $response->body();
        $maxBytes = (int) config('retro_catalog.max_image_bytes', 8_388_608);
        if ($body === '' || strlen($body) > $maxBytes) {
            throw new RuntimeException('La imagen de Wikimedia está vacía o supera el límite permitido.');
        }

        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $extension = match ($contentType) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => throw new RuntimeException('Formato de imagen de Wikimedia no permitido.'),
        };

        return ['body' => $body, 'extension' => $extension, 'content_type' => $contentType];
    }

    public function isAllowedImageUrl(string $url): bool
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? null) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === 'upload.wikimedia.org';
    }

    private function assertAllowedImageUrl(string $url): void
    {
        if (! $this->isAllowedImageUrl($url)) {
            throw new RuntimeException('Dominio de imagen de Wikimedia no permitido.');
        }
    }

    private function request(): PendingRequest
    {
        return Http::withUserAgent((string) config('retro_catalog.user_agent'))
            ->acceptJson()
            ->timeout((int) config('retro_catalog.timeout', 20))
            ->retry(2, 500);
    }
}
