-- Populate specializations table with dental specializations
-- This inserts all the required specializations for dentists

INSERT INTO `tbl_specializations` (`name`, `description`, `is_active`) VALUES
('General Dentist', 'General dentistry covering routine dental care, cleanings, fillings, and basic procedures', 1),
('Orthodontist', 'Specializes in correcting teeth and jaw alignment using braces, aligners, and other orthodontic appliances', 1),
('Periodontist', 'Specializes in prevention, diagnosis, and treatment of periodontal disease and dental implants', 1),
('Endodontist', 'Specializes in root canal treatment and diseases of the dental pulp', 1),
('Prosthodontist', 'Specializes in dental prosthetics including crowns, bridges, dentures, and dental implants', 1),
('Oral & Maxillofacial Surgeon', 'Specializes in surgical procedures involving the mouth, jaw, and face', 1),
('Pediatric Dentist', 'Specializes in dental care for children and adolescents', 1),
('Cosmetic Dentist', 'Specializes in aesthetic dental procedures including veneers, whitening, and smile makeovers', 1),
('Implantologist', 'Specializes in dental implant placement and restoration', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `is_active` = 1;

