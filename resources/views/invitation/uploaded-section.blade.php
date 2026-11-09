{{-- A section of a design imported from HTML. The markup was checked at import; values are escaped by the renderer. --}}
{!! app(\App\Services\Template\HtmlDesign\HtmlDesignRenderer::class)->section($invitation->design, $section['type'] ?? '', $config, $invitation) !!}
