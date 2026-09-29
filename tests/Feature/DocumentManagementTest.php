<?php

use App\Models\Client;
use App\Models\ClientPurchaseOrder;
use App\Models\Company;
use App\Models\Document;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function documentUploadPayload(string $type, int $id, string $name = 'contract.pdf'): array
{
    return [
        'documentable_type' => $type,
        'documentable_id' => $id,
        'document_type' => 'Contract',
        'expiration_date' => '2027-01-31',
        'file' => UploadedFile::fake()->create($name, 128, 'application/pdf'),
    ];
}

function documentCompanyUser(array|string $permissions): array
{
    $user = userWithPermissions($permissions);
    $company = Company::factory()->create();
    $company->users()->attach($user);

    return [$user, $company];
}

test('documents can be uploaded across supported parent modules', function () {
    Storage::fake('local');
    [$user, $company] = documentCompanyUser([
        'documents.upload',
        'companies.view',
        'clients.view',
        'vendors.view',
        'quotations.view',
        'client-pos.view',
        'purchase-orders.view',
    ]);

    $client = Client::factory()->create();
    $client->companies()->attach($company, ['status' => Company::STATUS_ACTIVE]);
    $vendor = Vendor::factory()->create();
    $vendor->companies()->attach($company, ['status' => Company::STATUS_ACTIVE]);
    $quotation = Quotation::factory()->create(['company_id' => $company->id, 'client_id' => $client->id]);
    $clientPurchaseOrder = ClientPurchaseOrder::factory()->create(['company_id' => $company->id, 'client_id' => $client->id]);
    $purchaseOrder = PurchaseOrder::factory()->create(['company_id' => $company->id, 'vendor_id' => $vendor->id]);

    $parents = [
        ['company', $company->id],
        ['client', $client->id],
        ['vendor', $vendor->id],
        ['quotation', $quotation->id],
        ['client_purchase_order', $clientPurchaseOrder->id],
        ['purchase_order', $purchaseOrder->id],
    ];

    foreach ($parents as $index => [$type, $id]) {
        $this->actingAs($user)
            ->post(route('documents.store'), documentUploadPayload($type, $id, "contract-{$index}.pdf"))
            ->assertRedirect();
    }

    expect(Document::count())->toBe(6);

    Document::query()->each(function (Document $document): void {
        Storage::disk('local')->assertExists($document->stored_path);
        expect($document->original_filename)->toEndWith('.pdf')
            ->and($document->document_type)->toBe('Contract')
            ->and($document->expiration_date?->toDateString())->toBe('2027-01-31');
    });
});

test('authorized users can open the document index', function () {
    [$user, $company] = documentCompanyUser(['documents.view', 'companies.view']);
    Document::factory()->create([
        'documentable_type' => Company::class,
        'documentable_id' => $company->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('documents.index'))
        ->assertOk();
});

test('authorized users can open the document upload page with accessible parents', function () {
    [$user, $company] = documentCompanyUser(['documents.upload', 'companies.view']);
    $otherCompany = Company::factory()->create(['company_name' => 'Hidden Company']);

    $this->actingAs($user)
        ->get(route('documents.create'))
        ->assertOk()
        ->assertSee($company->company_name)
        ->assertDontSee($otherCompany->company_name);
});

test('document index filters out inaccessible parent records', function () {
    [$user, $company] = documentCompanyUser(['documents.view', 'companies.view']);
    $otherCompany = Company::factory()->create();
    Document::factory()->create([
        'documentable_type' => Company::class,
        'documentable_id' => $company->id,
        'original_filename' => 'visible.pdf',
    ]);
    Document::factory()->create([
        'documentable_type' => Company::class,
        'documentable_id' => $otherCompany->id,
        'original_filename' => 'hidden.pdf',
    ]);

    $this->actingAs($user)
        ->get(route('documents.index'))
        ->assertOk()
        ->assertSee('visible.pdf')
        ->assertDontSee('hidden.pdf');
});

test('invalid document uploads are rejected', function () {
    Storage::fake('local');
    [$user, $company] = documentCompanyUser(['documents.upload', 'companies.view']);

    $this->actingAs($user)
        ->post(route('documents.store'), [
            ...documentUploadPayload('company', $company->id),
            'file' => UploadedFile::fake()->create('payload.exe', 10, 'application/x-msdownload'),
        ])
        ->assertSessionHasErrors('file');

    expect(Document::count())->toBe(0);
});

test('downloads are authorized through the parent resource', function () {
    Storage::fake('local');
    [$user, $company] = documentCompanyUser(['documents.download', 'companies.view']);
    Storage::disk('local')->put('documents/private.pdf', 'secret');
    $document = Document::factory()->create([
        'documentable_type' => Company::class,
        'documentable_id' => $company->id,
        'stored_path' => 'documents/private.pdf',
        'original_filename' => 'private.pdf',
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('documents.download', $document))
        ->assertOk();

    $otherUser = userWithPermissions(['documents.download', 'companies.view']);

    $this->actingAs($otherUser)
        ->get(route('documents.download', $document))
        ->assertForbidden();
});

test('deleting a document removes its private file', function () {
    Storage::fake('local');
    [$user, $company] = documentCompanyUser(['documents.delete', 'companies.view']);
    Storage::disk('local')->put('documents/delete-me.pdf', 'secret');
    $document = Document::factory()->create([
        'documentable_type' => Company::class,
        'documentable_id' => $company->id,
        'stored_path' => 'documents/delete-me.pdf',
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->delete(route('documents.destroy', $document))
        ->assertRedirect();

    expect(Document::query()->whereKey($document)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('documents/delete-me.pdf');
});
