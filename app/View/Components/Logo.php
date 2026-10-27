<?php

namespace App\View\Components;

use App\Services\LogoService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Brand logo: the one uploaded at /admin/settings, else the file from config/branding.php,
 * else the app name as text.
 */
class Logo extends Component
{
    public ?string $src;

    public string $name;

    /**
     * @param  string  $height  Tailwind height class for the image, e.g. "h-8".
     * @param  string  $textClass  Classes for the text fallback.
     */
    public function __construct(
        LogoService $logo,
        public string $height = 'h-8',
        public string $textClass = 'text-xl',
    ) {
        $this->name = (string) config('app.name');
        $this->src = $logo->url();
    }

    public function render(): View|Closure|string
    {
        return view('components.logo');
    }
}
