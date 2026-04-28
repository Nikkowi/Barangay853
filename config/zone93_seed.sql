-- ============================================================
-- ZONE 93 SEED DATA — Barangay 853, Pandacan, Manila
-- 15 Staff (admin/staff roles) + 20 Residents
-- Generated: 2026-04-28
-- NOTE: Passwords are hashed bcrypt of "Password@123"
-- ============================================================

USE `brgy_data`;

-- ============================================================
-- 1. USERS (15 staff + 20 residents = 35 total)
--    Existing IDs used: 1,15,16,17,18,19,20,21,23
--    New IDs start at 24
-- ============================================================

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `address`, `bio`, `is_active`, `created_at`, `updated_at`) VALUES
-- STAFF (admin / staff roles) — IDs 24–38
(24, 'Roger Santos',        'roger.santos@brgy853.ph',      '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'admin',  '09171234501', 'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Chairman', 1, '2026-04-28 08:00:00', '2026-04-28 08:00:00'),
(25, 'Maria Reyes',         'maria.reyes@brgy853.ph',       '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'admin',  '09171234502', 'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Secretary', 1, '2026-04-28 08:00:00', '2026-04-28 08:00:00'),
(26, 'Jose dela Cruz',      'jose.delacruz@brgy853.ph',     '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'admin',  '09171234503', 'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Treasurer', 1, '2026-04-28 08:00:00', '2026-04-28 08:00:00'),
(27, 'Lourdes Garcia',      'lourdes.garcia@brgy853.ph',    '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'staff',  '09171234504', 'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Kagawad', 1, '2026-04-28 08:00:00', '2026-04-28 08:00:00'),
(28, 'Eduardo Mendoza',     'eduardo.mendoza@brgy853.ph',   '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'staff',  '09171234505', 'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Kagawad', 1, '2026-04-28 08:00:00', '2026-04-28 08:00:00'),
(29, 'Rosario Bautista',    'rosario.bautista@brgy853.ph',  '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'staff',  '09171234506', 'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Kagawad', 1, '2026-04-28 08:00:00', '2026-04-28 08:00:00'),
(30, 'Fernando Aquino',     'fernando.aquino@brgy853.ph',   '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'staff',  '09171234507', 'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Kagawad', 1, '2026-04-28 08:00:00', '2026-04-28 08:00:00'),
(31, 'Teresita Villanueva', 'teresita.villanueva@brgy853.ph','$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'staff',  '09171234508', 'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Kagawad', 1, '2026-04-28 08:00:00', '2026-04-28 08:00:00'),
(32, 'Renato Flores',       'renato.flores@brgy853.ph',     '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'staff',  '09171234509', 'Zone 93, Brgy 853, Pandacan, Manila', 'SK Chairman', 1, '2026-04-28 08:00:00', '2026-04-28 08:00:00'),
(33, 'Gloria Ramos',        'gloria.ramos@brgy853.ph',      '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'staff',  '09171234510', 'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Health Worker', 1, '2026-04-28 08:00:00', '2026-04-28 08:00:00'),
(34, 'Danilo Pascual',      'danilo.pascual@brgy853.ph',    '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'staff',  '09171234511', 'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Tanod Chief', 1, '2026-04-28 08:00:00', '2026-04-28 08:00:00'),
(35, 'Carmela Soriano',     'carmela.soriano@brgy853.ph',   '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'staff',  '09171234512', 'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Tanod', 1, '2026-04-28 08:00:00', '2026-04-28 08:00:00'),
(36, 'Alfredo Torres',      'alfredo.torres@brgy853.ph',    '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'staff',  '09171234513', 'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Tanod', 1, '2026-04-28 08:00:00', '2026-04-28 08:00:00'),
(37, 'Natividad Cruz',      'natividad.cruz@brgy853.ph',    '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'staff',  '09171234514', 'Zone 93, Brgy 853, Pandacan, Manila', 'Day Care Worker', 1, '2026-04-28 08:00:00', '2026-04-28 08:00:00'),
(38, 'Rodrigo Castillo',    'rodrigo.castillo@brgy853.ph',  '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'staff',  '09171234515', 'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Tanod', 1, '2026-04-28 08:00:00', '2026-04-28 08:00:00'),

-- RESIDENTS — IDs 39–58
(39, 'Maricel Domingo',     'maricel.domingo@gmail.com',    '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234520', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(40, 'Ricardo Navarro',     'ricardo.navarro@gmail.com',    '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234521', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(41, 'Elvira Salazar',      'elvira.salazar@gmail.com',     '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234522', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(42, 'Bernardo Ocampo',     'bernardo.ocampo@gmail.com',    '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234523', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(43, 'Anita Sta. Ana',      'anita.staana@gmail.com',       '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234524', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(44, 'Wilfredo Hernandez',  'wilfredo.hernandez@gmail.com', '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234525', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(45, 'Concepcion Reyes',    'concepcion.reyes@gmail.com',   '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234526', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(46, 'Patricio Lim',        'patricio.lim@gmail.com',       '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234527', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(47, 'Josefina Tan',        'josefina.tan@gmail.com',       '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234528', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(48, 'Eugenio Dela Torre',  'eugenio.delatorre@gmail.com',  '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234529', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(49, 'Luzviminda Espiritu', 'luzviminda.espiritu@gmail.com','$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234530', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(50, 'Arsenio Macaraeg',    'arsenio.macaraeg@gmail.com',   '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234531', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(51, 'Felicitas Aguilar',   'felicitas.aguilar@gmail.com',  '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234532', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(52, 'Celestino Miranda',   'celestino.miranda@gmail.com',  '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234533', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(53, 'Rhodora Pascua',      'rhodora.pascua@gmail.com',     '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234534', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(54, 'Simplicio Buenaventura','simplicio.buenaventura@gmail.com','$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe','resident','09181234535','Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(55, 'Imelda Castañeda',    'imelda.castaneda@gmail.com',   '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234536', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(56, 'Venancio Padilla',    'venancio.padilla@gmail.com',   '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234537', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(57, 'Purificacion Abante', 'puri.abante@gmail.com',        '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234538', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(58, 'Hermenegildo Solis',  'hermenegildo.solis@gmail.com', '$2y$10$V8.FMLY6R4ej4v7Q6fcwUu/coqr6X9/9cTIIJ23bSpAt2KkRKWzoe', 'resident','09181234539', 'Zone 93, Brgy 853, Pandacan, Manila', NULL, 1, '2026-04-28 08:30:00', '2026-04-28 08:30:00');


-- ============================================================
-- 2. HOUSEHOLDS (10 households for 20 residents, ~2 per household)
-- ============================================================

INSERT INTO `households` (`id`, `household_code`, `address_line`, `purok_sitio`, `zone`, `barangay`, `city`, `province`, `postal_code`, `profile_notes`, `created_at`, `updated_at`) VALUES
(1, 'HH-93-001', '2880-A Kahilom III',     NULL, 'Zone 93', 'Barangay 853', 'Pandacan, Manila', 'Metro Manila', '1011', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(2, 'HH-93-002', '2880-B Kahilom III',     NULL, 'Zone 93', 'Barangay 853', 'Pandacan, Manila', 'Metro Manila', '1011', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(3, 'HH-93-003', '2881 Kahilom III',       NULL, 'Zone 93', 'Barangay 853', 'Pandacan, Manila', 'Metro Manila', '1011', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(4, 'HH-93-004', '2882 Kahilom III',       NULL, 'Zone 93', 'Barangay 853', 'Pandacan, Manila', 'Metro Manila', '1011', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(5, 'HH-93-005', '2883 Kahilom III',       NULL, 'Zone 93', 'Barangay 853', 'Pandacan, Manila', 'Metro Manila', '1011', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(6, 'HH-93-006', '2884 Kahilom III',       NULL, 'Zone 93', 'Barangay 853', 'Pandacan, Manila', 'Metro Manila', '1011', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(7, 'HH-93-007', '2885 Kahilom III',       NULL, 'Zone 93', 'Barangay 853', 'Pandacan, Manila', 'Metro Manila', '1011', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(8, 'HH-93-008', '2886-A Kahilom III',     NULL, 'Zone 93', 'Barangay 853', 'Pandacan, Manila', 'Metro Manila', '1011', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(9, 'HH-93-009', '2886-B Kahilom III',     NULL, 'Zone 93', 'Barangay 853', 'Pandacan, Manila', 'Metro Manila', '1011', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(10,'HH-93-010', '2887 Kahilom III',       NULL, 'Zone 93', 'Barangay 853', 'Pandacan, Manila', 'Metro Manila', '1011', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00');


-- ============================================================
-- 3. RESIDENTS (20 residents linked to households)
--    IDs 15–58 (using next available after existing 12,13,14)
--    Continuing from id=15
-- ============================================================

INSERT INTO `residents` (`id`, `household_id`, `full_name`, `gender`, `resident_type`, `date_of_birth`, `place_of_birth`, `civil_status`, `nationality`, `religion`, `contact_mobile`, `contact_email`, `is_senior_citizen`, `is_pwd`, `pwd_type`, `chronic_illnesses`, `registered_voter`, `voter_precinct_no`, `created_at`, `updated_at`) VALUES
(15, 1,  'Maricel Domingo',        'Female', 'Permanent', '1985-03-12', 'Manila',       'Married',  'Filipino', 'Catholic',     '09181234520', 'maricel.domingo@gmail.com',        0, 0, NULL, NULL, 1, '0853A-001', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(16, 1,  'Ricardo Navarro',        'Male',   'Permanent', '1983-07-25', 'Pandacan',     'Married',  'Filipino', 'Catholic',     '09181234521', 'ricardo.navarro@gmail.com',        0, 0, NULL, NULL, 1, '0853A-001', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(17, 2,  'Elvira Salazar',         'Female', 'Permanent', '1955-11-08', 'Manila',       'Widowed',  'Filipino', 'Catholic',     '09181234522', 'elvira.salazar@gmail.com',         1, 0, NULL, 'Hypertension', 1, '0853A-002', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(18, 2,  'Bernardo Ocampo',        'Male',   'Permanent', '1950-06-14', 'Tondo',        'Widowed',  'Filipino', 'Catholic',     '09181234523', 'bernardo.ocampo@gmail.com',        1, 0, NULL, 'Diabetes', 1, '0853A-002', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(19, 3,  'Anita Sta. Ana',         'Female', 'Permanent', '1990-01-30', 'Quezon City',  'Single',   'Filipino', 'Catholic',     '09181234524', 'anita.staana@gmail.com',           0, 0, NULL, NULL, 1, '0853A-003', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(20, 3,  'Wilfredo Hernandez',     'Male',   'Permanent', '1988-09-22', 'Manila',       'Married',  'Filipino', 'Catholic',     '09181234525', 'wilfredo.hernandez@gmail.com',     0, 0, NULL, NULL, 1, '0853A-003', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(21, 4,  'Concepcion Reyes',       'Female', 'Permanent', '1975-12-05', 'Sampaloc',     'Married',  'Filipino', 'Iglesia ni Cristo','09181234526','concepcion.reyes@gmail.com',   0, 0, NULL, NULL, 1, '0853A-004', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(22, 4,  'Patricio Lim',           'Male',   'Permanent', '1972-04-17', 'Binondo',      'Married',  'Filipino', 'Catholic',     '09181234527', 'patricio.lim@gmail.com',           0, 0, NULL, NULL, 1, '0853A-004', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(23, 5,  'Josefina Tan',           'Female', 'Permanent', '1998-08-11', 'Manila',       'Single',   'Filipino', 'Catholic',     '09181234528', 'josefina.tan@gmail.com',           0, 0, NULL, NULL, 1, '0853A-005', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(24, 5,  'Eugenio Dela Torre',     'Male',   'Permanent', '1996-02-28', 'Pandacan',     'Single',   'Filipino', 'Born Again',   '09181234529', 'eugenio.delatorre@gmail.com',      0, 0, NULL, NULL, 0, NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(25, 6,  'Luzviminda Espiritu',    'Female', 'Permanent', '1969-05-19', 'Sta. Cruz',    'Married',  'Filipino', 'Catholic',     '09181234530', 'luzviminda.espiritu@gmail.com',    0, 1, 'Physical Disability', NULL, 1, '0853A-006', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(26, 6,  'Arsenio Macaraeg',       'Male',   'Permanent', '1967-10-03', 'Taguig',       'Married',  'Filipino', 'Catholic',     '09181234531', 'arsenio.macaraeg@gmail.com',       0, 0, NULL, NULL, 1, '0853A-006', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(27, 7,  'Felicitas Aguilar',      'Female', 'Permanent', '2003-07-07', 'Manila',       'Single',   'Filipino', 'Catholic',     '09181234532', 'felicitas.aguilar@gmail.com',      0, 0, NULL, NULL, 1, '0853A-007', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(28, 7,  'Celestino Miranda',      'Male',   'Permanent', '2001-11-15', 'Pandacan',     'Single',   'Filipino', 'Catholic',     '09181234533', 'celestino.miranda@gmail.com',      0, 0, NULL, NULL, 1, '0853A-007', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(29, 8,  'Rhodora Pascua',         'Female', 'Permanent', '1979-03-24', 'Malate',       'Separated','Filipino', 'Catholic',     '09181234534', 'rhodora.pascua@gmail.com',         0, 0, NULL, NULL, 1, '0853A-008', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(30, 8,  'Simplicio Buenaventura', 'Male',   'Permanent', '1960-08-30', 'Manila',       'Married',  'Filipino', 'Catholic',     '09181234535', 'simplicio.buenaventura@gmail.com', 1, 0, NULL, 'Arthritis', 1, '0853A-008', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(31, 9,  'Imelda Castañeda',       'Female', 'Permanent', '1993-06-06', 'Manila',       'Single',   'Filipino', 'Catholic',     '09181234536', 'imelda.castaneda@gmail.com',       0, 0, NULL, NULL, 1, '0853A-009', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(32, 9,  'Venancio Padilla',       'Male',   'Permanent', '1991-12-20', 'Paco',         'Married',  'Filipino', 'Catholic',     '09181234537', 'venancio.padilla@gmail.com',       0, 0, NULL, NULL, 1, '0853A-009', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(33, 10, 'Purificacion Abante',    'Female', 'Permanent', '1948-09-01', 'Pasay',        'Widowed',  'Filipino', 'Catholic',     '09181234538', 'puri.abante@gmail.com',            1, 1, 'Visual Impairment', 'Hypertension, Cataracts', 1, '0853A-010', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(34, 10, 'Hermenegildo Solis',     'Male',   'Permanent', '1945-02-14', 'Sta. Ana',     'Married',  'Filipino', 'Catholic',     '09181234539', 'hermenegildo.solis@gmail.com',     1, 0, NULL, 'Heart Disease, Diabetes', 1, '0853A-010', '2026-04-28 08:30:00', '2026-04-28 08:30:00');


-- ============================================================
-- 4. RESIDENT RESIDENCY (for all 20 residents)
-- ============================================================

INSERT INTO `resident_residency` (`resident_id`, `year_moved_in`, `original_city_or_province`, `residency_type`, `residency_documents`, `created_at`, `updated_at`) VALUES
(15, '2010', 'Quezon City',   'Renter',    NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(16, '2010', 'Quezon City',   'Renter',    NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(17, '1985', 'Manila',        'Homeowner', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(18, '1980', 'Tondo',         'Homeowner', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(19, '2015', 'Quezon City',   'Renter',    NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(20, '2013', 'Manila',        'Renter',    NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(21, '2000', 'Sampaloc',      'Homeowner', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(22, '2000', 'Binondo',       'Homeowner', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(23, '2020', 'Manila',        'Renter',    NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(24, '2019', 'Pandacan',      'Renter',    NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(25, '1995', 'Sta. Cruz',     'Homeowner', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(26, '1995', 'Taguig',        'Homeowner', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(27, '2021', 'Manila',        'Renter',    NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(28, '2020', 'Pandacan',      'Renter',    NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(29, '2005', 'Malate',        'Renter',    NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(30, '1990', 'Manila',        'Homeowner', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(31, '2017', 'Manila',        'Renter',    NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(32, '2016', 'Paco',          'Renter',    NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(33, '1975', 'Pasay',         'Homeowner', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(34, '1970', 'Sta. Ana',      'Homeowner', NULL, '2026-04-28 08:30:00', '2026-04-28 08:30:00');


-- ============================================================
-- 5. RESIDENT EMPLOYMENT (20 residents)
-- ============================================================

INSERT INTO `resident_employment` (`resident_id`, `employment_status`, `nature_of_work`, `employer_name`, `work_address`, `monthly_income_range`, `created_at`, `updated_at`) VALUES
(15, 'Employed',       'Private',     'SM Pandacan',        'Pandacan, Manila',         '₱10,000–₱20,000',  '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(16, 'Employed',       'Private',     'Manila Electric Co.','Meralco Ave, Pasig',       '₱20,001–₱30,000',  '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(17, 'Retired',        NULL,          NULL,                  NULL,                       'Below ₱5,000',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(18, 'Retired',        NULL,          NULL,                  NULL,                       'Below ₱5,000',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(19, 'Employed',       'Private',     'BPO Manila',         'Ermita, Manila',            '₱15,000–₱25,000',  '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(20, 'Self-Employed',  'Trade',       'Own Sari-sari Store', 'Zone 93, Brgy 853',        '₱5,000–₱10,000',   '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(21, 'Employed',       'Government',  'Manila City Hall',   'Arroceros, Manila',         '₱20,001–₱30,000',  '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(22, 'Self-Employed',  'Trade',       'Hardware Store',     'Binondo, Manila',           '₱30,001–₱50,000',  '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(23, 'Employed',       'Private',     'Jollibee Pandacan',  'Pandacan, Manila',          '₱10,000–₱15,000',  '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(24, 'Student',        NULL,          'PUP Manila',         'Sta. Mesa, Manila',         'Below ₱5,000',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(25, 'Unemployed',     NULL,          NULL,                  NULL,                       'Below ₱5,000',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(26, 'Employed',       'Private',     'Petron Corporation', 'Pandacan, Manila',          '₱20,001–₱30,000',  '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(27, 'Student',        NULL,          'Mapua Manila',       'Intramuros, Manila',        'Below ₱5,000',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(28, 'Employed',       'Private',     'Grab Philippines',   'Online / Field',            '₱10,000–₱20,000',  '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(29, 'Employed',       'Private',     'Philippine General Hospital','Taft Ave, Manila',  '₱15,000–₱25,000',  '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(30, 'Retired',        NULL,          NULL,                  NULL,                       'Below ₱5,000',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(31, 'Employed',       'Private',     'Robinsons Manila',   'Pedro Gil, Manila',         '₱10,000–₱20,000',  '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(32, 'Employed',       'Government',  'Manila Police District','UN Ave, Manila',         '₱20,001–₱30,000',  '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(33, 'Retired',        NULL,          NULL,                  NULL,                       'Below ₱5,000',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(34, 'Retired',        NULL,          NULL,                  NULL,                       'Below ₱5,000',     '2026-04-28 08:30:00', '2026-04-28 08:30:00');


-- ============================================================
-- 6. RESIDENT EMERGENCY CONTACTS (one per resident)
-- ============================================================

INSERT INTO `resident_emergency_contacts` (`resident_id`, `name`, `contact_number`, `relationship`, `created_at`, `updated_at`) VALUES
(15, 'Ricardo Navarro',       '09181234521', 'Spouse',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(16, 'Maricel Domingo',       '09181234520', 'Spouse',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(17, 'Elena Salazar-Reyes',   '09181230001', 'Daughter',   '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(18, 'Carlo Ocampo',          '09181230002', 'Son',        '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(19, 'Divina Sta. Ana',       '09181230003', 'Mother',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(20, 'Lilia Hernandez',       '09181230004', 'Spouse',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(21, 'Patricio Lim',          '09181234527', 'Spouse',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(22, 'Concepcion Reyes',      '09181234526', 'Spouse',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(23, 'Rosa Tan',              '09181230005', 'Mother',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(24, 'Gregorio Dela Torre',   '09181230006', 'Father',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(25, 'Arsenio Macaraeg',      '09181234531', 'Spouse',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(26, 'Luzviminda Espiritu',   '09181234530', 'Spouse',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(27, 'Filomena Aguilar',      '09181230007', 'Mother',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(28, 'Perla Miranda',         '09181230008', 'Mother',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(29, 'Dante Pascua',          '09181230009', 'Brother',    '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(30, 'Maria Buenaventura',    '09181230010', 'Spouse',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(31, 'Noel Castañeda',        '09181230011', 'Brother',    '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(32, 'Imelda Castañeda',      '09181234536', 'Spouse',     '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(33, 'Rosario Abante-Cruz',   '09181230012', 'Daughter',   '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(34, 'Salvador Solis',        '09181230013', 'Son',        '2026-04-28 08:30:00', '2026-04-28 08:30:00');


-- ============================================================
-- 7. RESIDENT IDENTIFICATIONS (20 residents, sample IDs)
-- ============================================================

INSERT INTO `resident_identifications` (`resident_id`, `gov_id_type`, `gov_id_number`, `philhealth_number`, `sss_gsis_number`, `barangay_id_number`, `created_at`, `updated_at`) VALUES
(15, 'Voter ID',  'VID-853-0015', '12-345670015-1', '34-5670015-2', 'BRGID-93-0015', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(16, 'Voter ID',  'VID-853-0016', '12-345670016-1', '34-5670016-2', 'BRGID-93-0016', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(17, 'UMID',      'UMID-853-0017','12-345670017-1', '34-5670017-2', 'BRGID-93-0017', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(18, 'UMID',      'UMID-853-0018','12-345670018-1', '34-5670018-2', 'BRGID-93-0018', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(19, 'PhilSys',   'PSN-853-0019', '12-345670019-1', '34-5670019-2', 'BRGID-93-0019', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(20, 'Driver\'s License','DL-853-0020','12-345670020-1','34-5670020-2','BRGID-93-0020','2026-04-28 08:30:00','2026-04-28 08:30:00'),
(21, 'Voter ID',  'VID-853-0021', '12-345670021-1', '34-5670021-2', 'BRGID-93-0021', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(22, 'PhilSys',   'PSN-853-0022', '12-345670022-1', '34-5670022-2', 'BRGID-93-0022', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(23, 'PhilSys',   'PSN-853-0023', '12-345670023-1', '34-5670023-2', 'BRGID-93-0023', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(24, 'School ID', 'SCID-853-0024','12-345670024-1', NULL,            'BRGID-93-0024', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(25, 'PhilSys',   'PSN-853-0025', '12-345670025-1', '34-5670025-2', 'BRGID-93-0025', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(26, 'Voter ID',  'VID-853-0026', '12-345670026-1', '34-5670026-2', 'BRGID-93-0026', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(27, 'School ID', 'SCID-853-0027','12-345670027-1', NULL,            'BRGID-93-0027', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(28, 'PhilSys',   'PSN-853-0028', '12-345670028-1', '34-5670028-2', 'BRGID-93-0028', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(29, 'UMID',      'UMID-853-0029','12-345670029-1', '34-5670029-2', 'BRGID-93-0029', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(30, 'UMID',      'UMID-853-0030','12-345670030-1', '34-5670030-2', 'BRGID-93-0030', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(31, 'Voter ID',  'VID-853-0031', '12-345670031-1', '34-5670031-2', 'BRGID-93-0031', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(32, 'Driver\'s License','DL-853-0032','12-345670032-1','34-5670032-2','BRGID-93-0032','2026-04-28 08:30:00','2026-04-28 08:30:00'),
(33, 'UMID',      'UMID-853-0033','12-345670033-1', '34-5670033-2', 'BRGID-93-0033', '2026-04-28 08:30:00', '2026-04-28 08:30:00'),
(34, 'UMID',      'UMID-853-0034','12-345670034-1', '34-5670034-2', 'BRGID-93-0034', '2026-04-28 08:30:00', '2026-04-28 08:30:00');


-- ============================================================
-- 8. DOCUMENT REQUESTS (15 sample requests from various residents)
--    Continuing from existing id=22, next is id=23
-- ============================================================

INSERT INTO `document_requests` (`id`, `reference_no`, `resident_id`, `requester_name`, `contact_number`, `email`, `address`, `document_type`, `purpose`, `status`, `date_filed`, `receiving_staff`, `remarks`, `created_at`, `updated_at`) VALUES
(23, 'DOC-2026-Z93A0001', 15, 'Maricel Domingo',        '09181234520', 'maricel.domingo@gmail.com',        'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Clearance',                 'Employment',             'Released', '2026-04-28', 'Roger Santos',    '',   '2026-04-28 09:00:00', '2026-04-28 09:10:00'),
(24, 'DOC-2026-Z93A0002', 16, 'Ricardo Navarro',        '09181234521', 'ricardo.navarro@gmail.com',        'Zone 93, Brgy 853, Pandacan, Manila', 'Certificate of Residency',           'Loan Application',       'Approved', '2026-04-28', 'Maria Reyes',     '',   '2026-04-28 09:05:00', '2026-04-28 09:15:00'),
(25, 'DOC-2026-Z93A0003', 17, 'Elvira Salazar',         '09181234522', 'elvira.salazar@gmail.com',         'Zone 93, Brgy 853, Pandacan, Manila', 'Certificate of Indigency',           'Medical Assistance',     'Released', '2026-04-28', 'Roger Santos',    '',   '2026-04-28 09:10:00', '2026-04-28 09:20:00'),
(26, 'DOC-2026-Z93A0004', 18, 'Bernardo Ocampo',        '09181234523', 'bernardo.ocampo@gmail.com',        'Zone 93, Brgy 853, Pandacan, Manila', 'Community Tax Certificate (Cedula)', 'Personal Record',        'Released', '2026-04-28', 'Jose dela Cruz',  '',   '2026-04-28 09:15:00', '2026-04-28 09:25:00'),
(27, 'DOC-2026-Z93A0005', 19, 'Anita Sta. Ana',         '09181234524', 'anita.staana@gmail.com',           'Zone 93, Brgy 853, Pandacan, Manila', 'Good Moral Certificate',             'Employment',             'Pending',  '2026-04-28', NULL,              NULL, '2026-04-28 09:20:00', '2026-04-28 09:20:00'),
(28, 'DOC-2026-Z93A0006', 20, 'Wilfredo Hernandez',     '09181234525', 'wilfredo.hernandez@gmail.com',     'Zone 93, Brgy 853, Pandacan, Manila', 'Business Clearance',                 'Business Permit Renewal','Approved', '2026-04-28', 'Maria Reyes',     '',   '2026-04-28 09:25:00', '2026-04-28 09:35:00'),
(29, 'DOC-2026-Z93A0007', 21, 'Concepcion Reyes',       '09181234526', 'concepcion.reyes@gmail.com',       'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay ID',                        'Personal Use',           'Released', '2026-04-28', 'Lourdes Garcia',  '',   '2026-04-28 09:30:00', '2026-04-28 09:40:00'),
(30, 'DOC-2026-Z93A0008', 22, 'Patricio Lim',           '09181234527', 'patricio.lim@gmail.com',           'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Clearance',                 'Business',               'Review',   '2026-04-28', 'Jose dela Cruz',  '',   '2026-04-28 09:35:00', '2026-04-28 09:35:00'),
(31, 'DOC-2026-Z93A0009', 23, 'Josefina Tan',           '09181234528', 'josefina.tan@gmail.com',           'Zone 93, Brgy 853, Pandacan, Manila', 'First-Time Jobseeker Certificate',   'Employment',             'Pending',  '2026-04-28', NULL,              NULL, '2026-04-28 09:40:00', '2026-04-28 09:40:00'),
(32, 'DOC-2026-Z93A0010', 25, 'Luzviminda Espiritu',    '09181234530', 'luzviminda.espiritu@gmail.com',    'Zone 93, Brgy 853, Pandacan, Manila', 'Certificate of Indigency',           'PWD Benefit Application','Released', '2026-04-28', 'Roger Santos',    '',   '2026-04-28 09:45:00', '2026-04-28 09:55:00'),
(33, 'DOC-2026-Z93A0011', 27, 'Felicitas Aguilar',      '09181234532', 'felicitas.aguilar@gmail.com',      'Zone 93, Brgy 853, Pandacan, Manila', 'Barangay Clearance',                 'Scholarship Application','Approved', '2026-04-28', 'Maria Reyes',     '',   '2026-04-28 10:00:00', '2026-04-28 10:10:00'),
(34, 'DOC-2026-Z93A0012', 29, 'Rhodora Pascua',         '09181234534', 'rhodora.pascua@gmail.com',         'Zone 93, Brgy 853, Pandacan, Manila', 'Certificate of Residency',           'Bank Account Opening',   'Released', '2026-04-28', 'Lourdes Garcia',  '',   '2026-04-28 10:05:00', '2026-04-28 10:15:00'),
(35, 'DOC-2026-Z93A0013', 30, 'Simplicio Buenaventura', '09181234535', 'simplicio.buenaventura@gmail.com', 'Zone 93, Brgy 853, Pandacan, Manila', 'Community Tax Certificate (Cedula)', 'Personal Record',        'Released', '2026-04-28', 'Jose dela Cruz',  '',   '2026-04-28 10:10:00', '2026-04-28 10:20:00'),
(36, 'DOC-2026-Z93A0014', 33, 'Purificacion Abante',    '09181234538', 'puri.abante@gmail.com',            'Zone 93, Brgy 853, Pandacan, Manila', 'Certificate of Indigency',           'Medical Assistance',     'Released', '2026-04-28', 'Roger Santos',    '',   '2026-04-28 10:15:00', '2026-04-28 10:25:00'),
(37, 'DOC-2026-Z93A0015', 34, 'Hermenegildo Solis',     '09181234539', 'hermenegildo.solis@gmail.com',     'Zone 93, Brgy 853, Pandacan, Manila', 'Certificate of Indigency',           'Senior Benefit Claim',   'Approved', '2026-04-28', 'Maria Reyes',     '',   '2026-04-28 10:20:00', '2026-04-28 10:30:00');


-- ============================================================
-- 9. ADDITIONAL EXPENSES (Zone 93 relevant programs)
-- ============================================================

INSERT INTO `expenses` (`id`, `reference_no`, `date`, `category`, `title`, `description`, `amount`, `balance_after`, `approved_by`, `fiscal_year`, `posted_by`, `status`, `created_at`, `updated_at`) VALUES
(3, 'EXP-2026-3011', '2026-04-28', 'health',  'Free Medical Mission – Zone 93',          'Medicines, supplies, and doctor\'s honoraria for Zone 93 medical mission.', 8000.00, 22000.00, 'Brgy. Chairman Roger', '2026', 'Roger Santos', 'Posted', '2026-04-28 10:00:00', '2026-04-28 10:00:00'),
(4, 'EXP-2026-4022', '2026-04-28', 'event',   'Barangay General Assembly – Merienda',    'Refreshments for 35 participants at the April 2026 General Assembly.',      3000.00, 19000.00, 'Brgy. Chairman Roger', '2026', 'Roger Santos', 'Posted', '2026-04-28 10:05:00', '2026-04-28 10:05:00'),
(5, 'EXP-2026-5033', '2026-04-28', 'infra',   'Zone 93 Street Light Replacement',       'LED street light units and electrician labor for Zone 93.',                 5000.00, 14000.00, 'Brgy. Chairman Roger', '2026', 'Jose dela Cruz','Posted', '2026-04-28 10:10:00', '2026-04-28 10:10:00'),
(6, 'EXP-2026-6044', '2026-04-28', 'senior',  'Zone 93 Senior Citizen Monthly Stipend', 'Monthly financial assistance for 4 senior citizens in Zone 93.',           4000.00, 10000.00, 'Brgy. Chairman Roger', '2026', 'Roger Santos', 'Posted', '2026-04-28 10:15:00', '2026-04-28 10:15:00');


-- ============================================================
-- 10. BLOTTER PARTIES (link parties to existing blotter case 4)
-- ============================================================

INSERT INTO `blotter_parties` (`blotter_case_id`, `role`, `name`, `contact`, `resident_id`, `created_at`, `updated_at`) VALUES
(4, 'Complainant', 'Aj',          '09185237282', NULL, '2026-04-28 08:52:48', '2026-04-28 08:52:48'),
(4, 'Respondent',  'Manong Antoy','09185000001', NULL, '2026-04-28 08:52:48', '2026-04-28 08:52:48'),
(4, 'Respondent',  'Kuya Pogi',   '09185000002', NULL, '2026-04-28 08:52:48', '2026-04-28 08:52:48'),
(4, 'Witness',     'Aling Merly', '09185000003', NULL, '2026-04-28 08:52:48', '2026-04-28 08:52:48');


-- ============================================================
-- END OF ZONE 93 SEED DATA
-- ============================================================
