<?php

return [
    'checkout' => [
        'cart' => [
            'integrity' => [
                'qty-missing'   => 'Pelo menos um produto deve ter mais de 1 quantidade.',
            ],

            'invalid-file-extension'   => 'Extensão de arquivo inválida encontrada.',
            'inventory-warning'        => 'A quantidade solicitada não está disponível, por favor, tente novamente mais tarde.',
            'missing-links'            => 'Links para download estão ausentes para este produto.',
            'missing-options'          => 'Opções estão ausentes para este produto.',
            'selected-products-simple' => 'Os produtos selecionados devem ser do tipo simples.',
        ],
    ],

    'datagrid' => [
        'copy-of-slug'                  => 'copia-de-:value',
        'copy-of'                       => 'Cópia de :value',
        'variant-already-exist-message' => 'Variante com as mesmas opções de atributo já existe.',
    ],

    'response' => [
        'product-can-not-be-copied' => 'Produtos do tipo :type não podem ser copiados',
    ],

    'sort-by'  => [
        'options' => [
            'cheapest-first'  => 'Mais baratos primeiro',
            'expensive-first' => 'Mais caros primeiro',
            'from-a-z'        => 'De A a Z',
            'from-z-a'        => 'De Z a A',
            'latest-first'    => 'Mais recentes primeiro',
            'oldest-first'    => 'Mais antigos primeiro',
        ],
    ],

    'type'     => [
        'abstract'     => [
            'offers' => 'Compre :qty por :price cada e economize :discount',
        ],

        'bundle'       => 'Pacote',
        'booking'      => 'Reserva',
        'configurable' => 'Configurável',
        'downloadable' => 'Baixável',
        'grouped'      => 'Agrupado',
        'simple'       => 'Simples',
        'virtual'      => 'Virtual',
    ],
];
