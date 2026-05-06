<?php

use App\Helpers\DateHelpers;
use App\Helpers\VolunteerTicketCsvImporter;
use App\Models\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function verhoeffIsValid(string $number): bool
{
    $d = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
        [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
        [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
        [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
        [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
        [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
        [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
        [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
        [9, 8, 7, 6, 5, 4, 3, 2, 1, 0],
    ];

    $p = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
        [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
        [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
        [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
        [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
        [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
        [7, 0, 4, 6, 9, 1, 3, 2, 5, 8],
    ];

    $c = 0;
    $reversed = array_reverse(str_split($number));

    foreach ($reversed as $index => $digit) {
        $c = $d[$c][$p[$index % 8][(int) $digit]];
    }

    return $c === 0;
}

test('importer merges volunteers, handles odd headers, overnight shifts, group zero, and serial rules', function () {
    $csvPath = tempnam(sys_get_temp_dir(), 'volunteer-ticket-import-');

    expect($csvPath)->not->toBeFalse();

    $bom = "\xef\xbb\xbf";

    file_put_contents($csvPath, $bom.implode("\n", [
        '  JOB  ,Shift start date,Shift   start time,Shift end time,Volunteer Identifier,Phone (mobile preferred),First Name,Last.Name,Email,Zip code,Ignored Column',
        'Tidying,2026-05-07,16:45,20:00,VL-111,(555) 222-3333,Alex,Rivera,alex@example.com,55101,foo',
        'Stocking,2026-05-08,22:00,02:00,VL-111,5552223333,Alex,Rivera,alex@example.com,55101,bar',
    ]));

    $summary = app(VolunteerTicketCsvImporter::class)->import($csvPath);

    unlink($csvPath);

    expect($summary['rows_read'])->toBe(2);
    expect($summary['tickets_created'])->toBe(1);
    expect($summary['tickets_updated'])->toBe(1);
    expect($summary['invalid_rows'])->toBe(0);

    $ticket = Ticket::query()->where('vlid', 'VL-111')->first();

    expect($ticket)->not->toBeNull();
    expect($ticket->ps_year)->toBe(DateHelpers::psYearForDate(now()->setDate(2026, 5, 7)));
    expect($ticket->group_zero)->toBeTrue();
    expect($ticket->serial)->toHaveLength(6);
    expect($ticket->serial)->toStartWith('0');
    expect(verhoeffIsValid($ticket->serial))->toBeTrue();
    expect($ticket->shifts)->toBeArray()->toHaveCount(2);
    expect($ticket->shifts[0]['job'])->toBe('Tidying');
    expect($ticket->shifts[1]['job'])->toBe('Stocking');

    $start = now()->parse($ticket->shifts[1]['start'])->setTimezone(config('app.timezone'));
    $end = now()->parse($ticket->shifts[1]['end'])->setTimezone(config('app.timezone'));

    expect($start->format('Y-m-d H:i'))->toBe('2026-05-08 22:00');
    expect($end->format('Y-m-d H:i'))->toBe('2026-05-09 02:00');
});

test('importer merges existing ticket by name and email and recalculates group zero', function () {
    $psYear = DateHelpers::psYearForDate(now()->setDate(2026, 5, 7));

    $existing = Ticket::factory()->create([
        'ps_year' => $psYear,
        'first_name' => 'Dana',
        'last_name' => 'Lane',
        'email' => 'dana@example.com',
        'phone' => null,
        'vlid' => null,
        'group_zero' => false,
        'shifts' => [[
            'job' => 'Greeter',
            'start' => '2026-05-07T13:00:00Z',
            'end' => '2026-05-07T14:00:00Z',
        ]],
    ]);

    $csvPath = tempnam(sys_get_temp_dir(), 'volunteer-ticket-import-');

    expect($csvPath)->not->toBeFalse();

    file_put_contents($csvPath, implode("\n", [
        'Job,Shift start date,Shift start time,Shift end time,Volunteer Identifier,Phone (mobile preferred),First Name,Last Name,Email,Zip code',
        'Set-up,2026-05-07,17:00,18:00,, ,Dana,Lane,dana@example.com,55102',
    ]));

    $summary = app(VolunteerTicketCsvImporter::class)->import($csvPath);

    unlink($csvPath);

    $existing->refresh();

    expect($summary['tickets_created'])->toBe(0);
    expect($summary['tickets_updated'])->toBe(1);
    expect($existing->group_zero)->toBeTrue();
    expect($existing->shifts)->toHaveCount(2);
});

test('importer infers missing shift end time as four hours and supports csvs without phone/end-time columns', function () {
    $csvPath = tempnam(sys_get_temp_dir(), 'volunteer-ticket-import-');

    expect($csvPath)->not->toBeFalse();

    file_put_contents($csvPath, implode("\n", [
        'Job,Shift start date,Shift start time,Parent ID,Sub-Volunteer ID,Volunteer Identifier,Email,First Name,Last Name,Zip code',
        'Black flat pickup and sorting,5/7/2026,5:30 PM,0,5165815,3346872,kathy.mulier@fairview.org,Kathy,Mulier,55304',
        'Black flat pickup and sorting,5/8/2026,9:30 AM,0,5165816,3346872,kathy.mulier@fairview.org,Kathy,Mulier,55304',
    ]));

    $summary = app(VolunteerTicketCsvImporter::class)->import($csvPath);

    unlink($csvPath);

    expect($summary['rows_read'])->toBe(2);
    expect($summary['tickets_created'])->toBe(1);
    expect($summary['tickets_updated'])->toBe(1);
    expect($summary['invalid_rows'])->toBe(0);

    $ticket = Ticket::query()->where('vlid', '3346872')->first();

    expect($ticket)->not->toBeNull();
    expect($ticket->shifts)->toHaveCount(2);
    expect($ticket->group_zero)->toBeTrue();

    $firstStart = now()->parse($ticket->shifts[0]['start'])->setTimezone(config('app.timezone'));
    $firstEnd = now()->parse($ticket->shifts[0]['end'])->setTimezone(config('app.timezone'));
    $secondStart = now()->parse($ticket->shifts[1]['start'])->setTimezone(config('app.timezone'));
    $secondEnd = now()->parse($ticket->shifts[1]['end'])->setTimezone(config('app.timezone'));

    expect($firstStart->format('Y-m-d H:i'))->toBe('2026-05-07 17:30');
    expect($firstEnd->format('Y-m-d H:i'))->toBe('2026-05-07 21:30');
    expect($secondStart->format('Y-m-d H:i'))->toBe('2026-05-08 09:30');
    expect($secondEnd->format('Y-m-d H:i'))->toBe('2026-05-08 13:30');
});

test('importer preserves local shift times from csv rows without explicit end times', function () {
    $csvPath = tempnam(sys_get_temp_dir(), 'volunteer-ticket-import-');

    expect($csvPath)->not->toBeFalse();

    file_put_contents($csvPath, implode("\n", [
        'Job,Shift start date,Shift start time,Parent ID,Sub-Volunteer ID,Volunteer Identifier,Email,First Name,Last Name,Zip code',
        '"Greeter, outdoor",5/9/2026,1:15 PM,0,5167248,3347279,z.baelcyr@gmail.com,Zora,Bael-Cyr,55407',
    ]));

    $summary = app(VolunteerTicketCsvImporter::class)->import($csvPath);

    unlink($csvPath);

    expect($summary['rows_read'])->toBe(1);
    expect($summary['invalid_rows'])->toBe(0);

    $ticket = Ticket::query()->where('vlid', '3347279')->first();

    expect($ticket)->not->toBeNull();
    expect($ticket->shifts)->toHaveCount(1);
    expect($ticket->shifts[0]['job'])->toBe('Greeter, outdoor');
    expect($ticket->shifts[0]['start'])->toBe('2026-05-09 13:15:00');
    expect($ticket->shifts[0]['end'])->toBe('2026-05-09 17:15:00');
});

test('importer assigns zero-date shifts to april first of the current year', function () {
    $csvPath = tempnam(sys_get_temp_dir(), 'volunteer-ticket-import-');

    expect($csvPath)->not->toBeFalse();

    file_put_contents($csvPath, implode("\n", [
        'Job,Shift start date,Shift start time,Parent ID,Sub-Volunteer ID,Volunteer Identifier,Email,First Name,Last Name,Zip code',
        'Catalog writing and proofing? [offline],0000-00-00,12:00 AM,0,5237836,1172526,sseabergwood@gmail.com,Sophie,Seaberg-Wood,55118',
    ]));

    $summary = app(VolunteerTicketCsvImporter::class)->import($csvPath);

    unlink($csvPath);

    expect($summary['rows_read'])->toBe(1);
    expect($summary['invalid_rows'])->toBe(0);

    $ticket = Ticket::query()->where('vlid', '1172526')->first();

    expect($ticket)->not->toBeNull();
    expect($ticket->ps_year)->toBe(DateHelpers::psYearForDate(now()->startOfYear()->month(4)->day(1)));
    expect($ticket->shifts)->toHaveCount(1);
    expect($ticket->shifts[0]['start'])->toBe(now()->startOfYear()->month(4)->day(1)->format('Y-m-d').' 00:00:00');
    expect($ticket->shifts[0]['end'])->toBe(now()->startOfYear()->month(4)->day(1)->format('Y-m-d').' 04:00:00');
});

test('golden ticket manager imports csv through livewire upload workflow', function () {
    $csv = implode("\n", [
        'Job,Shift start date,Shift start time,Shift end time,Volunteer Identifier,Phone (mobile preferred),First Name,Last Name,Email,Zip code',
        'Registration,2026-05-07,16:45,18:00,VL-222,5551112222,Jamie,Park,jamie@example.com,55103',
    ]);

    $upload = UploadedFile::fake()->createWithContent('volunteers.csv', $csv);

    Livewire::test('gt.golden-ticket-manager')
        ->set('csv', $upload)
        ->call('importCsv')
        ->assertHasNoErrors()
        ->assertSee('Tickets created:')
        ->assertSee('1')
        ->assertSet('tickets.0.vlid', 'VL-222');

    expect(Ticket::query()->where('vlid', 'VL-222')->exists())->toBeTrue();
});

test('tickets enforce unique serial numbers within a ps year', function () {
    $psYear = DateHelpers::psYearForDate(now());

    Ticket::factory()->create([
        'ps_year' => $psYear,
        'serial' => '123456',
    ]);

    $threw = false;

    try {
        Ticket::factory()->create([
            'ps_year' => $psYear,
            'serial' => '123456',
        ]);
    } catch (Throwable $throwable) {
        $threw = true;
    }

    expect($threw)->toBeTrue();

    // Same serial is allowed in a different PS year.
    Ticket::factory()->create([
        'ps_year' => $psYear + 1,
        'serial' => '123456',
    ]);

    expect(
        DB::table('tickets')->where('serial', '123456')->count()
    )->toBe(2);
});
