<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Starlabs\LaravelProcard\Enums\PaymentStatus;
use Starlabs\LaravelProcard\Models\ProcardTransaction;

class ProcardTransactionFactory extends Factory
{
    protected $model = ProcardTransaction::class;

    public function definition(): array
    {
        return [
            'order_id' => 'ORD-'.$this->faker->unique()->randomNumber(5),
            'order_description' => $this->faker->sentence(),
            'amount' => $this->faker->randomFloat(2, 5, 500),
            'currency' => 'EUR',
            'status' => PaymentStatus::REGISTERED,
            'procard_transaction_id' => (string) $this->faker->randomNumber(8),
            'payer_email' => $this->faker->safeEmail(),
            'card_pan' => '403021******'.$this->faker->numerify('####'),
            'card_type' => $this->faker->randomElement(['Visa', 'MasterCard']),
            'language' => $this->faker->randomElement(['ua', 'en', 'ru']),
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => PaymentStatus::COMPLETED]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => PaymentStatus::CANCELLED]);
    }

    public function declined(): static
    {
        return $this->state(['status' => PaymentStatus::DECLINED]);
    }
}
