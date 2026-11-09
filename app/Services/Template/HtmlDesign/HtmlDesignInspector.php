<?php

namespace App\Services\Template\HtmlDesign;

use App\Services\Invitation\SectionRegistry;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use ZipArchive;

/**
 * Reads a design package (index.html, style.css, assets/) and reports everything wrong with it, without storing
 * anything. The HTML becomes a tree of plain data (see HtmlDesignRenderer): it is never run as Blade or PHP,
 * scripts are refused, and every field value is escaped when shown.
 */
class HtmlDesignInspector
{
    /** Sections the first version of the importer turns into dynamic ones. */
    public const SECTIONS = ['cover', 'greeting', 'couple', 'event', 'closing'];

    public const COMPONENTS = ['inv-guest-name' => 'guest'];

    public const EFFECTS = ['animate', 'next'];

    private const FORBIDDEN = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'base', 'svg', 'math', 'template', 'link', 'meta'];

    private const VOID = ['img', 'br', 'hr'];

    private const IMAGES = ['jpg', 'jpeg', 'png', 'webp'];

    private const MAX_ENTRIES = 300;

    private const MAX_ASSET_BYTES = 10 * 1024 * 1024;

    private const MAX_TOTAL_BYTES = 50 * 1024 * 1024;

    /** @var array<int, string> */
    private array $errors = [];

    /** @var array<int, string> */
    private array $warnings = [];

    /** @var array<int, string> */
    private array $assets = [];

    /** @var array<string, true> */
    private array $usedAssets = [];

    private string $assetBase = '';

    private int $fieldCount = 0;

    public function __construct(private SectionRegistry $registry) {}

    /**
     * @return array{errors: array<int, string>, warnings: array<int, string>, summary: array{sections: array<int, string>, fields: int, assets: int}, sections: array<string, array<string, mixed>>, samples: array<string, array<string, mixed>>, css: string, files: array<string, string>}
     */
    public function inspect(string $path, string $key): array
    {
        [$this->errors, $this->warnings, $this->assets, $this->usedAssets, $this->fieldCount] = [[], [], [], [], 0];
        $this->assetBase = "/storage/designs/{$key}/";

        $empty = ['sections' => [], 'samples' => [], 'css' => '', 'files' => []];
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return $this->result([...$empty], __('This file is not a valid zip archive.'));
        }

        $files = $this->readFiles($zip);
        $zip->close();

        if ($files === null || ! isset($files['index.html'], $files['style.css'])) {
            return $this->result([...$empty]);
        }

        $assetFiles = array_diff_key($files, ['index.html' => true, 'style.css' => true]);
        $this->assets = array_keys($assetFiles);

        [$sections, $samples] = $this->readHtml($files['index.html']);

        $scoper = new CssScoper('.inv-design-'.$key, $this->assets, $this->assetBase);
        $css = $scoper->process($files['style.css']);
        array_push($this->errors, ...$css['errors']);
        $this->usedAssets += $scoper->usedAssets;

        foreach (array_diff($this->assets, array_keys($this->usedAssets)) as $unused) {
            $this->warnings[] = __(':file is in the package but never used.', ['file' => $unused]);
        }

        return $this->result(['sections' => $sections, 'samples' => $samples, 'css' => $css['css'], 'files' => $assetFiles]);
    }

    /**
     * @param  array{sections: array<string, array<string, mixed>>, samples: array<string, array<string, mixed>>, css: string, files: array<string, string>}  $compiled
     * @return array{errors: array<int, string>, warnings: array<int, string>, summary: array{sections: array<int, string>, fields: int, assets: int}, sections: array<string, array<string, mixed>>, samples: array<string, array<string, mixed>>, css: string, files: array<string, string>}
     */
    private function result(array $compiled, ?string $error = null): array
    {
        if ($error !== null) {
            $this->errors[] = $error;
        }

        return [
            ...$compiled,
            'errors' => array_values(array_unique($this->errors)),
            'warnings' => array_values(array_unique($this->warnings)),
            'summary' => ['sections' => array_keys($compiled['sections']), 'fields' => $this->fieldCount, 'assets' => count($this->assets)],
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function readFiles(ZipArchive $zip): ?array
    {
        if ($zip->numFiles > self::MAX_ENTRIES) {
            $this->errors[] = __('The package has too many files (at most :max).', ['max' => self::MAX_ENTRIES]);

            return null;
        }

        $files = [];
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) $stat['name'];

            if (str_ends_with($name, '/') || str_starts_with($name, '__MACOSX/') || basename($name) === '.DS_Store') {
                continue;
            }

            $total += (int) $stat['size'];

            if ($total > self::MAX_TOTAL_BYTES || $stat['size'] > self::MAX_ASSET_BYTES) {
                $this->errors[] = __('The package or one of its files is too large: :file', ['file' => $name]);

                return null;
            }

            if (! $this->isAllowedPath($name)) {
                $this->errors[] = __('":file" is not allowed. A package holds index.html, style.css and images or fonts inside assets/.', ['file' => $name]);

                continue;
            }

            $contents = (string) $zip->getFromIndex($i);

            if (str_starts_with($name, 'assets/') && ! $this->isRealAsset($name, $contents)) {
                $this->errors[] = __(':file is not a real image or font file.', ['file' => $name]);

                continue;
            }

            $files[$name] = $contents;
        }

        foreach (['index.html', 'style.css'] as $required) {
            if (! isset($files[$required])) {
                $this->errors[] = __(':file is missing. It must be at the top level of the zip, not inside a folder.', ['file' => $required]);
            }
        }

        return $files;
    }

    private function isAllowedPath(string $name): bool
    {
        if (in_array($name, ['index.html', 'style.css'], true)) {
            return true;
        }

        return ! str_contains($name, '..') && preg_match('#^assets/[A-Za-z0-9._/-]+\.(jpe?g|png|webp|woff2?)$#i', $name) === 1;
    }

    private function isRealAsset(string $name, string $contents): bool
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (in_array($extension, self::IMAGES, true)) {
            return @getimagesizefromstring($contents) !== false;
        }

        return in_array(substr($contents, 0, 4), ['wOFF', 'wOF2'], true);
    }

    /**
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, array<string, mixed>>}
     */
    private function readHtml(string $html): array
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET | LIBXML_COMPACT);
        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//script') as $script) {
            $this->errors[] = __('Line :line: <script> is not allowed. Use data-inv-effect for effects.', ['line' => $script->getLineNo()]);
        }

        $sections = [];
        $samples = [];

        foreach ($xpath->query('//section') as $element) {
            /** @var DOMElement $element */
            $type = $element->getAttribute('id');

            if (! in_array($type, self::SECTIONS, true)) {
                $this->errors[] = $this->registry->has($type)
                    ? __('Line :line: section ":type" is not supported yet. Supported: :supported.', ['line' => $element->getLineNo(), 'type' => $type, 'supported' => implode(', ', self::SECTIONS)])
                    : __('Line :line: section id ":type" is not known. Use one of: :supported.', ['line' => $element->getLineNo(), 'type' => $type, 'supported' => implode(', ', self::SECTIONS)]);

                continue;
            }

            if (isset($sections[$type])) {
                $this->errors[] = __('Line :line: section ":type" appears twice.', ['line' => $element->getLineNo(), 'type' => $type]);

                continue;
            }

            $samples[$type] = [];
            $fields = collect($this->registry->fields($type))->keyBy('key')->all();
            $children = $this->children($element, $fields, $samples[$type], $type);
            $sections[$type] = ['class' => $element->getAttribute('class'), 'nodes' => $children];

            // Common fields such as the background photo are drawn by the page, not by the design's HTML.
            $common = array_column($this->registry->commonFields($type), 'key');
            $missing = collect($fields)->except($common)->filter(fn (array $field): bool => in_array($field['type'], ['text', 'textarea', 'image'], true) && empty($field['admin_only']) && ! array_key_exists($field['key'], $samples[$type]))->keys()->all();

            if ($missing !== []) {
                $this->warnings[] = __('Section ":type": these fields are not marked and will not show: :fields', ['type' => $type, 'fields' => implode(', ', $missing)]);
            }
        }

        if ($sections === []) {
            $this->errors[] = __('index.html has no usable <section>. Mark each one with an id such as id="cover".');
        }

        return [$sections, $samples];
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $sample
     * @return array<int, array<string, mixed>>
     */
    private function children(DOMNode $parent, array $fields, array &$sample, string $type, bool $inRepeat = false): array
    {
        $nodes = [];
        $repeated = [];

        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMText) {
                $this->html($nodes, e($child->nodeValue ?? ''));
            } elseif ($child instanceof DOMElement) {
                $name = $child->getAttribute('data-inv-repeat');

                if ($name === '') {
                    $this->element($child, $nodes, $fields, $sample, $type, $inRepeat);
                } elseif (isset($repeated[$name])) {
                    // Later copies of a repeated element only give sample data.
                    $item = [];
                    $discard = [];
                    $this->element($this->withoutRepeat($child), $discard, $fields[$name]['fields'] ?? [], $item, $type, true, silent: true);
                    $sample[$name][] = $item;
                } else {
                    $repeated[$name] = true;
                    $this->repeat($child, $name, $nodes, $fields, $sample, $type, $inRepeat);
                }
            }
        }

        return $nodes;
    }

    private function withoutRepeat(DOMElement $element): DOMElement
    {
        $copy = $element->cloneNode(true);
        $copy->removeAttribute('data-inv-repeat');

        return $copy;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $sample
     */
    private function repeat(DOMElement $element, string $name, array &$nodes, array $fields, array &$sample, string $type, bool $inRepeat): void
    {
        if ($inRepeat || ($fields[$name]['type'] ?? null) !== 'repeater') {
            $this->errors[] = __('Line :line: ":name" cannot be repeated in section ":type".', ['line' => $element->getLineNo(), 'name' => $name, 'type' => $type]);

            return;
        }

        $item = [];
        $children = [];
        $this->element($this->withoutRepeat($element), $children, $fields[$name]['fields'], $item, $type, true);
        $sample[$name] = [$item];
        $nodes[] = ['kind' => 'repeat', 'key' => $name, 'nodes' => $children];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, array<string, mixed>>|array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $sample
     */
    private function element(DOMElement $element, array &$nodes, array $fields, array &$sample, string $type, bool $inRepeat, bool $silent = false): void
    {
        $tag = strtolower($element->tagName);
        $fields = collect($fields)->keyBy('key')->all();

        if (isset(self::COMPONENTS[$tag])) {
            $nodes[] = ['kind' => 'component', 'name' => self::COMPONENTS[$tag]];

            return;
        }

        if (str_starts_with($tag, 'inv-')) {
            $this->errors[] = __('Line :line: <:tag> is not available yet.', ['line' => $element->getLineNo(), 'tag' => $tag]);

            return;
        }

        // <script> is reported once for the whole document in readHtml().
        if ($tag === 'script') {
            return;
        }

        if (in_array($tag, self::FORBIDDEN, true)) {
            $this->errors[] = __('Line :line: <:tag> is not allowed.', ['line' => $element->getLineNo(), 'tag' => $tag]);

            return;
        }

        $field = $element->getAttribute('data-inv-field');
        $definition = null;

        if ($field !== '') {
            $definition = $fields[$field] ?? null;

            if ($definition === null) {
                $this->errors[] = __('Line :line: ":field" is not a field of section ":type".', ['line' => $element->getLineNo(), 'field' => $field, 'type' => $type]);

                return;
            }
        }

        $attributes = $this->attributes($element, $tag, $definition);

        if ($definition !== null) {
            if (! $silent) {
                $this->fieldCount++;
            }

            $this->field($element, $tag, $field, $definition, $attributes, $nodes, $sample);

            return;
        }

        $this->html($nodes, "<{$tag}{$attributes}>");

        if (in_array($tag, self::VOID, true)) {
            return;
        }

        foreach ($this->children($element, $fields, $sample, $type, $inRepeat) as $child) {
            $this->append($nodes, $child);
        }

        $this->html($nodes, "</{$tag}>");
    }

    /**
     * Copies the safe attributes as escaped HTML; a field's own src or href is left out and filled at render time.
     *
     * @param  array<string, mixed>|null  $definition
     */
    private function attributes(DOMElement $element, string $tag, ?array $definition): string
    {
        $output = '';
        $classes = [];
        $data = [];

        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->name);
            $value = $attribute->value;

            if (in_array($name, ['data-inv-field', 'data-inv-value', 'data-inv-repeat'], true)) {
                continue;
            }

            if ($name === 'data-inv-effect') {
                if (! in_array($value, self::EFFECTS, true)) {
                    $this->errors[] = __('Line :line: effect ":effect" does not exist. Available: :effects.', ['line' => $element->getLineNo(), 'effect' => $value, 'effects' => implode(', ', self::EFFECTS)]);
                } elseif ($value === 'animate') {
                    $classes[] = 'inv-animate';
                } else {
                    $data[] = ' data-inv-next';
                }

                continue;
            }

            if (str_starts_with($name, 'on') || in_array($name, ['srcdoc', 'srcset', 'formaction'], true)) {
                $this->errors[] = __('Line :line: attribute ":name" is not allowed.', ['line' => $element->getLineNo(), 'name' => $name]);

                continue;
            }

            if ($name === 'style' && preg_match('/url\s*\(|expression\s*\(|javascript:|@import/i', $value) === 1) {
                $this->errors[] = __('Line :line: this style value is not allowed. Move it to style.css.', ['line' => $element->getLineNo()]);

                continue;
            }

            if ($name === 'class') {
                $classes[] = $value;

                continue;
            }

            if (in_array($name, ['src', 'href'], true)) {
                if ($definition !== null && in_array($definition['type'], ['image', 'url'], true)) {
                    continue;
                }

                $value = $this->link($value, $name, $element);

                if ($value === null) {
                    continue;
                }
            }

            $output .= ' '.$name.'="'.e($value).'"';
        }

        if ($classes !== []) {
            $output .= ' class="'.e(implode(' ', $classes)).'"';
        }

        return $output.implode('', $data);
    }

    private function link(string $value, string $attribute, DOMElement $element): ?string
    {
        $value = trim($value);
        $path = ltrim($value, './');

        if (in_array($path, $this->assets, true)) {
            $this->usedAssets[$path] = true;

            return $this->assetBase.$path;
        }

        if ($attribute === 'href' && preg_match('#^(https?://|mailto:|tel:|\#)#i', $value) === 1) {
            return $value;
        }

        $this->errors[] = str_starts_with($path, 'assets/')
            ? __('Line :line: :file is used but is not in the package.', ['line' => $element->getLineNo(), 'file' => $path])
            : __('Line :line: ":value" must be a file inside the package.', ['line' => $element->getLineNo(), 'value' => mb_strimwidth($value, 0, 60, '...')]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $sample
     */
    private function field(DOMElement $element, string $tag, string $key, array $definition, string $attributes, array &$nodes, array &$sample): void
    {
        $line = $element->getLineNo();
        $inner = [];

        switch ($definition['type']) {
            case 'image':
                if ($tag !== 'img') {
                    $this->errors[] = __('Line :line: ":field" is an image field and must be on an <img>.', ['line' => $line, 'field' => $key]);

                    return;
                }

                $source = trim($element->getAttribute('src'));
                $url = $source === '' ? null : $this->link($source, 'src', $element);
                $sample[$key] = $url;
                $this->append($nodes, ['kind' => 'if', 'key' => $key, 'nodes' => [['kind' => 'html', 'value' => "<img{$attributes} src=\""], ['kind' => 'url', 'key' => $key], ['kind' => 'html', 'value' => '">']]]);

                return;

            case 'url':
                if ($tag !== 'a') {
                    $this->errors[] = __('Line :line: ":field" is a link field and must be on an <a>.', ['line' => $line, 'field' => $key]);

                    return;
                }

                $sample[$key] = trim($element->getAttribute('href')) ?: null;
                $body = trim($element->textContent);
                $this->append($nodes, ['kind' => 'if', 'key' => $key, 'nodes' => [['kind' => 'html', 'value' => "<a{$attributes} href=\""], ['kind' => 'url', 'key' => $key], ['kind' => 'html', 'value' => '">'.e($body).'</a>']]]);

                return;

            case 'text':
            case 'textarea':
            case 'date':
            case 'time':
                break;

            default:
                $this->errors[] = __('Line :line: field ":field" cannot be marked in HTML.', ['line' => $line, 'field' => $key]);

                return;
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) !== 'br') {
                $this->errors[] = __('Line :line: ":field" may only contain text.', ['line' => $line, 'field' => $key]);

                return;
            }
        }

        $text = trim((string) preg_replace('/[ \t\r\n]+/u', ' ', $element->textContent));

        if (in_array($definition['type'], ['date', 'time'], true)) {
            $text = trim($element->getAttribute('data-inv-value')) ?: $text;
            $pattern = $definition['type'] === 'date' ? '/^\d{4}-\d{2}-\d{2}$/' : '/^\d{2}:\d{2}$/';

            if (preg_match($pattern, $text) !== 1) {
                $this->errors[] = __('Line :line: ":field" needs data-inv-value in the format :format.', ['line' => $line, 'field' => $key, 'format' => $definition['type'] === 'date' ? 'YYYY-MM-DD' : 'HH:MM']);

                return;
            }
        }

        $sample[$key] = $text;
        $inner[] = ['kind' => 'text', 'key' => $definition['type'] === 'date' ? 'date_label' : $key, 'lines' => $definition['type'] === 'textarea'];
        $this->append($nodes, ['kind' => 'if', 'key' => $key, 'nodes' => [['kind' => 'html', 'value' => "<{$tag}{$attributes}>"], ...$inner, ['kind' => 'html', 'value' => "</{$tag}>"]]]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function html(array &$nodes, string $value): void
    {
        $this->append($nodes, ['kind' => 'html', 'value' => $value]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $node
     */
    private function append(array &$nodes, array $node): void
    {
        $last = array_key_last($nodes);

        if ($node['kind'] === 'html' && $last !== null && $nodes[$last]['kind'] === 'html') {
            $nodes[$last]['value'] .= $node['value'];

            return;
        }

        $nodes[] = $node;
    }
}
