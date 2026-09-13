# OyeJo Gas - Branded Product Images ✅ COMPLETE

This folder contains AI-generated branded product shots for all OyeJo Gas cylinder sizes with OyeJogas brand.

## Brand Guidelines Used
- Primary Green: #0b6b3a (OyeJo Gas green)
- Accent Orange: #ff9d2e (flame)
- Brand Name: OYEJO GAS / OyeJogas
- Flame logo: orange flame icon
- Style: photorealistic studio, pure white background, e-commerce lighting, 8k, ultra detailed

## ✅ All Generated Images (19 PNGs - 29MB total)

### Core Cylinder Sizes - Main Product Shots
1. **3kg Camping** - `oyejogas-3kg-camping.png` (1.2MB) - Small portable camping cylinder with handle ring, OYEJO GAS 3KG LPG
2. **6kg** - `oyejogas-6kg.png` (1.4MB) - Small household size
3. **12.5kg** - `oyejogas-12-5kg.png` (800KB) - Family standard, best seller
4. **25kg** - `oyejogas-25kg.png` (1.1MB) - Large household
5. **50kg** - `oyejogas-50kg.png` (747KB) - Commercial / Industrial extra large

### New Cylinder Variants (Factory New - Shiny)
6. `oyejogas-3kg-new.png` (1.4MB) - Brand new 3kg
7. `oyejogas-12-5kg-new.png` (1.4MB) - Brand new 12.5kg
8. `oyejogas-25kg-new.png` (1.3MB) - Brand new 25kg
9. `oyejogas-50kg-new.png` (1.8MB) - Brand new 50kg

### Refill Variants (Sealed Full with Safety Cap)
10. `oyejogas-3kg-refill.png` (1.3MB) - 3kg refill sealed
11. `oyejogas-6kg-refill.png` (1.4MB) - 6kg refill sealed
12. `oyejogas-12-5kg-refill.png` (1.3MB) - 12.5kg refill sealed with red safety seal
13. `oyejogas-25kg-refill.png` (1.4MB) - 25kg refill
14. `oyejogas-50kg-refill.png` (1.2MB) - 50kg refill

### Service & Marketing Concepts
15. `oyejogas-exchange-concept.png` (1.8MB) - Exchange service concept (empty vs full with arrows)
16. `oyejogas-family-all-sizes.png` (1.7MB) - Complete lineup size comparison (3kg to 50kg)
17. `oyejogas-banner-all-products.png` (2.0MB) - Wide marketing banner 16:9 with all products
18. `oyejogas-hero-lifestyle.png` (2.8MB) - Lifestyle hero: Nigerian delivery driver delivering to family in Lagos

### Accessories
19. `oyejogas-accessories-regulator.png` (1.9MB) - Regulator + 1.5m orange hose set with gauge
20. `oyejogas-accessories-burner.png` (1.7MB) - Table-top double gas burner stainless steel

## Usage in Code

### Automatic Mapping (Already Integrated)
`includes/catalog.php` -> `oyejo_product_image()` now auto-maps:

- size_id 1 (3kg) => 3kg-camping.png + variants
- size_id 2 (6kg) => 6kg.png + refill variant
- size_id 3 (12.5kg) => 12.5kg.png + new + refill
- size_id 4 (25kg) => 25kg.png + new + refill
- size_id 5 (50kg) => 50kg.png + new + refill

Refill type, cylinder_new, exchange, and accessory SKUs have precise fallback logic.

**No database change needed - shop will automatically show branded images!**

### Manual Assignment (Optional - for admin)
If you want to explicitly set images in DB:

```sql
-- Assign branded images to existing seeded products
UPDATE products SET image='assets/images/products/oyejogas-12-5kg-refill.png' WHERE slug='refill-12-5kg';
UPDATE products SET image='assets/images/products/oyejogas-6kg-refill.png' WHERE slug='refill-6kg';
UPDATE products SET image='assets/images/products/oyejogas-12-5kg-new.png' WHERE slug='new-cylinder-12-5kg';
UPDATE products SET image='assets/images/products/oyejogas-exchange-concept.png' WHERE slug='exchange-12-5kg';
UPDATE products SET image='assets/images/products/oyejogas-accessories-regulator.png' WHERE slug='regulator-hose-set';
UPDATE products SET image='assets/images/products/oyejogas-accessories-burner.png' WHERE slug='table-top-burner';

-- For future products by size
UPDATE products SET image='assets/images/products/oyejogas-3kg-camping.png' WHERE size_id=1 AND type='cylinder_new';
UPDATE products SET image='assets/images/products/oyejogas-6kg.png' WHERE size_id=2 AND type='cylinder_new';
UPDATE products SET image='assets/images/products/oyejogas-25kg.png' WHERE size_id=4 AND type='cylinder_new';
UPDATE products SET image='assets/images/products/oyejogas-50kg.png' WHERE size_id=5 AND type='cylinder_new';
```

### Showcase Page
Visit `/product-showcase.php` to see all images in a beautiful gallery.

### Marketing Usage
- Shop listing: auto-used
- Product detail page: auto-used
- Homepage hero: use `oyejogas-hero-lifestyle.png`
- Banner: use `oyejogas-banner-all-products.png`
- Social media: all images are white-background e-commerce ready

## Next Steps (Optional)
- Generate WebP variants for performance: `cwebp input.png -o output.webp`
- Create thumbnail 300x300 versions for listings
- Add to S3/CDN if needed

Generated: 2026-09-13 - All OyeJogas cylinder sizes with OyeJo Gas brand
