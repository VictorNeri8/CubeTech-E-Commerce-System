
USE cubetech_ecommerce;

-- Categories
INSERT INTO categories (name) VALUES
('Electronics'),
('Accessories'),
('Home and Living');

-- Products
INSERT INTO products
(category_id, name, description, image, price, stock, is_active)
VALUES
((SELECT id FROM categories WHERE name = 'Electronics'),
 'Wireless Mouse', 'Compact wireless mouse for everyday use.',
 'mouse.jpg', 450.00, 25, 1),

((SELECT id FROM categories WHERE name = 'Electronics'),
 'Mechanical Keyboard', 'Mechanical keyboard for productivity and gaming.',
 'keyboard.jpg', 1850.00, 12, 1),

((SELECT id FROM categories WHERE name = 'Electronics'),
 'USB-C Hub', 'Multiport USB-C adapter.',
 'hub.jpg', 950.00, 18, 1),

((SELECT id FROM categories WHERE name = 'Electronics'),
 'Bluetooth Speaker', 'Portable wireless speaker.',
 'speaker.jpg', 1250.00, 0, 1),

((SELECT id FROM categories WHERE name = 'Accessories'),
 'Laptop Stand', 'Adjustable laptop stand.',
 'stand.jpg', 750.00, 20, 1),

((SELECT id FROM categories WHERE name = 'Accessories'),
 'Wireless Earbuds', 'Wireless earbuds with charging case.',
 'earbuds.jpg', 1499.00, 15, 1),

((SELECT id FROM categories WHERE name = 'Accessories'),
 'Mouse Pad', 'Large desk mouse pad.',
 'mousepad.jpg', 250.00, 40, 1),

((SELECT id FROM categories WHERE name = 'Accessories'),
 'USB Flash Drive', '64GB USB flash drive.',
 'flashdrive.jpg', 350.00, 30, 1),

((SELECT id FROM categories WHERE name = 'Home and Living'),
 'Desk Lamp', 'LED desk lamp for your workspace.',
 'lamp.jpg', 650.00, 10, 1),

((SELECT id FROM categories WHERE name = 'Home and Living'),
 'Storage Organizer', 'Desktop storage organizer.',
 'organizer.jpg', 399.00, 8, 1),

((SELECT id FROM categories WHERE name = 'Home and Living'),
 'Decorative Clock', 'Minimal wall clock.',
 'clock.jpg', 550.00, 5, 0);
