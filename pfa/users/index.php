<?php
/** 
 * Postfix Admin 
 * 
 * LICENSE 
 * This source file is subject to the GPL license that is bundled with  
 * this package in the file LICENSE.TXT. 
 * 
 * Further details on the project are available at http://postfixadmin.sf.net 
 * 
 * @version $Id: index.php 1558 2013-11-10 15:57:32Z christian_boltz $ 
 * @license GNU GPL v2 or later. 
 * 
 * File: index.php
 * Redirects potential users to login.php
 * Template File: -none-
 *
 * Template Variables:
 *
 * -none-
 *
 * Form POST \ GET Variables:
 *
 * -none-
 */
header ("Location: login.php");
exit;

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
