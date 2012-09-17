--
-- Dumping data for table `user_types`
--

INSERT INTO `user_types` (`key`, `name`, `description`, `attributes`, `used_for`) VALUES
('personal', 'Hosted Personal', 'A user with a personal hosted plan', '{"auto_form_fields":{"cn":{"data":["givenname","sn"]},"mail":{"data":["uid"]}},"form_fields":{"givenname":[],"mailalternateaddress":{"optional":true},"sn":[],"uid":[],"userpassword":{"type":"password"}},"fields":{"mailquota":"131072","nsroledn":"cn=personal-user,%(base_dn)s","objectclass":["top","inetorgperson","kolabinetorgperson","mailrecipient","organizationalperson","person"]}}', 'hosted'),
('professional', 'Hosted Professional', 'A user with a professional hosted plan', '{"auto_form_fields":{"cn":{"data":["givenname","sn"]},"mail":{"data":["uid"]}},"form_fields":{"alias":{"type":"list","optional":true,"maxcount":2},"givenname":[],"mailalternateaddress":{"optional":true},"sn":[],"uid":[],"userpassword":{"type":"password"}},"fields":{"mailquota":"1048576","nsroledn":"cn=professional-user,%(base_dn)s","objectclass":["top","inetorgperson","kolabinetorgperson","mailrecipient","organizationalperson","person"]}}', 'hosted');

