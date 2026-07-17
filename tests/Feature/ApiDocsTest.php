<?php

// Phase 18 — dokumentasi API (Scramble/OpenAPI).

test('the api docs UI is reachable', function () {
    $this->get('/docs/api')->assertOk();
});

test('the openapi spec is generated with core endpoints and security scheme', function () {
    $spec = $this->get('/docs/api.json')->assertOk()->json();

    expect($spec['openapi'])->toStartWith('3.')
        ->and($spec['info']['title'])->toBe(config('app.name'));

    $paths = array_keys($spec['paths']);
    expect($paths)->toContain('/dashboard')
        ->and($paths)->toContain('/reimbursements')
        ->and($paths)->toContain('/reimbursements/{reimbursement}/approve')
        ->and($paths)->toContain('/reimbursements/{reimbursement}/pay')
        ->and($paths)->toContain('/audit-logs');

    // Skema auth Bearer terdeklarasi.
    expect($spec['components']['securitySchemes'])->toHaveKey('http');
});
