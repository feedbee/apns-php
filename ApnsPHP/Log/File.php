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
 */

namespace ApnsPHP\Log;

class File implements LogInterface
{
	/**
	 * @var string
	 */
	protected $filePath;

	/**
	 * @param string|null $filePath
	 */
	public function __construct($filePath = null)
	{
		if ($filePath) {
			$this->setFile($filePath);
		}
	}

	/**
	 * Logs a message.
	 *
	 * @param  string $sMessage The message.
	 * @throws \ApnsPHP\Log\Exception
	 */
	public function log($sMessage)
	{
		if (!$this->filePath) {
			throw new Exception('File not specified');
		}
		$dateTime = new \DateTime();
		$message = sprintf("Date: %s - Message: %s\n", $dateTime->format(\DateTime::ISO8601), $sMessage);
		file_put_contents($this->filePath, $message, FILE_APPEND);
	}

	/**
	 * @param  string $file
	 * @throws Exception
	 */
	public function setFile($file)
	{
		if (!is_file($file)) {
			throw new Exception('File not exists');
		}
		if (!is_writable($file)) {
			throw new Exception('File not writable');
		}
		$this->filePath = $file;
	}
}