-- phpMyAdmin SQL Dump
-- version 4.9.11
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 22, 2025 at 01:58 PM
-- Server version: 5.7.27
-- PHP Version: 5.6.40

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aitchon_basketball_league`
--

-- --------------------------------------------------------

--
-- Table structure for table `divisions`
--

CREATE TABLE `divisions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_playoff` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `divisions`
--

INSERT INTO `divisions` (`id`, `name`, `is_playoff`) VALUES
(1, 'OPEN', 0),
(2, '35 OVER', 0),
(3, 'CO-ED TYKES', 0),
(4, 'CO-ED PEE WEE', 0),
(5, 'TEEN GIRLS', 0),
(6, 'TEEN BOYS', 0),
(7, 'PRACTICE', 0),
(10, 'PEE WEE SF', 1),
(11, 'GIRLS SF', 1),
(12, 'OPEN QF', 1),
(13, 'TYKES QF', 1),
(14, 'TYKES SF', 1),
(15, 'TEEN SF', 1),
(16, '35 OVER QF', 1),
(17, '35 OVER SF', 1),
(18, 'OPEN SF', 1),
(19, '35 OVER FINAL', 1),
(20, 'OPEN FINAL', 1),
(21, 'TYKES FINAL', 1),
(22, 'GIRLS FINAL', 1),
(24, 'TEEN FINAL', 1),
(25, 'TYKES FINAL', 1);

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE `games` (
  `id` int(11) NOT NULL,
  `date` datetime NOT NULL,
  `location` varchar(100) NOT NULL,
  `home_team_id` int(11) DEFAULT NULL,
  `away_team_id` int(11) DEFAULT NULL,
  `status` enum('scheduled','completed') DEFAULT 'scheduled',
  `is_playoff` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `games`
--

INSERT INTO `games` (`id`, `date`, `location`, `home_team_id`, `away_team_id`, `status`, `is_playoff`) VALUES
(20, '2025-02-22 14:00:00', 'COURT E', 17, 19, 'scheduled', 0),
(21, '2025-02-22 14:00:00', 'COURT F', 20, 21, 'scheduled', 0),
(22, '2025-02-22 15:00:00', 'COURT F', 22, 23, 'scheduled', 0),
(23, '2025-02-22 15:00:00', 'COURT E', 36, 37, 'scheduled', 0),
(24, '2025-02-22 16:00:00', 'COURT E', 38, 39, 'scheduled', 0),
(25, '2025-02-22 17:00:00', 'COURT E', 32, 33, 'scheduled', 0),
(27, '2025-02-22 16:00:00', 'COURT F', 40, 41, 'scheduled', 0),
(28, '2025-02-22 17:00:00', 'COURT F', 34, 35, 'scheduled', 0),
(29, '2025-02-22 14:00:00', 'COURT H', 24, 25, 'scheduled', 0),
(30, '2025-02-22 15:00:00', 'COURT H', 26, 27, 'scheduled', 0),
(31, '2025-02-22 16:00:00', 'COURT H', 28, 29, 'scheduled', 0),
(32, '2025-02-22 17:00:00', 'COURT H', 30, 31, 'scheduled', 0),
(33, '2025-03-01 14:00:00', 'COURT E', 9, 10, 'scheduled', 0),
(34, '2025-03-01 14:00:00', 'COURT H', 3, 5, 'scheduled', 0),
(35, '2025-03-01 14:00:00', 'COURT F', 14, 15, 'scheduled', 0),
(36, '2025-03-01 15:00:00', 'COURT E', 37, 41, 'scheduled', 0),
(37, '2025-03-01 15:00:00', 'COURT H', 13, 12, 'scheduled', 0),
(38, '2025-03-01 14:00:00', 'COURT H', 3, 7, 'scheduled', 0),
(39, '2025-03-01 15:00:00', 'COURT F', 52, 38, 'scheduled', 0),
(40, '2025-03-01 16:00:00', 'COURT E', 40, 36, 'scheduled', 0),
(41, '2025-03-01 16:00:00', 'COURT H', 9, 8, 'scheduled', 0),
(42, '2025-03-01 16:00:00', 'COURT F', 32, 44, 'scheduled', 0),
(43, '2025-03-01 17:00:00', 'COURT E', 42, 34, 'scheduled', 0),
(44, '2025-03-01 17:00:00', 'COURT H', 13, 11, 'scheduled', 0),
(45, '2025-03-01 17:00:00', 'COURT F', 33, 43, 'scheduled', 0),
(46, '2025-03-15 14:00:00', 'COURT E', 10, 8, 'scheduled', 0),
(47, '2025-03-15 14:00:00', 'COURT H', 6, 5, 'scheduled', 0),
(48, '2025-03-15 14:00:00', 'COURT H', 3, 7, 'scheduled', 0),
(49, '2025-03-15 14:00:00', 'COURT F', 16, 14, 'scheduled', 0),
(50, '2025-03-15 15:00:00', 'COURT H', 12, 11, 'scheduled', 0),
(51, '2025-03-15 15:00:00', 'COURT E', 38, 36, 'scheduled', 0),
(52, '2025-03-15 15:00:00', 'COURT F', 37, 39, 'scheduled', 0),
(53, '2025-03-15 16:00:00', 'COURT E', 52, 41, 'scheduled', 0),
(54, '2025-03-15 16:00:00', 'COURT H', 16, 15, 'scheduled', 0),
(55, '2025-03-15 16:00:00', 'COURT F', 35, 33, 'scheduled', 0),
(56, '2025-03-15 17:00:00', 'COURT E', 43, 42, 'scheduled', 0),
(57, '2025-03-15 17:00:00', 'COURT H', 13, 12, 'scheduled', 0),
(58, '2025-03-15 17:00:00', 'COURT F', 44, 34, 'scheduled', 0),
(59, '2025-03-29 14:00:00', 'COURT E', 9, 10, 'scheduled', 0),
(60, '2025-03-29 14:00:00', 'COURT H', 3, 6, 'scheduled', 0),
(61, '2025-03-29 14:00:00', 'COURT H', 5, 4, 'scheduled', 0),
(62, '2025-03-29 14:00:00', 'COURT F', 38, 39, 'scheduled', 0),
(63, '2025-03-29 15:00:00', 'COURT E', 41, 36, 'scheduled', 0),
(64, '2025-03-29 15:00:00', 'COURT H', 11, 12, 'scheduled', 0),
(65, '2025-03-29 15:00:00', 'COURT F', 14, 15, 'scheduled', 0),
(66, '2025-03-29 16:00:00', 'COURT E', 40, 52, 'scheduled', 0),
(67, '2025-03-29 16:00:00', 'COURT H', 10, 8, 'scheduled', 0),
(68, '2025-03-29 16:00:00', 'COURT F', 42, 32, 'scheduled', 0),
(69, '2025-03-29 17:00:00', 'COURT E', 44, 35, 'scheduled', 0),
(70, '2025-03-29 17:00:00', 'COURT H', 14, 16, 'scheduled', 0),
(71, '2025-03-29 17:00:00', 'COURT F', 43, 34, 'scheduled', 0),
(72, '2025-04-12 14:00:00', 'COURT F', 8, 9, 'scheduled', 0),
(73, '2025-04-12 14:00:00', 'COURT G', 15, 16, 'scheduled', 0),
(74, '2025-04-12 15:00:00', 'COURT F', 52, 39, 'scheduled', 0),
(75, '2025-04-12 15:00:00', 'COURT H', 11, 12, 'scheduled', 0),
(76, '2025-04-12 15:00:00', 'COURT G', 40, 37, 'scheduled', 0),
(77, '2025-04-12 16:00:00', 'COURT F', 41, 38, 'scheduled', 0),
(78, '2025-04-12 16:00:00', 'COURT H', 8, 10, 'scheduled', 0),
(79, '2025-04-12 16:00:00', 'COURT G', 15, 14, 'scheduled', 0),
(80, '2025-04-12 17:00:00', 'COURT F', 33, 44, 'scheduled', 0),
(81, '2025-04-12 17:00:00', 'COURT H', 11, 13, 'scheduled', 0),
(82, '2025-04-12 17:00:00', 'COURT G', 43, 35, 'scheduled', 0),
(83, '2025-04-19 14:00:00', 'COURT F', 40, 39, 'scheduled', 0),
(84, '2025-04-19 14:00:00', 'COURT H', 16, 15, 'scheduled', 0),
(85, '2025-04-19 14:00:00', 'COURT G', 36, 52, 'scheduled', 0),
(86, '2025-04-19 15:00:00', 'COURT F', 32, 35, 'scheduled', 0),
(87, '2025-04-19 15:00:00', 'COURT H', 44, 43, 'scheduled', 0),
(88, '2025-04-19 15:00:00', 'COURT G', 33, 42, 'scheduled', 0),
(89, '2025-04-19 16:00:00', 'COURT F', 37, 38, 'scheduled', 0),
(90, '2025-04-19 16:00:00', 'COURT H', 16, 14, 'scheduled', 0),
(91, '2025-04-19 16:00:00', 'COURT G', 40, 52, 'scheduled', 0),
(92, '2025-04-19 17:00:00', 'COURT F', 44, 35, 'scheduled', 0),
(93, '2025-04-19 17:00:00', 'COURT H', 42, 43, 'scheduled', 0),
(94, '2025-04-19 17:00:00', 'COURT G', 32, 34, 'scheduled', 0),
(95, '2025-05-03 14:00:00', 'COURT F', 13, 12, 'scheduled', 0),
(96, '2025-05-03 14:00:00', 'COURT G', 9, 8, 'scheduled', 0),
(97, '2025-05-03 15:00:00', 'COURT F', 36, 39, 'scheduled', 0),
(98, '2025-05-03 15:00:00', 'COURT H', 7, 5, 'scheduled', 0),
(99, '2025-05-03 15:00:00', 'COURT H', 4, 6, 'scheduled', 0),
(100, '2025-05-03 15:00:00', 'COURT G', 40, 41, 'scheduled', 0),
(101, '2025-05-03 16:00:00', 'COURT F', 42, 35, 'scheduled', 0),
(102, '2025-05-03 16:00:00', 'COURT H', 9, 10, 'scheduled', 0),
(103, '2025-05-03 16:00:00', 'COURT G', 52, 37, 'scheduled', 0),
(104, '2025-05-03 17:00:00', 'COURT F', 34, 33, 'scheduled', 0),
(105, '2025-05-03 17:00:00', 'COURT H', 13, 11, 'scheduled', 0),
(106, '2025-05-03 17:00:00', 'COURT G', 43, 32, 'scheduled', 0),
(107, '2025-05-10 16:00:00', 'COURT F', 40, 38, 'scheduled', 0),
(108, '2025-05-10 16:00:00', 'COURT H', 4, 3, 'scheduled', 0),
(109, '2025-05-10 16:00:00', 'COURT H', 7, 6, 'scheduled', 0),
(110, '2025-05-10 16:00:00', 'COURT G', 19, 20, 'scheduled', 0),
(111, '2025-05-10 17:00:00', 'COURT F', 32, 33, 'scheduled', 0),
(112, '2025-05-10 17:00:00', 'COURT H', 36, 37, 'scheduled', 0),
(113, '2025-05-10 17:00:00', 'COURT G', 17, 30, 'scheduled', 0),
(114, '2025-05-10 18:00:00', 'COURT F', 42, 44, 'scheduled', 0),
(115, '2025-05-10 18:00:00', 'COURT H', 41, 39, 'scheduled', 0),
(116, '2025-05-10 18:00:00', 'COURT G', 29, 31, 'scheduled', 0),
(117, '2025-05-10 19:00:00', 'COURT F', 21, 22, 'scheduled', 0),
(118, '2025-05-10 19:00:00', 'COURT H', 34, 35, 'scheduled', 0),
(119, '2025-05-10 19:00:00', 'COURT G', 23, 23, 'scheduled', 0),
(120, '2025-05-17 14:00:00', 'COURT F', 53, 54, 'scheduled', 1),
(121, '2025-05-17 15:00:00', 'COURT F', 57, 58, 'scheduled', 1),
(122, '2025-05-17 16:00:00', 'COURT F', 59, 60, 'scheduled', 1),
(123, '2025-05-17 17:00:00', 'COURT F', 90, 91, 'scheduled', 1),
(124, '2025-05-17 14:00:00', 'COURT H', 63, 64, 'scheduled', 1),
(125, '2025-05-17 14:00:00', 'COURT H', 65, 66, 'scheduled', 1),
(126, '2025-05-17 16:00:00', 'COURT H', 67, 68, 'scheduled', 1),
(127, '2025-05-17 17:00:00', 'COURT H', 61, 62, 'scheduled', 1),
(128, '2025-05-17 14:00:00', 'COURT G', 69, 70, 'scheduled', 1),
(129, '2025-05-17 15:00:00', 'COURT G', 71, 72, 'scheduled', 1),
(130, '2025-05-17 16:00:00', 'COURT G', 73, 74, 'scheduled', 1),
(131, '2025-05-17 17:00:00', 'COURT G', 75, 76, 'scheduled', 1),
(132, '2025-05-31 14:00:00', 'COURT F', 77, 78, 'scheduled', 1),
(133, '2025-05-31 15:00:00', 'COURT F', 79, 80, 'scheduled', 1),
(134, '2025-05-31 16:00:00', 'COURT F', 81, 81, 'scheduled', 1),
(135, '2025-05-31 17:00:00', 'COURT F', 82, 82, 'scheduled', 1),
(136, '2025-05-31 14:00:00', 'COURT H', 83, 83, 'scheduled', 1),
(137, '2025-05-31 15:00:00', 'COURT H', 84, 85, 'scheduled', 1),
(138, '2025-05-31 14:00:00', 'COURT G', 86, 86, 'scheduled', 1),
(139, '2025-05-31 15:00:00', 'COURT G', 87, 87, 'scheduled', 1),
(140, '2025-05-31 16:00:00', 'COURT G', 88, 89, 'scheduled', 1);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `players`
--

CREATE TABLE `players` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `team_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `players`
--

INSERT INTO `players` (`id`, `name`, `team_id`) VALUES
(11, 'Elliot Dimaano', 3),
(12, 'Mila Oddo', 3),
(13, 'Aeron Latham #21', 32),
(14, 'AJ Moratalla #15', 32),
(15, 'Alfonso Eguia #1', 32),
(16, 'Basti Soriano #3', 32),
(17, 'Dom Valencia #34', 32),
(18, 'JP Maala #12', 32),
(19, 'Marco Calo #10', 32),
(20, 'Marcus Abellera #6', 32),
(21, 'Rex Matuod #13', 32),
(22, 'Sam Soriano #32', 32),
(23, 'Zach Alindogan #20', 32),
(24, 'RG Galicia #14', 32),
(25, 'Gerry Quilo #00', 34),
(26, 'Jason Ricarte #2', 34),
(27, 'John Ricarte #33', 34),
(28, 'Josh Sollestre #24', 34),
(29, 'JR Sims #3', 34),
(30, 'Kyle Pelayo #20', 34),
(31, 'Michael Shaw #13', 34),
(32, 'Victor Roxas #22', 34),
(33, 'Al Antonio #5', 35),
(34, 'Andrew LaBrecque #24', 35),
(35, 'Andrew Pozon #32', 35),
(36, 'Jacinto Pozon #77', 35),
(37, 'Jalen Lee #21', 35),
(38, 'Jerry Pollock #7', 35),
(39, 'Kevin Filio #8', 35),
(40, 'Kyle Wawrzyniak #4', 35),
(41, 'Maddox Mangahas #3', 35),
(42, 'Marlon Rodriguez #11', 35),
(43, 'Michael Cagalingan #23', 35),
(44, 'Trecetan Cadelina #27', 44),
(45, 'John Reinoso #3', 44),
(46, 'Francis Reinoso #9', 44),
(47, 'Jian Carreon #10', 44),
(48, 'Andre Demanuel #16', 44),
(49, 'Jacob Galvan #0', 44),
(50, 'Joshua Azucena #5', 44),
(51, 'Ryan Michael #8', 44),
(52, 'Nate Hapin #17', 44),
(53, 'Jeremy Tolentino #7', 44),
(54, 'Dionte Dixon #7', 42),
(55, 'Jalen Dixon #3', 42),
(56, 'Julian Dixon #11', 42),
(57, 'Brandon Orzame #1', 42),
(58, 'Joe Rapadas #0', 42),
(59, 'MJ Caoagas #5', 42),
(60, 'Quinn Grobbel #2', 42),
(61, 'Zach Perez #4', 42),
(62, 'Jalen Cruz #00', 42),
(63, 'Trey Timban', 42),
(64, 'Robert Arcilla', 43),
(65, 'Austin Saavedra', 43),
(66, 'Joki Lesada', 43),
(67, 'Lance Limbo', 43),
(68, 'Ryan Concha', 43),
(69, 'Nick Abuel', 43),
(70, 'Sebastian Abuel', 43),
(71, 'Josh Ascano', 43),
(72, 'Carlwyn Larias', 43),
(73, 'Leo Cardenas', 43),
(74, 'Jolo Abordo', 43),
(75, 'Rodel Endaluz', 33),
(76, 'Bryan Bautista ', 33),
(77, 'Oscar Bautista', 33),
(78, 'Carl Bautista', 33),
(79, 'Tony Saclayan', 33),
(80, 'Victor Michael', 33),
(81, 'Christian Almeida', 33),
(82, 'Russel Almedia', 33),
(83, 'Kurt Apostol', 33),
(84, 'John Bernos', 33),
(85, 'Justin Bernos', 33),
(173, 'Kingsley Sarmiento', 3),
(174, 'Matteo Liwag', 3),
(175, 'Bryson Gregoire', 3),
(176, 'Robit Joshi', 3),
(178, 'Liam Cagalingan', 4),
(179, 'Storm Chaney', 4),
(180, 'Cassandra Dao', 4),
(181, 'Grayson Bolofer', 4),
(182, 'Michael Abuel', 4),
(183, 'Logan Karl', 4),
(185, 'Benjamin Burgain', 5),
(186, 'Olivia Burgain', 5),
(187, 'Rafael Dorego', 5),
(188, 'Liam Dillion', 5),
(189, 'Amelia Monato', 5),
(190, 'Elon Morales', 5),
(192, 'Aaliyah Ricarte', 6),
(193, 'Ivy Polack', 6),
(194, 'Quinn Quantz', 6),
(195, 'Julhian Sison', 6),
(196, 'Ethan Servito', 6),
(197, 'Jefferson Ian Aala', 6),
(198, 'Stella Rubio', 7),
(199, 'Luna Rubio', 7),
(200, 'Frankie Rubio', 7),
(201, 'Giorge Leong', 7),
(202, 'Ian He', 7),
(203, 'Sebastian Switzer', 7),
(204, 'Jameson Lipka', 8),
(205, 'Joshua Pollock', 8),
(206, 'Jordan Rodriguez', 8),
(207, 'Blaze Chaney', 8),
(208, 'Aaron Tuliao', 8),
(209, 'Jasper Refuerzo', 8),
(210, 'Mason Pascual', 8),
(211, 'Arianne Amano', 8),
(212, 'Alexander Marcu', 9),
(213, 'Darren Pasamba', 9),
(214, 'Andrew Bugarin', 9),
(215, 'Kyler Deguzman', 9),
(216, 'Joaquin Gambalan', 9),
(217, 'Alex Monato', 9),
(218, 'Noah Rubio', 9),
(226, 'Cecilia Cardenas', 11),
(227, 'Brenna Gough', 11),
(228, 'Toni Rose Valentino', 11),
(229, 'Erica Obra', 11),
(230, 'Eden Tandoc', 11),
(231, 'Lana Shim', 11),
(232, 'Shyla Conley', 11),
(233, 'Mia Panlasigue', 12),
(234, 'Angie Itchon', 12),
(235, 'Samantha Fandino', 12),
(236, 'Gia Leong', 12),
(237, 'Lorelai Montano', 12),
(238, 'Aurora Tandoc', 12),
(239, 'Amelia Kozak', 12),
(240, 'Makaela Rubio', 13),
(241, 'Bianca Garcia', 13),
(242, 'Mila Grabke', 13),
(243, 'Zaina Torrico', 13),
(244, 'Alexis Rosenbaum', 13),
(245, 'Nora Anantharam', 13),
(246, 'Lyla Anantharam', 13),
(247, 'Evin Dela Paz', 14),
(248, 'Matteo Dela Paz', 14),
(249, 'James Kendricks', 14),
(250, 'Gab Ochoa', 14),
(251, 'Gabriel Montano', 14),
(252, 'Lucas Dancel', 14),
(253, 'Jesus Rodriguez', 14),
(254, 'Ron Bennett Ochoa', 14),
(255, 'Lance Tolentino', 14),
(256, 'Gabriel Placente', 15),
(257, 'Dylan Shim', 15),
(258, 'Noah Gambalan', 15),
(259, 'Jhulian Villarin', 15),
(260, 'Jack Pascual', 15),
(261, 'Hizkiah Lanac', 15),
(262, 'Anthony Nahas IV', 15),
(263, 'Marc Sison', 15),
(264, 'Mason Cagalingan', 16),
(265, 'Collin Carandang', 16),
(266, 'Sebastian Abuel', 16),
(267, 'Landon Doerr', 16),
(268, 'Conner Carandang', 16),
(269, 'Julien Intalan', 16),
(270, 'Jacob Ciacico', 16),
(271, 'Ryan Ravela', 16),
(272, 'Luke Paciente', 16),
(273, 'Carlito Alo #3', 40),
(274, 'Rob Arevalo #10', 40),
(275, 'Mark Balagot #55', 40),
(276, 'Marlon Baranda #11', 40),
(277, 'Carl Bolofer #20', 40),
(278, 'Mark Cura #2', 40),
(279, 'Paul Doan #8', 40),
(280, 'Gil DelRosario #24', 40),
(281, 'Jerome Ebuen #26', 40),
(282, 'Bobby Gregroie #5', 40),
(283, 'Pong Macaspac #21', 40),
(284, 'Ron Quizon #25', 40),
(285, 'Angelo Quijano #8', 52),
(286, 'Brandon Dziklinski #10', 52),
(287, 'Brett Hammer #16', 52),
(288, 'Derrick Marasigan #28', 52),
(289, 'Ed Pascual #11', 52),
(290, 'Johnnie Peoples #22', 52),
(291, 'Kris Patnugot #13', 52),
(292, 'Lorimer Dorego #35', 52),
(293, 'Michael Dimaano #1', 52),
(294, 'Neil Valenzuela #26', 52),
(295, 'Ray Balido #4', 52),
(296, 'Chaz Grabke #8', 38),
(297, 'Denis Lacap #12', 38),
(298, 'Eric Naz #44', 38),
(299, 'Jasper Ocampo #7', 38),
(300, 'Jesus Rodriguez #24', 38),
(301, 'Justin Mupas #11', 38),
(302, 'Lyle Villahermosa #42', 38),
(303, 'Matt Liwag #1', 38),
(304, 'Nate Tuliao #0', 38),
(305, 'Ravi Sethi #33', 38),
(306, 'Noel Pentaflorida #25', 38),
(307, 'Roman Rosales #18', 38),
(308, 'Tony Avecilla #58', 38),
(309, 'Rene Lanzanas #24', 39),
(310, 'Ariel Gonzales #7', 39),
(311, 'Paolo Del Rosario #6', 39),
(312, 'Roldan Juco #16', 39),
(313, 'Paul Zagala #33', 39),
(314, 'Nelson Abenes #13', 39),
(315, 'Harold Hontucan #30', 39),
(316, 'German Dela Cruz #23', 39),
(317, 'Edmil Baluyot #20', 39),
(318, 'Nardito Alvar, Jr. #19', 39),
(319, 'Zacarias Ursua #9', 39),
(320, 'Norman Auel #17', 39),
(321, 'Jon Camitan #21', 39),
(322, 'John Lipka #42', 39),
(323, 'Kevin Siaton #77', 39),
(324, 'Rob Arcilla', 37),
(325, 'Tony Saavedra', 37),
(326, 'Dale SonServacio', 37),
(327, 'Jouie Ramos', 37),
(328, 'Marvin DelaPaz', 37),
(329, 'RJ Baluyot', 37),
(330, 'Shem Sarmiento', 37),
(331, 'Golden Laird', 37),
(332, 'Jeff Crawford', 37),
(333, 'Joe Vinuya', 37),
(334, 'Henry Trinidad', 37),
(335, 'Ivan Tanap', 37),
(336, 'Trez Arrozal', 37),
(337, 'Arvin Carson', 37),
(338, 'CJ Moore #2', 36),
(339, 'Edward Metcalf #3', 36),
(340, 'Jason Tago #6', 36),
(341, 'Eugene Valdez #8', 36),
(342, 'Ricky Yulo #9', 36),
(343, 'GCourt Cortes #10', 36),
(344, 'James Harvey Sevilleno #15', 36),
(345, 'Peter Jocoy #17', 36),
(346, 'Phil Debonaire #27', 36),
(347, 'Ryan Fuderanan #28', 36),
(348, 'Jay Segismundo #29', 36),
(349, 'Shervwin Chong #31', 36),
(350, 'JM Cortes #67', 36),
(351, 'Tony Brigoli #77', 36),
(352, 'Ben Doeer', 41),
(353, 'John Bernos', 41),
(354, 'John Dionisio', 41),
(355, 'Marden Morales', 41),
(356, 'Mikko Marzona', 41),
(357, 'Paul Angelo', 41),
(358, 'Rodel Endaluz', 41),
(359, 'Ron Villarosa', 41),
(360, 'Jacob Ascano', 10),
(361, 'Ben Madlangbayan', 10),
(362, 'Ryder Kozak', 10),
(363, 'Brayson Selvaraj', 10),
(364, 'Liam Atienza', 10),
(365, 'Logan Causey', 10),
(366, 'Malachi Mustin', 10);

-- --------------------------------------------------------

--
-- Table structure for table `player_stats`
--

CREATE TABLE `player_stats` (
  `id` int(11) NOT NULL,
  `player_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `points` int(11) DEFAULT NULL,
  `rebounds` int(11) DEFAULT NULL,
  `assists` int(11) DEFAULT NULL,
  `fouls` int(11) DEFAULT NULL,
  `three_pointers_made` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `scores`
--

CREATE TABLE `scores` (
  `id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `home_team_score` int(11) DEFAULT NULL,
  `away_team_score` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `coach` varchar(100) NOT NULL,
  `division_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`id`, `name`, `coach`, `division_id`) VALUES
(3, 'TEAM BLACK', 'Mike Dimaano', 3),
(4, 'TEAM PURPLE', 'Michael Cagalingan', 3),
(5, 'TEAM RED', 'Rich Bugarin', 3),
(6, 'TEAM WHITE', 'Jason Ricarte', 3),
(7, 'TEAM CRIMSON', 'Jordan Rubio', 3),
(8, 'TEAM NAVY BLUE', 'John Lipka', 4),
(9, 'TEAM RED', 'Arvin Dionisio', 4),
(10, 'TEAM WHITE', 'Diego Zimmerman', 4),
(11, 'TEAM NAVY BLUE', 'Rose Sarmiento Temple', 5),
(12, 'TEAM GREEN', 'Mike Panlasigue/Al Itchon', 5),
(13, 'TEAM BLACK', 'Joey Rubio', 5),
(14, 'TEAM PURPLE', 'Rob Arcilla', 6),
(15, 'TEAM GREEN', 'Trecetan Cadelina', 6),
(16, 'TEAM GREY', 'Dominic Valencia/Leo Cardenas', 6),
(17, 'PEE WEE RED', '', 7),
(18, 'PEE WEE BLACK', '', 7),
(19, 'PEE WEE WHITE', '', 7),
(20, 'PEE WEE BLUE', '', 7),
(21, 'TEEN GREY', '', 7),
(22, 'TEEN PURPLE', '', 7),
(23, 'TEEN GREEN', '', 7),
(24, 'TYKES BLACK', '', 7),
(25, 'TYKES CRIMSON', '', 7),
(26, 'TYKES WHITE', '', 7),
(27, 'TYKES RED', '', 7),
(28, 'TYKES PURPLE', '', 7),
(29, 'GIRLS BLACK', '', 7),
(30, 'GIRLS GREEN', '', 7),
(31, 'GIRLS BLUE', '', 7),
(32, 'POGI BOYZ', '', 1),
(33, 'ELIM MINISTRIES OPEN', '', 1),
(34, 'THE HANDSOME BOYS', '', 1),
(35, 'FLINT TROPICS', '', 1),
(36, 'COFFEE BEANERY', '', 2),
(37, 'DETROIT BALLERZ', '', 2),
(38, 'TOP GUN/BALLAHOLICS', '', 2),
(39, 'FEG', '', 2),
(40, 'BASA', '', 2),
(41, 'ELIM MINISTRIES', '', 2),
(42, 'BROTHERHOOD', '', 1),
(43, 'DETROIT BALLERZ', '', 1),
(44, 'MAINIT HEAT', '', 1),
(52, 'DETROIT BABOYS', '', 2),
(53, '2 SEED', '', 10),
(54, '3 SEED', '', 10),
(55, '1 SEED', '', 10),
(57, '2 SEED', '', 11),
(58, '3 SEED', '', 11),
(59, '2 SEED', '', 12),
(60, '7 SEED', '', 12),
(61, '4 SEED', '', 12),
(62, '5 SEED', '', 12),
(63, '4 SEED', '', 13),
(64, '5 SEED', '', 13),
(65, '2 SEED', '', 14),
(66, '3 SEED', '', 14),
(67, 'W 4 V 5', '', 14),
(68, '1 SEED', '', 14),
(69, '2 SEED', '', 15),
(70, '3 SEED', '', 15),
(71, '2 SEED', '', 16),
(72, '7 SEED', '', 16),
(73, '3 SEED', '', 16),
(74, '6 SEED', '', 16),
(75, '4 SEED', '', 16),
(76, '5 SEED', '', 16),
(77, '1 SEED', '', 17),
(78, 'LOW SEED', '', 17),
(79, '1 SEED', '', 18),
(80, 'LOW SEED', '', 18),
(81, 'FINAL', '', 19),
(82, 'FINAL', '', 20),
(83, 'FINAL', '', 21),
(84, '1 SEED', '', 22),
(85, 'W 2 V 3', '', 22),
(86, 'SEMIFINAL 2', '', 17),
(87, 'SEMIFINAL 2', '', 18),
(88, '1 SEED', '', 24),
(89, 'W 2 V 3', '', 24),
(90, '3 SEED', '', 12),
(91, '6 SEED', '', 12),
(92, 'FINAL', '', 21);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`) VALUES
(1, 'admin', '4a1031b785da3440af4104432614c13ed3a43b3e76ee0f0f366b6268f0765ce2', 'itchonangie@gmail.com'),
(2, 'al', '1e04c7bc58ec12e3a6f8d581215cdac078d2e7141d139dda45cc73623eeff03f', 'aitchon@yahoo.com');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `divisions`
--
ALTER TABLE `divisions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`),
  ADD KEY `home_team_id` (`home_team_id`),
  ADD KEY `away_team_id` (`away_team_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`id`),
  ADD KEY `team_id` (`team_id`);

--
-- Indexes for table `player_stats`
--
ALTER TABLE `player_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `game_id` (`game_id`);

--
-- Indexes for table `scores`
--
ALTER TABLE `scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `game_id` (`game_id`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `division_id` (`division_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `divisions`
--
ALTER TABLE `divisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `players`
--
ALTER TABLE `players`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=367;

--
-- AUTO_INCREMENT for table `player_stats`
--
ALTER TABLE `player_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `scores`
--
ALTER TABLE `scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `games`
--
ALTER TABLE `games`
  ADD CONSTRAINT `games_ibfk_1` FOREIGN KEY (`home_team_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `games_ibfk_2` FOREIGN KEY (`away_team_id`) REFERENCES `teams` (`id`);

--
-- Constraints for table `players`
--
ALTER TABLE `players`
  ADD CONSTRAINT `players_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);

--
-- Constraints for table `player_stats`
--
ALTER TABLE `player_stats`
  ADD CONSTRAINT `player_stats_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `player_stats_ibfk_2` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`);

--
-- Constraints for table `scores`
--
ALTER TABLE `scores`
  ADD CONSTRAINT `scores_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`);

--
-- Constraints for table `teams`
--
ALTER TABLE `teams`
  ADD CONSTRAINT `teams_ibfk_1` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
