<?php

namespace App\Services\Template\HtmlDesign;

use App\Services\Invitation\DesignRegistry;
use App\Services\Invitation\RenderedInvitation;
use App\Support\CoverGuest;
use App\Support\CoverTitle;
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

        $html = $this->nodes($section['nodes'], $config, $invitation);

        if ($type === 'cover' && ! $this->hasGuestName($section['nodes'])) {
            $html = $this->withGuestLine($html, $config, $invitation);
        }

        return '<div class="'.e($section['class']).'">'.$html.'</div>';
    }

    /**
     * Every design greets the guest above the cover button. A cover without <inv-guest-name> gets the shared
     * "Kepada Yth." lines right before the element marked data-inv-effect="next", else at its end. The markup
     * here is the importer's, with values escaped, so the last "<" before the mark opens that element.
     *
     * @param  array<string, mixed>  $config
     */
    private function withGuestLine(string $html, array $config, RenderedInvitation $invitation): string
    {
        $guest = view('invitation.partials.cover-guest', ['config' => $config, 'invitation' => $invitation])->render();
        $mark = strpos($html, ' data-inv-next');
        $start = $mark === false ? false : strrpos(substr($html, 0, $mark), '<');

        return $start === false ? $html.$guest : substr($html, 0, $start).$guest.substr($html, $start);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function hasGuestName(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (($node['kind'] === 'component' && $node['name'] === 'guest') || $this->hasGuestName($node['nodes'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $values
     */
    private function nodes(array $nodes, array $values, RenderedInvitation $invitation): string
    {
        return collect($nodes)->map(fn (array $node): string => match ($node['kind']) {
            'html' => $node['value'],
            // The cover title follows the cover's "title_layout" (only the cover's values carry it).
            'text' => $node['key'] === 'title' && CoverTitle::layout($values['title_layout'] ?? null) !== null
                ? view('invitation.partials.cover-title', ['names' => (string) $values['title'], 'layout' => CoverTitle::layout($values['title_layout'])])->render()
                : $this->text($values[$node['key']] ?? '', $node['lines'] ?? false),
            'url' => e((string) SafeUrl::filter($values[$node['key']] ?? null)),
            'if' => filled($values[$node['key']] ?? null) ? $this->nodes($node['nodes'], $values, $invitation) : '',
            'repeat' => collect(is_array($values[$node['key']] ?? null) ? $values[$node['key']] : [])
                ->map(fn (mixed $item): string => is_array($item) ? $this->nodes($node['nodes'], $item, $invitation) : '')
                ->implode(''),
            'component' => $node['name'] === 'guest' ? e(CoverGuest::name($values, $invitation->guestName)) : '',
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
