<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentWebhookFieldsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payments_table_has_webhook_fields(): void
    {
        // Assert that all required webhook fields exist
        $this->assertTrue(Schema::hasColumn('payments', 'reference_code'));
        $this->assertTrue(Schema::hasColumn('payments', 'transaction_id'));
        $this->assertTrue(Schema::hasColumn('payments', 'gateway_name'));
        $this->assertTrue(Schema::hasColumn('payments', 'paid_at'));
    }

    public function test_payments_table_has_reference_code_index(): void
    {
        // Get all indexes on the payments table
        $indexes = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableIndexes('payments');

        // Check that reference_code has an index
        $this->assertArrayHasKey('payments_reference_code_index', $indexes);
    }

    public function test_payments_table_has_transaction_id_index(): void
    {
        // Get all indexes on the payments table
        $indexes = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableIndexes('payments');

        // Check that transaction_id has an index
        $this->assertArrayHasKey('payments_transaction_id_index', $indexes);
    }

    public function test_payments_table_has_unique_tenant_reference_constraint(): void
    {
        // Get all indexes on the payments table
        $indexes = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableIndexes('payments');

        // Check that the unique constraint exists
        $this->assertArrayHasKey('unique_tenant_reference', $indexes);
        
        // Verify it's a unique index
        $uniqueIndex = $indexes['unique_tenant_reference'];
        $this->assertTrue($uniqueIndex->isUnique());
        
        // Verify it includes both tenant_id and reference_code
        $columns = $uniqueIndex->getColumns();
        $this->assertContains('tenant_id', $columns);
        $this->assertContains('reference_code', $columns);
    }
}
