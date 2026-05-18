<?php

namespace Tests;

use App\Models\DeanEmailMapping;
use App\Support\ReservationDeanRouting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Dean mapping required for AVR/Lobby + organization event flow in tests.
     */
    protected function seedSacdevDeanMappingForTests(string $email = 'sacdev.dean@test.xu.edu.ph'): void
    {
        DeanEmailMapping::create([
            'affiliation_type' => DeanEmailMapping::TYPE_OFFICE_DEPARTMENT,
            'affiliation_name' => ReservationDeanRouting::ORGANIZATION_DEAN_AFFILIATION_NAME,
            'office_code' => DeanEmailMapping::OFFICE_CODE_SACDEV,
            'approver_name' => 'SACDEV Approver',
            'approver_email' => $email,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function organizationEventAudiencePayload(): array
    {
        return ['event_request_type' => \App\Models\Reservation::EVENT_REQUEST_ORGANIZATION];
    }
}
