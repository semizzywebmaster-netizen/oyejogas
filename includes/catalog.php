<?php
/**
 * Oyejo Gas - storefront catalog helpers (Phase 8).
 * Promo-window math lives in SQL (NOW()) so PHP/DB timezone skew can never
 * leak an expired promo; PHP only formats precomputed values.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

/** Human labels for the products.type enum. */
function oyejo_product_types() {
    return [
        'cylinder_new' => 'New cylinder',
        'refill'       => 'Refill',
        'exchange'     => 'Exchange',
        'accessory'    => 'Accessory',
        'service'      => 'Service',
    ];
}

/**
 * SQL expression returning the live promo price (minor units) or NULL.
 * $alias is the products table alias used by the calling query.
 */
function oyejo_promo_sql($alias) {
    $a = '`' . str_replace('`', '', (string) $alias) . '`';
    return "CASE WHEN {$a}.`promo_price_minor` IS NOT NULL"
        . " AND {$a}.`promo_price_minor` < {$a}.`price_minor`"
        . " AND ({$a}.`promo_starts_at` IS NULL OR {$a}.`promo_starts_at` <= NOW())"
        . " AND ({$a}.`promo_ends_at` IS NULL OR {$a}.`promo_ends_at` >= NOW())"
        . " THEN {$a}.`promo_price_minor` END";
}

/** Price HTML from precomputed minor-unit values (promo may be NULL). */
function oyejo_price_html($price_minor, $promo_minor) {
    if ($promo_minor !== null) {
        return '<del>' . e(format_money((int) $price_minor)) . '</del>'
            . ' <strong>' . e(format_money((int) $promo_minor)) . '</strong>';
    }
    return '<strong>' . e(format_money((int) $price_minor)) . '</strong>';
}

/** [label, css-class] stock state for a product row. */
function oyejo_stock_state(array $p) {
    if (empty($p['track_inventory'])) {
        return ['Available', 'ok'];
    }
    $qty = (int) ($p['stock_qty'] ?? 0);
    if ($qty <= 0) {
        return ['Out of stock', 'out'];
    }
    if ($qty <= (int) ($p['low_stock_at'] ?? 5)) {
        return ['Low stock', 'low'];
    }
    return ['In stock', 'ok'];
}

function oyejo_stock_badge(array $p) {
    list($label, $class) = oyejo_stock_state($p);
    return '<span class="stock ' . $class . '">' . e($label) . '</span>';
}

/** Product image URL, falling back to branded OyeJo Gas size-based images. */
function oyejo_product_image(array $p) {
    if (!empty($p['image'])) {
        return url(ltrim((string) $p['image'], '/'));
    }
    $base = BASE_PATH . '/assets/images/products/';
    $size_id = (int)($p['size_id'] ?? 0);
    $type = (string)($p['type'] ?? '');
    $sku = strtolower((string)($p['sku'] ?? ''));
    $slug = strtolower((string)($p['slug'] ?? ''));

    // Exact type + size mapping for best accuracy
    $map = [
        // 3kg
        '1_refill' => 'oyejogas-3kg-refill.png',
        '1_cylinder_new' => 'oyejogas-3kg-new.png',
        // 6kg
        '2_refill' => 'oyejogas-6kg-refill.png',
        '2_cylinder_new' => 'oyejogas-6kg.png',
        // 12.5kg
        '3_refill' => 'oyejogas-12-5kg-refill.png',
        '3_cylinder_new' => 'oyejogas-12-5kg-new.png',
        '3_exchange' => 'oyejogas-exchange-concept.png',
        // 25kg
        '4_refill' => 'oyejogas-25kg-refill.png',
        '4_cylinder_new' => 'oyejogas-25kg-new.png',
        // 50kg
        '5_refill' => 'oyejogas-50kg-refill.png',
        '5_cylinder_new' => 'oyejogas-50kg-new.png',
    ];
    $key = $size_id . '_' . $type;
    if (isset($map[$key]) && is_file($base . $map[$key])) {
        return asset('assets/images/products/' . $map[$key]);
    }

    // Size-only fallback
    $size_map = [
        1 => 'oyejogas-3kg-camping.png',
        2 => 'oyejogas-6kg.png',
        3 => 'oyejogas-12-5kg.png',
        4 => 'oyejogas-25kg.png',
        5 => 'oyejogas-50kg.png',
    ];
    if ($size_id && isset($size_map[$size_id]) && is_file($base . $size_map[$size_id])) {
        return asset('assets/images/products/' . $size_map[$size_id]);
    }

    // Accessories
    if (strpos($sku, 'reg') !== false || strpos($slug, 'regulator') !== false || strpos($slug, 'hose') !== false) {
        if (is_file($base . 'oyejogas-accessories-regulator.png')) {
            return asset('assets/images/products/oyejogas-accessories-regulator.png');
        }
    }
    if (strpos($sku, 'burn') !== false || strpos($slug, 'burner') !== false) {
        if (is_file($base . 'oyejogas-accessories-burner.png')) {
            return asset('assets/images/products/oyejogas-accessories-burner.png');
        }
    }
    if ($type === 'exchange' && is_file($base . 'oyejogas-exchange-concept.png')) {
        return asset('assets/images/products/oyejogas-exchange-concept.png');
    }

    return asset('assets/images/product-placeholder.svg');
}

/** Full product gallery - all OyeJo Gas branded images */
function oyejo_product_gallery() {
    $base = 'assets/images/products/';
    return [
        '3kg' => [
            'label' => '3kg Camping - Small & Portable',
            'size' => '3kg',
            'description' => 'Perfect for camping, small families, and portable use',
            'images' => [
                $base.'oyejogas-3kg-camping.png',
                $base.'oyejogas-3kg-new.png',
                $base.'oyejogas-3kg-refill.png',
            ],
        ],
        '6kg' => [
            'label' => '6kg - Small Household',
            'size' => '6kg',
            'description' => 'Ideal for small households and single burners',
            'images' => [
                $base.'oyejogas-6kg.png',
                $base.'oyejogas-6kg-refill.png',
            ],
        ],
        '12.5kg' => [
            'label' => '12.5kg - Family Standard (Best Seller)',
            'size' => '12.5kg',
            'description' => 'Most popular size for Nigerian families',
            'images' => [
                $base.'oyejogas-12-5kg.png',
                $base.'oyejogas-12-5kg-new.png',
                $base.'oyejogas-12-5kg-refill.png',
            ],
        ],
        '25kg' => [
            'label' => '25kg - Large Household / Small Commercial',
            'size' => '25kg',
            'description' => 'For large families and small restaurants',
            'images' => [
                $base.'oyejogas-25kg.png',
                $base.'oyejogas-25kg-new.png',
                $base.'oyejogas-25kg-refill.png',
            ],
        ],
        '50kg' => [
            'label' => '50kg - Commercial / Industrial',
            'size' => '50kg',
            'description' => 'Heavy duty for restaurants, hotels, and industrial use',
            'images' => [
                $base.'oyejogas-50kg.png',
                $base.'oyejogas-50kg-new.png',
                $base.'oyejogas-50kg-refill.png',
            ],
        ],
        'family' => [
            'label' => 'Complete Family - All Sizes Comparison',
            'size' => 'All Sizes',
            'description' => 'Size comparison and service concepts',
            'images' => [
                $base.'oyejogas-family-all-sizes.png',
                $base.'oyejogas-exchange-concept.png',
                $base.'oyejogas-banner-all-products.png',
                $base.'oyejogas-hero-lifestyle.png',
            ],
        ],
        'accessories' => [
            'label' => 'Accessories & Add-ons',
            'size' => 'Accessories',
            'description' => 'Regulators, hoses, burners and safety equipment',
            'images' => [
                $base.'oyejogas-accessories-regulator.png',
                $base.'oyejogas-accessories-burner.png',
            ],
        ],
    ];
}

/** Get all product images flat list */
function oyejo_all_product_images() {
    $gallery = oyejo_product_gallery();
    $all = [];
    foreach ($gallery as $group) {
        foreach ($group['images'] as $img) {
            $all[] = $img;
        }
    }
    return array_unique($all);
}
