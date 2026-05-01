-- ============================================================
-- MIGRATION: Add household_role to residents table
-- Run this once on your brgy_data database
-- ============================================================

USE `brgy_data`;

-- 1. Add the column (nullable so existing rows don't break)
ALTER TABLE `residents`
  ADD COLUMN `household_role` ENUM('head','spouse','child','sibling','extended','boarder','other')
  NOT NULL DEFAULT 'other'
  AFTER `household_id`;

-- 2. Seed existing residents with inferred roles based on seed data
--    (Edit these to match your real data; use the admin UI for future records)
UPDATE `residents` SET `household_role` = 'head'     WHERE `id` IN (15, 17, 20, 22, 23, 25, 26, 27, 29, 30, 31, 32, 33, 34);
UPDATE `residents` SET `household_role` = 'spouse'   WHERE `id` IN (16, 21, 24, 26);
UPDATE `residents` SET `household_role` = 'sibling'  WHERE `id` IN (24, 28);
UPDATE `residents` SET `household_role` = 'extended' WHERE `id` IN (18, 33);
UPDATE `residents` SET `household_role` = 'other'    WHERE `id` IN (19, 29);

-- 3. Correct spouse pairs (spouse should be 'spouse', not both 'head')
--    HH-93-001: Maricel (head) + Ricardo (spouse)
UPDATE `residents` SET `household_role` = 'head'   WHERE `id` = 15;
UPDATE `residents` SET `household_role` = 'spouse' WHERE `id` = 16;

--    HH-93-004: Patricio (head) + Concepcion (spouse)
UPDATE `residents` SET `household_role` = 'head'   WHERE `id` = 22;
UPDATE `residents` SET `household_role` = 'spouse' WHERE `id` = 21;

--    HH-93-006: Arsenio (head) + Luzviminda (spouse/PWD)
UPDATE `residents` SET `household_role` = 'head'   WHERE `id` = 26;
UPDATE `residents` SET `household_role` = 'spouse' WHERE `id` = 25;

--    HH-93-009: Venancio (head) + Imelda (spouse)
UPDATE `residents` SET `household_role` = 'head'   WHERE `id` = 32;
UPDATE `residents` SET `household_role` = 'spouse' WHERE `id` = 31;

-- 4. Verify
SELECT id, full_name, household_id, household_role
FROM residents
ORDER BY household_id, FIELD(household_role,'head','spouse','child','sibling','extended','boarder','other');
