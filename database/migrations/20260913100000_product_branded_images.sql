-- Migration: Assign OyeJo Gas branded images to products
-- Generated 2026-09-13 - All cylinder sizes with OyeJo Gas brand

-- Update existing seeded products with branded images
UPDATE `products` SET `image`='assets/images/products/oyejogas-12-5kg-refill.png' WHERE `slug`='refill-12-5kg';
UPDATE `products` SET `image`='assets/images/products/oyejogas-6kg-refill.png' WHERE `slug`='refill-6kg';
UPDATE `products` SET `image`='assets/images/products/oyejogas-12-5kg-new.png' WHERE `slug`='new-cylinder-12-5kg';
UPDATE `products` SET `image`='assets/images/products/oyejogas-exchange-concept.png' WHERE `slug`='exchange-12-5kg';
UPDATE `products` SET `image`='assets/images/products/oyejogas-accessories-regulator.png' WHERE `slug`='regulator-hose-set';
UPDATE `products` SET `image`='assets/images/products/oyejogas-accessories-burner.png' WHERE `slug`='table-top-burner';

-- Create additional products for all sizes if not exists (for complete catalog)
-- Note: These are optional - the catalog.php fallback will show branded images even without explicit image field

-- Insert 3kg products if missing
INSERT INTO `products` (`category_id`, `size_id`, `sku`, `slug`, `name`, `description`, `type`, `price_minor`, `stock_qty`, `is_active`, `is_featured`, `image`, `sort_order`)
SELECT 1, 1, 'CYL-3KG-NEW', 'new-cylinder-3kg-camping', 'New 3kg Camping Cylinder (Full)', 'Brand-new 3kg camping cylinder supplied full, portable with handle, safety-checked. Perfect for camping and small households.', 'cylinder_new', 2500000, 15, 1, 1, 'assets/images/products/oyejogas-3kg-camping.png', 1
WHERE NOT EXISTS (SELECT 1 FROM `products` WHERE `slug`='new-cylinder-3kg-camping');

INSERT INTO `products` (`category_id`, `size_id`, `sku`, `slug`, `name`, `description`, `type`, `price_minor`, `stock_qty`, `is_active`, `image`, `sort_order`)
SELECT 2, 1, 'RFL-3KG', 'refill-3kg', '3kg Gas Refill', 'A full 3kg refill for your camping cylinder. We collect, refill and return, or deliver a full cylinder.', 'refill', 350000, 25, 1, 'assets/images/products/oyejogas-3kg-refill.png', 2
WHERE NOT EXISTS (SELECT 1 FROM `products` WHERE `slug`='refill-3kg');

-- 6kg new
INSERT INTO `products` (`category_id`, `size_id`, `sku`, `slug`, `name`, `description`, `type`, `price_minor`, `stock_qty`, `is_active`, `is_featured`, `image`, `sort_order`)
SELECT 1, 2, 'CYL-6KG-NEW', 'new-cylinder-6kg', 'New 6kg Cylinder (Full)', 'Brand-new 6kg cylinder supplied full and safety-checked.', 'cylinder_new', 3500000, 10, 1, 1, 'assets/images/products/oyejogas-6kg.png', 3
WHERE NOT EXISTS (SELECT 1 FROM `products` WHERE `slug`='new-cylinder-6kg');

-- 25kg products
INSERT INTO `products` (`category_id`, `size_id`, `sku`, `slug`, `name`, `description`, `type`, `price_minor`, `stock_qty`, `is_active`, `image`, `sort_order`)
SELECT 1, 4, 'CYL-25KG-NEW', 'new-cylinder-25kg', 'New 25kg Cylinder (Full)', 'Brand-new 25kg cylinder for large households and small commercial use.', 'cylinder_new', 7500000, 8, 1, 'assets/images/products/oyejogas-25kg-new.png', 10
WHERE NOT EXISTS (SELECT 1 FROM `products` WHERE `slug`='new-cylinder-25kg');

INSERT INTO `products` (`category_id`, `size_id`, `sku`, `slug`, `name`, `description`, `type`, `price_minor`, `stock_qty`, `is_active`, `image`, `sort_order`)
SELECT 2, 4, 'RFL-25KG', 'refill-25kg', '25kg Gas Refill', 'A full 25kg refill for large households.', 'refill', 2500000, 20, 1, 'assets/images/products/oyejogas-25kg-refill.png', 11
WHERE NOT EXISTS (SELECT 1 FROM `products` WHERE `slug`='refill-25kg');

-- 50kg products
INSERT INTO `products` (`category_id`, `size_id`, `sku`, `slug`, `name`, `description`, `type`, `price_minor`, `stock_qty`, `is_active`, `image`, `sort_order`)
SELECT 1, 5, 'CYL-50KG-NEW', 'new-cylinder-50kg', 'New 50kg Cylinder (Full)', 'Brand-new 50kg commercial cylinder for restaurants, hotels and industrial use.', 'cylinder_new', 12000000, 5, 1, 'assets/images/products/oyejogas-50kg-new.png', 12
WHERE NOT EXISTS (SELECT 1 FROM `products` WHERE `slug`='new-cylinder-50kg');

INSERT INTO `products` (`category_id`, `size_id`, `sku`, `slug`, `name`, `description`, `type`, `price_minor`, `stock_qty`, `is_active`, `image`, `sort_order`)
SELECT 2, 5, 'RFL-50KG', 'refill-50kg', '50kg Gas Refill', 'A full 50kg refill for commercial use.', 'refill', 5000000, 15, 1, 'assets/images/products/oyejogas-50kg-refill.png', 13
WHERE NOT EXISTS (SELECT 1 FROM `products` WHERE `slug`='refill-50kg');
