<?php

namespace App\Services\Template\HtmlDesign;

/**
 * Checks an uploaded design's stylesheet and limits every rule to that design's pages (.inv-design-{key}).
 * External files are refused; url() may only point at files of the package, which are rewritten to where they are stored.
 */
final class CssScoper
{
    private const GROUPING = ['@media', '@supports', '@container'];

    private const KEPT = ['@keyframes', '@-webkit-keyframes', '@font-face'];

    /** @var array<int, string> */
    private array $errors = [];

    /** @var array<string, true> */
    public array $usedAssets = [];

    /**
     * @param  array<int, string>  $assets  Package paths ("assets/photo.jpg").
     */
    public function __construct(private string $scope, private array $assets, private string $assetBase) {}

    /**
     * @return array{css: string, errors: array<int, string>}
     */
    public function process(string $css): array
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? '';

        return ['css' => $this->block($css), 'errors' => array_values(array_unique($this->errors))];
    }

    private function block(string $css): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($css);

        while ($offset < $length) {
            $brace = strpos($css, '{', $offset);
            $semicolon = strpos($css, ';', $offset);

            // A statement such as @import or @charset ends before any block starts.
            if ($semicolon !== false && ($brace === false || $semicolon < $brace)) {
                $this->statement(trim(substr($css, $offset, $semicolon - $offset)));
                $offset = $semicolon + 1;

                continue;
            }

            if ($brace === false) {
                break;
            }

            $prelude = trim(substr($css, $offset, $brace - $offset));
            $end = $this->closingBrace($css, $brace);

            if ($end === null) {
                $this->errors[] = __('style.css: a block is never closed.');

                break;
            }

            $output .= $this->rule($prelude, substr($css, $brace + 1, $end - $brace - 1));
            $offset = $end + 1;
        }

        return $output;
    }

    private function statement(string $statement): void
    {
        if ($statement !== '' && ! str_starts_with(strtolower($statement), '@charset')) {
            $this->errors[] = __('style.css: ":rule" is not allowed. Put every file inside the package.', ['rule' => mb_strimwidth($statement, 0, 60, '...')]);
        }
    }

    private function closingBrace(string $css, int $open): ?int
    {
        $depth = 0;

        for ($i = $open, $length = strlen($css); $i < $length; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}' && --$depth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function rule(string $prelude, string $body): string
    {
        $name = strtolower((string) strtok($prelude, " \t\r\n("));

        if (in_array($name, self::GROUPING, true)) {
            return $prelude.'{'.$this->block($body).'}';
        }

        if (in_array($name, self::KEPT, true)) {
            return $prelude.'{'.$this->declarations($body).'}';
        }

        if (str_starts_with($name, '@')) {
            $this->errors[] = __('style.css: ":rule" is not allowed.', ['rule' => $name]);

            return '';
        }

        if (str_contains($body, '{')) {
            $this->errors[] = __('style.css: nested rules are not supported (:selector).', ['selector' => mb_strimwidth($prelude, 0, 60, '...')]);

            return '';
        }

        return $this->selectors($prelude).'{'.$this->declarations($body).'}';
    }

    private function selectors(string $selectors): string
    {
        return collect(preg_split('/,(?![^(]*\))/', $selectors) ?: [])
            ->map(function (string $selector): string {
                $selector = trim($selector);
                $page = preg_replace('/^(:root|html|body)(?=$|[\s.#:\[>+~])/i', '', $selector, 1, $count);

                return $count === 1 ? $this->scope.$page : $this->scope.' '.$selector;
            })
            ->implode(',');
    }

    private function declarations(string $body): string
    {
        if (preg_match('/expression\s*\(|behavior\s*:|-moz-binding|javascript:|@import/i', $body) === 1) {
            $this->errors[] = __('style.css: a rule uses a value that is not allowed (:value).', ['value' => mb_strimwidth(trim($body), 0, 60, '...')]);

            return '';
        }

        return (string) preg_replace_callback('/url\(\s*([\'"]?)(.*?)\1\s*\)/i', function (array $match): string {
            $path = ltrim($match[2], './');

            if (! in_array($path, $this->assets, true)) {
                $this->errors[] = __('style.css: ":url" must be a file inside the package.', ['url' => mb_strimwidth($match[2], 0, 60, '...')]);

                return 'none';
            }

            $this->usedAssets[$path] = true;

            return 'url("'.$this->assetBase.$path.'")';
        }, $body);
    }
}
