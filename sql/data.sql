CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `email` varchar(100) NOT NULL,
  `firstName` varchar(60) NOT NULL,
  `lastName` varchar(60) NOT NULL,
  `address` varchar(100) DEFAULT NULL,
  `city` varchar(60) DEFAULT NULL,
  `state` char(2) DEFAULT NULL,
  `zip` varchar(9) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

INSERT INTO `users` (`id`, `active`, `email`, `firstName`, `lastName`, `address`, `city`, `state`, `zip`, `phone`) VALUES
(1, 1, 'email1@email.com', 'Bob', 'Sample', '123 Any Street', 'Any City', 'MI', '12345', '555-555-1212'),
(2, 0, 'mickey@disney.com', 'Mickey', 'Mouse', '456 Any Street', 'Orlando', 'FL', '67890', '555-555-1212'),
(3, 1, 'shreidan@babylon5.com', 'John', 'Sheridan', '789 Any Street', 'Z''ha''dum', 'B5', '12345', '555-555-1212'),
(4, 1, 'crichton@farscape.com', 'John', 'Crichton', '012 Any Street', 'Moya', 'AU', '34567', '555-555-1212'),
(5, 1, 'jane@doe.com', 'Jane', 'Doe', '123 Any Street', 'Doe', 'MO', '34567', '555-222-1212'),
(6, 1, 'john@doe.com', 'John', 'Doe', '123 Any Street', 'Doe', 'MO', '34567', '555-222-1212');
