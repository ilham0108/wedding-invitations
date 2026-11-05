<?php

namespace App\View\Components;

use App\Models\InvitationTemplate;
use App\Services\Invitation\ThemeResolver;
use App\Support\SafeUrl;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Template showcase card. It shows the uploaded thumbnail, or else the template's real cover: the still page of
 * TemplateCardController in a frame the size of a phone (9:14), scaled down to the card's width by the view.
 */
class TemplateCard extends Component
{
    public const FRAME_WIDTH = 390;

    public const FRAME_HEIGHT = 607;

    public string $styleVariables;

    /** Shown behind the frame while it loads. */
    public ?string $coverImage;

    public string $frameUrl;

    public function __construct(
        public InvitationTemplate $template,
        public ?string $useUrl = null,
        // A tall cover with only the name under it (marketing showcase).
        public bool $compact = false,
        ThemeResolver $themes = new ThemeResolver,
    ) {
        $this->styleVariables = $themes->cssVariables($themes->resolve($template->theme ?? []));
        $this->coverImage = SafeUrl::filter(($template->sample_content['cover'] ?? [])['background_image'] ?? ($template->default_configuration['cover'] ?? [])['background_image'] ?? null);
        // The version refreshes the browser's copy as soon as the template is saved.
        $this->frameUrl = route('templates.card', ['template' => $template, 'v' => $template->updated_at?->timestamp]);
    }

    public function render(): View|Closure|string
    {
        return view('components.template-card');
    }
}
