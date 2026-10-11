<?php

namespace Database\Seeders;

use App\Enums\LeaveCreditSource;
use App\Enums\Sex;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;

/**
 * Leave types under the CSC Omnibus Rules on Leave (CSC MC No. 41, s. 1998,
 * as amended) and special laws, as listed on CS Form No. 6 (Application for
 * Leave, revised 2020).
 *
 * VERIFY AGAINST CURRENT OFFICIAL RULES. Every value here can be changed by
 * HR under Leave → Leave Types; re-running this seeder does not overwrite
 * types that already exist.
 */
class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->types() as $order => $type) {
            LeaveType::firstOrCreate(['code' => $type['code']], [...$type, 'sort_order' => ($order + 1) * 10]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function types(): array
    {
        return [
            [
                'code' => LeaveType::VACATION,
                'name' => 'Vacation Leave',
                'description' => 'Sec. 51, Rule XVI, Omnibus Rules Implementing Book V of EO 292. 15 days a year, earned at 1.25 days a month, cumulative. File at least five days before the leave whenever possible.',
                'credit_source' => LeaveCreditSource::VacationCredits,
                'advance_notice_days' => 5,
            ],
            [
                'code' => LeaveType::FORCED,
                'name' => 'Mandatory / Forced Leave',
                'description' => 'Sec. 25, Rule XVI, Omnibus Rules. Employees with 10 or more days of vacation leave must take at least 5 days a year. Charged to vacation leave; days not taken are forfeited at year end unless cancelled for the exigency of the service.',
                'credit_source' => LeaveCreditSource::VacationCredits,
                'annual_days' => '5.000',
                'advance_notice_days' => 5,
            ],
            [
                'code' => LeaveType::SICK,
                'name' => 'Sick Leave',
                'description' => 'Sec. 43, Rule XVI, Omnibus Rules. 15 days a year, earned at 1.25 days a month, cumulative. File immediately on return to work. Sick leave beyond the sick leave balance is charged to vacation leave.',
                'credit_source' => LeaveCreditSource::SickCredits,
                'allows_past_dates' => true,
                'attachment_required_over_days' => 5,
                'attachment_hint' => 'Attach a medical certificate for sick leave of more than five days.',
            ],
            [
                'code' => 'ML',
                'name' => 'Maternity Leave',
                'description' => 'RA 11210 (105-Day Expanded Maternity Leave Law). 105 calendar days with full pay; 15 more days for solo mothers; 60 days for miscarriage or emergency termination of pregnancy. Not charged to leave credits.',
                'credit_source' => LeaveCreditSource::NotCharged,
                'max_days_per_request' => '105.000',
                'counts_calendar_days' => true,
                'allows_half_day' => false,
                'allows_past_dates' => true,
                'eligible_sex' => Sex::Female,
                'attachment_required_over_days' => 0,
                'attachment_hint' => 'Attach proof of pregnancy (medical certificate) or of delivery.',
            ],
            [
                'code' => 'PL',
                'name' => 'Paternity Leave',
                'description' => 'RA 8187 (Paternity Leave Act of 1996). 7 working days for each of the first four deliveries of the legitimate spouse. Not charged to leave credits.',
                'credit_source' => LeaveCreditSource::NotCharged,
                'max_days_per_request' => '7.000',
                'allows_past_dates' => true,
                'eligible_sex' => Sex::Male,
                'attachment_required_over_days' => 0,
                'attachment_hint' => 'Attach the child\'s birth certificate or proof of delivery, and the marriage contract.',
            ],
            [
                'code' => 'SPL',
                'name' => 'Special Privilege Leave',
                'description' => 'CSC MC No. 6, s. 1996, as amended. Up to 3 days a year for personal milestones, parental, filial and domestic obligations, personal transactions, calamity, accident, hospitalization or relocation. Not cumulative and not convertible to cash.',
                'credit_source' => LeaveCreditSource::AnnualAllotment,
                'annual_days' => '3.000',
                'allows_past_dates' => true,
            ],
            [
                'code' => 'SOLO',
                'name' => 'Solo Parent Leave',
                'description' => 'RA 8972 as amended by RA 11861 (Expanded Solo Parents Welfare Act). 7 working days a year for solo parents with at least six months of service. Not cumulative.',
                'credit_source' => LeaveCreditSource::AnnualAllotment,
                'annual_days' => '7.000',
                'attachment_required_over_days' => 0,
                'attachment_hint' => 'Attach a valid Solo Parent Identification Card.',
            ],
            [
                'code' => 'STUDY',
                'name' => 'Study Leave',
                'description' => 'CSC MC No. 21, s. 2004. Up to 6 months with pay to complete a master\'s degree or review for a bar or board examination, under a study leave contract. Not charged to leave credits.',
                'credit_source' => LeaveCreditSource::NotCharged,
                'max_days_per_request' => '180.000',
                'counts_calendar_days' => true,
                'allows_half_day' => false,
                'attachment_required_over_days' => 0,
                'attachment_hint' => 'Attach the approved study leave contract.',
            ],
            [
                'code' => 'VAWC',
                'name' => '10-Day VAWC Leave',
                'description' => 'RA 9262 (Anti-Violence Against Women and Their Children Act), Sec. 43. Up to 10 days with pay for women victims, extendible when necessary. Not charged to leave credits.',
                'credit_source' => LeaveCreditSource::NotCharged,
                'max_days_per_request' => '10.000',
                'allows_past_dates' => true,
                'eligible_sex' => Sex::Female,
                'attachment_required_over_days' => 0,
                'attachment_hint' => 'Attach a Barangay, Temporary or Permanent Protection Order, or a certification from the barangay, police or prosecutor.',
            ],
            [
                'code' => 'RP',
                'name' => 'Rehabilitation Privilege',
                'description' => 'Sec. 55, Rule XVI, Omnibus Rules. Up to 6 months with pay for wounds or injuries incurred in the performance of duty. Not charged to leave credits.',
                'credit_source' => LeaveCreditSource::NotCharged,
                'max_days_per_request' => '180.000',
                'counts_calendar_days' => true,
                'allows_half_day' => false,
                'allows_past_dates' => true,
                'attachment_required_over_days' => 0,
                'attachment_hint' => 'Attach the police or incident report and a medical certificate.',
            ],
            [
                'code' => 'SLBW',
                'name' => 'Special Leave Benefits for Women',
                'description' => 'RA 9710 (Magna Carta of Women); CSC MC No. 25, s. 2010. Up to 2 months with full pay after surgery for a gynecological disorder, for women with at least six months of service in the last twelve months.',
                'credit_source' => LeaveCreditSource::NotCharged,
                'max_days_per_request' => '60.000',
                'counts_calendar_days' => true,
                'allows_half_day' => false,
                'allows_past_dates' => true,
                'eligible_sex' => Sex::Female,
                'attachment_required_over_days' => 0,
                'attachment_hint' => 'Attach a medical certificate stating the surgery and the recovery period.',
            ],
            [
                'code' => 'SEL',
                'name' => 'Special Emergency (Calamity) Leave',
                'description' => 'CSC MC No. 2, s. 2012, as amended. Up to 5 days a year for employees directly affected by a calamity in an area declared under a state of calamity, taken within 30 days of the calamity. Not charged to leave credits.',
                'credit_source' => LeaveCreditSource::AnnualAllotment,
                'annual_days' => '5.000',
                'allows_past_dates' => true,
            ],
            [
                'code' => 'AL',
                'name' => 'Adoption Leave',
                'description' => 'RA 8552 as amended by RA 11642 (Domestic Administrative Adoption and Alternative Child Care Act). 60 days with pay for the adoptive parent. Not charged to leave credits.',
                'credit_source' => LeaveCreditSource::NotCharged,
                'max_days_per_request' => '60.000',
                'counts_calendar_days' => true,
                'allows_half_day' => false,
                'allows_past_dates' => true,
                'attachment_required_over_days' => 0,
                'attachment_hint' => 'Attach the Pre-Adoptive Placement Authority or the order of adoption.',
            ],
            [
                'code' => 'WL',
                'name' => 'Wellness Leave',
                'description' => 'Up to 5 days a year for mental and physical wellness. Not cumulative and not convertible to cash. Verify the governing CSC memorandum circular before use.',
                'credit_source' => LeaveCreditSource::AnnualAllotment,
                'annual_days' => '5.000',
                'allows_past_dates' => true,
            ],
        ];
    }
}
