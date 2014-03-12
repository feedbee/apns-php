<?php
namespace ApnsPHP\Log;
/**
 * @file
 * ApnsPHP\Log\Interface interface definition.
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://code.google.com/p/apns-php/wiki/License
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to aldo.armiento@gmail.com so we can send you a copy immediately.
 *
 * @author (C) 2010 Aldo Armiento (aldo.armiento@gmail.com)
 */

/**
 * The Log Interface.
 *
 * Implement the Log Interface and pass the object instance to all
 * ApnsPHP\Abstract based class to use a custom log.
 */
interface LogInterface
{
	/**
	 * Logs a message.
	 *
	 * @param string $sMessage The message.
	 */
	public function log($sMessage);
}