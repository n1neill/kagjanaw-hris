<?php

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\AnnouncementPublishedNotification;
use Illuminate\Support\Facades\Notification;

describe('posting', function () {
    it('publishes an announcement to everyone and notifies active users', function () {
        Notification::fake();
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->withAccount()->create();
        $inactive = User::factory()->employee()->inactive()->create();
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($hrOfficer)->post(route('announcements.store'), [
            'title' => 'Flag ceremony on Monday',
            'body' => "All employees are expected at 7:30 AM.\nWear the official uniform.",
            'audience' => 'everyone',
            'action' => 'publish',
        ]);

        $announcement = Announcement::sole();
        $response->assertRedirect(route('announcements.show', $announcement));
        expect($announcement->isPublished())->toBeTrue()
            ->and($announcement->created_by)->toBe($hrOfficer->id);
        Notification::assertSentTo([$employee->user, $admin], AnnouncementPublishedNotification::class);
        Notification::assertNotSentTo([$inactive, $hrOfficer], AnnouncementPublishedNotification::class);
    });

    it('notifies only the chosen offices', function () {
        Notification::fake();
        $hrOfficer = User::factory()->hrOfficer()->create();
        $treasury = Department::factory()->create();
        $inTreasury = Employee::factory()->withAccount()->create(['department_id' => $treasury->id]);
        $elsewhere = Employee::factory()->withAccount()->create();

        $this->actingAs($hrOfficer)->post(route('announcements.store'), [
            'title' => 'Treasury meeting',
            'body' => 'Meeting at 2 PM.',
            'audience' => 'departments',
            'departments' => [$treasury->id],
            'action' => 'publish',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($inTreasury->user, AnnouncementPublishedNotification::class);
        Notification::assertNotSentTo($elsewhere->user, AnnouncementPublishedNotification::class);
    });

    it('shows the form with the offices to choose from', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $treasury = Department::factory()->create(['name' => 'Municipal Treasurer\'s Office']);
        $announcement = Announcement::factory()->forDepartments($treasury)->create();

        $this->actingAs($hrOfficer)->get(route('announcements.create'))->assertOk()->assertSee('Municipal Treasurer&#039;s Office', false);
        $this->actingAs($hrOfficer)->get(route('announcements.edit', $announcement))->assertOk()->assertSee($announcement->title);
    });

    it('requires an office when addressing selected offices', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->post(route('announcements.store'), [
            'title' => 'Meeting',
            'body' => 'Meeting at 2 PM.',
            'audience' => 'departments',
            'action' => 'publish',
        ]);

        $response->assertSessionHasErrors(['departments' => 'Choose at least one office.']);
        expect(Announcement::count())->toBe(0);
    });

    it('saves a draft without notifying, and notifies once it is published', function () {
        Notification::fake();
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->withAccount()->create();
        $fields = ['title' => 'Draft memo', 'body' => 'To follow.', 'audience' => 'everyone'];

        $this->actingAs($hrOfficer)->post(route('announcements.store'), [...$fields, 'action' => 'draft']);
        $announcement = Announcement::sole();

        expect($announcement->published_at)->toBeNull();
        Notification::assertNothingSent();

        $this->actingAs($hrOfficer)->put(route('announcements.update', $announcement), [...$fields, 'action' => 'publish']);
        $this->actingAs($hrOfficer)->put(route('announcements.update', $announcement), [...$fields, 'title' => 'Memo (corrected)', 'action' => 'publish']);

        Notification::assertSentToTimes($employee->user, AnnouncementPublishedNotification::class, 1);
        expect($announcement->refresh()->title)->toBe('Memo (corrected)');
    });

    it('is managed only by the HR Office', function (Role $role) {
        $user = User::factory()->withRole($role)->create();
        $announcement = Announcement::factory()->create();

        $this->actingAs($user)->get(route('announcements.create'))->assertForbidden();
        $this->actingAs($user)->post(route('announcements.store'), ['title' => 'x', 'body' => 'y', 'audience' => 'everyone', 'action' => 'publish'])->assertForbidden();
        $this->actingAs($user)->put(route('announcements.update', $announcement), ['title' => 'x', 'body' => 'y', 'audience' => 'everyone', 'action' => 'publish'])->assertForbidden();
        $this->actingAs($user)->delete(route('announcements.destroy', $announcement))->assertForbidden();
    })->with([
        'system administrator' => Role::SystemAdministrator,
        'department head' => Role::DepartmentHead,
        'employee' => Role::Employee,
    ]);
});

describe('reading', function () {
    it('shows employees the current announcements addressed to them, pinned first', function () {
        $treasury = Department::factory()->create();
        $employee = Employee::factory()->withAccount()->create(['department_id' => $treasury->id]);
        $older = Announcement::factory()->create(['published_at' => now()->subDays(3)]);
        $pinned = Announcement::factory()->pinned()->create(['published_at' => now()->subDays(5)]);
        $forTreasury = Announcement::factory()->forDepartments($treasury)->create(['published_at' => now()->subDay()]);
        $forOthers = Announcement::factory()->forDepartments(Department::factory()->create())->create();
        $draft = Announcement::factory()->draft()->create();
        $expired = Announcement::factory()->expired()->create();

        $response = $this->actingAs($employee->user)->get(route('announcements.index'));

        expect($response->viewData('announcements')->pluck('id')->all())->toBe([$pinned->id, $forTreasury->id, $older->id]);
        $this->actingAs($employee->user)->get(route('dashboard'))->assertSee($forTreasury->title)->assertDontSee($forOthers->title);
    });

    it('hides drafts, expired announcements and other offices\' announcements', function (string $state) {
        $employee = Employee::factory()->withAccount()->create();
        $announcement = match ($state) {
            'draft' => Announcement::factory()->draft()->create(),
            'expired' => Announcement::factory()->expired()->create(),
            'other office' => Announcement::factory()->forDepartments(Department::factory()->create())->create(),
        };

        $this->actingAs($employee->user)->get(route('announcements.show', $announcement))->assertNotFound();
    })->with(['draft', 'expired', 'other office']);

    it('shows the HR Office every announcement with its status', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $draft = Announcement::factory()->draft()->create(['title' => 'Unpublished memo']);

        $this->actingAs($hrOfficer)->get(route('announcements.index'))->assertSee('Unpublished memo')->assertSee('Draft');
        $this->actingAs($hrOfficer)->get(route('announcements.show', $draft))->assertOk();
    });

    it('keeps the message as plain text', function () {
        $employee = Employee::factory()->withAccount()->create();
        $announcement = Announcement::factory()->create(['body' => '<script>alert(1)</script>']);

        $this->actingAs($employee->user)->get(route('announcements.show', $announcement))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    });
});

it('lists a user\'s own notifications', function () {
    $employee = Employee::factory()->withAccount()->create();
    $other = Employee::factory()->withAccount()->create();
    $employee->user->notify(new AnnouncementPublishedNotification(Announcement::factory()->create(['title' => 'Flag ceremony'])));
    $other->user->notify(new AnnouncementPublishedNotification(Announcement::factory()->create(['title' => 'Treasury meeting'])));

    $this->actingAs($employee->user)->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('New announcement: Flag ceremony')
        ->assertDontSee('Treasury meeting');
});
