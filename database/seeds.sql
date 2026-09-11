-- =====================================================================
-- Oyejo Gas - seed data (Phase 4). Imported once by the installer,
-- AFTER schema.sql. No login credentials are seeded here: the admin
-- account is created by the installer from operator-supplied input.
-- =====================================================================
SET NAMES utf8mb4;

-- ------------------------------------------------------------ 12 roles
INSERT INTO `roles` (`slug`, `name`, `description`, `is_system`) VALUES
('super_admin', 'Super Admin', 'Full system access', 1),
('admin', 'Admin', 'Day-to-day administration', 1),
('manager', 'Manager', 'Branch and store manager', 1),
('logistics_manager', 'Logistics Manager', 'Dispatch, drivers and zones', 1),
('driver', 'Driver', 'Deliveries and pickups', 1),
('customer_support', 'Customer Support', 'Tickets and customer help', 1),
('marketing_manager', 'Marketing Manager', 'Campaigns and content', 1),
('inventory_manager', 'Inventory Manager', 'Stock and suppliers', 1),
('finance_manager', 'Finance Manager', 'Payments and reconciliation', 1),
('customer', 'Customer', 'Shopping customer', 1),
('registered', 'Registered User', 'Signed-up user with limited access', 1),
('guest', 'Guest User', 'Public visitor, no login', 1);

-- ------------------------------------------------------ 78 permissions
INSERT INTO `permissions` (`slug`, `name`, `group_name`) VALUES
('system.super', 'Super access', 'system'),
('portal.admin', 'Access staff portal', 'portal'),
('portal.customer', 'Access customer portal', 'portal'),
('portal.driver', 'Access driver portal', 'portal'),
('shop.order', 'Place orders', 'portal'),
('wallet.own', 'Use own wallet', 'portal'),
('tickets.own', 'Manage own tickets', 'portal'),
('dashboard.view', 'View dashboard', 'dashboard'),
('users.view', 'View users', 'users'),
('users.create', 'Create users', 'users'),
('users.edit', 'Edit users', 'users'),
('users.suspend', 'Suspend users', 'users'),
('roles.view', 'View roles', 'roles'),
('roles.manage', 'Manage roles and permissions', 'roles'),
('customers.view', 'View customers', 'customers'),
('customers.edit', 'Edit customers', 'customers'),
('customers.suspend', 'Suspend customers', 'customers'),
('drivers.view', 'View drivers', 'drivers'),
('drivers.create', 'Create drivers', 'drivers'),
('drivers.edit', 'Edit drivers', 'drivers'),
('drivers.suspend', 'Suspend drivers', 'drivers'),
('products.view', 'View products', 'catalog'),
('products.create', 'Create products', 'catalog'),
('products.edit', 'Edit products', 'catalog'),
('products.delete', 'Delete products', 'catalog'),
('categories.manage', 'Manage categories', 'catalog'),
('inventory.view', 'View inventory', 'inventory'),
('inventory.adjust', 'Adjust stock', 'inventory'),
('inventory.transfer', 'Transfer stock', 'inventory'),
('cylinders.manage', 'Manage cylinders', 'inventory'),
('purchases.manage', 'Manage purchases', 'purchasing'),
('suppliers.manage', 'Manage suppliers', 'purchasing'),
('orders.view', 'View orders', 'orders'),
('orders.create', 'Create orders', 'orders'),
('orders.edit', 'Edit orders', 'orders'),
('orders.cancel', 'Cancel orders', 'orders'),
('orders.assign', 'Assign orders', 'orders'),
('refills.view', 'View refills', 'refills'),
('refills.manage', 'Manage refills', 'refills'),
('pickups.view', 'View pickups', 'pickups'),
('pickups.manage', 'Manage pickups', 'pickups'),
('pickups.update', 'Update pickup status', 'pickups'),
('deliveries.view', 'View deliveries', 'deliveries'),
('deliveries.assign', 'Assign deliveries', 'deliveries'),
('deliveries.update', 'Update delivery status', 'deliveries'),
('zones.manage', 'Manage delivery zones', 'deliveries'),
('slots.manage', 'Manage time slots', 'deliveries'),
('payments.view', 'View payments', 'payments'),
('payments.verify', 'Verify payments', 'payments'),
('refunds.manage', 'Manage refunds', 'payments'),
('invoices.view', 'View invoices', 'payments'),
('wallet.view', 'View wallets', 'wallet'),
('wallet.adjust', 'Adjust wallets', 'wallet'),
('coupons.manage', 'Manage coupons', 'coupons'),
('tickets.view', 'View tickets', 'tickets'),
('tickets.reply', 'Reply to tickets', 'tickets'),
('tickets.manage', 'Manage tickets', 'tickets'),
('reviews.view', 'View reviews', 'reviews'),
('reviews.moderate', 'Moderate reviews', 'reviews'),
('marketing.campaigns', 'Manage campaigns', 'marketing'),
('marketing.banners', 'Manage banners', 'marketing'),
('marketing.posts', 'Manage posts', 'marketing'),
('marketing.faqs', 'Manage FAQs', 'marketing'),
('marketing.newsletter', 'Manage newsletter', 'marketing'),
('spin.manage', 'Manage spin campaigns', 'engagement'),
('referrals.view', 'View referrals', 'engagement'),
('referrals.manage', 'Manage referrals', 'engagement'),
('notifications.view', 'View notifications', 'notifications'),
('notifications.send', 'Send notifications', 'notifications'),
('settings.view', 'View settings', 'settings'),
('settings.edit', 'Edit settings', 'settings'),
('toggles.manage', 'Manage feature toggles', 'settings'),
('backups.create', 'Create backups', 'ops'),
('backups.restore', 'Restore backups', 'ops'),
('logs.view', 'View error logs', 'ops'),
('logs.manage', 'Resolve errors and rotate logs', 'ops'),
('audit.view', 'View audit logs', 'ops'),
('reports.view', 'View reports', 'reports'),
('addons.view', 'View add-ons', 'addons'),
('addons.manage', 'Manage add-ons', 'addons');

-- ----------------------------------------------- role-permission mapping
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT `id` FROM `roles` WHERE `slug` = 'super_admin'), `id` FROM `permissions`;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT `id` FROM `roles` WHERE `slug` = 'admin'), `id` FROM `permissions`
WHERE `slug` NOT IN ('system.super', 'roles.manage');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT `id` FROM `roles` WHERE `slug` = 'manager'), `id` FROM `permissions`
WHERE `slug` IN ('portal.admin','dashboard.view','users.view','customers.view','customers.edit',
'drivers.view','products.view','inventory.view','orders.view','orders.edit','orders.assign',
'refills.view','pickups.view','deliveries.view','payments.view','tickets.view','tickets.reply',
'reviews.view','reports.view','settings.view');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT `id` FROM `roles` WHERE `slug` = 'logistics_manager'), `id` FROM `permissions`
WHERE `slug` IN ('portal.admin','dashboard.view','drivers.view','orders.view','orders.assign',
'refills.view','refills.manage','pickups.view','pickups.manage','deliveries.view',
'deliveries.assign','zones.manage','slots.manage');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT `id` FROM `roles` WHERE `slug` = 'driver'), `id` FROM `permissions`
WHERE `slug` IN ('portal.driver','deliveries.view','deliveries.update','pickups.view','pickups.update');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT `id` FROM `roles` WHERE `slug` = 'customer_support'), `id` FROM `permissions`
WHERE `slug` IN ('portal.admin','dashboard.view','customers.view','orders.view','tickets.view',
'tickets.reply','tickets.manage','reviews.view','refills.view','pickups.view');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT `id` FROM `roles` WHERE `slug` = 'marketing_manager'), `id` FROM `permissions`
WHERE `slug` IN ('portal.admin','dashboard.view','marketing.campaigns','marketing.banners',
'marketing.posts','marketing.faqs','marketing.newsletter','coupons.manage','spin.manage',
'referrals.view','notifications.view','notifications.send');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT `id` FROM `roles` WHERE `slug` = 'inventory_manager'), `id` FROM `permissions`
WHERE `slug` IN ('portal.admin','dashboard.view','products.view','products.create','products.edit',
'products.delete','categories.manage','inventory.view','inventory.adjust','inventory.transfer',
'purchases.manage','suppliers.manage','cylinders.manage');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT `id` FROM `roles` WHERE `slug` = 'finance_manager'), `id` FROM `permissions`
WHERE `slug` IN ('portal.admin','dashboard.view','payments.view','payments.verify','refunds.manage',
'invoices.view','wallet.view','orders.view','reports.view');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT `id` FROM `roles` WHERE `slug` = 'customer'), `id` FROM `permissions`
WHERE `slug` IN ('portal.customer','shop.order','wallet.own','tickets.own');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT `id` FROM `roles` WHERE `slug` = 'registered'), `id` FROM `permissions`
WHERE `slug` IN ('portal.customer');

-- -------------------------------------------------- 25 feature toggles
INSERT INTO `feature_toggles` (`key`, `label`, `description`, `enabled`) VALUES
('customer_registration', 'Customer registration', 'Allow new customer sign-ups', 1),
('guest_checkout', 'Guest checkout', 'Allow ordering without an account', 1),
('product_ordering', 'Product ordering', 'Enable the shop and checkout', 1),
('gas_refills', 'Gas refills', 'Enable refill requests', 1),
('cylinder_pickups', 'Cylinder pickups', 'Enable pickup requests', 1),
('cylinder_exchange', 'Cylinder exchange', 'Enable cylinder exchange', 1),
('delivery_service', 'Delivery service', 'Enable deliveries', 1),
('driver_portal', 'Driver portal', 'Enable driver access', 1),
('online_payments', 'Online payments', 'Enable gateway payments', 1),
('bank_transfers', 'Bank transfers', 'Enable bank transfer payments', 1),
('cash_on_delivery', 'Cash on delivery', 'Enable cash on delivery', 1),
('customer_wallet', 'Customer wallet', 'Enable wallet top-up and payments', 1),
('coupons', 'Coupons', 'Enable coupon codes', 1),
('reviews', 'Reviews', 'Enable product and delivery reviews', 1),
('support_tickets', 'Support tickets', 'Enable the helpdesk', 1),
('referrals', 'Referrals', 'Enable referral rewards', 1),
('spin_to_win', 'Spin-to-win', 'Enable spin campaigns', 1),
('email_notifications', 'Email notifications', 'Send emails', 1),
('sms_notifications', 'SMS notifications', 'Send SMS messages', 1),
('whatsapp_notifications', 'WhatsApp notifications', 'Send WhatsApp messages', 1),
('push_notifications', 'Push notifications', 'Send push notifications', 1),
('pwa_installation', 'PWA installation', 'Allow app installation', 1),
('marketing_campaigns', 'Marketing campaigns', 'Enable promotions', 1),
('addons', 'Add-ons', 'Enable the add-on system', 1),
('maintenance_mode', 'Maintenance mode', 'Lock the public site for maintenance', 0);

-- ------------------------------------------------------- website settings
INSERT INTO `settings` (`key`, `value`, `group_name`) VALUES
('site_name', 'Oyejo Gas', 'site'),
('tagline', 'Cooking gas, delivered.', 'site'),
('site_logo', '', 'appearance'),
('site_favicon', '', 'appearance'),
('site_color_primary', '#0b6b3a', 'appearance'),
('site_color_accent', '#ff9d2e', 'appearance'),
('pwa_name', 'Oyejo Gas - LPG ordering, refills & delivery', 'pwa'),
('pwa_short_name', 'Oyejo Gas', 'pwa'),
('pwa_theme_color', '#0b6b3a', 'pwa'),
('pwa_bg_color', '#f6f8f6', 'pwa'),
('contact_email', 'support@example.com', 'contact'),
('contact_phone', '+234 800 000 0000', 'contact'),
('contact_address', 'Lagos, Nigeria', 'contact'),
('currency', 'NGN', 'locale'),
('currency_symbol', '₦', 'locale'),
('timezone', 'Africa/Lagos', 'locale'),
('orders_prefix', 'ORD-', 'orders'),
('min_order_minor', '0', 'orders'),
('wallet_topup_min_minor', '10000', 'wallet'),
('referral_reward_referrer_minor', '50000', 'referrals'),
('referral_reward_referred_minor', '25000', 'referrals'),
('referral_min_purchase_minor', '200000', 'referrals'),
('referral_reward_expiry_days', '30', 'referrals'),
('referral_velocity_24h', '5', 'referrals'),
('notify_rate_per_minute', '30', 'notifications'),
('notify_max_attempts', '5', 'notifications'),
('backup_retention_days', '14', 'ops'),
('backup_keep_min', '3', 'ops'),
('backup_max_age_hours', '24', 'ops'),
('log_max_mb', '5', 'ops'),
('log_keep_files', '5', 'ops');

-- ------------------------------------------------------- product categories
INSERT INTO `categories` (`slug`, `name`, `description`, `sort_order`, `is_active`) VALUES
('lpg-cylinders', 'LPG Cylinders', 'New gas cylinders in all sizes', 1, 1),
('gas-refills', 'Gas Refills', 'Refill your existing cylinders', 2, 1),
('cylinder-exchange', 'Cylinder Exchange', 'Swap empty cylinders for full ones', 3, 1),
('accessories', 'Accessories', 'Regulators, hoses, burners and more', 4, 1);

-- ---------------------------------------------------------- cylinder sizes
INSERT INTO `cylinder_sizes` (`code`, `name`, `weight_kg`, `deposit_minor`, `sort_order`, `is_active`) VALUES
('3kg', '3kg Camping', 3.00, 150000, 1, 1),
('6kg', '6kg', 6.00, 250000, 2, 1),
('12.5kg', '12.5kg', 12.50, 400000, 3, 1),
('25kg', '25kg', 25.00, 800000, 4, 1),
('50kg', '50kg', 50.00, 1500000, 5, 1);

-- ---------------------------------------------------------- delivery zones
INSERT INTO `delivery_zones` (`name`, `description`, `fee_minor`, `free_above_minor`, `is_active`, `sort_order`) VALUES
('Lagos Mainland', 'Mainland neighbourhoods', 100000, 2000000, 1, 1),
('Ikeja and Environs', 'Ikeja, Ogba, Alausa axis', 120000, 2000000, 1, 2),
('Lagos Island', 'Ikoyi, Victoria Island, Marina axis', 150000, 2000000, 1, 3),
('Lekki and Ajah', 'Lekki, Ajah and Sangotedo axis', 180000, 2000000, 1, 4);

-- ---------------------------------------------------------- delivery slots
INSERT INTO `delivery_slots` (`name`, `window_start`, `window_end`, `is_active`, `sort_order`) VALUES
('Morning', '08:00:00', '12:00:00', 1, 1),
('Afternoon', '12:00:00', '16:00:00', 1, 2),
('Evening', '16:00:00', '20:00:00', 1, 3);

-- --------------------------------------------- core notification templates
INSERT INTO `notification_templates` (`slug`, `channel`, `event`, `subject`, `body`, `is_active`) VALUES
('user-password-reset-email', 'email', 'password_reset', 'Reset your password',
'Hello {{name}}, use this link to reset your password: {{link}}. It expires in {{ttl}}.', 1),
('user-registered-email', 'email', 'registration', 'Welcome to {{site}}',
'Hello {{name}}, your account is ready. Verify your email here: {{link}}.', 1),
('order-confirmed-email', 'email', 'order_confirmation', 'Order {{order_number}} confirmed',
'Hello {{name}}, your order {{order_number}} ({{total}}) is confirmed. Track it here: {{link}}.', 1),
('order-confirmed-whatsapp', 'whatsapp', 'order_confirmation', NULL,
'Hello {{name}}, your {{site}} order {{order_number}} ({{total}}) is confirmed.', 1),
('payment-confirmed-whatsapp', 'whatsapp', 'payment_confirmation', NULL,
'Hello {{name}}, payment of {{total}} for order {{order_number}} was received.', 1),
('delivery-completed-whatsapp', 'whatsapp', 'delivery_completion', NULL,
'Hello {{name}}, your order {{order_number}} has been delivered. Thank you for choosing {{site}}.', 1),
('ticket-update-email', 'email', 'ticket_update', '{{subject}}',
'Hello {{name}}, there is an update on your support ticket: {{message}}.', 1),
('wallet-credited-email', 'email', 'wallet_transaction', 'Wallet {{direction}}: {{amount}}',
'Hello {{name}}, your wallet was {{direction}} {{amount}}. New balance: {{balance}}. {{note}}', 1),
('referral-reward-email', 'email', 'referral_reward', 'Referral reward received',
'Hello {{name}}, you earned {{amount}} in referral rewards.', 1),
('spin-reward-email', 'email', 'spin_reward', 'You won {{prize}}',
'Hello {{name}}, your spin won {{prize}}! {{detail}}', 1),
('pickup-reminder-sms', 'sms', 'pickup_reminder', NULL,
'{{site}}: pickup {{pickup_number}} is scheduled for {{scheduled_date}}. Please keep the cylinder ready.', 1),
('pickup-reminder-whatsapp', 'whatsapp', 'pickup_reminder', NULL,
'Hello {{name}}, your {{site}} pickup {{pickup_number}} is scheduled for {{scheduled_date}}.', 1),
('promo-email', 'email', 'promo', '{{subject}}',
'{{message}}', 1),
('promo-whatsapp', 'whatsapp', 'promo', NULL,
'*{{subject}}*\n{{message}}', 1);

-- ------------------------------------------------------------ starter FAQs
INSERT INTO `faqs` (`question`, `answer`, `category`, `sort_order`, `is_active`) VALUES
('Which areas do you deliver to?',
'We deliver across Lagos, including the Mainland, Ikeja, Lagos Island, Lekki and Ajah. More zones are added regularly.',
'delivery', 1, 1),
('How does a gas refill work?',
'Choose your cylinder size, check out, and we will collect, refill and return your cylinder, or deliver a full one to you.',
'refills', 2, 1),
('Can I exchange my empty cylinder?',
'Yes. Book a cylinder exchange and our driver will swap your empty cylinder for a full one at your door.',
'exchange', 3, 1),
('Which payment methods do you accept?',
'Wallet, card payments online, bank transfer and cash on delivery. Availability depends on your area.',
'payments', 4, 1);

-- ------------------------------------------------------ homepage sections
-- NOTE: hero/services/how_it_works render statically on the homepage, so
-- these CMS rows ship inactive as editable spares. Content must be plain
-- text with blank-line paragraphs - the renderer escapes it, not JSON.
INSERT INTO `homepage_sections` (`slug`, `title`, `content`, `sort_order`, `is_active`) VALUES
('hero', 'Hero', 'Cooking gas, delivered.\n\nOrder LPG, book refills and swap cylinders - with live delivery tracking.', 1, 0),
('services', 'Services', 'Order LPG, gas refills, pickup and exchange, and fast delivery - everything around your cooking gas, in one place.', 2, 0),
('how_it_works', 'How it works', 'Choose your cylinder size or refill.\n\nCheck out with wallet, transfer, card or cash.\n\nRelax while we pick up, refill and deliver.', 3, 0);

-- ---------------------------------------------------------- schema baseline
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('0000_base_schema', 1);

-- ---------------------------------------------------------- demo products
-- Category ids 1-4 and size ids 1-5 are deterministic on a fresh install.
INSERT INTO `products` (`category_id`, `size_id`, `sku`, `slug`, `name`, `description`, `type`, `price_minor`, `promo_price_minor`, `promo_starts_at`, `promo_ends_at`, `stock_qty`, `low_stock_at`, `track_inventory`, `is_active`, `is_featured`, `sort_order`) VALUES
(2, 3, 'RFL-125', 'refill-12-5kg', '12.5kg Gas Refill', 'A full 12.5kg refill for your family-size cylinder. We collect, refill and return, or deliver a full cylinder to your door.', 'refill', 1250000, 1150000, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 30 DAY, 40, 5, 1, 1, 1, 1),
(2, 2, 'RFL-6', 'refill-6kg', '6kg Gas Refill', 'A full 6kg refill, sized for small households and single burners.', 'refill', 650000, NULL, NULL, NULL, 30, 5, 1, 1, 1, 2),
(1, 3, 'CYL-125-NEW', 'new-cylinder-12-5kg', 'New 12.5kg Cylinder (Full)', 'Brand-new 12.5kg cylinder supplied full and safety-checked, with receipt and warranty.', 'cylinder_new', 4500000, NULL, NULL, NULL, 12, 3, 1, 1, 1, 3),
(3, 3, 'EXC-125', 'exchange-12-5kg', '12.5kg Cylinder Exchange', 'Swap your empty 12.5kg cylinder for a full one at your door. No waiting, no deposit queue.', 'exchange', 1300000, NULL, NULL, NULL, 0, 5, 0, 1, 0, 4),
(4, NULL, 'ACC-REG', 'regulator-hose-set', 'Regulator + Hose Set', 'Safety regulator with 1.5m reinforced hose and clips. Fits all standard cylinders.', 'accessory', 850000, NULL, NULL, NULL, 3, 5, 1, 1, 0, 5),
(4, NULL, 'ACC-BURN', 'table-top-burner', 'Table-top Gas Burner', 'Two-burner table-top cooker with auto ignition and windshield legs.', 'accessory', 550000, NULL, NULL, NULL, 0, 2, 1, 1, 0, 6),
(4, NULL, 'ACC-OLD', 'old-valve', 'Old Valve (Retired)', 'Retired demo row used to verify inactive products stay hidden.', 'accessory', 100000, NULL, NULL, NULL, 0, 2, 1, 0, 0, 7);

-- ---------------------------------------------------------- demo coupons
INSERT INTO `coupons` (`code`, `name`, `type`, `value`, `min_order_minor`, `max_discount_minor`, `usage_limit`, `used_count`, `starts_at`, `ends_at`, `is_active`) VALUES
('WELCOME10', 'Welcome 10% off', 'percent', 10, 500000, 200000, 100, 0, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 90 DAY, 1),
('FLAT500', 'Flat 500 off', 'fixed', 50000, 1000000, NULL, NULL, 0, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 90 DAY, 1);

-- ---------------------------------------------------------- demo CMS content
INSERT INTO `faqs` (`question`, `answer`, `category`, `sort_order`, `is_active`) VALUES
('How fast is delivery?', 'Same-day in most Lagos zones when you order before 2pm.', 'delivery', 1, 1),
('How do refills work?', 'Book a refill, we pick up your empty cylinder and return it filled, or swap it instantly.', 'refill', 2, 1),
('Which payment methods do you accept?', 'Wallet, bank transfer, card online, or cash on delivery.', 'payment', 3, 1),
('Is my deposit refundable?', 'Yes. Cylinder deposits are refunded when you return our cylinder in good condition.', 'cylinder', 4, 1);

INSERT INTO `announcements` (`title`, `body`, `audience`, `is_active`) VALUES
('Welcome to Oyejo Gas', 'Order LPG, book refills and track deliveries from your account.', 'all', 1);

INSERT INTO `homepage_sections` (`slug`, `title`, `content`, `sort_order`, `is_active`) VALUES
('safety_note', 'Safety first', 'Every cylinder is weighed, sealed and leak-checked before dispatch.\n\nNever use a cylinder with a damaged valve - call us for a free swap check.', 10, 1),
('service_area', 'Where we deliver', 'We currently serve Lagos zones with same-day slots.\n\nMore cities are coming soon.', 20, 1);

-- ------------------------------------------------------- wallet settings
INSERT INTO `settings` (`key`, `value`, `group_name`) VALUES
('wallet_topup_max_minor', '50000000', 'wallet'),
('wallet_balance_cap_minor', '200000000', 'wallet'),
('wallet_daily_topup_max_minor', '100000000', 'wallet'),
('wallet_daily_topup_count', '5', 'wallet');
