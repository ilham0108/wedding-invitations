<?php

namespace App\View\Components;

use App\Models\InvitationTemplate;
use App\Services\Invitation\ThemeResolver;
use App\Support\SafeUrl;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Template showcase card. Without an uploaded thumbnail it shows the template's cover:
 * its background photo with title, subtitle and date (default content overlaid with
 * sample content, as on the template preview), or a themed placeholder without a photo.
 */
class TemplateCard extends Component
{
    /** @var array<string, string> */
    public array $theme;

    public string $styleVariables;

    public string $sampleTitle;

    public ?string $coverImage;

    public string $coverSubtitle;

    public string $coverDate;

    public bool $coverOverlay;

    public string $fontStylesheetUrl;

    public function __construct(
        public InvitationTemplate $template,
        public ?string $useUrl = null,
        // A tall cover with only the name under it (marketing showcase).
        public bool $compact = false,
        ThemeResolver $themes = new ThemeResolver,
    ) {
        $this->theme = $themes->resolve($template->theme ?? []);
        $this->styleVariables = $themes->cssVariables($this->theme);
        $this->fontStylesheetUrl = $themes->fontStylesheetUrl($this->theme);

        $cover = [...($template->default_configuration['cover'] ?? []), ...($template->sample_content['cover'] ?? [])];

        $this->sampleTitle = (string) (($cover['title'] ?? '') ?: $template->name);
        $this->coverImage = SafeUrl::filter($cover['background_image'] ?? null);
        $this->coverSubtitle = (string) ($cover['subtitle'] ?? '');
        $this->coverDate = (string) ($cover['date_text'] ?? '');
        $this->coverOverlay = (bool) ($cover['overlay'] ?? true);
    }

    public function render(): View|Closure|string
    {
        return view('components.template-card');
    }
}
