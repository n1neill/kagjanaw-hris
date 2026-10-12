<?php

namespace Database\Seeders;

use App\Enums\AllowanceCoverage;
use App\Enums\ContributionType;
use App\Models\AllowanceType;
use App\Models\DeductionRate;
use App\Models\SalaryGrade;
use App\Models\TaxBracket;
use Illuminate\Database\Seeder;

/**
 * Payroll reference data: the Salary Schedule, contribution rates, the
 * withholding tax table and PERA. Safe to re-run; existing rows HR has
 * edited are left alone.
 *
 * VERIFY ALL RATES AGAINST CURRENT ISSUANCES before production use. HR can
 * edit every value and add newer tables with their effectivity dates.
 */
class PayrollReferenceDataSeeder extends Seeder
{
    public const SALARY_SCHEDULE_EFFECTIVE = '2026-01-01';

    public const SALARY_SCHEDULE_BASIS = 'EO No. 64, s. 2024, third tranche (DBM NBC No. 601, Jan. 22, 2026, Annex A)';

    /**
     * Third-tranche monthly Salary Schedule for civilian personnel, effective
     * January 1, 2026 (national rates, in pesos). Grade => steps 1-8.
     *
     * @var array<int, list<int>>
     */
    public const SALARY_SCHEDULE = [
        1 => [14634, 14730, 14849, 14968, 15089, 15211, 15333, 15456],
        2 => [15522, 15636, 15752, 15869, 15986, 16103, 16223, 16342],
        3 => [16486, 16610, 16732, 16856, 16982, 17106, 17234, 17360],
        4 => [17506, 17636, 17767, 17898, 18031, 18163, 18298, 18433],
        5 => [18581, 18720, 18858, 18998, 19137, 19280, 19423, 19565],
        6 => [19716, 19862, 20009, 20158, 20307, 20456, 20609, 20761],
        7 => [20914, 21069, 21224, 21382, 21539, 21699, 21859, 22022],
        8 => [22423, 22627, 22832, 23038, 23246, 23456, 23668, 23883],
        9 => [24329, 24523, 24720, 24917, 25117, 25318, 25521, 25725],
        10 => [26917, 27131, 27347, 27565, 27786, 28007, 28230, 28456],
        11 => [31705, 31820, 32109, 32401, 32697, 32998, 33302, 33611],
        12 => [33947, 34069, 34357, 34648, 34943, 35242, 35544, 35850],
        13 => [36125, 36283, 36599, 36919, 37244, 37572, 37904, 38241],
        14 => [38764, 39141, 39523, 39910, 40300, 40696, 41097, 41503],
        15 => [42178, 42594, 43015, 43442, 43874, 44310, 44753, 45202],
        16 => [45694, 46152, 46615, 47084, 47559, 48040, 48528, 49020],
        17 => [49562, 50066, 50576, 51092, 51614, 52144, 52678, 53221],
        18 => [53818, 54371, 54933, 55499, 56075, 56657, 57246, 57842],
        19 => [59153, 59966, 60793, 61632, 62486, 63353, 64236, 65132],
        20 => [66052, 66970, 67904, 68853, 69818, 70772, 71727, 72671],
        21 => [73303, 74337, 75388, 76456, 77542, 78645, 79692, 80831],
        22 => [81796, 82963, 84151, 85356, 86582, 87746, 89011, 90295],
        23 => [91306, 92622, 93962, 95330, 96823, 98341, 99883, 101318],
        24 => [102603, 104209, 105841, 107500, 109185, 110898, 112533, 114301],
        25 => [116643, 118469, 120326, 122212, 124131, 126079, 128061, 130073],
        26 => [131807, 133870, 135968, 138100, 140268, 142469, 144707, 146983],
        27 => [148940, 151273, 153644, 155906, 158353, 160235, 162752, 165310],
        28 => [167129, 169752, 172418, 174797, 177545, 180339, 182660, 185537],
        29 => [187531, 190482, 193480, 196528, 199624, 202005, 205191, 208430],
        30 => [210718, 214038, 217207, 220425, 223691, 227224, 230595, 234240],
        31 => [300961, 306691, 312532, 318182, 323938, 329989, 336092, 342310],
        32 => [356237, 363257, 370418, 377359, 384805, 392400, 400150, 408055],
        33 => [449157, 462329],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedSalarySchedule();
        $this->seedContributionRates();
        $this->seedTaxTable();
        $this->seedAllowances();
    }

    private function seedSalarySchedule(): void
    {
        if (SalaryGrade::query()->whereDate('effective_from', self::SALARY_SCHEDULE_EFFECTIVE)->exists()) {
            return;
        }

        $now = now();
        $rows = [];

        foreach (self::SALARY_SCHEDULE as $grade => $steps) {
            foreach ($steps as $index => $amount) {
                $rows[] = [
                    'effective_from' => self::SALARY_SCHEDULE_EFFECTIVE,
                    'grade' => $grade,
                    'step' => $index + 1,
                    'amount' => $amount.'.00',
                    'legal_basis' => self::SALARY_SCHEDULE_BASIS,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        SalaryGrade::insert($rows);
    }

    private function seedContributionRates(): void
    {
        $rates = [
            [
                'type' => ContributionType::Gsis,
                'effective_from' => '1997-06-01',
                'employee_rate' => '9.00',
                'employer_rate' => '12.00',
                'salary_floor' => null,
                'salary_ceiling' => null,
                'legal_basis' => 'RA 8291 (GSIS Act of 1997): personal share 9%, government share 12% of monthly salary',
            ],
            [
                'type' => ContributionType::PhilHealth,
                'effective_from' => '2024-01-01',
                'employee_rate' => '2.50',
                'employer_rate' => '2.50',
                'salary_floor' => '10000.00',
                'salary_ceiling' => '100000.00',
                'legal_basis' => 'RA 11223 (UHC Act): 5% premium shared equally, on salaries from ₱10,000 to ₱100,000',
            ],
            [
                'type' => ContributionType::PagIbig,
                'effective_from' => '2024-02-01',
                'employee_rate' => '2.00',
                'employer_rate' => '2.00',
                'salary_floor' => null,
                'salary_ceiling' => '10000.00',
                'legal_basis' => 'HDMF Circular No. 460: 2% each on monthly compensation up to ₱10,000',
            ],
        ];

        foreach ($rates as $rate) {
            DeductionRate::query()->firstOrCreate(
                ['type' => $rate['type'], 'effective_from' => $rate['effective_from']],
                $rate,
            );
        }
    }

    private function seedTaxTable(): void
    {
        $basis = 'RA 10963 (TRAIN), revised withholding tax table on compensation effective January 1, 2023 (monthly)';
        $brackets = [
            ['over' => '0.00', 'base_tax' => '0.00', 'rate' => '0.00'],
            ['over' => '20833.00', 'base_tax' => '0.00', 'rate' => '15.00'],
            ['over' => '33333.00', 'base_tax' => '1875.00', 'rate' => '20.00'],
            ['over' => '66667.00', 'base_tax' => '8541.80', 'rate' => '25.00'],
            ['over' => '166667.00', 'base_tax' => '33541.80', 'rate' => '30.00'],
            ['over' => '666667.00', 'base_tax' => '183541.80', 'rate' => '35.00'],
        ];

        foreach ($brackets as $bracket) {
            TaxBracket::query()->firstOrCreate(
                ['effective_from' => '2023-01-01', 'over' => $bracket['over']],
                [...$bracket, 'legal_basis' => $basis],
            );
        }
    }

    private function seedAllowances(): void
    {
        AllowanceType::query()->firstOrCreate(['code' => AllowanceType::PERA], [
            'name' => 'Personnel Economic Relief Allowance (PERA)',
            'description' => 'Granted to civilian government personnel under the annual General Appropriations Act and DBM-CSC guidelines. Not taxable; reduced for days of absence without pay.',
            'monthly_amount' => '2000.00',
            'coverage' => AllowanceCoverage::AllSalaryGrade,
            'is_taxable' => false,
            'reduced_for_unpaid_days' => true,
            'is_active' => true,
        ]);
    }
}
