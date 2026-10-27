<?php

namespace App\View\Components;

use App\Services\FaviconService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * <link> tags for the site icon uploaded at /admin/settings; renders nothing until one is uploaded.
 */
class Favicon extends Component
{
    public ?string $icon;

    public ?string $appleIcon;

    public function __construct(FaviconService $favicon)
    {
        $this->icon = $favicon->url(32);
        $this->appleIcon = $favicon->url(180);
    }

    public function shouldRender(): bool
    {
        return $this->icon !== null;
    }

    public function render(): View|Closure|string
    {
        return view('components.favicon');
    }
}
