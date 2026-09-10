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

/** Product image URL, falling back to the placeholder graphic. */
function oyejo_product_image(array $p) {
    if (!empty($p['image'])) {
        return url(ltrim((string) $p['image'], '/'));
    }
    return url('assets/images/product-placeholder.svg');
}
