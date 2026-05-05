<?php

namespace Database\Factories;

use App\Helpers\DateHelpers;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ps_year' => DateHelpers::psYearForDate(now()),
            'serial' => str_pad((string) fake()->numberBetween(100000, 999999), 6, '0', STR_PAD_LEFT),
        ];
    }
}
