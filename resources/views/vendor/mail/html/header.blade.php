@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{-- Brand logo (LogoService::emailUrl, a PNG on APP_URL); the alt text names the site when images are blocked. --}}
<img src="{{ app(\App\Services\LogoService::class)->emailUrl() }}" class="logo" width="180" alt="{{ trim($slot) }}">
</a>
</td>
</tr>
