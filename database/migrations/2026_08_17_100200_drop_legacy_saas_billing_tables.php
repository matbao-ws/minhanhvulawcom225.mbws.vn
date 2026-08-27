<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the SaaS billing cluster.
 *
 * These five tables never described the shop's own business. They billed the
 * *vendor's* packages to whoever licensed this core: plans, subscriptions, add-ons
 * and platform invoices. Each deployment is one shop with one database, and the
 * shop has no use for its supplier's price list — the screens only ever showed a
 * customer numbers that belonged to someone else's commercial relationship.
 *
 * Feature flags are not part of this. `features` and `feature_settings` stay:
 * FeatureGate reads them directly and never consults a package.
 *
 * down() restores the structure, not the data. A drop cannot be undone in the sense
 * that matters, so back up before migrating if the rows still mean something.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Children before parents: package_features and project_subscription both
        // hold a foreign key into packages.
        foreach (['invoices', 'addons', 'package_features', 'project_subscription', 'packages'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('price', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('package_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('limit_value')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['package_id', 'feature_id']);
        });

        Schema::create('project_subscription', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->string('status')->default('active');
            $table->date('started_at')->nullable();
            $table->date('expired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('price', 15, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->boolean('is_purchased')->default(false);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->string('package_name');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->date('billing_date');
            $table->date('due_date');
            $table->string('payment_method')->nullable();
            $table->string('addon_code')->nullable();
            $table->string('sepay_transaction_id')->nullable();
            $table->timestamps();
        });
    }
};
