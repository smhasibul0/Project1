<?php

use App\Models\Contact;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every customer gets their own shipping mark (RTC/MOM), stamped on the orders
 * shipped for them. It is unique, so a mark always points at one customer.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('shipping_mark')->nullable()->unique()->after('contact_code');
        });

        // Give the customers already on file a mark.
        Contact::orderBy('id')->each(function (Contact $contact) {
            $contact->update([
                'shipping_mark' => Contact::suggestShippingMark($contact->name, $contact->business_name, $contact->id),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['shipping_mark']);
            $table->dropColumn('shipping_mark');
        });
    }
};
