-- --------------------------------------------------------

--
-- Minimal table structure for table 'media'
--

CREATE TABLE IF NOT EXISTS media (
  id int(11) NOT NULL AUTO_INCREMENT,
  dirname varchar(255) DEFAULT NULL,
  basename varchar(255) NOT NULL,
  `checksum` varchar(255) NOT NULL,
  alternative varchar(50) DEFAULT NULL,
  `group` varchar(255) DEFAULT NULL,
  created datetime DEFAULT NULL,
  modified datetime DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Minimal table structure for table 'media_links'
--

CREATE TABLE IF NOT EXISTS media_links (
  id int(11) NOT NULL AUTO_INCREMENT,
  media_id int(11) NOT NULL,
  model varchar(50) NOT NULL,
  foreign_id int(11) NOT NULL,
  field varchar(100) NOT NULL,
  created datetime DEFAULT NULL,
  modified datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY idxfk_foreign (media_id,model,foreign_id,main)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Example - add the field 'avatar' to the MediaFields fields list.
-- On upload the field avatar would store the relative path to the user's avatar.
-- In addition an entry would be added to the media table, and a reference created in
-- media_links linking the uploaded image, to the profile 'avatar' field
--
-- CREATE TABLE IF NOT EXISTS `profiles` (
--   `id` int(11) NOT NULL AUTO_INCREMENT,
--   `user_id` int(11) NOT NULL,
--   `alias` varchar(32) DEFAULT NULL,
--   `slug` varchar(50) NOT NULL,
--   `dob` date DEFAULT NULL,
--   `gender` char(1) DEFAULT 'x',
--   `tagline` varchar(255) NOT NULL,
--   `url` varchar(255) DEFAULT NULL,
--   `avatar` varchar(255) DEFAULT NULL,
--   `created` datetime DEFAULT NULL,
--   `modified` datetime DEFAULT NULL,
--   PRIMARY KEY (`id`)
-- ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
