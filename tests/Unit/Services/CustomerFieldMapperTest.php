<?php

use App\Services\CustomerFieldMapper;

describe('CustomerFieldMapper', function () {
    test('maps an unknown identification document code to a RuntimeException', function () {
        $mapper = new CustomerFieldMapper;

        expect(fn () => $mapper->identificationDocumentId('DEV-CC'))
            ->toThrow(RuntimeException::class, 'no se puede mapear de forma segura');
    });

    test('maps an unknown legal organization code to a RuntimeException', function () {
        $mapper = new CustomerFieldMapper;

        expect(fn () => $mapper->legalOrganizationId('DEV-LG'))
            ->toThrow(RuntimeException::class, 'no se puede mapear de forma segura');
    });

    test('maps an unknown non-null customer tribute code to a RuntimeException', function () {
        $mapper = new CustomerFieldMapper;

        expect(fn () => $mapper->tributeId('UNKNOWN'))
            ->toThrow(RuntimeException::class, 'no se puede mapear de forma segura');
    });

    test('maps the official natural person and IVA codes', function () {
        $mapper = new CustomerFieldMapper;

        expect($mapper->identificationDocumentId('CC'))->toBe(3);
        expect($mapper->legalOrganizationId('PN'))->toBe(2);
        expect($mapper->tributeId('01'))->toBe(18);
        expect($mapper->productTributeId('01'))->toBe(1);
        expect($mapper->standardCodeId('999'))->toBe(1);
    });
});
