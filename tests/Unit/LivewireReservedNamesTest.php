<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Livewire\Component;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * In the browser, $wire resolves its own aliases (upload, set, call, refresh, ...) before a
 * component's methods. A method with such a name works in Livewire::test() but silently calls
 * the built-in from wire:click / wire:submit (e.g. "upload" ran $wire.upload() and failed).
 */
class LivewireReservedNamesTest extends TestCase
{
    protected bool $seed = false;

    public function test_components_do_not_use_names_reserved_by_wire(): void
    {
        $reserved = $this->reservedNames();
        $this->assertContains('upload', $reserved, 'Could not read the $wire aliases from the Livewire bundle.');

        foreach (File::allFiles(app_path('Livewire')) as $file) {
            $class = 'App\\Livewire\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

            if (! class_exists($class) || ! is_subclass_of($class, Component::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $names = [
                ...array_map(fn (ReflectionMethod $method): string => $method->name, array_filter(
                    $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                    fn (ReflectionMethod $method): bool => $method->class === $class,
                )),
                ...array_map(fn (ReflectionProperty $property): string => $property->name, array_filter(
                    $reflection->getProperties(ReflectionProperty::IS_PUBLIC),
                    fn (ReflectionProperty $property): bool => $property->class === $class,
                )),
            ];

            $this->assertSame([], array_values(array_intersect($names, $reserved)), "{$class} uses a name that \$wire reserves in the browser.");
        }
    }

    /**
     * @return array<int, string>
     */
    private function reservedNames(): array
    {
        $bundle = File::get(base_path('vendor/livewire/livewire/dist/livewire.esm.js'));
        preg_match('/var aliases = \{(.*?)\};/s', $bundle, $match);
        preg_match_all('/"(\w+)":\s*"\$\w+"/', $match[1] ?? '', $names);

        return $names[1];
    }
}
