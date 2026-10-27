<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Services\WhatsApp\MessageRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class WhatsAppMessageRendererTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    public function test_variables_are_replaced_with_guest_and_event_data(): void
    {
        $invitation = $this->createInvitation(sections: ['cover', 'event']);
        $invitation->sections()->where('type', 'event')->first()->update(['configuration' => ['events' => [
            ['title' => 'Reception', 'date' => '2027-06-12', 'start_time' => '19:00', 'end_time' => '21:00', 'venue' => 'Grand Ballroom'],
        ]]]);
        $guest = Guest::factory()->for($invitation)->create(['name' => 'Mr. Robert']);
        $renderer = app(MessageRenderer::class);

        $message = $renderer->render(
            'Hi {{guest_name}}! {{ event_name }} on {{event_date}} at {{event_time}}, {{venue}}. {{invitation_url}} | {{rsvp_url}} | {{unknown}}',
            $guest,
            $invitation,
            $renderer->eventContext($invitation),
        );

        $link = route('invitations.personal', ['slug' => $invitation->slug, 'token' => $guest->token]);

        $this->assertSame("Hi Mr. Robert! John & Jane on Saturday, 12 June 2027 at 19:00 – 21:00, Grand Ballroom. {$link} | {$link}#rsvp-form | {{unknown}}", $message);
    }

    public function test_unknown_variables_are_reported(): void
    {
        $renderer = app(MessageRenderer::class);

        $this->assertSame(['guest_nmae', 'price'], $renderer->unknownVariables('Hi {{guest_nmae}} {{guest_name}} {{price}} {{price}}'));
        $this->assertSame([], $renderer->unknownVariables(MessageRenderer::defaultTemplate()));
    }

    public function test_missing_event_section_leaves_event_fields_empty(): void
    {
        $invitation = $this->createInvitation(sections: ['cover']);

        $this->assertSame(
            ['event_name' => 'John & Jane', 'event_date' => '', 'event_time' => '', 'venue' => ''],
            app(MessageRenderer::class)->eventContext($invitation),
        );
    }
}
