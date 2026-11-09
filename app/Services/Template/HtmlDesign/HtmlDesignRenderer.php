<?php

namespace App\Services\Template\HtmlDesign;

use App\Services\Invitation\DesignRegistry;
use App\Services\Invitation\RenderedInvitation;
use App\Support\SafeUrl;

/**
 * Turns the tree stored for an uploaded design into a section's HTML. The tree's markup was checked at import;
 * every value from the invitation is escaped here, and links go through SafeUrl.
 */
class HtmlDesignRenderer
{
    public function __construct(private DesignRegistry $designs) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function section(string $design, string $type, array $config, RenderedInvitation $invitation): string
    {
        $section = $this->designs->uploaded($design)['sections'][$type] ?? null;

        if ($section === null) {
            return '';
        }

        // The cover title and the greeting's names fall back like the other designs do.
        $config = match ($type) {
            'cover' => [...$config, 'title' => ($config['title'] ?? '') ?: $invitation->title],
            'greeting' => [...$config, 'names' => ($config['names'] ?? '') ?: $invitation->names()],
            default => $config,
        };

        return '<div class="'.e($section['class']).'">'.$this->nodes($section['nodes'], $config, $invitation).'</div>';
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $values
     */
    private function nodes(array $nodes, array $values, RenderedInvitation $invitation): string
    {
        return collect($nodes)->map(fn (array $node): string => match ($node['kind']) {
            'html' => $node['value'],
            'text' => $this->text($values[$node['key']] ?? '', $node['lines'] ?? false),
            'url' => e((string) SafeUrl::filter($values[$node['key']] ?? null)),
            'if' => filled($values[$node['key']] ?? null) ? $this->nodes($node['nodes'], $values, $invitation) : '',
            'repeat' => collect(is_array($values[$node['key']] ?? null) ? $values[$node['key']] : [])
                ->map(fn (mixed $item): string => is_array($item) ? $this->nodes($node['nodes'], $item, $invitation) : '')
                ->implode(''),
            'component' => $node['name'] === 'guest' ? e((string) $invitation->guestName) : '',
            default => '',
        })->implode('');
    }

    private function text(mixed $value, bool $lines): string
    {
        // Some fields are normalized to a list of lines (closing "inviters").
        $text = e(is_array($value) ? implode("\n", array_filter($value, 'is_string')) : (is_scalar($value) ? (string) $value : ''));

        return $lines ? nl2br($text, false) : $text;
    }
}
