<?php

namespace Tests\Feature;

use App\Models\Misc;
use App\Models\Pricing;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiscNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_can_be_added_and_edited_without_duplication(): void
    {
        Settings::create(['id' => 1]);
        Pricing::create(['service_id' => 'misc0001', 'service_type' => 5, 'price' => 10, 'currency' => 'EUR', 'term' => 1, 'as_usd' => 10, 'usd_per_month' => 10, 'next_due_date' => '2027-01-01']);
        $misc = Misc::create(['id' => 'misc0001', 'name' => 'Example']);
        $this->actingAs(User::factory()->create());
        $this->put(route('misc.note.update', $misc), ['note' => '<script>alert(1)</script>'])->assertRedirect(route('misc.show', $misc));
        $this->get(route('misc.show', $misc))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $this->put(route('misc.note.update', $misc), ['note' => 'Updated note'])->assertRedirect();
        $this->assertDatabaseCount('notes', 1);
        $this->assertDatabaseHas('notes', ['service_id' => $misc->id, 'note' => 'Updated note']);
        $this->get(route('misc.show', $misc))->assertSee('Updated note');
    }

    public function test_misc_is_available_in_general_note_forms(): void
    {
        Settings::create(['id' => 1]);
        Pricing::create(['service_id' => 'misc0002', 'service_type' => 5, 'price' => 10, 'currency' => 'EUR', 'term' => 1, 'as_usd' => 10, 'usd_per_month' => 10, 'next_due_date' => '2027-01-01']);
        $misc = Misc::create(['id' => 'misc0002', 'name' => 'Example Misc']);
        $this->actingAs(User::factory()->create());
        $this->get(route('notes.create'))->assertOk()->assertSee('Example Misc (Misc)');
        $this->post(route('notes.store'), ['service_id' => $misc->id, 'note' => 'Details'])->assertRedirect(route('notes.index'));
        $note = \App\Models\Note::where('service_id', $misc->id)->firstOrFail();
        $this->get(route('notes.edit', $note))->assertOk()->assertSee('value="misc0002" selected', false);
        $this->get(route('misc.show', $misc))->assertOk()->assertSee('Details');
    }
}
