<?php
/**
 * @file
 * ApnsPHP\Feedback class definition.
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

namespace ApnsPHP;

/**
 * The Feedback Service client.
 *
 * Apple Push Notification Service includes a feedback service that APNs continually
 * updates with a per-application list of devices for which there were failed-delivery
 * attempts. Providers should periodically query the feedback service to get the
 * list of device tokens for their applications, each of which is identified by
 * its topic. Then, after verifying that the application hasn’t recently been re-registered
 * on the identified devices, a provider should stop sending notifications to these
 * devices.
 *
 * @see http://tinyurl.com/ApplePushNotificationFeedback
 */
class Feedback extends AbstractClass
{
	/**
	 * @var integer Timestamp binary size in bytes.
	 */
	const TIME_BINARY_SIZE = 4;
	/**
	 * @var integer Token length binary size in bytes.
	 */
	const TOKEN_LENGTH_BINARY_SIZE = 2;

	/**
	 * @var array Feedback URLs environments.
	 */
	protected $_aServiceURLs = array(
		'ssl://feedback.push.apple.com:2196', // Production environment
		'ssl://feedback.sandbox.push.apple.com:2196' // Sandbox environment
	);

	/**
	 * @var array Feedback container.
	 */
	protected $_aFeedback;

	/**
	 * Receives feedback tuples from Apple Push Notification Service feedback.
	 *
	 * Every tuple (array) contains:
	 *  - timestamp indicating when the APNs determined that the application
	 *     no longer exists on the device. This value represents the seconds since
	 *     1970, anchored to UTC. You should use the timestamp to determine if the
	 *     application on the device re-registered with your service since the moment
	 *     the device token was recorded on the feedback service. If it hasn’t,
	 *     you should cease sending push notifications to the device.
	 *  - tokenLength The length of the device token (usually 32 bytes).
	 *  - deviceToken The device token.
	 *
	 * @return array Array of feedback tuples (array).
	 */
	public function receive()
	{
		$nFeedbackTupleLen = self::TIME_BINARY_SIZE + self::TOKEN_LENGTH_BINARY_SIZE + self::DEVICE_BINARY_SIZE;

		$this->_aFeedback = array();
		$sBuffer = '';
		while (!feof($this->_hSocket)) {
			$this->_log('INFO: Reading...');
			$sBuffer .= $sCurrBuffer = fread($this->_hSocket, 8192);
			$nCurrBufferLen = strlen($sCurrBuffer);
			if ($nCurrBufferLen > 0) {
				$this->_log("INFO: {$nCurrBufferLen} bytes read.");
			}
			unset($sCurrBuffer, $nCurrBufferLen);

			$nBufferLen = strlen($sBuffer);
			if ($nBufferLen >= $nFeedbackTupleLen) {
				$nFeedbackTuples = floor($nBufferLen / $nFeedbackTupleLen);
				for ($i = 0; $i < $nFeedbackTuples; $i++) {
					$sFeedbackTuple = substr($sBuffer, 0, $nFeedbackTupleLen);
					$sBuffer = substr($sBuffer, $nFeedbackTupleLen);
					$this->_aFeedback[] = $aFeedback = $this->_parseBinaryTuple($sFeedbackTuple);
					$this->_log(sprintf("INFO: New feedback tuple: timestamp=%d (%s), tokenLength=%d, deviceToken=%s.",
						$aFeedback['timestamp'], date('Y-m-d H:i:s', $aFeedback['timestamp']),
						$aFeedback['tokenLength'], $aFeedback['deviceToken']
					));
					unset($aFeedback);
				}
			}

			$read = array($this->_hSocket);
			$null = NULL;
			$nChangedStreams = stream_select($read, $null, $null, 0, $this->_nSocketSelectTimeout);
			if ($nChangedStreams === false) {
				$this->_log('WARNING: Unable to wait for a stream availability.');
				break;
			}
		}
		return $this->_aFeedback;
	}

	/**
	 * Parses binary tuples.
	 *
	 * @param  string $sBinaryTuple A binary tuple to parse.
	 * @return array Array with timestamp, tokenLength and deviceToken keys.
	 */
	protected function _parseBinaryTuple($sBinaryTuple)
	{
		return unpack('Ntimestamp/ntokenLength/H*deviceToken', $sBinaryTuple);
	}
}