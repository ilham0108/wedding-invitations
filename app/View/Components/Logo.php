<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Brand logo from config/branding.php; falls back to the app name as text when the file is missing.
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
        public string $height = 'h-8',
        public string $textClass = 'text-xl',
    ) {
        $path = ltrim((string) config('branding.logo'), '/');

        $this->name = (string) config('app.name');
        $this->src = $path !== '' && is_file(public_path($path))
            ? asset($path).'?v='.filemtime(public_path($path))
            : null;
    }

    public function render(): View|Closure|string
    {
        return view('components.logo');
    }
}
