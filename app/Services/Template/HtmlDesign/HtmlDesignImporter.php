<?php

namespace App\Services\Template\HtmlDesign;

use App\Enums\TemplateStatus;
use App\Models\InvitationTemplate;
use App\Models\UploadedDesign;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Invitation\DesignRegistry;
use App\Services\Template\TemplateSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Stores a design package that passed HtmlDesignInspector and creates a draft template that uses it.
 */
class HtmlDesignImporter
{
    /**
     * Fields whose text in the HTML also starts every new invitation: wording and pictures of the design.
     * Names, dates, places and people stay sample content, so they never reach a client's invitation.
     */
    private const DEFAULT_FIELDS = [
        'cover' => ['subtitle', 'button_label', 'background_image'],
        'greeting' => ['emblem', 'opening', 'message'],
        'couple' => ['heading', 'opening', 'intro'],
        'event' => ['heading'],
        'closing' => ['heading', 'message', 'sign_off'],
    ];

    public function __construct(
        private HtmlDesignInspector $inspector,
        private TemplateSchema $schema,
        private AuditLogger $auditLogger,
    ) {}

    public static function newKey(): string
    {
        return 'html-'.Str::lower(Str::random(10));
    }

    /**
     * @throws ValidationException when the package does not pass the inspection.
     */
    public function import(string $path, string $name, int $categoryId, User $admin): InvitationTemplate
    {
        $key = self::newKey();
        $result = $this->inspector->inspect($path, $key);

        if ($result['errors'] !== []) {
            throw ValidationException::withMessages(['package' => $result['errors']]);
        }

        $disk = Storage::disk('public');
        $directory = "designs/{$key}";

        try {
            foreach ($result['files'] as $file => $contents) {
                $disk->put("{$directory}/{$file}", $contents);
            }

            $disk->put("{$directory}/style.css", $result['css']);

            return DB::transaction(function () use ($key, $name, $categoryId, $admin, $result, $directory): InvitationTemplate {
                UploadedDesign::query()->create([
                    'key' => $key,
                    'name' => $name,
                    'directory' => $directory,
                    'sections' => $result['sections'],
                    'created_by' => $admin->getKey(),
                ]);
                DesignRegistry::flushUploaded();

                $template = InvitationTemplate::query()->create([
                    'template_category_id' => $categoryId,
                    'name' => $name,
                    'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
                    ...$this->schema->validate([
                        'design' => $key,
                        'schema' => ['sections' => array_keys($result['sections'])],
                        'theme' => ['preset' => 'elegant'],
                        'default_configuration' => $this->defaults($result['samples']),
                        'sample_content' => $result['samples'],
                    ]),
                    'status' => TemplateStatus::Draft,
                ]);

                $this->auditLogger->record('template.imported_html', $admin, $template, ['design' => $key]);

                return $template;
            });
        } catch (Throwable $exception) {
            $disk->deleteDirectory($directory);
            DesignRegistry::flushUploaded();

            throw $exception;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $samples
     * @return array<string, array<string, mixed>>
     */
    private function defaults(array $samples): array
    {
        return collect($samples)
            ->map(fn (array $sample, string $type): array => array_intersect_key($sample, array_flip(self::DEFAULT_FIELDS[$type] ?? [])))
            ->filter()
            ->all();
    }
}
