<?php

namespace Database\Seeders;

use App\Enums\GuestMessageStatus;
use App\Enums\InvitationStatus;
use App\Enums\RsvpAttendance;
use App\Enums\RsvpStatus;
use App\Models\Guest;
use App\Models\GuestMessage;
use App\Models\Invitation;
use App\Models\InvitationSection;
use App\Models\InvitationTemplate;
use App\Models\Rsvp;
use App\Models\User;
use App\Services\Guest\GuestService;
use App\Services\Invitation\InvitationService;
use Illuminate\Database\Seeder;

class DemoInvitationSeeder extends Seeder
{
    /**
     * One published demo invitation for the demo client (rules.md section 72).
     */
    public function run(InvitationService $invitations, GuestService $guests): void
    {
        $demoUser = User::query()->where('email', config('seeding.demo.email'))->first();

        if ($demoUser === null) {
            $this->command?->warn('Skipped demo invitation: the demo user was not seeded.');

            return;
        }

        $existing = Invitation::withTrashed()->where('slug', 'romeo-juliet')->first();

        if ($existing !== null) {
            if (! $existing->guests()->exists()) {
                $this->seedGuests($existing, $guests);
            }

            if (! $existing->rsvps()->exists()) {
                $this->seedResponses($existing);
            }

            return;
        }

        $template = InvitationTemplate::query()->where('slug', 'elegant-wedding')->firstOrFail();

        $invitation = $invitations->createFromTemplate($demoUser, $template, [
            'title' => 'Romeo & Juliet',
            'event_type' => 'wedding',
            'timezone' => 'Asia/Jakarta',
            'event_date' => '2027-06-12',
            'event_time' => '10:00',
            'venue' => 'Masjid Agung Al-Azhar',
        ]);

        $content = $template->sample_content ?? [];

        $invitation->sections()->get()->each(function (InvitationSection $section) use ($content): void {
            $section->update([
                'configuration' => array_replace($section->configuration ?? [], $content[$section->type] ?? []),
            ]);
        });

        $invitation->forceFill([
            'status' => InvitationStatus::Published,
            'published_at' => now(),
        ])->save();

        $this->seedGuests($invitation, $guests);
        $this->seedResponses($invitation);
    }

    /**
     * RSVP rows matching each answered guest, a few wishes, spread over recent days.
     */
    private function seedResponses(Invitation $invitation): void
    {
        $invitation->guests()
            ->where('rsvp_status', '!=', RsvpStatus::Pending)
            ->get()
            ->each(function (Guest $guest, int $index) use ($invitation): void {
                $attending = $guest->rsvp_status === RsvpStatus::Attending;

                $rsvp = new Rsvp([
                    'name' => $guest->name,
                    'attendance' => $attending ? RsvpAttendance::Attending : RsvpAttendance::NotAttending,
                    'number_of_guests' => $attending ? $guest->number_of_guests : 0,
                ]);
                $rsvp->invitation()->associate($invitation);
                $rsvp->guest()->associate($guest);
                $rsvp->created_at = now()->subDays(($index * 2) % 14);
                $rsvp->save();
            });

        foreach (['Selamat menempuh hidup baru! Semoga menjadi keluarga yang sakinah, mawaddah, warahmah.', 'Turut berbahagia untuk kalian berdua. Sampai jumpa di resepsi!', 'Semoga rumah tangga kalian selalu dipenuhi tawa dan berkah.'] as $index => $text) {
            $message = new GuestMessage([
                'name' => fake()->firstName(),
                'message' => $text,
                'status' => $index < 2 ? GuestMessageStatus::Approved : GuestMessageStatus::Pending,
            ]);
            $message->invitation()->associate($invitation);
            $message->save();
        }
    }

    /**
     * 20 demo guests across groups with a mix of RSVP answers (rules.md section 72).
     */
    private function seedGuests(Invitation $invitation, GuestService $guests): void
    {
        $groups = ['Family', 'Friends', 'Office', 'VIP'];
        $rsvps = [RsvpStatus::Attending, RsvpStatus::Attending, RsvpStatus::NotAttending, RsvpStatus::Pending];

        foreach (range(1, 20) as $number) {
            $guest = $guests->create($invitation, [
                'name' => fake()->name(),
                'phone' => '0812'.str_pad((string) (10000000 + $number), 8, '0', STR_PAD_LEFT),
                'group' => $groups[$number % count($groups)],
                'number_of_guests' => ($number % 3) + 1,
            ]);

            $guest->forceFill(['rsvp_status' => $rsvps[$number % count($rsvps)]])->save();
        }
    }
}
