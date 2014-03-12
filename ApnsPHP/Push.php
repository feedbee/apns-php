<?php
/**
 * @file
 * \ApnsPHP\Push class definition.
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
 * The Push Notification Provider.
 *
 * The class manages a message queue and sends notifications payload to Apple Push
 * Notification Service.
 */
class Push extends AbstractClass
{
	/**
	 * @var integer Payload command.
	 */
	const COMMAND_PUSH = 2;

	/**
	 * @var integer Error-response packet size.
	 */
	const ERROR_RESPONSE_SIZE = 6;

	/**
	 * @var integer Error-response command code.
	 */
	const ERROR_RESPONSE_COMMAND = 8;

	/**
	 * @var integer Status code for internal error (not Apple).
	 */
	const STATUS_CODE_INTERNAL_ERROR = 999;

	/**
	 * @var array Error-response messages.
	 */
	protected $_aErrorResponseMessages = array(
		0   => 'No errors encountered',
		1   => 'Processing error',
		2   => 'Missing device token',
		3   => 'Missing topic',
		4   => 'Missing payload',
		5   => 'Invalid token size',
		6   => 'Invalid topic size',
		7   => 'Invalid payload size',
		8   => 'Invalid token',
		self::STATUS_CODE_INTERNAL_ERROR => 'Internal error'
	);

	/**
	 * @var integer Send retry times.
	 */
	protected $_nSendRetryTimes = 3;

	/**
	 * @var array Service URLs environments.
	 */
	protected $_aServiceURLs = array(
		'ssl://gateway.push.apple.com:2195', // Production environment
		'ssl://gateway.sandbox.push.apple.com:2195' // Sandbox environment
	);

	/**
	 * @var array Message queue.
	 */
	protected $_aMessageQueue = array();

	/**
	 * @var array Error container.
	 */
	protected $_aErrors = array();

	/**
	 * Set the send retry times value.
	 *
	 * If the client is unable to send a payload to to the server retries at least
	 * for this value. The default send retry times is 3.
	 *
	 * @param integer $nRetryTimes Send retry times.
	 */
	public function setSendRetryTimes($nRetryTimes)
	{
		$this->_nSendRetryTimes = (int)$nRetryTimes;
	}

	/**
	 * Get the send retry time value.
	 *
	 * @return integer Send retry times.
	 */
	public function getSendRetryTimes()
	{
		return $this->_nSendRetryTimes;
	}

	/**
	 * Adds a message to the message queue.
	 *
	 * @param \ApnsPHP\Message $message The message.
	 */
	public function add(Message $message)
	{
		$sMessagePayload = $message->getPayload();
		$nRecipients = $message->getRecipientsNumber();

		$nMessageQueueLen = count($this->_aMessageQueue);
		for ($i = 0; $i < $nRecipients; $i++) {
			$nMessageID = $nMessageQueueLen + $i + 1;
			$sDeviceToken = $message->getRecipient($i);
			$this->_aMessageQueue[$nMessageID] = array(
				'MESSAGE' => $message,
				'DEVICE_TOKEN' => $sDeviceToken,
				'BINARY_NOTIFICATION' => $this->_getBinaryNotification(
					$sDeviceToken,
					$sMessagePayload,
					$nMessageID,
					$message->getExpiry(),
					$message->getPriority()
				),
				'ERRORS' => array()
			);
		}
	}

	/**
	 * Sends all messages in the message queue to Apple Push Notification Service.
	 *
	 * @throws \ApnsPHP\Push\Exception if not connected to the
	 *         service or no notification queued.
	 */
	public function send()
	{
		if (!$this->_hSocket) {
			throw new Push\Exception(
				'Not connected to Push Notification Service'
			);
		}

		if (empty($this->_aMessageQueue)) {
			throw new Push\Exception(
				'No notifications queued to be sent'
			);
		}

		$this->_aErrors = array();
		$nRun = 1;
		while (($nMessages = count($this->_aMessageQueue)) > 0) {
			$this->_log("INFO: Sending messages queue, run #{$nRun}: $nMessages message(s) left in queue.");

			$bError = false;
			foreach($this->_aMessageQueue as $k => &$aMessage) {
				if (function_exists('pcntl_signal_dispatch')) {
					pcntl_signal_dispatch();
				}

				/** @var \ApnsPHP\Message $message */
				$message = $aMessage['MESSAGE'];
				$sCustomIdentifier = (string)$message->getCustomIdentifier();
				$sCustomIdentifier = sprintf('[custom identifier: %s]', empty($sCustomIdentifier) ? 'unset' : $sCustomIdentifier);

				$nErrors = 0;
				if (!empty($aMessage['ERRORS'])) {
					foreach($aMessage['ERRORS'] as $aError) {
						if ($aError['statusCode'] == 0) {
							$this->_log("INFO: Message ID {$k} {$sCustomIdentifier} has no error ({$aError['statusCode']}), removing from queue...");
							$this->_removeMessageFromQueue($k);
							continue 2;
						} else if ($aError['statusCode'] > 1 && $aError['statusCode'] <= 8) {
							$this->_log("WARNING: Message ID {$k} {$sCustomIdentifier} has an unrecoverable error ({$aError['statusCode']}), removing from queue without retrying...");
							$this->_removeMessageFromQueue($k, true);
							continue 2;
						}
					}
					if (($nErrors = count($aMessage['ERRORS'])) >= $this->_nSendRetryTimes) {
						$this->_log(
							"WARNING: Message ID {$k} {$sCustomIdentifier} has {$nErrors} errors, removing from queue..."
						);
						$this->_removeMessageFromQueue($k, true);
						continue;
					}
				}

				$nLen = strlen($aMessage['BINARY_NOTIFICATION']);
				$this->_log("STATUS: Sending message ID {$k} {$sCustomIdentifier} (" . ($nErrors + 1) . "/{$this->_nSendRetryTimes}): {$nLen} bytes.");

				$aErrorMessage = null;
				if ($nLen !== ($nWritten = (int)@fwrite($this->_hSocket, $aMessage['BINARY_NOTIFICATION']))) {
					$aErrorMessage = array(
						'identifier' => $k,
						'statusCode' => self::STATUS_CODE_INTERNAL_ERROR,
						'statusMessage' => sprintf('%s (%d bytes written instead of %d bytes)',
							$this->_aErrorResponseMessages[self::STATUS_CODE_INTERNAL_ERROR], $nWritten, $nLen
						)
					);
				}
				usleep($this->_nWriteInterval);

				$bError = $this->_updateQueue($aErrorMessage);
				if ($bError) {
					break;
				}
			}

			if (!$bError) {
				$read = array($this->_hSocket);
				$null = NULL;
				$nChangedStreams = @stream_select($read, $null, $null, 0, $this->_nSocketSelectTimeout);
				if ($nChangedStreams === false) {
					$this->_log('ERROR: Unable to wait for a stream availability.');
					break;
				} else if ($nChangedStreams > 0) {
					$bError = $this->_updateQueue();
					if (!$bError) {
						$this->_aMessageQueue = array();
					}
				} else {
					$this->_aMessageQueue = array();
				}
			}

			$nRun++;
		}
	}

	/**
	 * Returns messages in the message queue.
	 *
	 * When a message is successful sent or reached the maximum retry time is removed
	 * from the message queue and inserted in the Errors container. Use the getErrors()
	 * method to retrieve messages with delivery error(s).
	 *
	 * @param boolean $bEmpty Empty message queue.
	 * @return array Array of messages left on the queue.
	 */
	public function getQueue($bEmpty = true)
	{
		$aRet = $this->_aMessageQueue;
		if ($bEmpty) {
			$this->_aMessageQueue = array();
		}
		return $aRet;
	}

	/**
	 * Returns messages not delivered to the end user because one (or more) error
	 * occurred.
	 *
	 * @param boolean $bEmpty Empty message container.
	 * @return array Array of messages not delivered because one or more errors
	 *         occurred.
	 */
	public function getErrors($bEmpty = true)
	{
		$aRet = $this->_aErrors;
		if ($bEmpty) {
			$this->_aErrors = array();
		}
		return $aRet;
	}

	/**
	 * Generate a binary notification from a device token and a JSON-encoded payload.
	 *
	 * @see http://tinyurl.com/ApplePushNotificationBinary
	 *
	 * @param  string $sDeviceToken The device token.
	 * @param  string $sPayload The JSON-encoded payload.
	 * @param  integer $nMessageID Message unique ID.
	 * @param  integer $nExpire Seconds, starting from now, that
	 *         identifies when the notification is no longer valid and can be discarded.
	 *         Pass a negative value (-1 for example) to request that APNs not store
	 *         the notification at all. Default is 86400 * 7, 7 days.
	 * @param  integer $nPriority The notification’s priority.
	 *         Provide one of the following values:
	 *          - 10 The push message is sent immediately. The push notification must trigger an alert,
	 *            sound, or badge on the device. It is an error to use this priority for a push that contains
	 *            only the content-available key.
	 *          - 5 The push message is sent at a time that conserves power on the device receiving it.
	 *         Default is 10.
	 * @return string A binary notification.
	 */
	protected function _getBinaryNotification($sDeviceToken, $sPayload, $nMessageID = 0, $nExpire = 604800, $nPriority = 10)
	{
		$sDeviceTokenBinary = pack('H*', $sDeviceToken);
		$nTokenLength = strlen($sDeviceTokenBinary);
		$nPayloadLength = strlen($sPayload);

		$sItems  = pack('Cn', 1, $nTokenLength) . $sDeviceTokenBinary; // 1 Device token (32 bytes)
		$sItems .= pack('Cn', 2, $nPayloadLength) . $sPayload;         // 2 Payload (variable length)
		$sItems .= pack('CnN', 3, 4, $nMessageID);                     // 3 Notification identifier (4 bytes)
		$sItems .= pack('CnN', 4, 4, $nExpire);                        // 4 Expiration date (4 bytes)
		$sItems .= pack('CnC', 5, 1, $nPriority);                      // 5 Priority (1 byte)

		$nFrameDataLength = strlen($sItems);

		$sRet = pack('CN', self::COMMAND_PUSH, $nFrameDataLength) . $sItems;

		return $sRet;
	}

	/**
	 * Parses the error message.
	 *
	 * @param string $sErrorMessage The Error Message.
	 * @return array Array with command, statusCode and identifier keys.
	 */
	protected function _parseErrorMessage($sErrorMessage)
	{
		return unpack('Ccommand/CstatusCode/Nidentifier', $sErrorMessage);
	}

	/**
	 * Reads an error message (if present) from the main stream.
	 * If the error message is present and valid the error message is returned,
	 * otherwise null is returned.
	 *
	 * @return array|null Return the error message array.
	 */
	protected function _readErrorMessage()
	{
		$sErrorResponse = @fread($this->_hSocket, self::ERROR_RESPONSE_SIZE);
		if ($sErrorResponse === false || strlen($sErrorResponse) != self::ERROR_RESPONSE_SIZE) {
			return null;
		}
		$aErrorResponse = $this->_parseErrorMessage($sErrorResponse);
		if (!is_array($aErrorResponse) || empty($aErrorResponse)) {
			return null;
		}
		if (!isset($aErrorResponse['command'], $aErrorResponse['statusCode'], $aErrorResponse['identifier'])) {
			return null;
		}
		if ($aErrorResponse['command'] != self::ERROR_RESPONSE_COMMAND) {
			return null;
		}
		$aErrorResponse['time'] = time();
		$aErrorResponse['statusMessage'] = 'None (unknown)';
		if (isset($this->_aErrorResponseMessages[$aErrorResponse['statusCode']])) {
			$aErrorResponse['statusMessage'] = $this->_aErrorResponseMessages[$aErrorResponse['statusCode']];
		}
		return $aErrorResponse;
	}

	/**
	 * Checks for error message and deletes messages successfully sent from message queue.
	 *
	 * @param  array $aErrorMessage The error message. It will anyway
	 *         always be read from the main stream. The latest successful message
	 *         sent is the lowest between this error message and the message that
	 *         was read from the main stream.
	 *         @see _readErrorMessage()
	 * @return boolean True if an error was received.
	 */
	protected function _updateQueue($aErrorMessage = null)
	{
		$aStreamErrorMessage = $this->_readErrorMessage();
		if (!isset($aErrorMessage) && !isset($aStreamErrorMessage)) {
			return false;
		} else if (isset($aErrorMessage, $aStreamErrorMessage)) {
			if ($aStreamErrorMessage['identifier'] <= $aErrorMessage['identifier']) {
				$aErrorMessage = $aStreamErrorMessage;
				unset($aStreamErrorMessage);
			}
		} else if (!isset($aErrorMessage) && isset($aStreamErrorMessage)) {
			$aErrorMessage = $aStreamErrorMessage;
			unset($aStreamErrorMessage);
		}

		$this->_log('ERROR: Unable to send message ID ' .
			$aErrorMessage['identifier'] . ': ' .
			$aErrorMessage['statusMessage'] . ' (' . $aErrorMessage['statusCode'] . ').');

		$this->disconnect();

		foreach($this->_aMessageQueue as $k => &$aMessage) {
			if ($k < $aErrorMessage['identifier']) {
				unset($this->_aMessageQueue[$k]);
			} else if ($k == $aErrorMessage['identifier']) {
				$aMessage['ERRORS'][] = $aErrorMessage;
			} else {
				break;
			}
		}

		$this->connect();

		return true;
	}

	/**
	 * Remove a message from the message queue.
	 *
	 * @param  integer $nMessageID The Message ID.
	 * @param  boolean $bError Insert the message in the Error container.
	 * @throws \ApnsPHP\Push\Exception if the Message ID is not valid or message
	 *         does not exists.
	 */
	protected function _removeMessageFromQueue($nMessageID, $bError = false)
	{
		if (!is_numeric($nMessageID) || $nMessageID <= 0) {
			throw new Push\Exception(
				'Message ID format is not valid.'
			);
		}
		if (!isset($this->_aMessageQueue[$nMessageID])) {
			throw new Push\Exception(
				"The Message ID {$nMessageID} does not exists."
			);
		}
		if ($bError) {
			$this->_aErrors[$nMessageID] = $this->_aMessageQueue[$nMessageID];
		}
		unset($this->_aMessageQueue[$nMessageID]);
	}
}