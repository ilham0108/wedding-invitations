<?php

namespace Tests\Feature;

use App\Jobs\ProcessGuestImport;
use App\Livewire\GuestImport;
use App\Models\Guest;
use App\Models\Invitation;
use App\Services\Guest\GuestImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesInvitations;
use Tests\Concerns\InteractsWithPlans;
use Tests\TestCase;

class GuestImportTest extends TestCase
{
    use CreatesInvitations, InteractsWithPlans, RefreshDatabase;

    private function importer(Invitation $invitation): Testable
    {
        return Livewire::actingAs($invitation->user)->test(GuestImport::class, ['invitationUlid' => $invitation->ulid]);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function csv(array $rows, string $name = 'guests.csv'): UploadedFile
    {
        $content = implode("\n", array_map(fn (array $row): string => implode(',', $row), $rows));

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_preview_reports_valid_and_invalid_rows_before_saving(): void
    {
        $invitation = $this->createInvitation();
        Guest::factory()->for($invitation)->create(['name' => 'Existing', 'phone' => '6281299999999']);

        $component = $this->importer($invitation)->set('file', $this->csv([
            ['Name', 'Phone', 'Email', 'Group', 'Guests'],
            ['Robert', '0812-1111-1111', 'robert@example.com', 'Family', '2'],
            ['Lisa', '081222222222', '', 'Friends', ''],
            ['', '081233333333', '', '', ''],
            ['Bad Phone', '12', '', '', ''],
            ['Dup In File', '0812-1111-1111', '', '', ''],
            ['Already There', '081299999999', '', '', ''],
            ['Bad Email', '', 'not-an-email', '', ''],
            ['Too Many', '', '', '', '99'],
        ]));

        $preview = $component->get('preview');

        $this->assertSame(8, $preview['total']);
        $this->assertSame(2, $preview['valid_count']);
        $this->assertSame(6, $preview['invalid_count']);
        $this->assertSame([4, 5, 6, 7, 8, 9], array_column($preview['invalid'], 'row'));
        $this->assertSame(1, Guest::query()->count(), 'Nothing is saved before confirmation.');

        $component->call('confirm')->assertSet('preview', null)->assertDispatched('guests-imported');

        $this->assertSame(['Already There' => false, 'Existing' => true, 'Lisa' => true, 'Robert' => true], [
            'Already There' => $invitation->guests()->where('name', 'Already There')->exists(),
            'Existing' => $invitation->guests()->where('name', 'Existing')->exists(),
            'Lisa' => $invitation->guests()->where('name', 'Lisa')->exists(),
            'Robert' => $invitation->guests()->where('name', 'Robert')->exists(),
        ]);

        $robert = $invitation->guests()->where('name', 'Robert')->with('group')->sole();
        $this->assertSame('6281211111111', $robert->phone);
        $this->assertSame(2, $robert->number_of_guests);
        $this->assertSame('Family', $robert->group->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'guest.imported', 'auditable_id' => $invitation->id]);
    }

    public function test_indonesian_headers_are_understood(): void
    {
        $invitation = $this->createInvitation();

        $this->importer($invitation)
            ->set('file', $this->csv([['Nama', 'No HP', 'Grup', 'Jumlah'], ['Budi', '081234500000', 'Keluarga', '3']]))
            ->call('confirm');

        $budi = $invitation->guests()->with('group')->sole();
        $this->assertSame('Budi', $budi->name);
        $this->assertSame(3, $budi->number_of_guests);
        $this->assertSame('Keluarga', $budi->group->name);
    }

    public function test_excel_files_are_imported(): void
    {
        $invitation = $this->createInvitation();

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([['Name', 'Phone', 'Guests'], ['Excel Guest', 6281234500001, 2]]);
        $path = tempnam(sys_get_temp_dir(), 'guests').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $this->importer($invitation)
            ->set('file', UploadedFile::fake()->createWithContent('guests.xlsx', (string) file_get_contents($path)))
            ->assertHasNoErrors()
            ->call('confirm');

        $guest = $invitation->guests()->sole();
        $this->assertSame('Excel Guest', $guest->name);
        $this->assertSame('6281234500001', $guest->phone);

        @unlink($path);
    }

    public function test_non_spreadsheet_files_are_rejected(): void
    {
        $invitation = $this->createInvitation();

        $this->importer($invitation)
            ->set('file', UploadedFile::fake()->create('evil.php', 5, 'application/x-php'))
            ->assertHasErrors('file')
            ->assertSet('preview', null);
    }

    public function test_preview_token_cannot_be_tampered_with(): void
    {
        $invitation = $this->createInvitation();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->importer($invitation)
            ->set('file', $this->csv([['Name'], ['Robert']]))
            ->set('preview.token', 'forged');
    }

    public function test_large_imports_are_queued(): void
    {
        Queue::fake();
        $invitation = $this->createInvitation();
        $this->unlimited($invitation->user);

        $rows = [['Name', 'Phone']];
        foreach (range(1, GuestImportService::QUEUE_THRESHOLD + 1) as $number) {
            $rows[] = ["Guest {$number}", '0813'.str_pad((string) $number, 8, '0', STR_PAD_LEFT)];
        }

        $this->importer($invitation)
            ->set('file', $this->csv($rows))
            ->call('confirm')
            ->assertDispatched('notify', type: 'info', message: (GuestImportService::QUEUE_THRESHOLD + 1).' guests are being imported in the background.');

        Queue::assertPushed(ProcessGuestImport::class, fn (ProcessGuestImport $job): bool => count($job->rows) === GuestImportService::QUEUE_THRESHOLD + 1);
    }

    public function test_import_template_can_be_downloaded(): void
    {
        $invitation = $this->createInvitation();

        $response = $this->actingAs($invitation->user)->get('/dashboard/guests/import-template')->assertOk();

        $this->assertStringContainsString('Name,Phone,Email,Group,Guests', $response->streamedContent());
    }
}
