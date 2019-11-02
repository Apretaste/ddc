-- phpMyAdmin SQL Dump
-- version 4.6.6deb5
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 01-11-2019 a las 01:00:45
-- Versión del servidor: 5.7.26-0ubuntu0.18.10.1
-- Versión de PHP: 7.3.7-1+ubuntu18.10.1+deb.sury.org+1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `apretaste`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ddc_articles`
--

CREATE TABLE `ddc_articles` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `link` varchar(255) NOT NULL,
  `pubDate` datetime NOT NULL,
  `author` varchar(40) NOT NULL,
  `description` text,
  `category_id` tinyint(4) NOT NULL,
  `tags` varchar(255) NOT NULL,
  `intro` text,
  `image` varchar(40) DEFAULT NULL,
  `content` text NOT NULL,
  `comments` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ddc_categories`
--

CREATE TABLE `ddc_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(20) NOT NULL,
  `url` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `ddc_categories`
--

INSERT INTO `ddc_categories` (`id`, `name`, `url`) VALUES
(1, 'Cuba', 'http://fetchrss.com/rss/5d7945108a93f8666f8b45675d7a44858a93f83a5e8b4569.xml'),
(2, 'Internacional', 'http://fetchrss.com/rss/5d7945108a93f8666f8b45675dbb9bc88a93f89b7e8b4567.xml'),
(3, 'Derechos Humanos', 'http://fetchrss.com/rss/5d7945108a93f8666f8b45675dbb9bdd8a93f8c0018b4567.xml'),
(4, 'Cultura', 'http://fetchrss.com/rss/5d7945108a93f8666f8b45675dbb9bf98a93f836018b4567.xml'),
(5, 'Ocio', 'http://fetchrss.com/rss/5d7945108a93f8666f8b45675dbb9c048a93f86a018b4567.xml'),
(6, 'Deportes', 'http://fetchrss.com/rss/5d7945108a93f8666f8b45675dbb9c1a8a93f8f7018b4568.xml'),
(7, 'De Leer', 'http://fetchrss.com/rss/5d7945108a93f8666f8b45675dbb9c288a93f8c0018b4568.xml');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `ddc_articles`
--
ALTER TABLE `ddc_articles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `title` (`title`);

--
-- Indices de la tabla `ddc_categories`
--
ALTER TABLE `ddc_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `ddc_articles`
--
ALTER TABLE `ddc_articles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
--
-- AUTO_INCREMENT de la tabla `ddc_categories`
--
ALTER TABLE `ddc_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
