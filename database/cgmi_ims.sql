-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 29, 2026 at 02:46 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cgmi_ims`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(1, 'Structural Materials'),
(2, 'Fasteners & Hardware'),
(3, 'Plumbing Supplies'),
(4, 'Electrical Supplies'),
(5, 'Paint & Finishing'),
(6, 'Tools & Equipment'),
(7, 'Adhesives & Sealants'),
(8, 'Insulation & Weatherproofing');

-- --------------------------------------------------------

--
-- Table structure for table `checker_records`
--

CREATE TABLE `checker_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `record_type` enum('incoming','outgoing') NOT NULL,
  `record_source` enum('pos','manual') NOT NULL DEFAULT 'manual',
  `inventory_item_id` int(10) UNSIGNED DEFAULT NULL,
  `sale_id` int(10) UNSIGNED DEFAULT NULL,
  `sale_item_id` int(10) UNSIGNED DEFAULT NULL,
  `sku` varchar(60) DEFAULT NULL,
  `item_name` varchar(160) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(40) DEFAULT NULL,
  `supplier` varchar(160) DEFAULT NULL,
  `purpose` varchar(80) DEFAULT NULL,
  `check_status` varchar(40) NOT NULL DEFAULT 'pending',
  `approval_status` varchar(40) NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `confirmation_notes` text DEFAULT NULL,
  `checked_by` int(10) UNSIGNED DEFAULT NULL,
  `confirmed_by` int(10) UNSIGNED DEFAULT NULL,
  `checked_at` datetime NOT NULL DEFAULT current_timestamp(),
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incident_reports`
--

CREATE TABLE `incident_reports` (
  `reportId` int(11) NOT NULL,
  `reportNo` varchar(25) NOT NULL,
  `reportedBy` int(10) UNSIGNED NOT NULL,
  `reportTitle` varchar(150) NOT NULL,
  `reportNarrative` text NOT NULL,
  `reportImg` varchar(255) NOT NULL,
  `reportStatus` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updateAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `report_category` enum('Inventory Discrepancy','Damaged Item','Missing Item','Incorrect Item','Stock Issue','IoT Sensor Issue','Shelf Issue','Hardware Issue','System Issue','Safety Incident','Unauthorized Activity','Other') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incident_reports`
--

INSERT INTO `incident_reports` (`reportId`, `reportNo`, `reportedBy`, `reportTitle`, `reportNarrative`, `reportImg`, `reportStatus`, `createdAt`, `updateAt`, `report_category`) VALUES
(7, 'INC-20260926-0007', 3, 'Test 1', 'Test Sample 123 1924390yweq', 'uploads/incidents/incident-incident-photo-f5459ea7.jpg', 'rejected', '2026-09-26 09:56:18', '2026-09-26 09:56:29', 'Inventory Discrepancy');

-- --------------------------------------------------------

--
-- Table structure for table `incident_reviews`
--

CREATE TABLE `incident_reviews` (
  `reviewId` int(11) NOT NULL,
  `reportId` int(11) NOT NULL,
  `reviewedBy` int(10) UNSIGNED NOT NULL,
  `reviewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `review_notes` text DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incident_reviews`
--

INSERT INTO `incident_reviews` (`reviewId`, `reportId`, `reviewedBy`, `reviewed_at`, `review_notes`, `isActive`) VALUES
(7, 7, 1, '2026-09-26 09:56:29', 'Rejected by admin', 0);

-- --------------------------------------------------------

--
-- Table structure for table `ingoing`
--

CREATE TABLE `ingoing` (
  `ingoing_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `ingoing_notes` text DEFAULT 'Match verified',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `modified_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ingoing_status` enum('Pending','Checked') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ingoing`
--

INSERT INTO `ingoing` (`ingoing_id`, `supplier_id`, `ingoing_notes`, `created_by`, `created_date`, `modified_by`, `modified_date`, `ingoing_status`) VALUES
(40, 4, NULL, 7, '2026-09-28 13:57:44', 3, '2026-09-28 13:58:03', 'Checked');

-- --------------------------------------------------------

--
-- Table structure for table `ingoing_items`
--

CREATE TABLE `ingoing_items` (
  `ingoing_item_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `ingoing_id` int(11) NOT NULL,
  `ingoing_quantity` int(11) NOT NULL,
  `ingoing_item_condition` enum('Accepted / Good','Damaged','Incomplete / Missing') NOT NULL,
  `ingoing_item_notes` text DEFAULT NULL,
  `ingoing_item_status` enum('Pending','Confirmed','Rejected') NOT NULL DEFAULT 'Pending',
  `confirmed_by` int(10) UNSIGNED DEFAULT NULL,
  `confirmed_date` timestamp NULL DEFAULT NULL,
  `confirmation_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ingoing_items`
--

INSERT INTO `ingoing_items` (`ingoing_item_id`, `item_id`, `ingoing_id`, `ingoing_quantity`, `ingoing_item_condition`, `ingoing_item_notes`, `ingoing_item_status`, `confirmed_by`, `confirmed_date`, `confirmation_notes`) VALUES
(56, 38, 40, 100, 'Accepted / Good', 'Received in good condition — matches delivery.', 'Confirmed', 3, '2026-09-28 13:58:03', 'Confirmed and added to warehouse stock.');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `item_id` int(11) NOT NULL,
  `sub_category_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `sku` varchar(255) NOT NULL,
  `item_img` varchar(255) DEFAULT NULL,
  `reorder_level` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `modified_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_weight_kg` decimal(10,2) DEFAULT NULL,
  `shelf_tracking` enum('shelves','not_shelves') NOT NULL DEFAULT 'not_shelves'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`item_id`, `sub_category_id`, `unit_id`, `item_name`, `sku`, `item_img`, `reorder_level`, `is_active`, `created_by`, `created_date`, `modified_by`, `modified_date`, `unit_price`, `unit_weight_kg`, `shelf_tracking`) VALUES
(21, 7, 2, 'Tex Screws', 'ITM-0001', NULL, 0, 1, 1, '2026-08-29 08:39:20', 3, '2026-09-12 07:00:07', 450.00, NULL, 'not_shelves'),
(22, 8, 2, 'Rivets', 'ITM-0002', NULL, 0, 1, 1, '2026-08-29 08:46:41', 3, '2026-09-12 07:00:00', 450.00, NULL, 'not_shelves'),
(23, 3, 7, 'Portland Cement', 'ITM-0003', NULL, 0, 1, 1, '2026-08-29 08:54:02', 1, '2026-08-29 08:54:02', 450.00, NULL, 'not_shelves'),
(24, 17, 1, 'ROYU WH 1-GANG 1-WAY SWITCH', 'ITM-0004', NULL, 0, 1, 1, '2026-08-29 08:56:06', 1, '2026-08-29 08:56:06', 51.00, NULL, 'not_shelves'),
(25, 17, 1, 'ROYU WH 3-GANG 1-WAY SWITCH', 'ITM-0005', NULL, 0, 1, 1, '2026-08-29 08:57:30', 1, '2026-08-29 08:57:30', 114.00, NULL, 'not_shelves'),
(26, 17, 1, 'ROYU WH 2-GANG 1-WAY SWITCH', 'ITM-0006', NULL, 0, 1, 1, '2026-08-29 08:59:16', 1, '2026-08-29 08:59:16', 82.00, NULL, 'not_shelves'),
(27, 17, 1, 'ROYU WD 1-GANG 1-WAY SWITCH', 'ITM-0007', NULL, 0, 1, 1, '2026-08-29 08:59:44', 1, '2026-08-29 08:59:44', 64.00, NULL, 'not_shelves'),
(28, 17, 1, 'ROYU WD 2-GANG 1-WAY SWITCH', 'ITM-0008', NULL, 0, 1, 1, '2026-08-29 09:00:55', 1, '2026-08-29 09:00:55', 105.00, NULL, 'not_shelves'),
(29, 17, 1, 'ROYU WD 3-GANG 1-WAY SWITCH', 'ITM-0009', NULL, 0, 1, 1, '2026-08-29 09:01:43', 1, '2026-08-29 09:01:43', 145.00, NULL, 'not_shelves'),
(30, 17, 1, 'ROYU WH 1-GANG OUTLET', 'ITM-0010', NULL, 0, 1, 1, '2026-08-29 09:06:28', 1, '2026-08-29 09:06:28', 52.00, NULL, 'not_shelves'),
(31, 17, 1, 'ROYU WH 2-GANG OUTLET', 'ITM-0011', NULL, 0, 1, 1, '2026-08-29 09:08:15', 1, '2026-08-29 09:08:15', 84.00, NULL, 'not_shelves'),
(32, 17, 1, 'ROYU WH 3-GANG OUTLET', 'ITM-0012', NULL, 0, 1, 1, '2026-08-29 09:10:04', 1, '2026-08-29 09:10:04', 116.00, NULL, 'not_shelves'),
(33, 17, 1, 'ROYU WD 1-GANG OUTLET', 'ITM-0013', NULL, 0, 1, 1, '2026-08-29 09:10:32', 1, '2026-08-29 09:10:32', 63.00, NULL, 'not_shelves'),
(34, 17, 1, 'ROYU WD 3-GANG OUTLET', 'ITM-0014', NULL, 0, 1, 1, '2026-08-29 09:11:20', 1, '2026-08-29 09:15:23', 140.00, NULL, 'not_shelves'),
(35, 20, 1, 'FIREFLY LED BULB 5W DL E27', 'ITM-0015', NULL, 0, 1, 1, '2026-08-29 09:11:59', 1, '2026-08-29 09:18:49', 62.00, NULL, 'not_shelves'),
(36, 20, 1, 'ECOLUM LED BULB 9W DL E27', 'ITM-0016', NULL, 0, 1, 1, '2026-08-29 09:22:16', 9, '2026-09-28 13:58:40', 66.00, NULL, 'not_shelves'),
(37, 3, 14, '1231231231', 'ITM-0017', NULL, 0, 0, 1, '2026-09-26 09:43:14', 1, '2026-09-29 12:25:26', 123213.00, NULL, 'not_shelves'),
(38, 15, 1, 'Faucet (Blue)', 'ITM-0018', NULL, 0, 1, 3, '2026-09-28 13:56:45', 3, '2026-09-28 13:56:45', 100.00, NULL, 'not_shelves');

-- --------------------------------------------------------

--
-- Table structure for table `outgoing`
--

CREATE TABLE `outgoing` (
  `outgoing_id` int(10) UNSIGNED NOT NULL,
  `record_source` enum('Manual','POS') NOT NULL,
  `purpose` enum('Customer Purchase','Damaged Item Removal','For Replacement','Store Replenishment','IoT Shelves Replenishment','Emergency Release') NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `checked_by` int(10) UNSIGNED DEFAULT NULL,
  `checked_date` timestamp NULL DEFAULT NULL,
  `status` enum('Pending','Checked') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `outgoing_items`
--

CREATE TABLE `outgoing_items` (
  `outgoing_item_id` int(10) UNSIGNED NOT NULL,
  `outgoing_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit` varchar(40) DEFAULT NULL,
  `outgoing_notes` text DEFAULT NULL,
  `outgoing_item_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `source_location` enum('Warehouse','Store') NOT NULL DEFAULT 'Warehouse',
  `confirmed_by` int(10) UNSIGNED DEFAULT NULL,
  `confirmed_date` datetime DEFAULT NULL,
  `confirmed_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `return_request`
--

CREATE TABLE `return_request` (
  `return_request_id` int(11) NOT NULL,
  `sale_item_id` int(11) DEFAULT NULL,
  `return_type` enum('Refund','Replacement') NOT NULL,
  `return_quantity` int(11) NOT NULL,
  `return_money` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Confirmed','Rejected') NOT NULL DEFAULT 'Pending',
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `outgoing_item_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale`
--

CREATE TABLE `sale` (
  `sale_id` int(11) NOT NULL,
  `sale_number` varchar(20) NOT NULL,
  `cashier_user_id` int(10) UNSIGNED NOT NULL,
  `payment_method` enum('Cash','Online Bank Transfer') NOT NULL DEFAULT 'Cash',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cash_tendered` decimal(10,2) DEFAULT NULL,
  `change_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fulfillment_type` enum('Pick-up','Delivery') NOT NULL DEFAULT 'Pick-up',
  `receipt_img` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_item`
--
-- Error reading structure for table cgmi_ims.sale_item: #1932 - Table &#039;cgmi_ims.sale_item&#039; doesn&#039;t exist in engine
-- Error reading data for table cgmi_ims.sale_item: #1064 - You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near &#039;FROM `cgmi_ims`.`sale_item`&#039; at line 1

-- --------------------------------------------------------

--
-- Table structure for table `shelves`
--

CREATE TABLE `shelves` (
  `shelf_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `shelf_name` varchar(150) NOT NULL,
  `shelf_location` enum('Top Layer','Middle Layer','Bottom Layer') NOT NULL,
  `shelf_status` enum('Unused','Occupied','Maintenance') DEFAULT 'Unused',
  `shelf_quantity` int(10) UNSIGNED DEFAULT NULL,
  `max_weight` decimal(10,2) DEFAULT NULL,
  `min_weight` decimal(10,2) DEFAULT NULL,
  `low_stock` tinyint(1) DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `modified_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shelves`
--

INSERT INTO `shelves` (`shelf_id`, `item_id`, `shelf_name`, `shelf_location`, `shelf_status`, `shelf_quantity`, `max_weight`, `min_weight`, `low_stock`, `created_by`, `created_date`, `modified_by`, `modified_date`) VALUES
(7, NULL, 'asdasdas', 'Bottom Layer', 'Unused', 0, NULL, NULL, 0, 1, '2026-09-27 12:52:00', 1, '2026-09-27 12:52:00'),
(8, NULL, 'asdasd', 'Middle Layer', 'Unused', 0, NULL, NULL, 0, 1, '2026-09-27 14:03:14', 1, '2026-09-27 14:03:14'),
(9, NULL, 'asdasdasdas', 'Top Layer', 'Unused', 0, NULL, NULL, 0, 1, '2026-09-27 14:03:20', 1, '2026-09-27 14:03:20'),
(10, NULL, 'asdasda', 'Middle Layer', 'Maintenance', 0, NULL, NULL, 0, 1, '2026-09-27 14:03:25', 1, '2026-09-27 14:03:25'),
(11, NULL, 'asdasda', 'Middle Layer', 'Unused', 0, NULL, NULL, 0, 1, '2026-09-27 14:03:32', 1, '2026-09-27 14:03:32');

--
-- Triggers `shelves`
--
DELIMITER $$
CREATE TRIGGER `trg_shelves_qty_status_bi` BEFORE INSERT ON `shelves` FOR EACH ROW BEGIN
    IF NEW.item_id IS NULL THEN
        
        SET NEW.shelf_quantity = 0;
        IF NEW.shelf_status = 'Occupied' THEN
            SET NEW.shelf_status = 'Unused';
        END IF;
    ELSEIF NEW.shelf_quantity IS NOT NULL AND NEW.shelf_quantity > 0 THEN
        
        SET NEW.shelf_status = 'Occupied';
    ELSEIF NEW.shelf_status IN ('Unused', 'Maintenance') THEN
        
        SET NEW.shelf_quantity = 0;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_shelves_qty_status_bu` BEFORE UPDATE ON `shelves` FOR EACH ROW BEGIN
    IF NEW.item_id IS NULL THEN
        
        SET NEW.shelf_quantity = 0;
        IF NEW.shelf_status = 'Occupied' THEN
            SET NEW.shelf_status = 'Unused';
        END IF;
    ELSEIF NEW.shelf_quantity IS NOT NULL AND NEW.shelf_quantity > 0 THEN
        
        SET NEW.shelf_status = 'Occupied';
    ELSEIF NEW.shelf_status IN ('Unused', 'Maintenance') THEN
        
        SET NEW.shelf_quantity = 0;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `store_items`
--

CREATE TABLE `store_items` (
  `store_item_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `store_item_quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_items`
--

INSERT INTO `store_items` (`store_item_id`, `item_id`, `store_item_quantity`) VALUES
(60, 37, 0),
(67, 36, 10),
(68, 23, 10),
(74, 38, 0);

-- --------------------------------------------------------

--
-- Table structure for table `sub_categories`
--

CREATE TABLE `sub_categories` (
  `sub_category_id` int(11) NOT NULL,
  `sub_category_name` varchar(150) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sub_categories`
--

INSERT INTO `sub_categories` (`sub_category_id`, `sub_category_name`, `category_id`) VALUES
(1, 'Lumber & Wood', 1),
(2, 'Steel & Metal', 1),
(3, 'Concrete & Masonry', 1),
(4, 'Roofing Materials', 1),
(5, 'Framing Supplies', 1),
(6, 'Bolts & Nuts', 2),
(7, 'Screws & Nails', 2),
(8, 'Anchors & Inserts', 2),
(9, 'Hinges & Brackets', 2),
(10, 'Washers & Clips', 2),
(11, 'Pipes & Fittings', 3),
(12, 'Valves & Controls', 3),
(13, 'Water Heaters', 3),
(14, 'Drain & Sewer', 3),
(15, 'Faucets & Fixtures', 3),
(16, 'Wiring & Cables', 4),
(17, 'Switches & Outlets', 4),
(18, 'Circuit Breakers', 4),
(19, 'Conduits & Raceways', 4),
(20, 'Lighting Fixtures', 4),
(21, 'Interior Paint', 5),
(22, 'Exterior Paint', 5),
(23, 'Primers & Sealers', 5),
(24, 'Stains & Varnishes', 5),
(25, 'Brushes & Rollers', 5),
(26, 'Hand Tools', 6),
(27, 'Power Tools', 6),
(28, 'Measuring Tools', 6),
(29, 'Safety Equipment', 6),
(30, 'Cutting Tools', 6),
(31, 'Construction Adhesives', 7),
(32, 'Caulks & Sealants', 7),
(33, 'Epoxy & Resins', 7),
(34, 'Tapes', 7),
(35, 'Grouts & Mortars', 7),
(36, 'Fiberglass Insulation', 8),
(37, 'Foam Insulation', 8),
(38, 'Weatherstripping', 8),
(39, 'House Wrap', 8),
(40, 'Vapor Barriers', 8);

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `supplierId` int(11) NOT NULL,
  `supplierName` varchar(50) NOT NULL,
  `supplierAddress` varchar(255) DEFAULT NULL,
  `supplierNumber` varchar(20) DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`supplierId`, `supplierName`, `supplierAddress`, `supplierNumber`, `isActive`) VALUES
(1, 'PrimeBuild Hardware', 'National Highway, Calamba, Laguna', '09171234567', 1),
(2, 'Metro Construction Supply', 'Brgy. Real, Calamba, Laguna', '09182345678', 1),
(3, 'SolidCore Materials', 'Brgy. Canlubang, Calamba, Laguna', '09293456789', 1),
(4, 'BuildRight Trading', 'Brgy. Halang, Calamba, Laguna', '09304567890', 1),
(5, 'Lakeside Hardware Supply', 'Brgy. Bucal, Calamba, Laguna', '09415678901', 1),
(6, 'ProTools Industrial Supply', 'Santa Rosa, Laguna', '09526789012', 1),
(7, 'Golden Hammer Hardware', 'Cabuyao, Laguna', '09637890123', 1),
(8, 'Reliable Construction Supply', 'Biñan, Laguna', '09748901234', 1),
(9, 'EverStrong Materials Trading', 'San Pedro, Laguna', '09859012345', 1),
(10, 'TopGrade Hardware Center', 'Los Baños, Laguna', '09960123456', 1);

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `unit_id` int(11) NOT NULL,
  `unit_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`unit_id`, `unit_name`) VALUES
(1, 'pcs'),
(2, 'box'),
(3, 'kg'),
(4, 'liter'),
(5, 'meter'),
(6, 'roll'),
(7, 'sack'),
(8, 'gallon'),
(9, 'set'),
(10, 'pair'),
(11, 'sheet'),
(12, 'bag'),
(13, 'can'),
(14, 'bundle');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super-admin','admin','inventory','checker','cashier') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `first_name` varchar(150) NOT NULL,
  `last_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `is_active`, `failed_attempts`, `locked_until`, `last_login_at`, `created_at`, `updated_at`, `first_name`, `last_name`) VALUES
(1, 'admin', '$2y$12$pmkYmEA6i0UKwA39.KPiYenLgC/GLsK8doom0BHYkG58OZy3xqdBK', 'admin', 1, 0, NULL, '2026-09-29 20:25:17', '2026-04-10 08:11:16', '2026-09-29 12:25:17', 'MJ', 'Hita'),
(3, 'inventory', '$2y$12$8z9xGVDCwTAm9fj1YAuY5OHC.llP5If6ipWNvRS4BqUnKkomt8JYS', 'inventory', 1, 0, NULL, '2026-09-29 20:35:02', '2026-04-10 08:11:16', '2026-09-29 12:35:02', 'Kenneth', 'Sayson'),
(5, 'Mark', '$2y$10$jjcsSLsbnmoukHWmJ.eoSOUU/CdaLKiOUz99r.A3yrScnWu3gG0D6', 'admin', 1, 0, NULL, '2026-04-16 20:34:16', '2026-04-10 15:24:25', '2026-09-28 13:14:29', '', ''),
(6, 'Ken', '$2y$10$zMujiL1IGgLR0XIy89pVh.XFM/1PH3dTXsI/N8UMH76etGzP5N4Li', 'admin', 1, 0, NULL, '2026-04-11 16:55:39', '2026-04-11 08:50:09', '2026-09-27 13:37:32', '', ''),
(7, 'Checker01', '$2y$10$eLKViD0qyHV5xVLBoQUmL.N5F4b7vSkH6kJFZ6PlWzWhwk90/aBTy', 'checker', 1, 0, NULL, '2026-09-28 21:57:21', '2026-05-09 06:07:08', '2026-09-28 13:57:21', 'Reyn', 'Apiado'),
(8, 'Cashier', '$2y$10$9d8sv2ss3Uhbwm/PvEU4w.8K4jxBzlCszhnKxEhPi50Tj1tACY2ba', 'cashier', 1, 0, NULL, '2026-09-27 22:10:38', '2026-08-17 09:43:39', '2026-09-27 14:10:38', '', ''),
(9, 'super-admin', '$2y$10$eajgwp/2yLYaMoyfctjBV.vNMSD6Y0qq0xJD9RNYxL3ECISXAvgSu', 'super-admin', 1, 0, NULL, '2026-09-29 20:46:22', '2026-09-28 11:54:08', '2026-09-29 12:46:22', 'Albert', 'Einstein');

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_items`
--

CREATE TABLE `warehouse_items` (
  `warehouse_item_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `warehouse_item_quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warehouse_items`
--

INSERT INTO `warehouse_items` (`warehouse_item_id`, `item_id`, `warehouse_item_quantity`) VALUES
(64, 37, 0),
(71, 36, 50),
(72, 23, 10),
(78, 38, 100);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `checker_records`
--
ALTER TABLE `checker_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_checker_outgoing_sale_item` (`record_type`,`sale_item_id`),
  ADD KEY `idx_checker_type_date` (`record_type`,`checked_at`),
  ADD KEY `idx_checker_item` (`inventory_item_id`),
  ADD KEY `idx_checker_sale` (`sale_id`,`sale_item_id`);

--
-- Indexes for table `incident_reports`
--
ALTER TABLE `incident_reports`
  ADD PRIMARY KEY (`reportId`),
  ADD KEY `fk_report_user` (`reportedBy`);

--
-- Indexes for table `incident_reviews`
--
ALTER TABLE `incident_reviews`
  ADD PRIMARY KEY (`reviewId`),
  ADD KEY `fk_incident_report` (`reportId`),
  ADD KEY `fk_reviewed_user` (`reviewedBy`);

--
-- Indexes for table `ingoing`
--
ALTER TABLE `ingoing`
  ADD PRIMARY KEY (`ingoing_id`),
  ADD KEY `fk_ingoing_to_supplier` (`supplier_id`),
  ADD KEY `fk_ingoing_created_by` (`created_by`),
  ADD KEY `fk_ingoing_modified_by` (`modified_by`);

--
-- Indexes for table `ingoing_items`
--
ALTER TABLE `ingoing_items`
  ADD PRIMARY KEY (`ingoing_item_id`),
  ADD KEY `fk_ingoing_items_to_inventory_items` (`item_id`),
  ADD KEY `fk_ingoing_items_to_ingoing` (`ingoing_id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_inventory_items_to_sub_categories` (`sub_category_id`),
  ADD KEY `fk_inventory_items_to_units` (`unit_id`),
  ADD KEY `fk_inventory_item_created_by` (`created_by`),
  ADD KEY `fk_inventory_item_to_users` (`modified_by`);

--
-- Indexes for table `outgoing`
--
ALTER TABLE `outgoing`
  ADD PRIMARY KEY (`outgoing_id`),
  ADD KEY `fk_outgoing_created_by` (`created_by`),
  ADD KEY `fk_outgoing_checked_by` (`checked_by`);

--
-- Indexes for table `outgoing_items`
--
ALTER TABLE `outgoing_items`
  ADD PRIMARY KEY (`outgoing_item_id`),
  ADD KEY `fk_outgoing_items_outgoing` (`outgoing_id`),
  ADD KEY `fk_outgoing_items_item` (`item_id`);

--
-- Indexes for table `return_request`
--
ALTER TABLE `return_request`
  ADD PRIMARY KEY (`return_request_id`),
  ADD KEY `fk_return_request_to_sale_item` (`sale_item_id`),
  ADD KEY `fk_return_created_by_users` (`created_by`),
  ADD KEY `fk_return_approved_by_users` (`approved_by`),
  ADD KEY `fk_return_request_to_outgoing_item` (`outgoing_item_id`);

--
-- Indexes for table `sale`
--
ALTER TABLE `sale`
  ADD PRIMARY KEY (`sale_id`),
  ADD KEY `fk_sale_to_users` (`cashier_user_id`),
  ADD KEY `fk_sale_created_by_users` (`created_by`);

--
-- Indexes for table `shelves`
--
ALTER TABLE `shelves`
  ADD PRIMARY KEY (`shelf_id`),
  ADD KEY `fk_shelves_to_inventory_item` (`item_id`),
  ADD KEY `fk_shelves_created_by` (`created_by`),
  ADD KEY `fk_shelves_modified_by` (`modified_by`);

--
-- Indexes for table `store_items`
--
ALTER TABLE `store_items`
  ADD PRIMARY KEY (`store_item_id`),
  ADD UNIQUE KEY `item_id` (`item_id`);

--
-- Indexes for table `sub_categories`
--
ALTER TABLE `sub_categories`
  ADD PRIMARY KEY (`sub_category_id`),
  ADD KEY `fk_sub_categories_to_categories` (`category_id`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`supplierId`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`unit_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `uq_users_username` (`username`);

--
-- Indexes for table `warehouse_items`
--
ALTER TABLE `warehouse_items`
  ADD PRIMARY KEY (`warehouse_item_id`),
  ADD UNIQUE KEY `item_id` (`item_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `checker_records`
--
ALTER TABLE `checker_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incident_reports`
--
ALTER TABLE `incident_reports`
  MODIFY `reportId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `incident_reviews`
--
ALTER TABLE `incident_reviews`
  MODIFY `reviewId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `ingoing`
--
ALTER TABLE `ingoing`
  MODIFY `ingoing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `ingoing_items`
--
ALTER TABLE `ingoing_items`
  MODIFY `ingoing_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `outgoing`
--
ALTER TABLE `outgoing`
  MODIFY `outgoing_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `outgoing_items`
--
ALTER TABLE `outgoing_items`
  MODIFY `outgoing_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `return_request`
--
ALTER TABLE `return_request`
  MODIFY `return_request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sale`
--
ALTER TABLE `sale`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `shelves`
--
ALTER TABLE `shelves`
  MODIFY `shelf_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `store_items`
--
ALTER TABLE `store_items`
  MODIFY `store_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `sub_categories`
--
ALTER TABLE `sub_categories`
  MODIFY `sub_category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `supplier`
--
ALTER TABLE `supplier`
  MODIFY `supplierId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `unit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `warehouse_items`
--
ALTER TABLE `warehouse_items`
  MODIFY `warehouse_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `incident_reports`
--
ALTER TABLE `incident_reports`
  ADD CONSTRAINT `fk_report_user` FOREIGN KEY (`reportedBy`) REFERENCES `users` (`id`);

--
-- Constraints for table `incident_reviews`
--
ALTER TABLE `incident_reviews`
  ADD CONSTRAINT `fk_incident_report` FOREIGN KEY (`reportId`) REFERENCES `incident_reports` (`reportId`),
  ADD CONSTRAINT `fk_reviewed_user` FOREIGN KEY (`reviewedBy`) REFERENCES `users` (`id`);

--
-- Constraints for table `ingoing`
--
ALTER TABLE `ingoing`
  ADD CONSTRAINT `fk_ingoing_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_ingoing_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_ingoing_to_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplierId`);

--
-- Constraints for table `ingoing_items`
--
ALTER TABLE `ingoing_items`
  ADD CONSTRAINT `fk_ingoing_items_to_ingoing` FOREIGN KEY (`ingoing_id`) REFERENCES `ingoing` (`ingoing_id`),
  ADD CONSTRAINT `fk_ingoing_items_to_inventory_items` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`);

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `fk_inventory_item_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_inventory_item_to_users` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_inventory_items_to_sub_categories` FOREIGN KEY (`sub_category_id`) REFERENCES `sub_categories` (`sub_category_id`),
  ADD CONSTRAINT `fk_inventory_items_to_units` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`);

--
-- Constraints for table `outgoing`
--
ALTER TABLE `outgoing`
  ADD CONSTRAINT `fk_outgoing_checked_by` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_outgoing_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `outgoing_items`
--
ALTER TABLE `outgoing_items`
  ADD CONSTRAINT `fk_outgoing_items_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_outgoing_items_outgoing` FOREIGN KEY (`outgoing_id`) REFERENCES `outgoing` (`outgoing_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `return_request`
--
ALTER TABLE `return_request`
  ADD CONSTRAINT `fk_return_approved_by_users` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_return_created_by_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_return_request_to_outgoing_item` FOREIGN KEY (`outgoing_item_id`) REFERENCES `outgoing_items` (`outgoing_item_id`),
  ADD CONSTRAINT `fk_return_request_to_sale_item` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_item` (`sale_item_id`);

--
-- Constraints for table `sale`
--
ALTER TABLE `sale`
  ADD CONSTRAINT `fk_sale_created_by_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_sale_to_users` FOREIGN KEY (`cashier_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `shelves`
--
ALTER TABLE `shelves`
  ADD CONSTRAINT `fk_shelves_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_shelves_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_shelves_to_inventory_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`);

--
-- Constraints for table `store_items`
--
ALTER TABLE `store_items`
  ADD CONSTRAINT `fk_store_items_to_inventory` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`);

--
-- Constraints for table `sub_categories`
--
ALTER TABLE `sub_categories`
  ADD CONSTRAINT `fk_sub_categories_to_categories` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Constraints for table `warehouse_items`
--
ALTER TABLE `warehouse_items`
  ADD CONSTRAINT `fk_warehouse_items_to_inventory` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
