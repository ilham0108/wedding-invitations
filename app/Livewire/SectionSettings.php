<?php

namespace App\Livewire;

use App\Livewire\Concerns\EditsSectionFields;
use App\Livewire\Concerns\ResolvesEditableInvitation;
use App\Models\Illustration;
use App\Models\InvitationSection;
use App\Services\IllustrationService;
use App\Services\Invitation\DesignRegistry;
use App\Services\Invitation\InvitationSectionService;
use App\Services\Invitation\SectionRegistry;
use App\Services\MediaService;
use App\Services\Subscription\SubscriptionLimitService;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Property editor for one section. The form is generated from SectionRegistry fields
 * and every change is validated and autosaved.
 */
class SectionSettings extends Component
{
    use EditsSectionFields, ResolvesEditableInvitation, WithFileUploads;

    #[Locked]
    public string $sectionUlid = '';

    #[Locked]
    public string $type = '';

    public string $variant = '';

    public ?string $savedAt = null;

    public function mount(string $invitationUlid, string $sectionUlid, SectionRegistry $registry, InvitationSectionService $sections): void
    {
        $this->invitationUlid = $invitationUlid;
        $section = $sections->find($this->invitation(), $sectionUlid);

        $this->sectionUlid = $section->ulid;
        $this->type = $section->type;
        $this->variant = $registry->resolveVariant($section->type, $section->variant);
        $this->config = $registry->sanitize($section->type, $section->configuration ?? []);
    }

    public function updatedVariant(): void
    {
        $this->save();
    }

    public function save(): void
    {
        $this->validateConfig(
            ['variant' => ['required', Rule::in(app(SectionRegistry::class)->variants($this->type))]],
            ['variant' => mb_strtolower(__('Layout'))],
        );

        $section = $this->section();
        app(InvitationSectionService::class)->updateConfiguration($section, $this->withTemplateOnlyValues($section), $this->variant);

        $this->savedAt = now()->format('H:i:s');
        $this->dispatch('invitation-updated', section: $this->sectionUlid);
    }

    /**
     * Gallery "Set cover": copy a photo into the invitation's cover section background.
     */
    public function setAsCover(string $path, int $index, InvitationSectionService $sections): void
    {
        $url = Arr::get($this->config, "{$path}.{$index}");
        $cover = $this->invitation()->sections()->where('type', 'cover')->first();

        if (! is_string($url) || $cover === null) {
            return;
        }

        $sections->updateConfiguration($cover, [...($cover->configuration ?? []), 'background_image' => $url]);
        $this->dispatch('invitation-updated', section: $cover->ulid);
    }

    public function render(SectionRegistry $registry, IllustrationService $illustrations): View
    {
        return view('livewire.section-settings', [
            'label' => $registry->label($this->type),
            'fields' => $registry->clientFields($this->type),
            'variants' => $registry->variants($this->type),
            'designFeatures' => app(DesignRegistry::class)->features($this->invitation()->settings?->design, $this->invitation()->settings?->decoration ?? []),
            ...$this->pickerViewData($illustrations),
        ]);
    }

    protected function storeUploadedImage(TemporaryUploadedFile $file): string
    {
        return app(MediaService::class)->storeImage($this->invitation(), $file, $this->type)->url();
    }

    protected function editableFields(): array
    {
        return app(SectionRegistry::class)->clientFields($this->type);
    }

    protected function canUseIllustration(Illustration $illustration): bool
    {
        return app(SubscriptionLimitService::class)->canUseIllustration($this->invitation()->user()->firstOrFail(), $illustration);
    }

    /**
     * The form values with the template-only fields ("admin_only") put back to what is stored,
     * so a client cannot change them even with a hand-made request.
     *
     * @return array<string, mixed>
     */
    private function withTemplateOnlyValues(InvitationSection $section): array
    {
        $keys = app(SectionRegistry::class)->adminOnlyKeys($this->type);

        return [...Arr::except($this->config, $keys), ...Arr::only($section->configuration ?? [], $keys)];
    }

    private function section(): InvitationSection
    {
        return app(InvitationSectionService::class)->find($this->invitation(), $this->sectionUlid);
    }
}
