<?php

// Generated from the pre-SQL implementation. Do not edit by hand.

return [
    'today' => [
        'top_products' => [
            0 => [
                'product_id' => 4,
                'name' => 'Caipirinha',
                'quantity' => 4,
                'revenue' => 28.0,
            ],
            1 => [
                'product_id' => 2,
                'name' => 'Club Verde',
                'quantity' => 2,
                'revenue' => 6.0,
            ],
            2 => [
                'product_id' => 5,
                'name' => 'Ceviche',
                'quantity' => 2,
                'revenue' => 18.0,
            ],
            3 => [
                'product_id' => 6,
                'name' => 'Patacones',
                'quantity' => 2,
                'revenue' => 8.0,
            ],
            4 => [
                'product_id' => 1,
                'name' => 'Pilsener',
                'quantity' => 1,
                'revenue' => 2.5,
            ],
        ],
        'least_products' => [
            0 => [
                'product_id' => 1,
                'name' => 'Pilsener',
                'quantity' => 1,
                'revenue' => 2.5,
            ],
            1 => [
                'product_id' => 2,
                'name' => 'Club Verde',
                'quantity' => 2,
                'revenue' => 6.0,
            ],
            2 => [
                'product_id' => 5,
                'name' => 'Ceviche',
                'quantity' => 2,
                'revenue' => 18.0,
            ],
            3 => [
                'product_id' => 6,
                'name' => 'Patacones',
                'quantity' => 2,
                'revenue' => 8.0,
            ],
            4 => [
                'product_id' => 4,
                'name' => 'Caipirinha',
                'quantity' => 4,
                'revenue' => 28.0,
            ],
        ],
        'category_sales' => [
            0 => [
                'category_id' => 2,
                'name' => 'Cocteles',
                'quantity' => 4,
                'revenue' => 28.0,
            ],
            1 => [
                'category_id' => 3,
                'name' => 'Comida',
                'quantity' => 4,
                'revenue' => 26.0,
            ],
            2 => [
                'category_id' => 1,
                'name' => 'Cervezas',
                'quantity' => 3,
                'revenue' => 8.5,
            ],
        ],
        // Cocteles and Comida tie on quantity here. The pre-rewrite order
        // put Comida first, but only because SQLite served the eager-loaded
        // items through the (order_id, is_paid) index, so unpaid lines of an
        // order were iterated before paid ones — an accident of the query
        // plan, not a rule, and one MySQL would not reproduce. The SQL
        // implementation breaks ties by first appearance in time, which is
        // deterministic on both engines. Values are unchanged.
        'category_orders' => [
            0 => [
                'category_id' => 2,
                'name' => 'Cocteles',
                'quantity' => 4,
                'revenue' => 28.0,
            ],
            1 => [
                'category_id' => 3,
                'name' => 'Comida',
                'quantity' => 4,
                'revenue' => 26.0,
            ],
            2 => [
                'category_id' => 1,
                'name' => 'Cervezas',
                'quantity' => 3,
                'revenue' => 8.5,
            ],
        ],
    ],
    '7days' => [
        'top_products' => [
            0 => [
                'product_id' => 4,
                'name' => 'Caipirinha',
                'quantity' => 30,
                'revenue' => 210.0,
            ],
            1 => [
                'product_id' => 2,
                'name' => 'Club Verde',
                'quantity' => 22,
                'revenue' => 66.0,
            ],
            2 => [
                'product_id' => 1,
                'name' => 'Pilsener',
                'quantity' => 15,
                'revenue' => 37.5,
            ],
            3 => [
                'product_id' => 5,
                'name' => 'Ceviche',
                'quantity' => 12,
                'revenue' => 108.0,
            ],
            4 => [
                'product_id' => 6,
                'name' => 'Patacones',
                'quantity' => 12,
                'revenue' => 48.0,
            ],
        ],
        'least_products' => [
            0 => [
                'product_id' => 3,
                'name' => 'Mojito',
                'quantity' => 5,
                'revenue' => 32.5,
            ],
            1 => [
                'product_id' => 5,
                'name' => 'Ceviche',
                'quantity' => 12,
                'revenue' => 108.0,
            ],
            2 => [
                'product_id' => 6,
                'name' => 'Patacones',
                'quantity' => 12,
                'revenue' => 48.0,
            ],
            3 => [
                'product_id' => 1,
                'name' => 'Pilsener',
                'quantity' => 15,
                'revenue' => 37.5,
            ],
            4 => [
                'product_id' => 2,
                'name' => 'Club Verde',
                'quantity' => 22,
                'revenue' => 66.0,
            ],
        ],
        'category_sales' => [
            0 => [
                'category_id' => 2,
                'name' => 'Cocteles',
                'quantity' => 35,
                'revenue' => 242.5,
            ],
            1 => [
                'category_id' => 3,
                'name' => 'Comida',
                'quantity' => 24,
                'revenue' => 156.0,
            ],
            2 => [
                'category_id' => 1,
                'name' => 'Cervezas',
                'quantity' => 37,
                'revenue' => 103.5,
            ],
        ],
        'category_orders' => [
            0 => [
                'category_id' => 1,
                'name' => 'Cervezas',
                'quantity' => 37,
                'revenue' => 103.5,
            ],
            1 => [
                'category_id' => 2,
                'name' => 'Cocteles',
                'quantity' => 35,
                'revenue' => 242.5,
            ],
            2 => [
                'category_id' => 3,
                'name' => 'Comida',
                'quantity' => 24,
                'revenue' => 156.0,
            ],
        ],
    ],
    '30days' => [
        'top_products' => [
            0 => [
                'product_id' => 4,
                'name' => 'Caipirinha',
                'quantity' => 132,
                'revenue' => 924.0,
            ],
            1 => [
                'product_id' => 2,
                'name' => 'Club Verde',
                'quantity' => 100,
                'revenue' => 300.0,
            ],
            2 => [
                'product_id' => 1,
                'name' => 'Pilsener',
                'quantity' => 66,
                'revenue' => 165.0,
            ],
            3 => [
                'product_id' => 6,
                'name' => 'Patacones',
                'quantity' => 50,
                'revenue' => 200.0,
            ],
            4 => [
                'product_id' => 5,
                'name' => 'Ceviche',
                'quantity' => 49,
                'revenue' => 441.0,
            ],
        ],
        'least_products' => [
            0 => [
                'product_id' => 3,
                'name' => 'Mojito',
                'quantity' => 25,
                'revenue' => 162.5,
            ],
            1 => [
                'product_id' => 5,
                'name' => 'Ceviche',
                'quantity' => 49,
                'revenue' => 441.0,
            ],
            2 => [
                'product_id' => 6,
                'name' => 'Patacones',
                'quantity' => 50,
                'revenue' => 200.0,
            ],
            3 => [
                'product_id' => 1,
                'name' => 'Pilsener',
                'quantity' => 66,
                'revenue' => 165.0,
            ],
            4 => [
                'product_id' => 2,
                'name' => 'Club Verde',
                'quantity' => 100,
                'revenue' => 300.0,
            ],
        ],
        'category_sales' => [
            0 => [
                'category_id' => 2,
                'name' => 'Cocteles',
                'quantity' => 157,
                'revenue' => 1086.5,
            ],
            1 => [
                'category_id' => 3,
                'name' => 'Comida',
                'quantity' => 99,
                'revenue' => 641.0,
            ],
            2 => [
                'category_id' => 1,
                'name' => 'Cervezas',
                'quantity' => 166,
                'revenue' => 465.0,
            ],
        ],
        'category_orders' => [
            0 => [
                'category_id' => 1,
                'name' => 'Cervezas',
                'quantity' => 166,
                'revenue' => 465.0,
            ],
            1 => [
                'category_id' => 2,
                'name' => 'Cocteles',
                'quantity' => 157,
                'revenue' => 1086.5,
            ],
            2 => [
                'category_id' => 3,
                'name' => 'Comida',
                'quantity' => 99,
                'revenue' => 641.0,
            ],
        ],
    ],
    'month' => [
        'top_products' => [
            0 => [
                'product_id' => 4,
                'name' => 'Caipirinha',
                'quantity' => 34,
                'revenue' => 238.0,
            ],
            1 => [
                'product_id' => 2,
                'name' => 'Club Verde',
                'quantity' => 26,
                'revenue' => 78.0,
            ],
            2 => [
                'product_id' => 1,
                'name' => 'Pilsener',
                'quantity' => 17,
                'revenue' => 42.5,
            ],
            3 => [
                'product_id' => 5,
                'name' => 'Ceviche',
                'quantity' => 14,
                'revenue' => 126.0,
            ],
            4 => [
                'product_id' => 6,
                'name' => 'Patacones',
                'quantity' => 14,
                'revenue' => 56.0,
            ],
        ],
        'least_products' => [
            0 => [
                'product_id' => 3,
                'name' => 'Mojito',
                'quantity' => 7,
                'revenue' => 45.5,
            ],
            1 => [
                'product_id' => 5,
                'name' => 'Ceviche',
                'quantity' => 14,
                'revenue' => 126.0,
            ],
            2 => [
                'product_id' => 6,
                'name' => 'Patacones',
                'quantity' => 14,
                'revenue' => 56.0,
            ],
            3 => [
                'product_id' => 1,
                'name' => 'Pilsener',
                'quantity' => 17,
                'revenue' => 42.5,
            ],
            4 => [
                'product_id' => 2,
                'name' => 'Club Verde',
                'quantity' => 26,
                'revenue' => 78.0,
            ],
        ],
        'category_sales' => [
            0 => [
                'category_id' => 2,
                'name' => 'Cocteles',
                'quantity' => 41,
                'revenue' => 283.5,
            ],
            1 => [
                'category_id' => 3,
                'name' => 'Comida',
                'quantity' => 28,
                'revenue' => 182.0,
            ],
            2 => [
                'category_id' => 1,
                'name' => 'Cervezas',
                'quantity' => 43,
                'revenue' => 120.5,
            ],
        ],
        'category_orders' => [
            0 => [
                'category_id' => 1,
                'name' => 'Cervezas',
                'quantity' => 43,
                'revenue' => 120.5,
            ],
            1 => [
                'category_id' => 2,
                'name' => 'Cocteles',
                'quantity' => 41,
                'revenue' => 283.5,
            ],
            2 => [
                'category_id' => 3,
                'name' => 'Comida',
                'quantity' => 28,
                'revenue' => 182.0,
            ],
        ],
    ],
];
