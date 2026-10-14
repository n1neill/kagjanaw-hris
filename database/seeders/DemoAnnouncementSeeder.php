<?php

namespace Database\Seeders;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\User;
use App\Services\AnnouncementService;
use Illuminate\Database\Seeder;

/**
 * A few announcements from the HR Office, published through the real
 * service so the demo accounts also get the bell notifications.
 * Development only.
 */
class DemoAnnouncementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(AnnouncementService $announcements): void
    {
        if (Announcement::query()->exists()) {
            return;
        }

        $hr = User::query()->where('username', '2015-0101')->sole();
        $healthOffice = Department::query()->where('code', 'MHO')->sole();

        $announcements->create([
            'title' => 'Flag raising ceremony every Monday',
            'body' => "All employees are expected at the municipal grounds at 7:45 AM every Monday for the flag raising ceremony.\n\nPlease wear the prescribed office uniform. Attendance is checked by office.",
            'audience' => AnnouncementAudience::Everyone,
            'is_pinned' => true,
            'expires_on' => null,
            'departments' => [],
        ], true, $hr);

        $announcements->create([
            'title' => 'Updating of 201 files',
            'body' => "The HR Office is updating the 201 files of all employees.\n\nPlease submit a copy of any new certificate of eligibility, training certificate or change in civil status to the HRMO before the end of the month.",
            'audience' => AnnouncementAudience::Everyone,
            'is_pinned' => false,
            'expires_on' => now()->endOfMonth()->toDateString(),
            'departments' => [],
        ], true, $hr);

        $announcements->create([
            'title' => 'Health Office duty schedule for the coming month',
            'body' => 'The duty schedule of the Rural Health Unit staff for next month is posted at the Health Office. Please check your assigned dates and coordinate swaps with your department head.',
            'audience' => AnnouncementAudience::Departments,
            'is_pinned' => false,
            'expires_on' => null,
            'departments' => [$healthOffice->id],
        ], true, $hr);
    }
}
