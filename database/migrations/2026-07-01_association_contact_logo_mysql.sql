ALTER TABLE association
  ADD COLUMN IF NOT EXISTS mobile VARCHAR(20) AFTER registration_number,
  ADD COLUMN IF NOT EXISTS email VARCHAR(160) AFTER mobile;

UPDATE association SET
  name = 'Sree Amitra''s InfraCity Owners Welfare Association',
  address = '530/1, Sree Amitra''s InfraCity Phase - I, Thudiyalur Road, Chinavedampatti, Coimbatore - 641049.',
  registration_number = 'TN Reg No: SRG/Coimbatore North/123/2025',
  mobile = '63803 18705',
  email = 'saicowa@outlook.com',
  logo_url = '/uploads/association-logo.jpg';
