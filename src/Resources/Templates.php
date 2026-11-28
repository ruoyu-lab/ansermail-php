<?php

declare(strict_types=1);

namespace Anser\Resources;

use Anser\Http;
use Anser\Page;

/**
 * Every call takes the template's id or its alias. Send with one: `$anser->emails->send(['template' => …])`.
 */
final class Templates extends Resource
{
    /**
     * A new template with only a draft; publish it before sending with it. `subject`, `html` and `text` are
     * Handlebars; `alias` is 1–100 letters, digits, `.`, `_` and `-`, unique in the workspace.
     *
     * @param array<string, mixed> $params name, alias, subject, html, text, from, reply_to
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed> the template: id, name, alias, draft, published, has_unpublished_changes…
     */
    public function create(array $params, array $options = []): array
    {
        return self::object($this->http->request('POST', '/templates', [], $params, $options));
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function get(string $template, array $options = []): array
    {
        return self::object($this->http->request('GET', self::path($template), options: $options));
    }

    /**
     * One page, newest first; `q` matches the name and the alias.
     *
     * @param array{limit?: int, after?: string, before?: string, q?: string} $params
     * @param array{timeout?: float, max_retries?: int} $options
     */
    public function list(array $params = [], array $options = []): Page
    {
        return Page::from($this->http->request('GET', '/templates', $params, options: $options));
    }

    /**
     * Every template matching, page after page.
     *
     * @param array{limit?: int, q?: string} $params
     * @param array{timeout?: float, max_retries?: int} $options
     * @return \Generator<int, array<string, mixed>>
     */
    public function listAll(array $params = [], array $options = []): \Generator
    {
        return $this->http->iterate('/templates', $params, $options);
    }

    /**
     * Change the name, the alias or the draft; `null` (or `''`) clears a field. Emails keep using the published
     * version until you publish.
     *
     * @param array<string, mixed> $changes name, alias, subject, html, text, from, reply_to
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function update(string $template, array $changes, array $options = []): array
    {
        return self::object($this->http->request('PATCH', self::path($template), [], $changes, $options));
    }

    /**
     * Emails already sent with it stay, with `template` null.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function delete(string $template, array $options = []): array
    {
        return self::object($this->http->request('DELETE', self::path($template), options: $options));
    }

    /**
     * Save the draft as the next version; emails use it from now on. Throws with the code `no_changes` when there
     * is nothing new.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function publish(string $template, array $options = []): array
    {
        return self::object($this->http->request('POST', self::path($template) . '/publish', options: $options));
    }

    /**
     * A copy of the draft, without the alias and unpublished; named "Copy of …" unless you name it.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function duplicate(string $template, ?string $name = null, array $options = []): array
    {
        $body = $name === null ? new \stdClass() : ['name' => $name];
        return self::object($this->http->request('POST', self::path($template) . '/duplicate', [], $body, $options));
    }

    /**
     * The published versions, newest first.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return list<array<string, mixed>>
     */
    public function versions(string $template, array $options = []): array
    {
        return self::data($this->http->request('GET', self::path($template) . '/versions', options: $options));
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function version(string $template, int $version, array $options = []): array
    {
        return self::object($this->http->request('GET', self::path($template) . "/versions/{$version}", options: $options));
    }

    /**
     * Put a version's content back into the draft; publish to send it again.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function restore(string $template, int $version, array $options = []): array
    {
        $path = self::path($template) . "/versions/{$version}/restore";
        return self::object($this->http->request('POST', $path, options: $options));
    }

    /**
     * Render content with variables without storing anything; every field's problems come back together in
     * `errors`.
     *
     * @param array{subject?: string|null, html?: string|null, text?: string|null, variables?: array<string, mixed>} $params
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed> subject, html, text, variables, suggested_variables (a sample shaped like the
     *     template uses its variables, to edit), errors
     */
    public function render(array $params, array $options = []): array
    {
        if (($params['variables'] ?? null) === []) {
            unset($params['variables']);
        }
        return self::object($this->http->request('POST', '/templates/render', [], $params, $options));
    }

    private static function path(string $template): string
    {
        return '/templates/' . Http::path($template);
    }
}
