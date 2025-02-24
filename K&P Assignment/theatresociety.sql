-- phpMyAdmin SQL Dump
-- version 5.1.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 05, 2024 at 10:41 PM
-- Server version: 10.4.19-MariaDB
-- PHP Version: 7.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `theatresociety`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` varchar(12) NOT NULL,
  `admin_name` varchar(30) NOT NULL,
  `password` varchar(30) NOT NULL,
  `conpassword` varchar(30) NOT NULL,
  `created_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `admin_name`, `password`, `conpassword`, `created_time`) VALUES
('A100', 'Edwin', 'Admin$1234', 'Admin$1234', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `booking`
--

CREATE TABLE `booking` (
  `booking_id` varchar(12) NOT NULL,
  `student_id` varchar(12) DEFAULT NULL,
  `ticket_id` varchar(12) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `booking`
--

INSERT INTO `booking` (`booking_id`, `student_id`, `ticket_id`, `date`, `quantity`) VALUES
('B4638', 'S2000', 'T1001', '2024-05-05', 5),
('B5724', 'S2000', 'T1005', '2024-05-01', 5);

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` varchar(12) NOT NULL,
  `event_pic` varchar(30) DEFAULT NULL,
  `event_name` varchar(30) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `event_time` varchar(20) DEFAULT NULL,
  `location` varchar(30) DEFAULT NULL,
  `description` varchar(999) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `event_status` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `event_pic`, `event_name`, `event_date`, `event_time`, `location`, `description`, `price`, `event_status`) VALUES
('E1001', 'E1.jpg', 'Shakespearean Showcase', '2024-09-12', '08:00 AM', 'TARUMT DTAR', 'Step into the enchanting world of William Shakespeare with our \"Shakespearean Showcase\" event. Experience the timeless beauty of his works as talented actors bring his iconic characters to life on stage. From tragic tales of love and betrayal to uproarious comedies filled with wit and mischief, this event celebrates the enduring legacy of the Bard and his unparalleled contributions to literature and theatre.', '50.00', 1),
('E1002', 'E2.jpg', 'Broadway Revue', '2024-08-22', '08:00 AM', 'TARUMT DTAR', 'Step into the enchanting world of William Shakespeare with our \"Get ready for a dazzling display of song, dance, and spectacle at our \"Broadway Revue\" event. Journey through the vibrant streets of Broadway as we pay homage to the greatest musicals of all time. From classic show tunes to contemporary hits, this event promises to captivate audiences with its electrifying performances and show-stopping numbers.', '70.00', 1),
('E1003', 'E3.jpg', 'Playwrights Corner', '2024-10-09', '09:00 AM', 'TARUMT DTAR', 'Celebrate the art of storytelling with our \"Playwrights Corner\" event, where emerging playwrights showcase their original works for the stage. Experience a diverse range of theatrical genres and themes as these talented writers explore the complexities of the human experience through compelling narratives and thought-provoking dialogue. Be among the first to witness the birth of tomorrows theatrical masterpieces.', '70.00', 1),
('E1004', 'E4.jpg', 'Shakespearean Showcase', '2024-08-05', '09:00 AM', 'TARUMT DTAR', 'Step into the enchanting world of William Shakespeare with our \"Shakespearean Showcase\" event. Experience the timeless beauty of his works as talented actors bring his iconic characters to life on stage. From tragic tales of love and betrayal to uproarious comedies filled with wit and mischief, this event celebrates the enduring legacy of the Bard and his unparalleled contributions to literature and theatre.', '100.00', 1),
('E1005', 'E5.jpg', 'Improv Extravaganza', '2024-07-17', '08:00 AM', 'TARUMT DTAR', 'Dive into the rich tapestry of classical theatre with our \"Classical Theatre Classics\" event. From Greek tragedies to Elizabethan dramas, this event celebrates the enduring power and relevance of timeless theatrical masterpieces. Experience the intensity of tragic heroes, the wit of comic foils, and the depth of human emotion as we transport you to worlds both ancient and timeless.', '100.00', 1),
('E1006', '6637eeb578cac.jpg', 'Behind the Curtain', '2024-09-04', '12:35', 'TARUMT DTAR', 'Dive into the rich tapestry of classical theatre with our \"Go behind the scenes of the theatrical world with our \"Behind the Curtain\" event. Gain insight into the art of stagecraft as we explore the various aspects of theatre production, from set design and costume creation to lighting and sound engineering. Meet the unsung heroes who work tirelessly behind the scenes to bring productions to life and discover the magic that happens offstage.', '100.00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `payment_id` varchar(12) NOT NULL,
  `booking_id` varchar(12) DEFAULT NULL,
  `tax` decimal(6,2) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `payment_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`payment_id`, `booking_id`, `tax`, `total_amount`, `status`, `payment_date`) VALUES
('P0244', 'B4638', '0.06', '265.00', 'pending', NULL),
('P2740', 'B5724', '0.06', '530.00', 'Paid', '2024-05-01');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `student_id` varchar(12) NOT NULL,
  `studName` varchar(30) NOT NULL,
  `studEmail` varchar(30) NOT NULL,
  `studpassword` varchar(30) NOT NULL,
  `constudpassword` varchar(30) NOT NULL,
  `contact` varchar(30) DEFAULT NULL,
  `birthday` varchar(30) DEFAULT NULL,
  `gender` varchar(30) DEFAULT NULL,
  `pic` varchar(99) DEFAULT NULL,
  `created_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`student_id`, `studName`, `studEmail`, `studpassword`, `constudpassword`, `contact`, `birthday`, `gender`, `pic`, `created_time`) VALUES
('S1005', 'jiajin', 'siajinsheng0419@gmail.com', 'Qwer$1234', 'Qwer$1234', '0121234567', '2000-06-29', 'Male', 'jj.jpg', '2024-04-29 01:49:22'),
('S2000', 'JS', 'siajinsheng0419@gmail.com', 'Qwer$1234', 'Qwer$1234', '0121234556', '2024-04-18', 'Male', '66321bf7b6fb7.jpg', '2024-04-28 09:41:23');

-- --------------------------------------------------------

--
-- Table structure for table `ticket`
--

CREATE TABLE `ticket` (
  `ticket_id` varchar(12) NOT NULL,
  `event_id` varchar(12) DEFAULT NULL,
  `maxTicket` int(11) DEFAULT NULL,
  `ticketAvailable` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `ticket`
--

INSERT INTO `ticket` (`ticket_id`, `event_id`, `maxTicket`, `ticketAvailable`) VALUES
('T1001', 'E1001', 100, 41),
('T1002', 'E1002', 100, 95),
('T1003', 'E1003', 100, 100),
('T1004', 'E1004', 100, 100),
('T1005', 'E1005', 100, 95),
('T1006', 'E1006', 200, 200);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `ticket`
--
ALTER TABLE `ticket`
  ADD PRIMARY KEY (`ticket_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking`
--
ALTER TABLE `booking`
  ADD CONSTRAINT `booking_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `ticket` (`ticket_id`),
  ADD CONSTRAINT `booking_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`);

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`booking_id`);

--
-- Constraints for table `ticket`
--
ALTER TABLE `ticket`
  ADD CONSTRAINT `ticket_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
