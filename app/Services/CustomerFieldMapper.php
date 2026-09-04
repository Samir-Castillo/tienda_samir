<?php

namespace App\Services;

/**
 * Maps local customer field values to Factus V1 integer IDs.
 */
class CustomerFieldMapper
{
    /**
     * Map identification_document_code to Factus identification_document_id.
     *
     * Factus V1 IDs: 1=Registro civil, 2=Tarjeta identidad, 3=CC, 4=TE,
     * 5=CE, 6=NIT, 7=Pasaporte, 8=Doc extranjero, 9=PEP, 10=NIT otro pais, 11=NUIP
     *
     * @throws \RuntimeException When the code is unknown, so the invoice is
     *                           never sent with an invented identification id.
     */
    public function identificationDocumentId(string $code): int
    {
        return match (strtoupper($code)) {
            'CC' => 3,
            'NIT' => 6,
            'TI' => 2,
            'CE' => 5,
            'PP' => 7,
            'RC' => 1,
            'TE' => 4,
            'PEP' => 9,
            default => throw new \RuntimeException(
                "El código de identificación '{$code}' no se puede mapear de forma segura "
                .'a un ID de Factus. Configure un código oficial válido antes de facturar.'
            ),
        };
    }

    /**
     * Map legal_organization_code to Factus legal_organization_id.
     *
     * Factus V1 IDs: 1=Persona Juridica, 2=Persona Natural
     *
     * @throws \RuntimeException When the code is unknown, so the invoice is
     *                           never sent with an invented organization id.
     */
    public function legalOrganizationId(string $code): int
    {
        return match (strtoupper($code)) {
            'PJ', 'JURIDICA' => 1,
            'PN', 'NATURAL' => 2,
            default => throw new \RuntimeException(
                "El código de organización legal '{$code}' no se puede mapear de forma segura "
                .'a un ID de Factus. Configure un código oficial válido antes de facturar.'
            ),
        };
    }

    /**
     * Map tribute_code to Factus tribute_id.
     *
     * Factus V1 IDs: 18=IVA, 21=No aplica (ZZ)
     *
     * @throws \RuntimeException When a non-null code is unknown, so the
     *                           invoice is never sent with an invented tribute.
     */
    public function tributeId(?string $code): int
    {
        if ($code === null) {
            return 21;
        }

        return match (strtoupper($code)) {
            'IVA', '01' => 18,
            'ZZ' => 21,
            default => throw new \RuntimeException(
                "El código de tributo del cliente '{$code}' no se puede mapear de forma segura "
                .'a un ID de Factus. Configure un código oficial válido antes de facturar.'
            ),
        };
    }

    /**
     * Map a local product tax code to the Factus item tribute_id.
     *
     * This is the tribute catalog used on Factus items (impuesto del bien),
     * which differs from the customer tribute catalog (see tributeId()).
     *
     * Confirmed sandbox mapping: 1 = código 01 = IVA.
     *
     * @throws \RuntimeException When the code cannot be safely mapped, so the
     *                           invoice is never sent with an invented tribute.
     */
    public function productTributeId(string $code): int
    {
        return match (strtoupper($code)) {
            '01', 'IVA' => 1,
            default => throw new \RuntimeException(
                "El código de impuesto '{$code}' no se puede mapear de forma segura "
                .'a un ID de tributo de Factus. Configure un código de impuesto válido '
                .'antes de facturar este producto.'
            ),
        };
    }

    /**
     * Map standard_code string to Factus standard_code_id integer.
     *
     * Factus V1 IDs: 1=Estándar contribuyente, 2=UNSPSC, 3=Partida Arancelaria, 4=GTIN
     */
    public function standardCodeId(string $code): int
    {
        return match ($code) {
            '999', '1' => 1,
            '2' => 2,
            '3' => 3,
            '4' => 4,
            default => 1,
        };
    }
}
