<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 * Default menempel ke Reimbursement; override attachable_type/id untuk Payment.
 */
class AttachmentFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->word().'.pdf';

        return [
            'attachable_type' => Reimbursement::class,
            'attachable_id' => Reimbursement::factory(),
            'uploaded_by' => User::factory(),
            'file_name' => $name,
            'file_path' => 'attachments/'.fake()->uuid().'/'.$name,
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(10_000, 5_000_000),
        ];
    }
}
