<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonthlySessionPollingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_status_without_include_movements_returns_counts_but_not_the_heavy_lists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 09:00:00', 'Asia/Manila'));
        $secretary = $this->secretarySession();
        $this->withSession($secretary)->postJson(route('forms.monthly.session.start'))->assertOk();

        $light = $this->withSession($secretary)->getJson(route('forms.monthly.session.status'))->assertOk();
        $light->assertJsonPath('session.movement_count', 0)
            ->assertJsonPath('session.activity_count', 0)
            ->assertJsonMissingPath('movements')
            ->assertJsonMissingPath('activities');

        $this->withSession($secretary)
            ->getJson(route('forms.monthly.session.status', ['include_movements' => 1]))
            ->assertOk()
            ->assertJsonPath('movements', [])
            ->assertJsonPath('activities', []);
    }

    public function test_widget_polls_lightly_pauses_in_hidden_tabs_and_loads_the_list_only_for_an_open_dialog(): void
    {
        $html = $this->withSession($this->secretarySession())
            ->get(route('forms.monthly.deliberation'))
            ->assertOk()
            ->getContent();
        $script = $this->widgetScript($html);

        $this->assertStringNotContainsString('fetch(`${statusUrl}?include_movements=1`, {', $script);
        $this->assertStringContainsString('const full = dialog.open;', $script);
        $this->assertStringContainsString('fetch(full ? `${statusUrl}?include_movements=1` : statusUrl', $script);
        $this->assertStringContainsString('if (true) {', $script);
        $this->assertStringContainsString('if (document.hidden) return;', $script);
    }

    public function test_monthly_session_page_runs_one_light_poll_shared_with_the_widget(): void
    {
        $html = $this->withSession($this->secretarySession())
            ->get(route('forms.monthly.session'))
            ->assertOk()
            ->getContent();

        // The widget's own interval is switched off on this page.
        $this->assertStringContainsString('if (false) {', $this->widgetScript($html));

        $page = substr($html, strpos($html, "getElementById('monthlyMovementRows')"));
        $page = substr($page, 0, strpos($page, '</script>'));
        $this->assertSame(1, substr_count($page, 'setInterval('));
        $this->assertStringContainsString("fetch(statusUrl, { headers: { 'Accept': 'application/json' } })", $page);
        $this->assertStringContainsString("new CustomEvent('monthly-session-status'", $page);
        $this->assertStringContainsString('if (!document.hidden) poll();', $page);
        // The full list is requested only after the cheap count shows a change.
        $countCheck = strpos($page, 'Number(body.dataset.renderedCount) !== result.session.movement_count');
        $fullFetch = strpos($page, '?include_movements=1');
        $this->assertNotFalse($countCheck);
        $this->assertNotFalse($fullFetch);
        $this->assertGreaterThan($countCheck, $fullFetch);
    }

    private function widgetScript(string $html): string
    {
        $start = strpos($html, "const startUrl = ");
        $this->assertNotFalse($start, 'Monthly session widget script was not rendered.');

        return substr($html, $start, strpos($html, '</script>', $start) - $start);
    }

    private function secretarySession(): array
    {
        $user = User::query()->create([
            'gmail' => 'polling.secretary.'.fake()->unique()->numerify('####').'@example.test',
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'form_generator_allow' => true,
        ]);

        return ['login_user' => ['id' => $user->id, 'gmail' => $user->gmail]];
    }
}
