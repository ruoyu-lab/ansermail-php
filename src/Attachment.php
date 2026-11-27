<?php

declare(strict_types=1);

namespace Anser;

/** Builds the `attachments` entries of an email: the API takes the content as base64. */
final class Attachment
{
    /**
     * @param string|null $contentId set it to show the attachment inline: `<img src="cid:…">`
     * @return array{filename: string, content: string, content_type?: string, content_id?: string}
     */
    public static function fromBytes(string $filename, string $bytes, ?string $contentType = null, ?string $contentId = null): array
    {
        $attachment = ['filename' => $filename, 'content' => base64_encode($bytes)];
        if ($contentType !== null) {
            $attachment['content_type'] = $contentType;
        }
        if ($contentId !== null) {
            $attachment['content_id'] = $contentId;
        }
        return $attachment;
    }

    /**
     * @return array{filename: string, content: string, content_type?: string, content_id?: string}
     */
    public static function fromPath(string $path, ?string $filename = null, ?string $contentType = null, ?string $contentId = null): array
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException("Could not read {$path}");
        }
        return self::fromBytes($filename ?? basename($path), $bytes, $contentType, $contentId);
    }
}
