-- phpMyAdmin SQL Dump
-- version 3.4.8
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Mar 29, 2012 at 04:24 PM
-- Server version: 5.5.13
-- PHP Version: 5.3.8

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `kolab_wap`
--

-- --------------------------------------------------------

--
-- Table structure for table `group_types`
--

CREATE TABLE IF NOT EXISTS `group_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` text NOT NULL,
  `name` varchar(256) NOT NULL,
  `description` text NOT NULL,
  `attributes` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `group_types`
--

INSERT INTO `group_types` (`id`, `key`, `name`, `description`, `attributes`) VALUES
(1, 'kolab', 'Kolab Distribution Group', 'A Kolab Distribution Group (with mail address)', 's:187:"{"auto_form_fields":{"mail":{"data":["cn"]}},"fields":{"objectclass":["top","groupofuniquenames","kolabgroupofuniquenames"]},"form_fields":{"cn":[],"uniquemember":{"type":"multiselect"}}}";'),
(2, 'posix', 'POSIX Group', 'A UNIX POSIX Group', 's:166:"{"auto_form_fields":{"gidnumber":[]},"fields":{"objectclass":["top","groupofuniquenames","posixgroup"]},"form_fields":{"cn":[],"uniquemember":{"type":"multiselect"}}}";');

-- --------------------------------------------------------

--
-- Table structure for table `options`
--

CREATE TABLE IF NOT EXISTS `options` (
  `attribute` varchar(128) NOT NULL,
  `option_values` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `options`
--

INSERT INTO `options` (`attribute`, `option_values`) VALUES
('preferredlanguage', '["aa_DJ","aa_ER","aa_ET","af_ZA","am_ET","an_ES","ar_AE","ar_BH","ar_DZ","ar_EG","ar_IN","ar_IQ","ar_JO","ar_KW","ar_LB","ar_LY","ar_MA","ar_OM","ar_QA","ar_SA","ar_SD","ar_SY","ar_TN","ar_YE","as_IN","az_AZ","be_BY","bg_BG","bn_BD","bn_IN","bokmal","br_FR","bs_BA","byn_ER","C","ca_AD","ca_ES","ca_FR","ca_IT","catalan","croatian","csb_PL","cs_CZ","cy_GB","czech","da_DK","danish","dansk","de_AT","de_BE","de_CH","de_DE","de_LU","deutsch","dutch","dz_BT","eesti","el_CY","el_GR","en_AU","en_BW","en_CA","en_DK","en_GB","en_HK","en_IE","en_IN","en_NZ","en_PH","en_SG","en_US","en_ZA","en_ZW","es_AR","es_BO","es_CL","es_CO","es_CR","es_DO","es_EC","es_ES","es_GT","es_HN","es_MX","es_NI","es_PA","es_PE","es_PR","es_PY","es_SV","estonian","es_US","es_UY","es_VE","et_EE","eu_ES","fa_IR","fi_FI","finnish","fo_FO","fr_BE","fr_CA","fr_CH","french","fr_FR","fr_LU","fy_NL","ga_IE","galego","galician","gd_GB","german","gez_ER","gez_ET","gl_ES","greek","gu_IN","gv_GB","hebrew","he_IL","hi_IN","hr_HR","hrvatski","hsb_DE","hu_HU","hungarian","hy_AM","icelandic","id_ID","is_IS","italian","it_CH","it_IT","iw_IL","ja_JP","japanese","ka_GE","kk_KZ","kl_GL","km_KH","kn_IN","ko_KR","korean","ku_TR","kw_GB","ky_KG","lg_UG","lithuanian","lo_LA","lt_LT","lv_LV","mai_IN","mg_MG","mi_NZ","mk_MK","ml_IN","mn_MN","mr_IN","ms_MY","mt_MT","nb_NO","ne_NP","nl_BE","nl_NL","nn_NO","no_NO","norwegian","nr_ZA","nso_ZA","nynorsk","oc_FR","om_ET","om_KE","or_IN","pa_IN","pa_PK","pl_PL","polish","portuguese","POSIX","pt_BR","pt_PT","romanian","ro_RO","ru_RU","russian","ru_UA","rw_RW","se_NO","sid_ET","si_LK","sk_SK","slovak","slovene","slovenian","sl_SI","so_DJ","so_ET","so_KE","so_SO","spanish","sq_AL","sr_CS","sr_ME","sr_RS","ss_ZA","st_ZA","sv_FI","sv_SE","swedish","ta_IN","te_IN","tg_TJ","thai","th_TH","ti_ER","ti_ET","tig_ER","tl_PH","tn_ZA","tr_CY","tr_TR","ts_ZA","tt_RU","turkish","uk_UA","ur_PK","uz_UZ","ve_ZA","vi_VN","wa_BE","xh_ZA","yi_US","zh_CN","zh_HK","zh_SG","zh_TW","zu_ZA"]');

-- --------------------------------------------------------

--
-- Table structure for table `user_types`
--

CREATE TABLE IF NOT EXISTS `user_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` text NOT NULL,
  `name` varchar(256) NOT NULL,
  `description` text NOT NULL,
  `attributes` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `user_types`
--

INSERT INTO `user_types` (`id`, `key`, `name`, `description`, `attributes`) VALUES
(1, 'kolab', 'Kolab User', 'A Kolab User', '{"auto_form_fields":{"cn":{"data":["givenname","sn"]},"displayname":{"data":["givenname","sn"]},"mail":{"data":["givenname","preferredlanguage","sn"]},"mailalternateaddress":{"data":["givenname","preferredlanguage","sn"]},"mailhost":[],"uid":{"data":["givenname","preferredlanguage","sn"]},"userpassword":[]},"form_fields":{"initials":[],"givenname":[],"mailalternateaddress":{"type":"list"},"nsrole":{"type":"list","autocomplete":true},"preferredlanguage":{"type":"select","values":["en_US","de_DE","de_CH","en_GB","fr_FR"]},"sn":[],"title":[]},"fields":{"objectclass":["top","inetorgperson","organizationalperson","person","kolabinetorgperson"]}}'),
(2, 'posix', 'POSIX User', 'A POSIX user (with a home directory and shell access)', '{"auto_form_fields":{"cn":{"data":["givenname","sn"]},"displayname":{"data":["givenname","sn"]},"gidnumber":[],"homedirectory":{"data":["givenname","sn"]},"uid":{"data":["givenname","sn"]},"uidnumber":[],"userpassword":[]},"form_fields":{"givenname":[],"initials":[],"preferredlanguage":{"type":"select","values_source":"<uri>"},"shell":{"type":"select","values":["/bin/bash","/usr/bin/git-shell","/sbin/nologin"]},"sn":[],"title":[]},"fields":{"objectclass":["top","inetorgperson","organizationalperson","person","posixaccount"]}}');

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
