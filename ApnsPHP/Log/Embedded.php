<?php
/**
 * @file
 * ApnsPHP\Log\Embedded class definition.
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
 * @author (C) 2014 Valera Leontyev (feedbee@gmail.com)
 */

namespace ApnsPHP\Log;

/**
 * A simple logger.
 *
 * This simple logger implements the Log Interface and is the default logger for
 * all ApnsPHP\Abstract based class.
 *
 * This simple logger outputs The Message to standard output prefixed with date,
 * service name (ApplePushNotificationService) and Process ID (PID).
 */
class Embedded implements LogInterface
{
	/**
	 * Logs a message.
	 *
	 * @param string $sMessage The message.
	 */
	public function log($sMessage)
	{
		printf("%s ApnsPHP[%d]: %s\n",
			date('r'), getmypid(), trim($sMessage)
		);
	}
}