-- phpMyAdmin SQL Dump
-- version 4.8.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 07, 2026 at 03:51 AM
-- Server version: 10.1.37-MariaDB
-- PHP Version: 7.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gsccxzp_pal_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `fuel_items`
--

CREATE TABLE `fuel_items` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `container` enum('yes','no') NOT NULL,
  `status` enum('Active','Inactive') NOT NULL,
  `remarks` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `fuel_items`
--

INSERT INTO `fuel_items` (`id`, `name`, `unit`, `container`, `status`, `remarks`) VALUES
(1, 'Diesel', 'L', 'yes', 'Active', ''),
(2, 'Unleaded', 'L', 'yes', 'Active', ''),
(3, 'ATF', 'L', 'no', 'Active', ''),
(4, '2-T Oil', 'mL', 'no', 'Active', ''),
(5, 'Oil 30', 'L', 'no', 'Active', ''),
(6, 'Oil 40', 'L', 'no', 'Active', ''),
(7, 'Helix Plus', 'L', 'no', 'Active', ''),
(9, 'Hydraulic Oil 10', 'pail', 'no', 'Active', ''),
(10, 'Oil Filter', 'pc/s', 'no', 'Active', ''),
(12, 'Brake Fluid', 'L', 'no', 'Active', ''),
(13, 'Gear Oil', 'L', 'no', 'Active', ''),
(14, 'Grease Oil', 'L', 'no', 'Active', ''),
(15, 'Shell Rimula R4 X', 'L', 'no', 'Active', ''),
(16, 'Coolant', 'L', 'no', 'Active', ''),
(17, 'Fuel Filter', 'pc/s', 'no', 'Active', ''),
(18, 'Washer Fluid', 'L', 'yes', 'Active', '');

-- --------------------------------------------------------

--
-- Table structure for table `fuel_requests`
--

CREATE TABLE `fuel_requests` (
  `id` int(11) NOT NULL,
  `gas_slip_id` varchar(20) NOT NULL,
  `fuel_item_id` int(11) NOT NULL,
  `quantity` varchar(10) NOT NULL,
  `container` enum('yes','no') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `fuel_requests`
--

INSERT INTO `fuel_requests` (`id`, `gas_slip_id`, `fuel_item_id`, `quantity`, `container`) VALUES
(1, '2026-04-00001', 1, '11', 'no'),
(2, '2026-01-00002', 1, '46.18', 'no');

-- --------------------------------------------------------

--
-- Table structure for table `gas_slips`
--

CREATE TABLE `gas_slips` (
  `id` int(11) NOT NULL,
  `gas_slip_id` varchar(20) DEFAULT NULL,
  `date_issued` date NOT NULL,
  `validity_until` date NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `requested_by` varchar(100) NOT NULL,
  `area` varchar(100) NOT NULL,
  `status` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `gas_slips`
--

INSERT INTO `gas_slips` (`id`, `gas_slip_id`, `date_issued`, `validity_until`, `vehicle_id`, `route_id`, `purpose`, `requested_by`, `area`, `status`) VALUES
(1, '2026-04-00001', '2026-01-02', '2026-01-03', 3, 250, 'inspection', 'cej', 'Puerto Princesa [Main Office]', 'printed'),
(2, '2026-01-00002', '2026-01-02', '2026-01-03', 104, 250, 'inspection', 'L. Cabanting', 'Puerto Princesa [Main Office]', 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `gas_slip_routes`
--

CREATE TABLE `gas_slip_routes` (
  `id` int(11) NOT NULL,
  `gas_slip_id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `sequence_no` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `gas_slip_routes`
--

INSERT INTO `gas_slip_routes` (`id`, `gas_slip_id`, `route_id`, `sequence_no`) VALUES
(1, 1, 250, 1),
(2, 2, 250, 1);

-- --------------------------------------------------------

--
-- Table structure for table `routes`
--

CREATE TABLE `routes` (
  `id` int(11) NOT NULL,
  `area` varchar(100) DEFAULT NULL,
  `route` char(6) DEFAULT NULL,
  `origin` varchar(100) DEFAULT NULL,
  `destination` varchar(100) DEFAULT NULL,
  `distance_km` decimal(6,2) DEFAULT NULL,
  `fuel_allocation` decimal(6,2) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `remarks` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `routes`
--

INSERT INTO `routes` (`id`, `area`, `route`, `origin`, `destination`, `distance_km`, `fuel_allocation`, `status`, `remarks`) VALUES
(1, 'El Nido', 'EN0500', 'El Nido', 'Bubulongan', '6.00', '1.00', 'active', ''),
(2, 'El Nido', 'EN0500', 'El Nido', 'Corong-Corong', '3.00', '1.00', 'active', ''),
(3, 'El Nido', 'EN0500', 'El Nido', 'Maligaya', '4.00', '1.00', 'active', ''),
(4, 'El Nido', 'EN0500', 'El Nido', 'Buena Suerte', '6.00', '1.00', 'active', ''),
(5, 'El Nido', 'EN0500', 'El Nido', 'Masagana', '7.20', '1.00', 'active', ''),
(6, 'El Nido', 'EN0500', 'El Nido', 'Nasigdan', '12.00', '1.00', 'active', ''),
(7, 'El Nido', 'EN0500', 'El Nido', 'Villa Libertad', '30.00', '1.75', 'active', ''),
(8, 'El Nido', 'EN0500', 'El Nido', 'Pasadena', '28.00', '1.75', 'active', ''),
(9, 'El Nido', 'EN0500', 'El Nido', 'Lamoro', '30.00', '1.75', 'active', ''),
(10, 'El Nido', 'EN0500', 'El Nido', 'Barutoan', '43.00', '2.00', 'active', ''),
(11, 'El Nido', 'EN0500', 'El Nido', 'Bucana', '53.00', '2.50', 'active', ''),
(12, 'El Nido', 'EN0500', 'El Nido', 'Tiniguiban', '56.00', '2.50', 'active', ''),
(13, 'Taytay', 'TY0400', 'Taytay', 'Poblacion', '6.00', '1.00', 'active', ''),
(14, 'Taytay', 'TY0400', 'Taytay', 'Arado II', '7.00', '1.00', 'active', ''),
(15, 'Taytay', 'TY0411', 'Taytay', 'Bato', '28.00', '1.50', 'active', 'South Barangay'),
(16, 'Taytay', 'TY0411', 'Taytay', 'Abongan', '52.00', '2.50', 'active', 'South Barangay'),
(17, 'Taytay', 'TY0411', 'Taytay', 'Paglaum', '42.00', '2.00', 'active', 'South Barangay'),
(18, 'Taytay', 'TY0411', 'Taytay', 'Libertad', '50.00', '2.50', 'active', 'South Barangay'),
(19, 'Taytay', 'TY0411', 'Taytay', 'Talog', '54.00', '2.50', 'active', 'South Barangay'),
(20, 'Taytay', 'TY0412', 'Taytay', 'New Quinlo/Old Quinlo', '50.00', '2.50', 'active', 'North Barangay'),
(21, 'Taytay', 'TY0425', 'Taytay', 'Pularaquen/Canique', '52.00', '2.50', 'active', ''),
(22, 'Dumaran', 'TY0413', 'Dumaran', 'Itangil', '100.00', '4.00', 'active', ''),
(23, 'Dumaran', 'TY0414', 'Dumaran', 'Tanatanaon', '94.00', '4.00', 'active', ''),
(24, 'Dumaran', 'TY0415', 'Dumaran', 'Sta.Maria', '102.00', '4.00', 'active', ''),
(25, 'Dumaran', 'TY0416', 'Dumaran', 'Ilian', '106.00', '4.25', 'active', ''),
(26, 'Dumaran', 'TY0417', 'Dumaran', 'Danleg', '118.40', '4.75', 'active', ''),
(27, 'Dumaran', 'TY0418', 'Dumaran', 'Sta.Teresita', '140.00', '5.50', 'active', ''),
(28, 'Dumaran', 'TY0419', 'Dumaran', 'Magsaysay', '110.00', '4.50', 'active', ''),
(29, 'Dumaran', 'TY0420', 'Dumaran', 'Casian (Island)', '0.00', '0.00', 'active', ''),
(30, 'Dumaran', 'TY0421', 'Dumaran', 'Biton (Island)', '0.00', '0.00', 'active', ''),
(31, 'Dumaran', 'TY0422', 'Dumaran', 'Paly (Island)', '0.00', '0.00', 'active', ''),
(32, 'Dumaran', 'TY0423', 'Dumaran', 'Capayas', '138.00', '5.25', 'active', ''),
(33, 'Dumaran', 'TY0424', 'Dumaran', 'Culasian', '115.00', '4.50', 'active', ''),
(34, 'Roxas', 'RX0300', 'Roxas', 'Barangay 1', '6.00', '1.00', 'active', ''),
(35, 'Roxas', 'RX0301', 'Roxas', 'Barangay 2', '4.00', '1.00', 'active', ''),
(36, 'Roxas', 'RX0302', 'Roxas', 'Barangay 3', '10.00', '1.00', 'active', ''),
(37, 'Roxas', 'RX0303', 'Roxas', 'Barangay 4', '14.00', '1.00', 'active', ''),
(38, 'Roxas', 'RX0304', 'Roxas', 'Bgy. Retac, Bliss', '12.00', '1.00', 'active', ''),
(39, 'Roxas', 'RX0305', 'Roxas', 'Caramay', '80.00', '3.50', 'active', ''),
(40, 'Roxas', 'RX0305', 'Roxas', 'Salvacion', '68.00', '3.00', 'active', ''),
(41, 'Roxas', 'RX0305', 'Roxas', 'Nicanor Zabala', '85.00', '3.50', 'active', ''),
(42, 'Roxas', 'RX0305', 'Roxas', 'San Miguel', '80.00', '3.30', 'active', ''),
(43, 'Roxas', 'RX0308', 'Roxas', 'San Jose', '32.00', '1.75', 'active', ''),
(44, 'Roxas', 'RX0308', 'Roxas', 'Malcampo', '20.00', '1.50', 'active', ''),
(45, 'Roxas', 'RX0308', 'Roxas', 'San Dionisio', '22.00', '1.50', 'active', ''),
(46, 'Roxas', 'RX0308', 'Roxas', 'Abaroan', '40.00', '2.00', 'active', ''),
(47, 'Roxas', 'RX0308', 'Roxas', 'New Cuyo', '50.00', '2.50', 'active', ''),
(48, 'Roxas', 'RX0308', 'Roxas', 'Rizal', '54.00', '2.50', 'active', ''),
(49, 'Roxas', 'RX0308', 'Roxas', 'Tagumpay', '55.00', '2.50', 'active', ''),
(50, 'Roxas', 'RX0308', 'Roxas', 'Magara', '68.00', '3.00', 'active', ''),
(51, 'Roxas', 'RX0309', 'Roxas', 'Minara', '13.00', '1.25', 'active', ''),
(52, 'Roxas', 'RX0309', 'Roxas', 'San Nicolas', '24.00', '1.50', 'active', ''),
(53, 'Roxas', 'RX0309', 'Roxas', 'Sandoval ', '41.00', '2.00', 'active', ''),
(54, 'Roxas', 'RX0309', 'Roxas', 'Iraan ', '50.00', '2.50', 'active', ''),
(55, 'Roxas', 'RX0309', 'Roxas', 'Dumarao', '85.00', '3.50', 'active', ''),
(56, 'Araceli', 'RX0307', 'Araceli', 'Poblacion ', '5.00', '1.00', 'active', ''),
(57, 'Araceli', 'RX0307', 'Araceli', 'Tinintinan', '12.00', '1.00', 'active', ''),
(58, 'Cagayancillo', 'CG0550', 'Cagayancillo', 'Poblacion', '8.00', '1.00', 'active', 'North Lipot,South Lipot, Wahig, Calsada, Magsaysay, Bantayan, Convento, Tacas'),
(59, 'Cagayancillo', 'CG0550', 'Cagayancillo', 'Sta Cruz', '12.00', '1.00', 'active', ''),
(60, 'Cagayancillo', 'CG0550', 'Cagayancillo', 'Talaga', '15.00', '1.25', 'active', ''),
(61, 'Cagayancillo', 'CG0550', 'Cagayancillo', 'Mampio', '20.00', '1.50', 'active', ''),
(62, 'Cagayancillo', 'CG0550', 'Cagayancillo', 'Nusa', '24.00', '1.50', 'active', ''),
(63, 'San Vicente', 'SV0306', 'San Vicente', 'Poblacion', '6.00', '1.00', 'active', ''),
(64, 'San Vicente', 'SV0306', 'San Vicente', 'Panindigan', '14.00', '1.00', 'active', ''),
(65, 'San Vicente', 'SV0306', 'San Vicente', 'Macatumbalen', '22.00', '1.50', 'active', ''),
(66, 'San Vicente', 'SV0307', 'San Vicente', 'Kemdeng', '28.00', '1.50', 'active', ''),
(67, 'San Vicente', 'SV0307', 'San Vicente', 'New Agutaya', '26.00', '1.50', 'active', ''),
(68, 'San Vicente', 'SV0307', 'San Vicente', 'San Isidro', '18.60', '1.00', 'active', ''),
(69, 'San Vicente', 'SV0307', 'San Vicente', 'Alimanguan', '35.00', '2.00', 'active', ''),
(70, 'San Vicente', 'SV0307', 'San Vicente', 'Sto Nino', '42.00', '2.00', 'active', ''),
(71, 'San Vicente', 'SV0308', 'San Vicente', 'New Canipo', '52.00', '2.50', 'active', ''),
(72, 'Brookes Point', 'BP0200', 'Brookes Point', 'Poblacion 1', '16.00', '1.25', 'active', ''),
(73, 'Brookes Point', 'BP0200', 'Brookes Point', 'Poblacion 1', '16.00', '1.25', 'active', ''),
(74, 'Brookes Point', 'BP0201', 'Brookes Point', 'Poblacion 2 & So. Balacan, Pangobilian', '20.00', '1.50', 'active', ''),
(75, 'Brookes Point', 'BP0201', 'Brookes Point', 'So., Balacan, Moreno Subd. Pob Dist 2', '20.00', '1.50', 'active', ''),
(76, 'Brookes Point', 'BP0202', 'Brookes Point', 'Rawland Subd. & Blessed Ville,Poblacion District 2', '21.00', '1.50', 'active', ''),
(77, 'Brookes Point', 'BP0202', 'Brookes Point', 'Edwards Subd. & Jesmil Subdivision Poblacion 2', '21.00', '1.50', 'active', ''),
(78, 'Brookes Point', 'BP0210', 'Brookes Point', 'Pangobilian National Hi-Way,So. Balacan , So. Aluluwang Pangobilan', '21.00', '1.50', 'active', ''),
(79, 'Brookes Point', 'BP0210', 'Brookes Point', 'Bgy. Tubtub & Portion Of Bgy Amas', '21.00', '1.50', 'active', ''),
(80, 'Brookes Point', 'BP0210', 'Brookes Point', 'Portion Of Bgy. Amas, Bgy. Pangobilian, & So. Paratungon', '33.00', '1.75', 'active', ''),
(81, 'Brookes Point', 'BP0210', 'Brookes Point', 'Portion Of Paratungon & Pangobilian Proper', '30.00', '1.75', 'active', ''),
(82, 'Brookes Point', 'BP0210', 'Brookes Point', 'So. Suring, So. Mati, So Babangon, Pangobilian', '29.00', '1.75', 'active', ''),
(83, 'Brookes Point', 'BP0210', 'Brookes Point', 'So. Suring 2, Bgy. Mainit, Bgy.Imulnod', '28.00', '1.75', 'active', ''),
(84, 'Brookes Point', 'BP0210', 'Brookes Point', 'Bgy. Mainit, So.Raang, Bgy Aribungos, So. Tigaplan Aribungos', '35.00', '2.00', 'active', ''),
(85, 'Brookes Point', 'BP0210', 'Brookes Point', 'So. Baribe-Aribungos', '31.00', '1.75', 'active', ''),
(86, 'Brookes Point', 'BP0210', 'Brookes Point', 'So. Raang & So. Cabar, Aribungos', '34.00', '1.75', 'active', ''),
(87, 'Brookes Point', 'BP0210', 'Brookes Point', 'So. Lada, So. Suring 1, So. Pangobilian, So Camilit Ipilan', '32.00', '1.75', 'active', ''),
(88, 'Brookes Point', 'BP0210', 'Brookes Point', 'So. Lada, Pangobilian, So. Tagusao, So. Pintasan, Barong Barong', '30.00', '1.75', 'active', ''),
(89, 'Brookes Point', 'BP0220', 'Brookes Point', 'So.Candis, So. Malulunan, Proper Barong-Barong, So. Curanga, Proper Ipilan', '38.00', '2.00', 'active', ''),
(90, 'Brookes Point', 'BP0220', 'Brookes Point', 'Bgy. Proper & So. Linao Ipilan', '40.00', '2.00', 'active', ''),
(91, 'Brookes Point', 'BP0220', 'Brookes Point', 'So. Linao Ipilan, So. Pitik-Pitik, So. Limbasan, Relocation, Ipilan & Mambalot Portion', '41.00', '2.00', 'active', ''),
(92, 'Brookes Point', 'BP0220', 'Brookes Point', 'Prk 2,3,4, 5,6 & 7, Rizal, Mambalot', '47.00', '2.25', 'active', ''),
(93, 'Brookes Point', 'BP0220', 'Brookes Point', 'So. Lapiac Ipilan, Maasin Proper, New Panay, Prk 6, Maasin', '52.00', '2.50', 'active', ''),
(94, 'Brookes Point', 'BP0220', 'Brookes Point', 'Proper Maasin, Purok 7 Maasin, So Abubakar Calasaguen', '53.00', '2.50', 'active', ''),
(95, 'Brookes Point', 'BP0220', 'Brookes Point', 'Proper Calasaguen, Prk 6 & 7 Calasaguen', '62.00', '2.75', 'active', ''),
(96, 'Brookes Point', 'BP0250', 'Brookes Point', 'So. Matangkay, So Cadjasan,Venturanza, Oring Oring Proper,So. Tagpirara, Saraza', '31.00', '1.75', 'active', ''),
(97, 'Brookes Point', 'BP0250', 'Brookes Point', 'So. Tagpirara, Saraza, So. Tabud, Saraza, So. Tamlang Sarazaso. Jurusan, Saraza', '33.00', '1.75', 'active', ''),
(98, 'Brookes Point', 'BP0250', 'Brookes Point', 'So. Tamlang, So Cabangaan, Sea Shore,Samariniana Proper, Tagpait Salogon', '42.00', '2.00', 'active', ''),
(99, 'Brookes Point', 'BP0250', 'Brookes Point', 'So. Magugurang, So. Kiniurong Salogon Proper,', '45.00', '2.25', 'active', ''),
(100, 'Brookes Point', 'BP0250', 'Brookes Point', 'So. Locon, So. Aplaya,So. Babanga, So.  Idiok, So. Bulnok, So. Pansor, Proper Malis', '56.00', '2.50', 'active', ''),
(101, 'Aborlan', 'AB0150', 'Aborlan', 'So. Tagbarungis, Inagawan Sub-Colony, Taytayin, Inagawan Interior', '82.00', '3.50', 'active', ''),
(102, 'Aborlan', 'AB0150', 'Aborlan', 'Inagawan Interior, Calibugan, Escalona, Alit-Alit, Parina Kamuning', '75.00', '3.25', 'active', ''),
(103, 'Aborlan', 'AB0150', 'Aborlan', 'Marambuaya, Kamuning, Tagumpay,', '69.00', '3.00', 'active', ''),
(104, 'Aborlan', 'AB0160', 'Aborlan', 'Isaub ( Del-At,Tagpit, Centro, Linao )', '67.00', '3.00', 'active', ''),
(105, 'Aborlan', 'AB0160', 'Aborlan', 'Isaub (Linao, Maligaya Village ) Sagpangan ( Centro,  Maringit, Maligaya) ', '65.00', '3.00', 'active', ''),
(106, 'Aborlan', 'AB0160', 'Aborlan', 'Sagpangan (Katuwang Tuwangan, Maligaya ) Iraan ( Mayligan, Mamonmon, Centro ) ', '61.00', '2.75', 'active', ''),
(107, 'Aborlan', 'AB0160', 'Aborlan', 'San Juan ( Marikit, Titibawan ) Iraan Proper, Mabini, Gogognan ', '43.00', '2.25', 'active', ''),
(108, 'Aborlan', 'AB0160', 'Aborlan', 'Mabini, Magbabadil, Barake', '62.00', '2.75', 'active', ''),
(109, 'Aborlan', 'AB0160', 'Aborlan', 'Magbabadil ( So. Parang ) Cabigaan', '37.00', '2.00', 'active', ''),
(110, 'Aborlan', 'AB0160', 'Aborlan', 'Apis, Cabigaan, San Juan', '69.00', '3.00', 'active', ''),
(111, 'Aborlan', 'AB0160', 'Aborlan', 'San Juan , Old Site ', '42.00', '2.00', 'active', ''),
(112, 'Aborlan', 'AB0160', 'Aborlan', 'Poblacion', '5.00', '1.00', 'active', ''),
(113, 'Aborlan', 'AB0160', 'Aborlan', 'Poblacion , Tagpait', '32.00', '2.75', 'active', ''),
(114, 'Aborlan', 'AB0161', 'Aborlan', 'Tagpait, Gogognan, Magsaysay', '61.00', '2.75', 'active', ''),
(115, 'Aborlan', 'AB0161', 'Aborlan', 'Magsaysay, Aplaya, Tagbariri, Valderama', '51.00', '2.50', 'active', ''),
(116, 'Aborlan', 'AB0161', 'Aborlan', 'Marimbras, Maasin Plaridel, Tigman', '67.00', '3.00', 'active', ''),
(117, 'Aborlan', 'AB0161', 'Aborlan', 'Tigman , Plaridel', '65.00', '3.00', 'active', ''),
(118, 'Aborlan', 'AB0161', 'Aborlan', 'Plaridel, Km81, Jose Rizal Line 6', '78.00', '3.25', 'active', ''),
(119, 'Aborlan', 'AB0161', 'Aborlan', 'Jose Rizal, Apoc-Apoc', '110.00', '4.50', 'active', ''),
(120, 'Puerto Princesa [Main Office]', 'PX0010', 'Puerto Princesa [Main Office]', 'Paleco Employee', '0.00', '0.00', 'active', ''),
(121, 'Puerto Princesa [Main Office]', 'PX0100', 'Puerto Princesa [Main Office]', 'Poblacion I', '30.00', '2.00', 'active', ''),
(122, 'Puerto Princesa [Main Office]', 'PX0101', 'Puerto Princesa [Main Office]', 'Poblacion 2', '30.00', '2.00', 'active', ''),
(123, 'Puerto Princesa [Main Office]', 'PX0102', 'Puerto Princesa [Main Office]', 'Poblacion 3', '30.00', '2.00', 'active', ''),
(124, 'Puerto Princesa [Main Office]', 'PX0103', 'Puerto Princesa [Main Office]', 'Poblacion 4', '30.00', '2.00', 'active', ''),
(125, 'Puerto Princesa [Main Office]', 'PX0107', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(126, 'Puerto Princesa [Main Office]', 'PX0200', 'Puerto Princesa [Main Office]', 'Poblacion 5', '30.00', '2.00', 'active', ''),
(127, 'Puerto Princesa [Main Office]', 'PX0201', 'Puerto Princesa [Main Office]', 'Poblacion 6', '30.00', '2.00', 'active', ''),
(128, 'Puerto Princesa [Main Office]', 'PX0202', 'Puerto Princesa [Main Office]', 'Poblacion 7', '30.00', '2.00', 'active', ''),
(129, 'Puerto Princesa [Main Office]', 'PX0203', 'Puerto Princesa [Main Office]', 'Poblacion 8', '30.00', '2.00', 'active', ''),
(130, 'Puerto Princesa [Main Office]', 'PX0204', 'Puerto Princesa [Main Office]', 'Bgy. San Isidro', '30.00', '2.00', 'active', ''),
(131, 'Puerto Princesa [Main Office]', 'PX0207', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(132, 'Puerto Princesa [Main Office]', 'PX0300', 'Puerto Princesa [Main Office]', 'Poblacion 9', '30.00', '2.00', 'active', ''),
(133, 'Puerto Princesa [Main Office]', 'PX0301', 'Puerto Princesa [Main Office]', 'Poblacion 10', '30.00', '2.00', 'active', ''),
(134, 'Puerto Princesa [Main Office]', 'PX0302', 'Puerto Princesa [Main Office]', 'Poblacion 11', '30.00', '2.00', 'active', ''),
(135, 'Puerto Princesa [Main Office]', 'PX0303', 'Puerto Princesa [Main Office]', 'Poblacion 12', '30.00', '2.00', 'active', ''),
(136, 'Puerto Princesa [Main Office]', 'PX0304', 'Puerto Princesa [Main Office]', 'Poblacion 13', '30.00', '2.00', 'active', ''),
(137, 'Puerto Princesa [Main Office]', 'PX0305', 'Puerto Princesa [Main Office]', 'Poblacion 14', '30.00', '2.00', 'active', ''),
(138, 'Puerto Princesa [Main Office]', 'PX0306', 'Puerto Princesa [Main Office]', 'Malvar / Pub Mkt', '30.00', '2.00', 'active', ''),
(139, 'Puerto Princesa [Main Office]', 'PX0307', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(140, 'Puerto Princesa [Main Office]', 'PX0400', 'Puerto Princesa [Main Office]', 'Poblacion 15', '30.00', '2.00', 'active', ''),
(141, 'Puerto Princesa [Main Office]', 'PX0401', 'Puerto Princesa [Main Office]', 'Poblacion 16', '30.00', '2.00', 'active', ''),
(142, 'Puerto Princesa [Main Office]', 'PX0402', 'Puerto Princesa [Main Office]', 'Poblacion 17', '30.00', '2.00', 'active', ''),
(143, 'Puerto Princesa [Main Office]', 'PX0403', 'Puerto Princesa [Main Office]', 'Poblacion 18', '30.00', '2.00', 'active', ''),
(144, 'Puerto Princesa [Main Office]', 'PX0404', 'Puerto Princesa [Main Office]', 'Poblacion 19', '30.00', '2.00', 'active', ''),
(145, 'Puerto Princesa [Main Office]', 'PX0407', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(146, 'Puerto Princesa [Main Office]', 'PX0500', 'Puerto Princesa [Main Office]', 'Poblacion 20', '30.00', '2.00', 'active', ''),
(147, 'Puerto Princesa [Main Office]', 'PX0501', 'Puerto Princesa [Main Office]', 'Barrio 1', '30.00', '2.00', 'active', ''),
(148, 'Puerto Princesa [Main Office]', 'PX0502', 'Puerto Princesa [Main Office]', 'Barrio 2', '30.00', '2.00', 'active', ''),
(149, 'Puerto Princesa [Main Office]', 'PX0503', 'Puerto Princesa [Main Office]', 'Barrio 3', '30.00', '2.00', 'active', ''),
(150, 'Puerto Princesa [Main Office]', 'PX0504', 'Puerto Princesa [Main Office]', 'Barrio 4', '30.00', '2.00', 'active', ''),
(151, 'Puerto Princesa [Main Office]', 'PX0505', 'Puerto Princesa [Main Office]', 'Barrio 5', '30.00', '2.00', 'active', ''),
(152, 'Puerto Princesa [Main Office]', 'PX0506', 'Puerto Princesa [Main Office]', 'Barrio 6', '30.00', '2.00', 'active', ''),
(153, 'Puerto Princesa [Main Office]', 'PX0507', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(154, 'Puerto Princesa [Main Office]', 'PX0508', 'Puerto Princesa [Main Office]', 'Bapa - Sicsican', '30.00', '2.00', 'active', ''),
(155, 'Puerto Princesa [Main Office]', 'PX0509', 'Puerto Princesa [Main Office]', 'Sicsican Housing', '30.00', '2.00', 'active', ''),
(156, 'Puerto Princesa [Main Office]', 'PX0510', 'Puerto Princesa [Main Office]', 'Bgy. Luzviminda', '56.40', '2.00', 'active', ''),
(157, 'Puerto Princesa [Main Office]', 'PX0511', 'Puerto Princesa [Main Office]', 'Golden Valley Sicsican', '30.00', '2.00', 'active', ''),
(158, 'Puerto Princesa [Main Office]', 'PX0512', 'Puerto Princesa [Main Office]', 'Bgy. Mangingisda', '79.00', '3.50', 'active', ''),
(159, 'Puerto Princesa [Main Office]', 'PX0513', 'Puerto Princesa [Main Office]', 'Bgy. Montible', '49.40', '2.50', 'active', ''),
(160, 'Puerto Princesa [Main Office]', 'PX0514', 'Puerto Princesa [Main Office]', 'Honda Bay, Sta. Lourdes', '48.60', '2.50', 'active', ''),
(161, 'Puerto Princesa [Main Office]', 'PX0515', 'Puerto Princesa [Main Office]', 'Tagburos Aplaya', '30.00', '2.00', 'active', ''),
(162, 'Puerto Princesa [Main Office]', 'PX0516', 'Puerto Princesa [Main Office]', 'Typoco, San Manuel', '30.00', '2.00', 'active', ''),
(163, 'Puerto Princesa [Main Office]', 'PX0517', 'Puerto Princesa [Main Office]', 'Bapa-Ellen View', '30.00', '2.00', 'active', ''),
(164, 'Puerto Princesa [Main Office]', 'PX0518', 'Puerto Princesa [Main Office]', 'Anonang Sicsican', '30.00', '2.00', 'active', ''),
(165, 'Puerto Princesa [Main Office]', 'PX0519', 'Puerto Princesa [Main Office]', 'Visapa, Irawan', '30.00', '2.00', 'active', ''),
(166, 'Puerto Princesa [Main Office]', 'PX0520', 'Puerto Princesa [Main Office]', 'Busngol', '30.00', '2.00', 'active', ''),
(167, 'Puerto Princesa [Main Office]', 'PX0521', 'Puerto Princesa [Main Office]', 'Fishermans Village', '30.00', '2.00', 'active', ''),
(168, 'Puerto Princesa [Main Office]', 'PX0522', 'Puerto Princesa [Main Office]', 'Sunrise, San Jose', '30.00', '2.00', 'active', ''),
(169, 'Puerto Princesa [Main Office]', 'PX0523', 'Puerto Princesa [Main Office]', 'Sta Cruz/Bacungan', '73.00', '3.00', 'active', ''),
(170, 'Puerto Princesa [Main Office]', 'PX0524', 'Puerto Princesa [Main Office]', 'Bgy. Salvacion', '91.00', '4.00', 'active', ''),
(171, 'Puerto Princesa [Main Office]', 'PX0525', 'Puerto Princesa [Main Office]', 'Bgy. Manalo', '105.00', '4.50', 'active', ''),
(172, 'Puerto Princesa [Main Office]', 'PX0526', 'Puerto Princesa [Main Office]', 'Bgy. Maruyugon', '109.00', '4.50', 'active', ''),
(173, 'Puerto Princesa [Main Office]', 'PX0527', 'Puerto Princesa [Main Office]', 'Bgy. Lucbuan', '117.00', '4.50', 'active', ''),
(174, 'Puerto Princesa [Main Office]', 'PX0528', 'Puerto Princesa [Main Office]', 'Bgy. Maoyon', '123.00', '5.00', 'active', ''),
(175, 'Puerto Princesa [Main Office]', 'PX0529', 'Puerto Princesa [Main Office]', 'Bgy Mangingisda', '83.00', '3.50', 'active', ''),
(176, 'Puerto Princesa [Main Office]', 'PX0530', 'Puerto Princesa [Main Office]', 'Bgy. Bahile', '101.00', '5.00', 'active', ''),
(177, 'Puerto Princesa [Main Office]', 'PX0531', 'Puerto Princesa [Main Office]', 'Bgy. Babuyan', '127.00', '5.00', 'active', ''),
(178, 'Puerto Princesa [Main Office]', 'PX0532', 'Puerto Princesa [Main Office]', 'Bgy. San Rafael', '133.00', '5.00', 'active', ''),
(179, 'Puerto Princesa [Main Office]', 'PX0533', 'Puerto Princesa [Main Office]', 'Bgy. Tanabag', '128.00', '5.00', 'active', ''),
(180, 'Puerto Princesa [Main Office]', 'PX0534', 'Puerto Princesa [Main Office]', 'Bgy. Concepcion', '147.00', '5.50', 'active', ''),
(181, 'Puerto Princesa [Main Office]', 'PX0535', 'Puerto Princesa [Main Office]', 'Bgy. Binduyan', '155.00', '6.00', 'active', ''),
(182, 'Puerto Princesa [Main Office]', 'PX0536', 'Puerto Princesa [Main Office]', 'Bgy. Langogan', '187.00', '7.00', 'active', ''),
(183, 'Puerto Princesa [Main Office]', 'PX0537', 'Puerto Princesa [Main Office]', 'Bgy. Macarascas', '191.00', '7.00', 'active', ''),
(184, 'Puerto Princesa [Main Office]', 'PX0538', 'Puerto Princesa [Main Office]', 'Bgy. Montible', '49.40', '2.50', 'active', ''),
(185, 'Puerto Princesa [Main Office]', 'PX0539', 'Puerto Princesa [Main Office]', 'Bukana, Bgy. Iwahig', '56.00', '2.50', 'active', ''),
(186, 'Puerto Princesa [Main Office]', 'PX0540', 'Puerto Princesa [Main Office]', 'Bgy. Napsan', '124.00', '5.00', 'active', ''),
(187, 'Puerto Princesa [Main Office]', 'PX0701', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(188, 'Puerto Princesa [Main Office]', 'PX0702', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(189, 'Puerto Princesa [Main Office]', 'PX0703', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(190, 'Puerto Princesa [Main Office]', 'PX0704', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(191, 'Puerto Princesa [Main Office]', 'PX0705', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(192, 'Puerto Princesa [Main Office]', 'PX0900', 'Puerto Princesa [Main Office]', 'Bapa', '30.00', '2.00', 'active', ''),
(193, 'Quezon', 'QZ0029', 'Quezon', 'Poblacion, Quezon', '30.00', '2.00', 'active', ''),
(194, 'Quezon', 'QZ0030', 'Quezon', 'Quezon', '30.00', '2.00', 'active', ''),
(195, 'Quezon', 'QZ0031', 'Quezon', 'Quezon', '30.00', '2.00', 'active', ''),
(196, 'Quezon', 'QZ0032', 'Quezon', 'Employees/Send To Main', '0.00', '0.00', 'active', ''),
(197, 'Rizal', 'RZ0035', 'Rizal', 'Poblacion, Rizal', '30.00', '2.00', 'active', ''),
(198, 'Rizal', 'RZ0036', 'Rizal', 'Poblacion, Rizal', '30.00', '2.00', 'active', ''),
(199, 'Rizal', 'RZ0037', 'Rizal', 'Poblacion, Rizal', '30.00', '2.00', 'active', ''),
(200, 'Rizal', 'RZ0038', 'Rizal', 'Poblacion, Rizal', '30.00', '2.00', 'active', ''),
(201, 'Narra', 'nset', 'Narra', 'Antipuluan', '17.00', '1.50', 'inactive', ''),
(202, 'Narra', 'nset', 'Narra', 'Panacan 2', '26.00', '1.47', 'inactive', ''),
(203, 'Narra', 'nset', 'Narra', 'Poblacion', '25.66', '1.46', 'inactive', ''),
(204, 'Narra', 'nset', 'Narra', 'Panacan 1', '29.00', '1.57', 'inactive', ''),
(205, 'Narra', 'nset', 'Narra', 'Elvita', '30.00', '1.75', 'inactive', ''),
(206, 'Narra', 'nset', 'Narra', 'Taritien', '35.00', '2.00', 'inactive', ''),
(207, 'Narra', 'nset', 'Narra', 'Malatgao', '35.00', '2.00', 'inactive', ''),
(208, 'Narra', 'nset', 'Narra', 'Malinao', '37.00', '2.00', 'inactive', ''),
(209, 'Narra', 'nset', 'Narra', 'Tinagong Dagat', '39.00', '2.00', 'inactive', ''),
(210, 'Narra', 'nset', 'Narra', 'Sandoval', '39.00', '2.00', 'inactive', ''),
(211, 'Narra', 'nset', 'Narra', 'Estrella Village', '41.00', '1.97', 'inactive', ''),
(212, 'Narra', 'nset', 'Narra', 'Apo-Aporawan', '45.00', '2.50', 'inactive', ''),
(213, 'Narra', 'nset', 'Narra', 'Bagong Sikat', '45.00', '2.50', 'inactive', ''),
(214, 'Narra', 'nset', 'Narra', 'Batang Batang', '51.00', '2.50', 'inactive', ''),
(215, 'Narra', 'nset', 'Narra', 'P. Urduja', '55.00', '2.50', 'inactive', ''),
(216, 'Narra', 'nset', 'Narra', 'Teresa', '55.00', '2.50', 'inactive', ''),
(217, 'Narra', 'nset', 'Narra', 'San Isidro', '57.00', '2.50', 'inactive', ''),
(218, 'Narra', 'nset', 'Narra', 'Dumanguena', '65.00', '3.00', 'inactive', ''),
(219, 'Narra', 'nset', 'Narra', 'Calatigas', '71.00', '2.97', 'inactive', ''),
(220, 'Narra', 'nset', 'Narra', 'Aramaywan', '81.00', '3.50', 'inactive', ''),
(221, 'Narra', 'nset', 'Narra', 'Tacras', '85.00', '3.50', 'inactive', ''),
(222, 'Narra', 'nset', 'Narra', 'Burirao', '89.00', '4.00', 'inactive', ''),
(223, 'Narra', 'nset', 'Narra', 'Abo-Abo', '97.00', '4.00', 'inactive', ''),
(224, 'Narra', 'nset', 'Narra', 'Ipilan', '101.00', '4.00', 'inactive', ''),
(225, 'Narra', 'nset', 'Narra', 'Isumbo', '107.00', '4.50', 'inactive', ''),
(226, 'Narra', 'nset', 'Narra', 'Panitian', '125.00', '5.00', 'inactive', ''),
(227, 'Narra', 'nset', 'Narra', 'Labog', '141.00', '5.50', 'inactive', ''),
(228, 'Rizal', 'RZ0035', 'Rizal', 'Pajo Area', '30.00', '1.00', 'active', ''),
(229, 'Rizal', 'RZ0035', 'Rizal', 'Balite ', '30.00', '1.00', 'active', ''),
(230, 'Rizal', 'RZ0035', 'Rizal', 'Purok Pagkakaisa', '60.00', '2.00', 'active', ''),
(231, 'Rizal', 'RZ0035', 'Rizal', 'Core', '40.00', '1.75', 'active', ''),
(232, 'Rizal', 'RZ0036', 'Rizal', 'Poblacion', '70.00', '2.50', 'active', ''),
(233, 'Rizal', 'RZ0036', 'Rizal', 'Mahogany', '70.00', '2.50', 'active', ''),
(234, 'Rizal', 'RZ0036', 'Rizal', 'Trucut', '60.00', '2.00', 'active', ''),
(235, 'Rizal', 'RZ0036', 'Rizal', 'Purok Pakpaklawin', '30.00', '1.00', 'active', ''),
(236, 'Rizal', 'RZ0036', 'Rizal', 'Purok Katutubo', '33.00', '1.50', 'active', ''),
(237, 'Rizal', 'RZ0036', 'Rizal', 'Ranho #1', '35.00', '1.50', 'active', ''),
(238, 'Rizal', 'RZ0036', 'Rizal', 'Sitio Malapandig', '55.00', '1.75', 'active', ''),
(239, 'Rizal', 'RZ0036', 'Rizal', 'Ranho #2', '33.00', '1.50', 'active', ''),
(240, 'Rizal', 'RZ0036', 'Rizal', 'Gomiok Punta Baja', '45.00', '1.50', 'active', ''),
(241, 'Rizal', 'RZ0036', 'Rizal', 'Ararab', '40.00', '1.50', 'active', ''),
(242, 'Rizal', 'RZ0036', 'Rizal', 'Brgy Iraan', '34.00', '1.50', 'active', ''),
(243, 'Rizal', 'RZ0036', 'Rizal', 'Purok Maligay', '30.00', '1.00', 'active', ''),
(244, 'Rizal', 'RZ0036', 'Rizal', 'Libtong', '30.00', '1.00', 'active', ''),
(245, 'Rizal', 'RZ0036', 'Rizal', 'Purok Nagkakaisa', '38.00', '1.75', 'active', ''),
(246, 'Rizal', 'RZ0036', 'Rizal', 'Salong-Song', '40.00', '1.75', 'active', ''),
(247, 'Rizal', 'RZ0037', 'Rizal', 'Pk Liway-Way', '41.00', '1.75', 'active', ''),
(248, 'Rizal', 'RZ0037', 'Rizal', 'Tribal Katutubo', '47.00', '2.00', 'active', NULL),
(249, 'Rizal', 'RZ0038', 'Rizal', 'Base', '70.00', '2.50', 'active', NULL),
(250, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Aborlan', '110.00', '0.00', 'active', ''),
(251, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Narra', '188.60', '0.00', 'active', ''),
(252, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Sofronio Espanola', '304.00', '0.00', 'active', ''),
(253, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Brookes Point', '360.00', '0.00', 'active', ''),
(254, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Bataraza', '474.00', '0.00', 'active', ''),
(255, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Rio Tuba', '486.00', '0.00', 'active', ''),
(256, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Buliluyan', '556.00', '0.00', 'active', ''),
(257, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Quezon', '296.00', '0.00', 'active', ''),
(258, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Rizal', '442.00', '0.00', 'active', ''),
(259, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Roxas', '270.00', '0.00', 'active', ''),
(260, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'San Vicente', '316.00', '0.00', 'active', ''),
(261, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Taytay', '386.80', '0.00', 'active', ''),
(262, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Dumaran (Sta Teresita)', '478.00', '0.00', 'active', ''),
(263, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'El Nido', '564.00', '0.00', 'active', ''),
(264, 'Roxas', NULL, 'Roxas', 'San Vicente', '134.60', '0.00', 'active', ''),
(265, 'Roxas', NULL, 'Roxas', 'Taytay', '150.80', '0.00', 'active', ''),
(266, 'Roxas', NULL, 'Roxas', 'Dumaran', '208.00', '0.00', 'active', ''),
(267, 'Roxas', NULL, 'Roxas', 'El Nido', '294.00', '0.00', 'active', ''),
(268, 'Taytay', NULL, 'Taytay', 'Dumaran (Sta Teresita)', '110.40', '0.00', 'active', ''),
(269, 'Taytay', NULL, 'Taytay', 'El Nido', '123.20', '0.00', 'active', ''),
(270, 'Brookes Point', NULL, 'Brookes Point', 'Bataraza', '134.00', '0.00', 'active', ''),
(271, 'Brookes Point', NULL, 'Brookes Point', 'Rio Tuba', '120.00', '0.00', 'active', ''),
(272, 'Brookes Point', NULL, 'Brookes Point', 'Buliluyan', '198.00', '0.00', 'active', ''),
(273, 'Brookes Point', NULL, 'Brookes Point', 'Sofronio Espanola', '56.80', '0.00', 'active', ''),
(274, 'Brookes Point', NULL, 'Brookes Point', 'Quezon', '252.80', '0.00', 'active', ''),
(275, 'Quezon', NULL, 'Quezon', 'Rizal', '160.40', '0.00', 'active', ''),
(276, 'Quezon', NULL, 'Quezon', 'Narra', '106.60', '0.00', 'active', ''),
(277, 'Quezon', NULL, 'Quezon', 'Bataraza', '216.00', '0.00', 'active', ''),
(278, 'Narra', NULL, 'Narra', 'Aborlan', '69.20', '0.00', 'inactive', ''),
(279, 'Narra', NULL, 'Narra', 'Sofronio Espanola', '124.00', '0.00', 'inactive', ''),
(280, 'Narra', NULL, 'Narra', 'Brookes Point', '178.00', '0.00', 'inactive', ''),
(281, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'San Vicente, Kemdeng', '378.00', NULL, 'active', NULL),
(282, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'San Vicente, New Canipo', '337.80', '0.00', 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `routes_backup`
--

CREATE TABLE `routes_backup` (
  `id` int(11) NOT NULL,
  `area` varchar(100) DEFAULT NULL,
  `route` char(4) DEFAULT NULL,
  `origin` varchar(100) DEFAULT NULL,
  `destination` varchar(100) DEFAULT NULL,
  `distance_km` decimal(6,2) DEFAULT NULL,
  `fuel_allocation` decimal(6,2) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `remarks` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `routes_backup`
--

INSERT INTO `routes_backup` (`id`, `area`, `route`, `origin`, `destination`, `distance_km`, `fuel_allocation`, `status`, `remarks`) VALUES
(1, 'El Nido', '0500', 'El Nido', 'Bubulongan', '6.00', '1.00', 'active', ''),
(2, 'El Nido', '0500', 'El Nido', 'Corong-Corong', '3.00', '1.00', 'active', ''),
(3, 'El Nido', '0500', 'El Nido', 'Maligaya', '4.00', '1.00', 'active', ''),
(4, 'El Nido', '0500', 'El Nido', 'Buena Suerte', '6.00', '1.00', 'active', ''),
(5, 'El Nido', '0500', 'El Nido', 'Masagana', '7.20', '1.00', 'active', ''),
(6, 'El Nido', '0500', 'El Nido', 'Nasigdan', '12.00', '1.00', 'active', ''),
(7, 'El Nido', '0500', 'El Nido', 'Villa Libertad', '30.00', '1.75', 'active', ''),
(8, 'El Nido', '0500', 'El Nido', 'Pasadena', '28.00', '1.75', 'active', ''),
(9, 'El Nido', '0500', 'El Nido', 'Lamoro', '30.00', '1.75', 'active', ''),
(10, 'El Nido', '0500', 'El Nido', 'Barutoan', '43.00', '2.00', 'active', ''),
(11, 'El Nido', '0500', 'El Nido', 'Bucana', '53.00', '2.50', 'active', ''),
(12, 'El Nido', '0500', 'El Nido', 'Tiniguiban', '56.00', '2.50', 'active', ''),
(13, 'Taytay', '0400', 'Taytay', 'Poblacion', '6.00', '1.00', 'active', ''),
(14, 'Taytay', '0400', 'Taytay', 'Arado II', '7.00', '1.00', 'active', ''),
(15, 'Taytay', '0411', 'Taytay', 'Bato', '28.00', '1.50', 'active', 'South Barangay'),
(16, 'Taytay', '0411', 'Taytay', 'Abongan', '52.00', '2.50', 'active', 'South Barangay'),
(17, 'Taytay', '0411', 'Taytay', 'Paglaum', '42.00', '2.00', 'active', 'South Barangay'),
(18, 'Taytay', '0411', 'Taytay', 'Libertad', '50.00', '2.50', 'active', 'South Barangay'),
(19, 'Taytay', '0411', 'Taytay', 'Talog', '54.00', '2.50', 'active', 'South Barangay'),
(20, 'Taytay', '0412', 'Taytay', 'New Quinlo/Old Quinlo', '50.00', '2.50', 'active', 'North Barangay'),
(21, 'Taytay', '0425', 'Taytay', 'Pularaquen/Canique', '52.00', '2.50', 'active', ''),
(22, 'Dumaran', '0413', 'Dumaran', 'Itangil', '100.00', '4.00', 'active', ''),
(23, 'Dumaran', '0414', 'Dumaran', 'Tanatanaon', '94.00', '4.00', 'active', ''),
(24, 'Dumaran', '0415', 'Dumaran', 'Sta.Maria', '102.00', '4.00', 'active', ''),
(25, 'Dumaran', '0416', 'Dumaran', 'Ilian', '106.00', '4.25', 'active', ''),
(26, 'Dumaran', '0417', 'Dumaran', 'Danleg', '118.40', '4.75', 'active', ''),
(27, 'Dumaran', '0418', 'Dumaran', 'Sta.Teresita', '140.00', '5.50', 'active', ''),
(28, 'Dumaran', '0419', 'Dumaran', 'Magsaysay', '110.00', '4.50', 'active', ''),
(29, 'Dumaran', '0420', 'Dumaran', 'Casian (Island)', '0.00', '0.00', 'active', ''),
(30, 'Dumaran', '0421', 'Dumaran', 'Biton (Island)', '0.00', '0.00', 'active', ''),
(31, 'Dumaran', '0422', 'Dumaran', 'Paly (Island)', '0.00', '0.00', 'active', ''),
(32, 'Dumaran', '0423', 'Dumaran', 'Capayas', '138.00', '5.25', 'active', ''),
(33, 'Dumaran', '0424', 'Dumaran', 'Culasian', '115.00', '4.50', 'active', ''),
(34, 'Roxas', '0300', 'Roxas', 'Barangay 1', '6.00', '1.00', 'active', ''),
(35, 'Roxas', '0301', 'Roxas', 'Barangay 2', '4.00', '1.00', 'active', ''),
(36, 'Roxas', '0302', 'Roxas', 'Barangay 3', '10.00', '1.00', 'active', ''),
(37, 'Roxas', '0303', 'Roxas', 'Barangay 4', '14.00', '1.00', 'active', ''),
(38, 'Roxas', '0304', 'Roxas', 'Bgy. Retac, Bliss', '12.00', '1.00', 'active', ''),
(39, 'Roxas', '0305', 'Roxas', 'Caramay', '80.00', '3.50', 'active', ''),
(40, 'Roxas', '0305', 'Roxas', 'Salvacion', '68.00', '3.00', 'active', ''),
(41, 'Roxas', '0305', 'Roxas', 'Nicanor Zabala', '85.00', '3.50', 'active', ''),
(42, 'Roxas', '0305', 'Roxas', 'San Miguel', '80.00', '3.30', 'active', ''),
(43, 'Roxas', '0308', 'Roxas', 'San Jose', '32.00', '1.75', 'active', ''),
(44, 'Roxas', '0308', 'Roxas', 'Malcampo', '20.00', '1.50', 'active', ''),
(45, 'Roxas', '0308', 'Roxas', 'San Dionisio', '22.00', '1.50', 'active', ''),
(46, 'Roxas', '0308', 'Roxas', 'Abaroan', '40.00', '2.00', 'active', ''),
(47, 'Roxas', '0308', 'Roxas', 'New Cuyo', '50.00', '2.50', 'active', ''),
(48, 'Roxas', '0308', 'Roxas', 'Rizal', '54.00', '2.50', 'active', ''),
(49, 'Roxas', '0308', 'Roxas', 'Tagumpay', '55.00', '2.50', 'active', ''),
(50, 'Roxas', '0308', 'Roxas', 'Magara', '68.00', '3.00', 'active', ''),
(51, 'Roxas', '0309', 'Roxas', 'Minara', '13.00', '1.25', 'active', ''),
(52, 'Roxas', '0309', 'Roxas', 'San Nicolas', '24.00', '1.50', 'active', ''),
(53, 'Roxas', '0309', 'Roxas', 'Sandoval ', '41.00', '2.00', 'active', ''),
(54, 'Roxas', '0309', 'Roxas', 'Iraan ', '50.00', '2.50', 'active', ''),
(55, 'Roxas', '0309', 'Roxas', 'Dumarao', '85.00', '3.50', 'active', ''),
(56, 'Araceli', '0307', 'Araceli', 'Poblacion ', '5.00', '1.00', 'active', ''),
(57, 'Araceli', '0307', 'Araceli', 'Tinintinan', '12.00', '1.00', 'active', ''),
(58, 'Cagayancillo', '0550', 'Cagayancillo', 'Poblacion', '8.00', '1.00', 'active', 'North Lipot,South Lipot, Wahig, Calsada, Magsaysay, Bantayan, Convento, Tacas'),
(59, 'Cagayancillo', '0550', 'Cagayancillo', 'Sta Cruz', '12.00', '1.00', 'active', ''),
(60, 'Cagayancillo', '0550', 'Cagayancillo', 'Talaga', '15.00', '1.25', 'active', ''),
(61, 'Cagayancillo', '0550', 'Cagayancillo', 'Mampio', '20.00', '1.50', 'active', ''),
(62, 'Cagayancillo', '0550', 'Cagayancillo', 'Nusa', '24.00', '1.50', 'active', ''),
(63, 'San Vicente', '0306', 'San Vicente', 'Poblacion', '6.00', '1.00', 'active', ''),
(64, 'San Vicente', '0306', 'San Vicente', 'Panindigan', '14.00', '1.00', 'active', ''),
(65, 'San Vicente', '0306', 'San Vicente', 'Macatumbalen', '22.00', '1.50', 'active', ''),
(66, 'San Vicente', '0307', 'San Vicente', 'Kemdeng', '28.00', '1.50', 'active', ''),
(67, 'San Vicente', '0307', 'San Vicente', 'New Agutaya', '26.00', '1.50', 'active', ''),
(68, 'San Vicente', '0307', 'San Vicente', 'San Isidro', '18.60', '1.00', 'active', ''),
(69, 'San Vicente', '0307', 'San Vicente', 'Alimanguan', '35.00', '2.00', 'active', ''),
(70, 'San Vicente', '0307', 'San Vicente', 'Sto Nino', '42.00', '2.00', 'active', ''),
(71, 'San Vicente', '0308', 'San Vicente', 'New Canipo', '52.00', '2.50', 'active', ''),
(72, 'Brookes Point', '0200', 'Brookes Point', 'Poblacion 1', '16.00', '1.25', 'active', ''),
(73, 'Brookes Point', '0200', 'Brookes Point', 'Poblacion 1', '16.00', '1.25', 'active', ''),
(74, 'Brookes Point', '0201', 'Brookes Point', 'Poblacion 2 & So. Balacan, Pangobilian', '20.00', '1.50', 'active', ''),
(75, 'Brookes Point', '0201', 'Brookes Point', 'So., Balacan, Moreno Subd. Pob Dist 2', '20.00', '1.50', 'active', ''),
(76, 'Brookes Point', '0202', 'Brookes Point', 'Rawland Subd. & Blessed Ville,Poblacion District 2', '21.00', '1.50', 'active', ''),
(77, 'Brookes Point', '0202', 'Brookes Point', 'Edwards Subd. & Jesmil Subdivision Poblacion 2', '21.00', '1.50', 'active', ''),
(78, 'Brookes Point', '0210', 'Brookes Point', 'Pangobilian National Hi-Way,So. Balacan , So. Aluluwang Pangobilan', '21.00', '1.50', 'active', ''),
(79, 'Brookes Point', '0210', 'Brookes Point', 'Bgy. Tubtub & Portion Of Bgy Amas', '21.00', '1.50', 'active', ''),
(80, 'Brookes Point', '0210', 'Brookes Point', 'Portion Of Bgy. Amas, Bgy. Pangobilian, & So. Paratungon', '33.00', '1.75', 'active', ''),
(81, 'Brookes Point', '0210', 'Brookes Point', 'Portion Of Paratungon & Pangobilian Proper', '30.00', '1.75', 'active', ''),
(82, 'Brookes Point', '0210', 'Brookes Point', 'So. Suring, So. Mati, So Babangon, Pangobilian', '29.00', '1.75', 'active', ''),
(83, 'Brookes Point', '0210', 'Brookes Point', 'So. Suring 2, Bgy. Mainit, Bgy.Imulnod', '28.00', '1.75', 'active', ''),
(84, 'Brookes Point', '0210', 'Brookes Point', 'Bgy. Mainit, So.Raang, Bgy Aribungos, So. Tigaplan Aribungos', '35.00', '2.00', 'active', ''),
(85, 'Brookes Point', '0210', 'Brookes Point', 'So. Baribe-Aribungos', '31.00', '1.75', 'active', ''),
(86, 'Brookes Point', '0210', 'Brookes Point', 'So. Raang & So. Cabar, Aribungos', '34.00', '1.75', 'active', ''),
(87, 'Brookes Point', '0210', 'Brookes Point', 'So. Lada, So. Suring 1, So. Pangobilian, So Camilit Ipilan', '32.00', '1.75', 'active', ''),
(88, 'Brookes Point', '0210', 'Brookes Point', 'So. Lada, Pangobilian, So. Tagusao, So. Pintasan, Barong Barong', '30.00', '1.75', 'active', ''),
(89, 'Brookes Point', '0220', 'Brookes Point', 'So.Candis, So. Malulunan, Proper Barong-Barong, So. Curanga, Proper Ipilan', '38.00', '2.00', 'active', ''),
(90, 'Brookes Point', '0220', 'Brookes Point', 'Bgy. Proper & So. Linao Ipilan', '40.00', '2.00', 'active', ''),
(91, 'Brookes Point', '0220', 'Brookes Point', 'So. Linao Ipilan, So. Pitik-Pitik, So. Limbasan, Relocation, Ipilan & Mambalot Portion', '41.00', '2.00', 'active', ''),
(92, 'Brookes Point', '0220', 'Brookes Point', 'Prk 2,3,4, 5,6 & 7, Rizal, Mambalot', '47.00', '2.25', 'active', ''),
(93, 'Brookes Point', '0220', 'Brookes Point', 'So. Lapiac Ipilan, Maasin Proper, New Panay, Prk 6, Maasin', '52.00', '2.50', 'active', ''),
(94, 'Brookes Point', '0220', 'Brookes Point', 'Proper Maasin, Purok 7 Maasin, So Abubakar Calasaguen', '53.00', '2.50', 'active', ''),
(95, 'Brookes Point', '0220', 'Brookes Point', 'Proper Calasaguen, Prk 6 & 7 Calasaguen', '62.00', '2.75', 'active', ''),
(96, 'Brookes Point', '0250', 'Brookes Point', 'So. Matangkay, So Cadjasan,Venturanza, Oring Oring Proper,So. Tagpirara, Saraza', '31.00', '1.75', 'active', ''),
(97, 'Brookes Point', '0250', 'Brookes Point', 'So. Tagpirara, Saraza, So. Tabud, Saraza, So. Tamlang Sarazaso. Jurusan, Saraza', '33.00', '1.75', 'active', ''),
(98, 'Brookes Point', '0250', 'Brookes Point', 'So. Tamlang, So Cabangaan, Sea Shore,Samariniana Proper, Tagpait Salogon', '42.00', '2.00', 'active', ''),
(99, 'Brookes Point', '0250', 'Brookes Point', 'So. Magugurang, So. Kiniurong Salogon Proper,', '45.00', '2.25', 'active', ''),
(100, 'Brookes Point', '0250', 'Brookes Point', 'So. Locon, So. Aplaya,So. Babanga, So.  Idiok, So. Bulnok, So. Pansor, Proper Malis', '56.00', '2.50', 'active', ''),
(101, 'Aborlan', '0150', 'Aborlan', 'So. Tagbarungis, Inagawan Sub-Colony, Taytayin, Inagawan Interior', '82.00', '3.50', 'active', ''),
(102, 'Aborlan', '0150', 'Aborlan', 'Inagawan Interior, Calibugan, Escalona, Alit-Alit, Parina Kamuning', '75.00', '3.25', 'active', ''),
(103, 'Aborlan', '0150', 'Aborlan', 'Marambuaya, Kamuning, Tagumpay,', '69.00', '3.00', 'active', ''),
(104, 'Aborlan', '0160', 'Aborlan', 'Isaub ( Del-At,Tagpit, Centro, Linao )', '67.00', '3.00', 'active', ''),
(105, 'Aborlan', '0160', 'Aborlan', 'Isaub (Linao, Maligaya Village ) Sagpangan ( Centro,  Maringit, Maligaya) ', '65.00', '3.00', 'active', ''),
(106, 'Aborlan', '0160', 'Aborlan', 'Sagpangan (Katuwang Tuwangan, Maligaya ) Iraan ( Mayligan, Mamonmon, Centro ) ', '61.00', '2.75', 'active', ''),
(107, 'Aborlan', '0160', 'Aborlan', 'San Juan ( Marikit, Titibawan ) Iraan Proper, Mabini, Gogognan ', '43.00', '2.25', 'active', ''),
(108, 'Aborlan', '0160', 'Aborlan', 'Mabini, Magbabadil, Barake', '62.00', '2.75', 'active', ''),
(109, 'Aborlan', '0160', 'Aborlan', 'Magbabadil ( So. Parang ) Cabigaan', '37.00', '2.00', 'active', ''),
(110, 'Aborlan', '0160', 'Aborlan', 'Apis, Cabigaan, San Juan', '69.00', '3.00', 'active', ''),
(111, 'Aborlan', '0160', 'Aborlan', 'San Juan , Old Site ', '42.00', '2.00', 'active', ''),
(112, 'Aborlan', '0160', 'Aborlan', 'Poblacion', '5.00', '1.00', 'active', ''),
(113, 'Aborlan', '0160', 'Aborlan', 'Poblacion , Tagpait', '32.00', '2.75', 'active', ''),
(114, 'Aborlan', '0161', 'Aborlan', 'Tagpait, Gogognan, Magsaysay', '61.00', '2.75', 'active', ''),
(115, 'Aborlan', '0161', 'Aborlan', 'Magsaysay, Aplaya, Tagbariri, Valderama', '51.00', '2.50', 'active', ''),
(116, 'Aborlan', '0161', 'Aborlan', 'Marimbras, Maasin Plaridel, Tigman', '67.00', '3.00', 'active', ''),
(117, 'Aborlan', '0161', 'Aborlan', 'Tigman , Plaridel', '65.00', '3.00', 'active', ''),
(118, 'Aborlan', '0161', 'Aborlan', 'Plaridel, Km81, Jose Rizal Line 6', '78.00', '3.25', 'active', ''),
(119, 'Aborlan', '0161', 'Aborlan', 'Jose Rizal, Apoc-Apoc', '110.00', '4.50', 'active', ''),
(120, 'Puerto Princesa [Main Office]', '0010', 'Puerto Princesa [Main Office]', 'Paleco Employee', '0.00', '0.00', 'active', ''),
(121, 'Puerto Princesa [Main Office]', '0100', 'Puerto Princesa [Main Office]', 'Poblacion I', '30.00', '2.00', 'active', ''),
(122, 'Puerto Princesa [Main Office]', '0101', 'Puerto Princesa [Main Office]', 'Poblacion 2', '30.00', '2.00', 'active', ''),
(123, 'Puerto Princesa [Main Office]', '0102', 'Puerto Princesa [Main Office]', 'Poblacion 3', '30.00', '2.00', 'active', ''),
(124, 'Puerto Princesa [Main Office]', '0103', 'Puerto Princesa [Main Office]', 'Poblacion 4', '30.00', '2.00', 'active', ''),
(125, 'Puerto Princesa [Main Office]', '0107', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(126, 'Puerto Princesa [Main Office]', '0200', 'Puerto Princesa [Main Office]', 'Poblacion 5', '30.00', '2.00', 'active', ''),
(127, 'Puerto Princesa [Main Office]', '0201', 'Puerto Princesa [Main Office]', 'Poblacion 6', '30.00', '2.00', 'active', ''),
(128, 'Puerto Princesa [Main Office]', '0202', 'Puerto Princesa [Main Office]', 'Poblacion 7', '30.00', '2.00', 'active', ''),
(129, 'Puerto Princesa [Main Office]', '0203', 'Puerto Princesa [Main Office]', 'Poblacion 8', '30.00', '2.00', 'active', ''),
(130, 'Puerto Princesa [Main Office]', '0204', 'Puerto Princesa [Main Office]', 'Bgy. San Isidro', '30.00', '2.00', 'active', ''),
(131, 'Puerto Princesa [Main Office]', '0207', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(132, 'Puerto Princesa [Main Office]', '0300', 'Puerto Princesa [Main Office]', 'Poblacion 9', '30.00', '2.00', 'active', ''),
(133, 'Puerto Princesa [Main Office]', '0301', 'Puerto Princesa [Main Office]', 'Poblacion 10', '30.00', '2.00', 'active', ''),
(134, 'Puerto Princesa [Main Office]', '0302', 'Puerto Princesa [Main Office]', 'Poblacion 11', '30.00', '2.00', 'active', ''),
(135, 'Puerto Princesa [Main Office]', '0303', 'Puerto Princesa [Main Office]', 'Poblacion 12', '30.00', '2.00', 'active', ''),
(136, 'Puerto Princesa [Main Office]', '0304', 'Puerto Princesa [Main Office]', 'Poblacion 13', '30.00', '2.00', 'active', ''),
(137, 'Puerto Princesa [Main Office]', '0305', 'Puerto Princesa [Main Office]', 'Poblacion 14', '30.00', '2.00', 'active', ''),
(138, 'Puerto Princesa [Main Office]', '0306', 'Puerto Princesa [Main Office]', 'Malvar / Pub Mkt', '30.00', '2.00', 'active', ''),
(139, 'Puerto Princesa [Main Office]', '0307', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(140, 'Puerto Princesa [Main Office]', '0400', 'Puerto Princesa [Main Office]', 'Poblacion 15', '30.00', '2.00', 'active', ''),
(141, 'Puerto Princesa [Main Office]', '0401', 'Puerto Princesa [Main Office]', 'Poblacion 16', '30.00', '2.00', 'active', ''),
(142, 'Puerto Princesa [Main Office]', '0402', 'Puerto Princesa [Main Office]', 'Poblacion 17', '30.00', '2.00', 'active', ''),
(143, 'Puerto Princesa [Main Office]', '0403', 'Puerto Princesa [Main Office]', 'Poblacion 18', '30.00', '2.00', 'active', ''),
(144, 'Puerto Princesa [Main Office]', '0404', 'Puerto Princesa [Main Office]', 'Poblacion 19', '30.00', '2.00', 'active', ''),
(145, 'Puerto Princesa [Main Office]', '0407', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(146, 'Puerto Princesa [Main Office]', '0500', 'Puerto Princesa [Main Office]', 'Poblacion 20', '30.00', '2.00', 'active', ''),
(147, 'Puerto Princesa [Main Office]', '0501', 'Puerto Princesa [Main Office]', 'Barrio 1', '30.00', '2.00', 'active', ''),
(148, 'Puerto Princesa [Main Office]', '0502', 'Puerto Princesa [Main Office]', 'Barrio 2', '30.00', '2.00', 'active', ''),
(149, 'Puerto Princesa [Main Office]', '0503', 'Puerto Princesa [Main Office]', 'Barrio 3', '30.00', '2.00', 'active', ''),
(150, 'Puerto Princesa [Main Office]', '0504', 'Puerto Princesa [Main Office]', 'Barrio 4', '30.00', '2.00', 'active', ''),
(151, 'Puerto Princesa [Main Office]', '0505', 'Puerto Princesa [Main Office]', 'Barrio 5', '30.00', '2.00', 'active', ''),
(152, 'Puerto Princesa [Main Office]', '0506', 'Puerto Princesa [Main Office]', 'Barrio 6', '30.00', '2.00', 'active', ''),
(153, 'Puerto Princesa [Main Office]', '0507', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(154, 'Puerto Princesa [Main Office]', '0508', 'Puerto Princesa [Main Office]', 'Bapa - Sicsican', '30.00', '2.00', 'active', ''),
(155, 'Puerto Princesa [Main Office]', '0509', 'Puerto Princesa [Main Office]', 'Sicsican Housing', '30.00', '2.00', 'active', ''),
(156, 'Puerto Princesa [Main Office]', '0510', 'Puerto Princesa [Main Office]', 'Bgy. Luzviminda', '56.40', '2.00', 'active', ''),
(157, 'Puerto Princesa [Main Office]', '0511', 'Puerto Princesa [Main Office]', 'Golden Valley Sicsican', '30.00', '2.00', 'active', ''),
(158, 'Puerto Princesa [Main Office]', '0512', 'Puerto Princesa [Main Office]', 'Bgy. Mangingisda', '79.00', '3.50', 'active', ''),
(159, 'Puerto Princesa [Main Office]', '0513', 'Puerto Princesa [Main Office]', 'Bgy. Montible', '49.40', '2.50', 'active', ''),
(160, 'Puerto Princesa [Main Office]', '0514', 'Puerto Princesa [Main Office]', 'Honda Bay, Sta. Lourdes', '48.60', '2.50', 'active', ''),
(161, 'Puerto Princesa [Main Office]', '0515', 'Puerto Princesa [Main Office]', 'Tagburos Aplaya', '30.00', '2.00', 'active', ''),
(162, 'Puerto Princesa [Main Office]', '0516', 'Puerto Princesa [Main Office]', 'Typoco, San Manuel', '30.00', '2.00', 'active', ''),
(163, 'Puerto Princesa [Main Office]', '0517', 'Puerto Princesa [Main Office]', 'Bapa-Ellen View', '30.00', '2.00', 'active', ''),
(164, 'Puerto Princesa [Main Office]', '0518', 'Puerto Princesa [Main Office]', 'Anonang Sicsican', '30.00', '2.00', 'active', ''),
(165, 'Puerto Princesa [Main Office]', '0519', 'Puerto Princesa [Main Office]', 'Visapa, Irawan', '30.00', '2.00', 'active', ''),
(166, 'Puerto Princesa [Main Office]', '0520', 'Puerto Princesa [Main Office]', 'Busngol', '30.00', '2.00', 'active', ''),
(167, 'Puerto Princesa [Main Office]', '0521', 'Puerto Princesa [Main Office]', 'Fishermans Village', '30.00', '2.00', 'active', ''),
(168, 'Puerto Princesa [Main Office]', '0522', 'Puerto Princesa [Main Office]', 'Sunrise, San Jose', '30.00', '2.00', 'active', ''),
(169, 'Puerto Princesa [Main Office]', '0523', 'Puerto Princesa [Main Office]', 'Sta Cruz/Bacungan', '73.00', '3.00', 'active', ''),
(170, 'Puerto Princesa [Main Office]', '0524', 'Puerto Princesa [Main Office]', 'Bgy. Salvacion', '91.00', '4.00', 'active', ''),
(171, 'Puerto Princesa [Main Office]', '0525', 'Puerto Princesa [Main Office]', 'Bgy. Manalo', '105.00', '4.50', 'active', ''),
(172, 'Puerto Princesa [Main Office]', '0526', 'Puerto Princesa [Main Office]', 'Bgy. Maruyugon', '109.00', '4.50', 'active', ''),
(173, 'Puerto Princesa [Main Office]', '0527', 'Puerto Princesa [Main Office]', 'Bgy. Lucbuan', '117.00', '4.50', 'active', ''),
(174, 'Puerto Princesa [Main Office]', '0528', 'Puerto Princesa [Main Office]', 'Bgy. Maoyon', '123.00', '5.00', 'active', ''),
(175, 'Puerto Princesa [Main Office]', '0529', 'Puerto Princesa [Main Office]', 'Bgy Mangingisda', '83.00', '3.50', 'active', ''),
(176, 'Puerto Princesa [Main Office]', '0530', 'Puerto Princesa [Main Office]', 'Bgy. Bahile', '101.00', '5.00', 'active', ''),
(177, 'Puerto Princesa [Main Office]', '0531', 'Puerto Princesa [Main Office]', 'Bgy. Babuyan', '127.00', '5.00', 'active', ''),
(178, 'Puerto Princesa [Main Office]', '0532', 'Puerto Princesa [Main Office]', 'Bgy. San Rafael', '133.00', '5.00', 'active', ''),
(179, 'Puerto Princesa [Main Office]', '0533', 'Puerto Princesa [Main Office]', 'Bgy. Tanabag', '128.00', '5.00', 'active', ''),
(180, 'Puerto Princesa [Main Office]', '0534', 'Puerto Princesa [Main Office]', 'Bgy. Concepcion', '147.00', '5.50', 'active', ''),
(181, 'Puerto Princesa [Main Office]', '0535', 'Puerto Princesa [Main Office]', 'Bgy. Binduyan', '155.00', '6.00', 'active', ''),
(182, 'Puerto Princesa [Main Office]', '0536', 'Puerto Princesa [Main Office]', 'Bgy. Langogan', '187.00', '7.00', 'active', ''),
(183, 'Puerto Princesa [Main Office]', '0537', 'Puerto Princesa [Main Office]', 'Bgy. Macarascas', '191.00', '7.00', 'active', ''),
(184, 'Puerto Princesa [Main Office]', '0538', 'Puerto Princesa [Main Office]', 'Bgy. Montible', '49.40', '2.50', 'active', ''),
(185, 'Puerto Princesa [Main Office]', '0539', 'Puerto Princesa [Main Office]', 'Bukana, Bgy. Iwahig', '56.00', '2.50', 'active', ''),
(186, 'Puerto Princesa [Main Office]', '0540', 'Puerto Princesa [Main Office]', 'Bgy. Napsan', '124.00', '5.00', 'active', ''),
(187, 'Puerto Princesa [Main Office]', '0701', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(188, 'Puerto Princesa [Main Office]', '0702', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(189, 'Puerto Princesa [Main Office]', '0703', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(190, 'Puerto Princesa [Main Office]', '0704', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(191, 'Puerto Princesa [Main Office]', '0705', 'Puerto Princesa [Main Office]', 'Pbf, Bigloads, Military', '30.00', '2.00', 'active', ''),
(192, 'Puerto Princesa [Main Office]', '0900', 'Puerto Princesa [Main Office]', 'Bapa', '30.00', '2.00', 'active', ''),
(193, 'Quezon', '0029', 'Quezon', 'Poblacion, Quezon', '30.00', '2.00', 'active', ''),
(194, 'Quezon', '0030', 'Quezon', 'Quezon', '30.00', '2.00', 'active', ''),
(195, 'Quezon', '0031', 'Quezon', 'Quezon', '30.00', '2.00', 'active', ''),
(196, 'Quezon', '0032', 'Quezon', 'Employees/Send To Main', '0.00', '0.00', 'active', ''),
(197, 'Rizal', '0035', 'Rizal', 'Poblacion, Rizal', '30.00', '2.00', 'active', ''),
(198, 'Rizal', '0036', 'Rizal', 'Poblacion, Rizal', '30.00', '2.00', 'active', ''),
(199, 'Rizal', '0037', 'Rizal', 'Poblacion, Rizal', '30.00', '2.00', 'active', ''),
(200, 'Rizal', '0038', 'Rizal', 'Poblacion, Rizal', '30.00', '2.00', 'active', ''),
(201, 'Narra', 'nset', 'Narra', 'Antipuluan', '17.00', '1.50', 'inactive', ''),
(202, 'Narra', 'nset', 'Narra', 'Panacan 2', '26.00', '1.47', 'inactive', ''),
(203, 'Narra', 'nset', 'Narra', 'Poblacion', '25.66', '1.46', 'inactive', ''),
(204, 'Narra', 'nset', 'Narra', 'Panacan 1', '29.00', '1.57', 'inactive', ''),
(205, 'Narra', 'nset', 'Narra', 'Elvita', '30.00', '1.75', 'inactive', ''),
(206, 'Narra', 'nset', 'Narra', 'Taritien', '35.00', '2.00', 'inactive', ''),
(207, 'Narra', 'nset', 'Narra', 'Malatgao', '35.00', '2.00', 'inactive', ''),
(208, 'Narra', 'nset', 'Narra', 'Malinao', '37.00', '2.00', 'inactive', ''),
(209, 'Narra', 'nset', 'Narra', 'Tinagong Dagat', '39.00', '2.00', 'inactive', ''),
(210, 'Narra', 'nset', 'Narra', 'Sandoval', '39.00', '2.00', 'inactive', ''),
(211, 'Narra', 'nset', 'Narra', 'Estrella Village', '41.00', '1.97', 'inactive', ''),
(212, 'Narra', 'nset', 'Narra', 'Apo-Aporawan', '45.00', '2.50', 'inactive', ''),
(213, 'Narra', 'nset', 'Narra', 'Bagong Sikat', '45.00', '2.50', 'inactive', ''),
(214, 'Narra', 'nset', 'Narra', 'Batang Batang', '51.00', '2.50', 'inactive', ''),
(215, 'Narra', 'nset', 'Narra', 'P. Urduja', '55.00', '2.50', 'inactive', ''),
(216, 'Narra', 'nset', 'Narra', 'Teresa', '55.00', '2.50', 'inactive', ''),
(217, 'Narra', 'nset', 'Narra', 'San Isidro', '57.00', '2.50', 'inactive', ''),
(218, 'Narra', 'nset', 'Narra', 'Dumanguena', '65.00', '3.00', 'inactive', ''),
(219, 'Narra', 'nset', 'Narra', 'Calatigas', '71.00', '2.97', 'inactive', ''),
(220, 'Narra', 'nset', 'Narra', 'Aramaywan', '81.00', '3.50', 'inactive', ''),
(221, 'Narra', 'nset', 'Narra', 'Tacras', '85.00', '3.50', 'inactive', ''),
(222, 'Narra', 'nset', 'Narra', 'Burirao', '89.00', '4.00', 'inactive', ''),
(223, 'Narra', 'nset', 'Narra', 'Abo-Abo', '97.00', '4.00', 'inactive', ''),
(224, 'Narra', 'nset', 'Narra', 'Ipilan', '101.00', '4.00', 'inactive', ''),
(225, 'Narra', 'nset', 'Narra', 'Isumbo', '107.00', '4.50', 'inactive', ''),
(226, 'Narra', 'nset', 'Narra', 'Panitian', '125.00', '5.00', 'inactive', ''),
(227, 'Narra', 'nset', 'Narra', 'Labog', '141.00', '5.50', 'inactive', ''),
(228, 'Rizal', '0035', 'Rizal', 'Pajo Area', '30.00', '1.00', 'active', ''),
(229, 'Rizal', '0035', 'Rizal', 'Balite ', '30.00', '1.00', 'active', ''),
(230, 'Rizal', '0035', 'Rizal', 'Purok Pagkakaisa', '60.00', '2.00', 'active', ''),
(231, 'Rizal', '0035', 'Rizal', 'Core', '40.00', '1.75', 'active', ''),
(232, 'Rizal', '0036', 'Rizal', 'Poblacion', '70.00', '2.50', 'active', ''),
(233, 'Rizal', '0036', 'Rizal', 'Mahogany', '70.00', '2.50', 'active', ''),
(234, 'Rizal', '0036', 'Rizal', 'Trucut', '60.00', '2.00', 'active', ''),
(235, 'Rizal', '0036', 'Rizal', 'Purok Pakpaklawin', '30.00', '1.00', 'active', ''),
(236, 'Rizal', '0036', 'Rizal', 'Purok Katutubo', '33.00', '1.50', 'active', ''),
(237, 'Rizal', '0036', 'Rizal', 'Ranho #1', '35.00', '1.50', 'active', ''),
(238, 'Rizal', '0036', 'Rizal', 'Sitio Malapandig', '55.00', '1.75', 'active', ''),
(239, 'Rizal', '0036', 'Rizal', 'Ranho #2', '33.00', '1.50', 'active', ''),
(240, 'Rizal', '0036', 'Rizal', 'Gomiok Punta Baja', '45.00', '1.50', 'active', ''),
(241, 'Rizal', '0036', 'Rizal', 'Ararab', '40.00', '1.50', 'active', ''),
(242, 'Rizal', '0036', 'Rizal', 'Brgy Iraan', '34.00', '1.50', 'active', ''),
(243, 'Rizal', '0036', 'Rizal', 'Purok Maligay', '30.00', '1.00', 'active', ''),
(244, 'Rizal', '0036', 'Rizal', 'Libtong', '30.00', '1.00', 'active', ''),
(245, 'Rizal', '0036', 'Rizal', 'Purok Nagkakaisa', '38.00', '1.75', 'active', ''),
(246, 'Rizal', '0036', 'Rizal', 'Salong-Song', '40.00', '1.75', 'active', ''),
(247, 'Rizal', '0037', 'Rizal', 'Pk Liway-Way', '41.00', '1.75', 'active', ''),
(248, 'Rizal', '0037', 'Rizal', 'Tribal Katutubo', '47.00', '2.00', 'active', ''),
(249, 'Rizal', '0038', 'Rizal', 'Base', '70.00', '2.50', 'active', ''),
(250, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Aborlan', '110.00', '0.00', 'active', ''),
(251, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Narra', '188.60', '0.00', 'active', ''),
(252, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Sofronio Espanola', '304.00', '0.00', 'active', ''),
(253, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Brookes Point', '360.00', '0.00', 'active', ''),
(254, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Bataraza', '474.00', '0.00', 'active', ''),
(255, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Rio Tuba', '486.00', '0.00', 'active', ''),
(256, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Buliluyan', '556.00', '0.00', 'active', ''),
(257, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Quezon', '296.00', '0.00', 'active', ''),
(258, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Rizal', '442.00', '0.00', 'active', ''),
(259, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Roxas', '270.00', '0.00', 'active', ''),
(260, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'San Vicente', '316.00', '0.00', 'active', ''),
(261, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Taytay', '386.80', '0.00', 'active', ''),
(262, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'Dumaran (Sta Teresita)', '478.00', '0.00', 'active', ''),
(263, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'El Nido', '564.00', '0.00', 'active', ''),
(264, 'Roxas', NULL, 'Roxas', 'San Vicente', '134.60', '0.00', 'active', ''),
(265, 'Roxas', NULL, 'Roxas', 'Taytay', '150.80', '0.00', 'active', ''),
(266, 'Roxas', NULL, 'Roxas', 'Dumaran', '208.00', '0.00', 'active', ''),
(267, 'Roxas', NULL, 'Roxas', 'El Nido', '294.00', '0.00', 'active', ''),
(268, 'Taytay', NULL, 'Taytay', 'Dumaran (Sta Teresita)', '110.40', '0.00', 'active', ''),
(269, 'Taytay', NULL, 'Taytay', 'El Nido', '123.20', '0.00', 'active', ''),
(270, 'Brookes Point', NULL, 'Brookes Point', 'Bataraza', '134.00', '0.00', 'active', ''),
(271, 'Brookes Point', NULL, 'Brookes Point', 'Rio Tuba', '120.00', '0.00', 'active', ''),
(272, 'Brookes Point', NULL, 'Brookes Point', 'Buliluyan', '198.00', '0.00', 'active', ''),
(273, 'Brookes Point', NULL, 'Brookes Point', 'Sofronio Espanola', '56.80', '0.00', 'active', ''),
(274, 'Brookes Point', NULL, 'Brookes Point', 'Quezon', '252.80', '0.00', 'active', ''),
(275, 'Quezon', NULL, 'Quezon', 'Rizal', '160.40', '0.00', 'active', ''),
(276, 'Quezon', NULL, 'Quezon', 'Narra', '106.60', '0.00', 'active', ''),
(277, 'Quezon', NULL, 'Quezon', 'Bataraza', '216.00', '0.00', 'active', ''),
(278, 'Narra', NULL, 'Narra', 'Aborlan', '69.20', '0.00', 'inactive', ''),
(279, 'Narra', NULL, 'Narra', 'Sofronio Espanola', '124.00', '0.00', 'inactive', ''),
(280, 'Narra', NULL, 'Narra', 'Brookes Point', '178.00', '0.00', 'inactive', ''),
(281, 'Puerto Princesa [Main Office]', NULL, 'Puerto Princesa [Main Office]', 'San Vicente, Kemding', '378.00', NULL, 'active', NULL),
(282, 'Narra', NULL, 'Narra', 'Puerto Princesa City', '180.00', '0.00', 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `area` varchar(100) NOT NULL,
  `fuel_supplier` varchar(100) NOT NULL,
  `r_approval` varchar(150) NOT NULL COMMENT 'recommending approval',
  `r_designation` varchar(100) NOT NULL COMMENT 'Recommending Approval Designation',
  `a_approval` varchar(150) NOT NULL COMMENT 'Approved By',
  `a_designation` varchar(100) NOT NULL COMMENT 'Approved By Designation',
  `b_approval` varchar(150) DEFAULT NULL,
  `b_designation` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `area`, `fuel_supplier`, `r_approval`, `r_designation`, `a_approval`, `a_designation`, `b_approval`, `b_designation`) VALUES
(1, '', 'Camaro Trading', 'Cesary E. Jaramilla', 'General Services Section Head', 'Engr. Rez L. Contrivida, PEE', 'General Manager', 'Mary Eustelia S. Bundac', 'Institutional Services Manager'),
(2, 'Puerto Princesa [Main Office]', 'Camaro Trading', 'Cesary E. Jaramilla', 'General Services Section Head', 'Engr. Rez L. Contrivida, PEE', 'General Manager', 'Shirley A. Tolentino', 'Acting Institutional Services Manager'),
(3, 'Narra', 'petron', 'Engr Tawtawan', 'Care take Care', 'Engr Tawtawan', 'Area Manager', 'Neriza Regal', 'Area Manager');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_initial` char(1) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `username` varchar(102) NOT NULL,
  `password` varchar(255) NOT NULL,
  `area` varchar(100) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `department` varchar(25) NOT NULL,
  `session_token` varchar(64) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `middle_initial`, `last_name`, `username`, `password`, `area`, `role`, `department`, `session_token`, `last_login`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Roer jay', 'G', 'Padrones', 'rg_padrones', '$2y$10$/86eCl1b.WTPIL8oUhVrTeOl3s2fbYNRoYlozALlHyz/3DrtUUj3q', 'Puerto Princesa [Main Office]', 'admin', '', 'e4f77be3a1ec723d7ed1b4652754af92b34874f196ed07a728307a8512bc29ce', '2026-01-02 11:31:18', 'active', '2025-08-18 05:23:16', '2026-01-02 03:31:18'),
(2, 'Leobert', 'C', 'Cabanting', 'lc_cabanting', '$2y$10$Le.ubsEKXWVjRwY.bEDkluZwnBWMqQDYgJZsg1s0x1UfDLHwLBHdq', 'Roxas', 'user', '', 'a416644fe7496238d1dfba8ede565a176b44229fade6900442ad8df938ffec04', '2025-08-20 14:16:25', 'active', '2025-08-18 07:05:59', '2025-10-13 06:06:19'),
(3, 'Ralph', 'B', 'Teleron', 'rb_teleron', '$2y$10$DtEmCj/oFGTOm5ifGp85ruLZLgDySGoxjOS/hRi4XjKV2sdMdkCsi', 'Aborlan', 'user', '', '2d98df38b1401227bda9a319d8bd1b0494062e2a12f3c7982819419b0be11a1b', '2025-09-30 09:00:21', 'active', '2025-08-18 07:06:43', '2025-10-13 06:06:14'),
(4, 'Cesary', 'E', 'Jaramilla', 'ce_jaramilla', '$2y$10$0.Z6meZK.Vbz50mJPWOTt.j9SQ8662Jbrh2rTiWjQtGfJY.7Rak2y', 'Puerto Princesa [Main Office]', 'admin', '', 'a6cf9c9f73deea30f7df8736cc6b9e2433bbbf8271d66dab2bd5168ff2027d1b', '2026-01-02 11:15:29', 'active', '2025-08-26 02:10:48', '2026-01-02 03:15:29');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `plate_no` varchar(20) NOT NULL COMMENT 'plate number',
  `brand` varchar(50) NOT NULL COMMENT 'Vehicle manufacturer (e.g., Toyota, Honda)',
  `model` varchar(50) NOT NULL COMMENT 'Model name (e.g., Hilux, Civic)',
  `km_per_liter` decimal(5,2) DEFAULT NULL,
  `idling_rate` decimal(5,2) DEFAULT NULL,
  `category` enum('4-wheels','2-wheels','trucks') NOT NULL,
  `ownership` enum('coop-owned','private') DEFAULT NULL COMMENT '''Private'', ''Personal''',
  `status` enum('active','inactive') DEFAULT NULL COMMENT '''Active'', ''Inactive''',
  `remarks` text NOT NULL COMMENT 'Additional notes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `plate_no`, `brand`, `model`, `km_per_liter`, `idling_rate`, `category`, `ownership`, `status`, `remarks`) VALUES
(1, 'VRC-755', 'Toyota', 'Hilux', '8.20', NULL, '4-wheels', 'coop-owned', 'active', 'ISD'),
(2, 'VRC-238', 'Toyota', 'Fortuner', '8.00', NULL, '4-wheels', 'coop-owned', 'active', 'OGM'),
(3, 'VAA-3615', 'Mitsubishi', 'Montero', '10.00', NULL, '4-wheels', 'coop-owned', 'active', ''),
(4, 'FC-0990', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(5, 'FA-09874', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(6, 'VG-9126', 'Honda', 'CFT 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(7, 'GC-65276', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(8, 'FC-0210', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(9, 'FC-1102', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(10, 'VUA 479', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(11, 'VG-9120', 'Honda', 'CFT 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(12, 'VUA 475', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(13, 'GD-48615', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(14, '327 VOE', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(15, '328 VOE', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(16, '314 VUA', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(17, '476 VUA', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(18, '816 VUA', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(19, '561 VIQ', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(20, 'GC-65374', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(21, 'GC-65376', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(22, '331 VOE ', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(23, '477 VUA', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(24, '311 VUA', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(25, '333 VOE ', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(26, 'VJ-6098', 'Honda', 'CFT 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(27, 'VAD-969 ', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(28, '324 VOE', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(29, '315 VUA', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(30, 'FC 0275', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(31, 'VIQ-557', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(32, '325 VOE', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(33, '313 VUA', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(34, 'VH-2103', 'Honda', 'TMX 155', '30.00', NULL, '2-wheels', 'coop-owned', 'active', 'with sidecar'),
(35, 'DA-74270', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(36, 'FC-0213', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(37, 'VIQ-564', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(38, 'DA-74276', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(39, 'DA-74622', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(40, '317 VUA', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(41, '310 VUA', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(42, 'DA-74631', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(43, 'FC 1268', 'Honda', 'TMX 155', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(44, 'DA-74284', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(45, 'VD-7428', 'Honda', 'CFT 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(46, 'DA-76606', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(47, 'VB-8467', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(48, 'VB-8461 ', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(49, 'VB-8462 ', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(50, 'VJ-6807', 'Honda', 'CFT 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(51, 'VG-9015', 'Honda', 'CFT 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(52, 'VG-9016', 'Honda', 'CFT 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(53, 'XT-5154', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(54, 'DA-74614 ', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(55, 'DA-74562 ', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(56, 'GC-65456', 'Honda', 'TMX 150', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(57, 'DC-88515', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(58, 'DC-88789', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(59, 'GC-63501 ', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(60, 'DA-74273 ', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(61, 'GD-48621 ', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(62, 'GE-93282 ', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(63, 'FC-0207', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(64, 'FC 0211', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(65, 'VCT 704', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(66, 'VDB-356', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(67, 'VDB-354', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(68, 'VIQ-558/ ', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(69, 'VIQ-566', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(70, 'FC-1101', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(71, '485 VUA', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(72, '481 VUA', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(73, '480 VUA', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(74, '306 VUA', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(75, '307 VUA', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(76, '308 VUA', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(77, '312 VUA', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(78, '309 VUA', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(79, 'GE-93280', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(80, 'FC-1151', 'Honda', 'XRM 125 FI', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(81, 'XT-5163', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', ''),
(82, 'TST-1234', 'Suzuki', 'Raider150', '30.00', NULL, '2-wheels', 'private', 'inactive', 'For Testing Only'),
(83, 'CAU-2723', 'Isuzu', 'Elf Manlift Truck 2006', '7.00', NULL, 'trucks', 'coop-owned', 'active', 'Aborlan'),
(84, 'VAA 1046', 'MITSUBISHI', 'L300 2016', '15.40', NULL, '4-wheels', 'coop-owned', 'active', 'Aborlan'),
(85, 'CBJ 1574', 'Isuzu', 'Drill truck 2006', '8.09', NULL, 'trucks', 'coop-owned', 'active', 'ACOD'),
(86, 'CAT 2402', 'Mitsubishi', 'Manlift Truck 2006', '10.00', NULL, 'trucks', 'coop-owned', 'active', 'ACOD'),
(87, 'AAQ 4666', 'Hino', 'Boom Truck 2014', '6.50', NULL, 'trucks', 'coop-owned', 'active', 'ACOD'),
(88, 'ZF 9734', 'Hino', 'Dropside Truck 2017', '8.10', NULL, 'trucks', 'coop-owned', 'active', 'ACOD'),
(89, 'VAB 2179', 'Foton', 'Boom Truck 2018', '6.50', NULL, 'trucks', 'coop-owned', 'active', 'ACOD'),
(90, 'VRC 752', 'Toyota', 'Innova 2010', '8.00', NULL, '4-wheels', 'coop-owned', 'active', 'ISD'),
(91, 'PQE 191', 'Mitsubishi', 'L300 2011', '15.40', NULL, '4-wheels', 'coop-owned', 'active', 'ACOD'),
(92, 'PQE 832', 'Mitsubishi', 'L300 2011', '15.40', NULL, '4-wheels', 'coop-owned', 'active', 'ACOD'),
(93, 'UQM 401', 'Isuzu', 'Dropside  Truck 2012', '6.50', NULL, 'trucks', 'coop-owned', 'active', 'ACOD'),
(94, 'AAC 7761', 'Nissan', 'Navara 2013', '10.00', NULL, '4-wheels', 'coop-owned', 'active', 'TSD'),
(95, 'AAC 9132', 'Mitsubishi', 'L300 2013', '15.40', NULL, '4-wheels', 'coop-owned', 'active', 'TSD'),
(96, 'VJD-581', 'Honda', 'XRM', '30.00', NULL, '2-wheels', 'private', 'active', 'Peace Worker'),
(97, 'VC-7887', 'Honda', 'XRM', '30.00', NULL, '2-wheels', 'private', 'active', 'Peace Worker'),
(98, '487-VBP', 'Honda', 'Beat', '30.00', NULL, '2-wheels', 'private', 'active', 'Contractual'),
(99, '040304', 'Suzuki', 'Crossover', '30.00', NULL, '2-wheels', 'private', 'active', 'Contractual'),
(100, '624 VZN', 'Honda', 'Beat', '30.00', NULL, '2-wheels', 'private', 'active', 'Contractual'),
(101, 'CBJ 1298', 'Isuzu Manlift', 'Elf 2006', '8.10', '0.60', 'trucks', 'coop-owned', 'active', 'NSO'),
(102, 'YEY 846', 'Fuso', 'Canter 2007', '10.60', '4.00', 'trucks', 'coop-owned', 'active', 'NSO'),
(103, 'AAC 8964', 'Mitsubishi', 'Canter 2014', '11.60', '4.00', 'trucks', 'coop-owned', 'active', 'NSO'),
(104, 'VAB 2178', 'Foton', 'Crane Truck 2018', '6.80', '5.00', 'trucks', 'coop-owned', 'active', ''),
(105, 'VAB 5026', 'Mitsubishi', 'L200 2023', '13.64', NULL, '4-wheels', 'coop-owned', 'active', 'NSO'),
(106, 'VAB 8056', 'Mitsubishi', 'Triton Pick up 2024', '13.00', NULL, '4-wheels', 'coop-owned', 'active', 'NSO'),
(107, 'CAR 1357', 'Mitsubishi', 'Canter w/ Boom 2006', '10.60', '4.00', 'trucks', 'coop-owned', 'active', 'QEO'),
(108, 'VAA 1867', 'Mitsubishi', 'L 300 2017', '15.40', NULL, '4-wheels', 'coop-owned', 'active', 'QEO'),
(109, 'VAC 2734', 'Fuso', 'Drop Side Truck w/ Boom 2024', '11.60', '5.00', 'trucks', 'coop-owned', 'active', 'QEO'),
(110, 'AAC 9134', 'Mitsubishi', 'L300 2014', '15.40', NULL, '4-wheels', 'coop-owned', 'active', 'REO'),
(111, 'CAR 7413', 'Isuzu', 'Elf Drill Truck 2016', '8.10', '6.00', 'trucks', 'coop-owned', 'active', 'BPSO'),
(112, 'UJQ 394', 'Mitsubishi', 'Canter 2013', '11.60', '4.00', 'trucks', 'coop-owned', 'active', 'BPSO'),
(113, 'VAA 1856', 'Foton', 'Crane Truck 2016', '6.80', '5.00', 'trucks', 'coop-owned', 'active', 'BPSO'),
(114, 'AAD 2430', 'Mitsubishi', 'L300 2014', '15.40', NULL, '4-wheels', 'coop-owned', 'active', 'BPSO'),
(115, 'DDM 3429', 'Mitsubishi', 'L 300 2016', '15.40', NULL, '4-wheels', 'coop-owned', 'active', 'BPSO'),
(116, 'VAB 1335', 'Mitsubishi', 'L 300 2021', '15.40', NULL, '4-wheels', 'coop-owned', 'active', 'BPSO'),
(117, 'VAB 5028', 'Mitsubishi', 'L 200 2023', '13.64', NULL, '4-wheels', 'coop-owned', 'active', 'BPSO'),
(118, 'VAB 8059', 'Mitsubishi', 'Triton Pick up 2024', '13.00', NULL, '4-wheels', 'coop-owned', 'active', 'BPSO'),
(119, 'FC 1150', 'Honda', 'XRM FI 125', '30.00', NULL, '2-wheels', 'coop-owned', 'active', 'BPSO'),
(120, '4131 VA', 'Honda', 'XRM 125', '30.00', NULL, '2-wheels', 'coop-owned', 'inactive', 'BPSO'),
(121, 'CAU 3158', 'Isuzu', 'Elf Manlift 2006', '8.10', '6.00', 'trucks', 'coop-owned', 'active', 'RSO'),
(122, 'ZEG 124', 'Isuzu', 'Boom Truck 2012', '3.33', '3.78', 'trucks', 'coop-owned', 'active', 'REO'),
(123, '7887 VC', 'Honda', 'Beat', '30.00', NULL, '2-wheels', 'private', 'active', 'Peace Worker'),
(124, '9016 VG', 'Honda', 'XRM', '30.00', NULL, '2-wheels', 'coop-owned', 'active', 'METER READER'),
(125, 'MAX 12', 'Mitsubishi', 'Montero', '0.08', NULL, '4-wheels', 'private', 'active', 'Fuel Allowance');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `fuel_items`
--
ALTER TABLE `fuel_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fuel_requests`
--
ALTER TABLE `fuel_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gas_slips`
--
ALTER TABLE `gas_slips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gas_slip_custom_id` (`gas_slip_id`);

--
-- Indexes for table `gas_slip_routes`
--
ALTER TABLE `gas_slip_routes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_gsr_gas_slip` (`gas_slip_id`);

--
-- Indexes for table `routes`
--
ALTER TABLE `routes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `routes_backup`
--
ALTER TABLE `routes_backup`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `fuel_items`
--
ALTER TABLE `fuel_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `fuel_requests`
--
ALTER TABLE `fuel_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `gas_slips`
--
ALTER TABLE `gas_slips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `gas_slip_routes`
--
ALTER TABLE `gas_slip_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `routes`
--
ALTER TABLE `routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=283;

--
-- AUTO_INCREMENT for table `routes_backup`
--
ALTER TABLE `routes_backup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=283;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `gas_slip_routes`
--
ALTER TABLE `gas_slip_routes`
  ADD CONSTRAINT `fk_gsr_gas_slip` FOREIGN KEY (`gas_slip_id`) REFERENCES `gas_slips` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
