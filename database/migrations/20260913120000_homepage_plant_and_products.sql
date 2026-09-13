-- Migration: Add homepage sections for plant showcase and product showcase
-- Makes everything editable via Admin → Marketing → Homepage

-- Plant showcase section (editable content)
INSERT INTO `homepage_sections` (`slug`, `title`, `content`, `sort_order`, `is_active`) VALUES
('plant_showcase', 'Our LPG Refilling Plant - Owode-Egba', 'OYEJO GAS LPG REFILLING PLANT - OWODE-EGBA\n\nSafety, Quality, Trust — Energy for Every Home.\n\nState-of-the-art refilling facility with solar power, verified weighing, and sealed cylinders. Our Owode-Egba plant ensures every cylinder is safety-checked and sealed.\n\nFeatures:\n• Solar powered facility\n• Digital weighing and safety checks\n• Same-day pickup and delivery\n• Verified full cylinders', 5, 1)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`), `content`=VALUES(`content`), `is_active`=VALUES(`is_active`);

-- Refilling action section
INSERT INTO `homepage_sections` (`slug`, `title`, `content`, `sort_order`, `is_active`) VALUES
('refilling_action', 'How We Refill Your Cylinder', 'Watch Our Staff Refill Your Cylinder with Precision\n\nStep 1: Customer Brings Empty Cylinder - To our plant or we pickup from your door\n\nStep 2: Staff Refills with Machine - Digital scale, safety valves, sealed cap, professional refilling machine\n\nStep 3: Weighed & Sealed for Delivery - Verified full, receipt, and fast delivery to your home\n\nEvery cylinder is weighed, sealed, and safety-checked at our Owode-Egba plant.', 6, 1)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`), `content`=VALUES(`content`), `is_active`=VALUES(`is_active`);

-- Product showcase section
INSERT INTO `homepage_sections` (`slug`, `title`, `content`, `sort_order`, `is_active`) VALUES
('product_showcase', 'All Cylinder Sizes - Branded OyeJo Gas', 'All Cylinder Sizes — With OyeJo Gas Brand\n\nFrom 3kg camping to 50kg commercial, all our cylinders are branded with OYEJO GAS, sealed, and safety-checked.\n\n• 3kg Camping - Small & Portable - Perfect for camping\n• 6kg - Small Household - Compact for flats\n• 12.5kg - Family Standard - Best seller, most popular\n• 25kg - Large Household - Big families\n• 50kg - Commercial - Restaurants and industrial\n\nAll cylinders: Green #0b6b3a with orange flame logo, white OYEJO GAS text, white background e-commerce ready.', 4, 1)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`), `content`=VALUES(`content`), `is_active`=VALUES(`is_active`);

-- Banners for plant images (editable via Admin → Marketing → Banners)
INSERT INTO `banners` (`title`, `body`, `image`, `link_url`, `position`, `sort_order`, `is_active`) VALUES
('Oyejo Gas Plant - Owode-Egba', 'Safety, Quality, Trust — Energy for Every Home. Our state-of-the-art LPG refilling plant in Owode-Egba.', 'uploads/banners/plant-hero.png', '/#plant', 'home_top', 1, 1),
('Staff Refilling Action', 'Watch our staff refill your cylinder with precision refilling machine at Owode-Egba plant.', 'uploads/banners/refilling-action.png', '/#refill', 'home_bottom', 1, 1)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);

-- Update existing safety note to mention plant
UPDATE `homepage_sections` SET `content`='Every cylinder is weighed, sealed and leak-checked at our Owode-Egba plant before dispatch.\n\nNever use a cylinder with a damaged valve - call us for a free swap check.\n\nOur Owode-Egba refilling plant operates with solar power and digital safety systems.' WHERE `slug`='safety_note';
