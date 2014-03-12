<?php
namespace ApnsPHP;

/**
 * @file
 * ApnsPHP\Message class definition.
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
 * The Push Notification Message.
 *
 * The class represents a message to be delivered to an end user device.
 * Notification Service.
 *
 * @see http://tinyurl.com/ApplePushNotificationPayload
 */
class Message
{
	/**
	 * @var integer The maximum size allowed for a notification payload.
	 */
	const PAYLOAD_MAXIMUM_SIZE = 256;
	/**
	 * @var string The Apple-reserved aps namespace.
	 */
	const APPLE_RESERVED_NAMESPACE = 'aps';

	/**
	 * @var boolean If the JSON payload is longer than maximum allowed size, shorts message text.
	 */
	protected $_bAutoAdjustLongPayload = true;

	/**
	 * @var array Recipients device tokens.
	 */
	protected $_aDeviceTokens = array();

	/**
	 * @var string Alert message to display to the user.
	 */
	protected $_sText;

	/**
	 * @var integer Number to badge the application icon with.
	 */
	protected $_nBadge;

	/**
	 * @var string Sound to play.
	 */
	protected $_sSound;

	/**
	 * @var boolean True to initiates the Newsstand background download. @see http://tinyurl.com/ApplePushNotificationNewsstand
	 */
	protected $_bContentAvailable;

	/**
	 * @var@var mixed Custom properties container.
	 */
	protected $_aCustomProperties;

	/**
	 * @var integer That message will expire in 604800 seconds (86400 * 7, 7 days) if not successful delivered.
	 */
	protected $_nExpiryValue = 604800;

	/**
	 * @var integer Message priority value (integer 5 or 10 are valid values for APNs, default is 10).
	 */
	protected $_nPriorityValue = 10;

	/**
	 * @var mixed Custom message identifier.
	 */
	protected $_mCustomIdentifier;

	/**
	 * Constructor.
	 *
	 * @param string $sDeviceToken Recipient's device token.
	 */
	public function __construct($sDeviceToken = null)
	{
		if (isset($sDeviceToken)) {
			$this->addRecipient($sDeviceToken);
		}
	}

	/**
	 * Add a recipient device token.
	 *
	 * @param  string $sDeviceToken Recipient's device token.
	 * @throws \ApnsPHP\Message\Exception if the device token
	 *         is not well formed.
	 */
	public function addRecipient($sDeviceToken)
	{
		if (!preg_match('~^[a-f0-9]{64}$~i', $sDeviceToken)) {
			throw new Message\Exception(
				"Invalid device token '{$sDeviceToken}'"
			);
		}
		$this->_aDeviceTokens[] = $sDeviceToken;
	}

	/**
	 * Get the number of recipients.
	 *
	 * @return integer The number of recipients.
	 */
	public function getRecipientsNumber()
	{
		return count($this->_aDeviceTokens);
	}

	/**
	 * Get a recipient.
	 *
	 * @param  integer $nRecipient Recipient number to return.
	 * @throws \ApnsPHP\Message\Exception if no recipient number
	 *         exists.
	 * @return string The recipient token at index $nRecipient.
	 */
	public function getRecipient($nRecipient = 0)
	{
		if (!isset($this->_aDeviceTokens[$nRecipient])) {
			throw new Message\Exception(
				"No recipient at index '{$nRecipient}'"
			);
		}
		return $this->_aDeviceTokens[$nRecipient];
	}

	/**
	 * Get all recipients.
	 *
	 * @return array Array of all recipients device token.
	 */
	public function getRecipients()
	{
		return $this->_aDeviceTokens;
	}

	/**
	 * Set the alert message to display to the user.
	 *
	 * @param string $sText An alert message to display to the user.
	 * @see ApnsPHP\Message\Custom
	 */
	public function setText($sText)
	{
		$this->_sText = $sText;
	}

	/**
	 * Get the alert message to display to the user.
	 *
	 * @return string The alert message to display to the user.
	 */
	public function getText()
	{
		return $this->_sText;
	}

	/**
	 * Set the number to badge the application icon with.
	 *
	 * @param  integer $nBadge A number to badge the application icon with.
	 * @throws \ApnsPHP\Message\Exception if badge is not an
	 *         integer.
	 */
	public function setBadge($nBadge)
	{
		if (!is_int($nBadge)) {
			throw new Message\Exception(
				"Invalid badge number '{$nBadge}'"
			);
		}
		$this->_nBadge = $nBadge;
	}

	/**
	 * Get the number to badge the application icon with.
	 *
	 * @return integer The number to badge the application icon with.
	 */
	public function getBadge()
	{
		return $this->_nBadge;
	}

	/**
	 * Set the sound to play.
	 *
	 * @param  string $sSound A sound to play ('default' is the default sound).
	 */
	public function setSound($sSound = 'default')
	{
		$this->_sSound = $sSound;
	}

	/**
	 * Get the sound to play.
	 *
	 * @return string The sound to play.
	 */
	public function getSound()
	{
		return $this->_sSound;
	}

	/**
	 * Initiates the Newsstand background download.
	 * @see http://tinyurl.com/ApplePushNotificationNewsstand
	 *
	 * @param  boolean $bContentAvailable True to initiates the Newsstand background download.
	 * @throws \ApnsPHP\Message\Exception if ContentAvailable is not a
	 *         boolean.
	 */
	public function setContentAvailable($bContentAvailable = true)
	{
		if (!is_bool($bContentAvailable)) {
			throw new Message\Exception(
				"Invalid content-available value '{$bContentAvailable}'"
			);
		}
		$this->_bContentAvailable = $bContentAvailable ? true : null;
	}

	/**
	 * Get if should initiates the Newsstand background download.
	 *
	 * @return boolean Initiates the Newsstand background download property.
	 */
	public function getContentAvailable()
	{
		return $this->_bContentAvailable;
	}

	/**
	 * Set a custom property.
	 *
	 * @param  string $sName Custom property name.
	 * @param  mixed $mValue Custom property value.
	 * @throws \ApnsPHP\Message\Exception if custom property name is not outside
	 *         the Apple-reserved 'aps' namespace.
	 */
	public function setCustomProperty($sName, $mValue)
	{
		if ($sName == self::APPLE_RESERVED_NAMESPACE) {
			throw new Message\Exception(
				"Property name '" . self::APPLE_RESERVED_NAMESPACE . "' can not be used for custom property."
			);
		}
		$this->_aCustomProperties[trim($sName)] = $mValue;
	}

	/**
	 * Get the first custom property name.
	 *
	 * @deprecated Use getCustomPropertyNames() instead.
	 *
	 * @return string|null The first custom property name.
	 */
	public function getCustomPropertyName()
	{
		if (!is_array($this->_aCustomProperties)) {
			return null;
		}
		$aKeys = array_keys($this->_aCustomProperties);
		return $aKeys[0];
	}

	/**
	 * Get the first custom property value.
	 *
	 * @deprecated Use getCustomProperty() instead.
	 *
	 * @return mixed|null The first custom property value.
	 */
	public function getCustomPropertyValue()
	{
		if (!is_array($this->_aCustomProperties)) {
			return null;
		}
		$aKeys = array_keys($this->_aCustomProperties);
		return $this->_aCustomProperties[$aKeys[0]];
	}

	/**
	 * Get all custom properties names.
	 *
	 * @return array All properties names.
	 */
	public function getCustomPropertyNames()
	{
		if (!is_array($this->_aCustomProperties)) {
			return array();
		}
		return array_keys($this->_aCustomProperties);
	}

	/**
	 * Get the custom property value.
	 *
	 * @param  string $sName Custom property name.
	 * @throws \ApnsPHP\Message\Exception if no property exists with the specified
	 *         name.
	 * @return string The custom property value.
	 */
	public function getCustomProperty($sName)
	{
		if (!array_key_exists($sName, $this->_aCustomProperties)) {
			throw new Message\Exception(
				"No property exists with the specified name '{$sName}'."
			);
		}
		return $this->_aCustomProperties[$sName];
	}

	/**
	 * Set the auto-adjust long payload value.
	 *
	 * @param  boolean $bAutoAdjust If true a long payload is shorted cutting
	 *         long text value.
	 */
	public function setAutoAdjustLongPayload($bAutoAdjust)
	{
		$this->_bAutoAdjustLongPayload = (boolean)$bAutoAdjust;
	}

	/**
	 * Get the auto-adjust long payload value.
	 *
	 * @return boolean The auto-adjust long payload value.
	 */
	public function getAutoAdjustLongPayload()
	{
		return $this->_bAutoAdjustLongPayload;
	}

	/**
	 * PHP Magic Method. When an object is "converted" to a string, JSON-encoded
	 * payload is returned.
	 *
	 * @return string JSON-encoded payload.
	 */
	public function __toString()
	{
		try {
			$sJSONPayload = $this->getPayload();
		} catch (Message\Exception $e) {
			$sJSONPayload = '';
		}
		return $sJSONPayload;
	}

	/**
	 * Get the payload dictionary.
	 *
	 * @return array The payload dictionary.
	 */
	protected function _getPayload()
	{
		$aPayload[self::APPLE_RESERVED_NAMESPACE] = array();

		if (isset($this->_sText)) {
			$aPayload[self::APPLE_RESERVED_NAMESPACE]['alert'] = (string)$this->_sText;
		}
		if (isset($this->_nBadge) && $this->_nBadge >= 0) {
			$aPayload[self::APPLE_RESERVED_NAMESPACE]['badge'] = (int)$this->_nBadge;
		}
		if (isset($this->_sSound)) {
			$aPayload[self::APPLE_RESERVED_NAMESPACE]['sound'] = (string)$this->_sSound;
		}
		if (isset($this->_bContentAvailable)) {
			$aPayload[self::APPLE_RESERVED_NAMESPACE]['content-available'] = (int)$this->_bContentAvailable;
		}

		if (is_array($this->_aCustomProperties)) {
			foreach($this->_aCustomProperties as $sPropertyName => $mPropertyValue) {
				$aPayload[$sPropertyName] = $mPropertyValue;
			}
		}

		return $aPayload;
	}

	/**
	 * Convert the message in a JSON-encoded payload.
	 *
	 * @throws \ApnsPHP\Message\Exception if payload is longer than maximum allowed
	 *         size and AutoAdjustLongPayload is disabled.
	 * @return string JSON-encoded payload.
	 */
	public function getPayload()
	{
		$sJSON = json_encode($this->_getPayload(), defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0);
		if (!defined('JSON_UNESCAPED_UNICODE') && function_exists('mb_convert_encoding')) {
			preg_match_all('~\\\\u([0-9a-f]{4})~i', $sJSON, $aFound);
			if (!empty($aFound)) {
				$aReplace = array();
				foreach ($aFound[1] as $sFound) {
					$aReplace[] = mb_convert_encoding(pack("H*", $sFound), "UTF-8", "UTF-16");
				}
				$sJSON = str_replace($aFound[0], $aReplace, $sJSON);
			}
			unset($aFound, $aReplace);
		}

		$sJSONPayload = str_replace(
			'"' . self::APPLE_RESERVED_NAMESPACE . '":[]',
				'"' . self::APPLE_RESERVED_NAMESPACE . '":{}',
			$sJSON
		);
		$nJSONPayloadLen = strlen($sJSONPayload);

		if ($nJSONPayloadLen > self::PAYLOAD_MAXIMUM_SIZE) {
			if ($this->_bAutoAdjustLongPayload) {
				$nMaxTextLen = $nTextLen = strlen($this->_sText) - ($nJSONPayloadLen - self::PAYLOAD_MAXIMUM_SIZE);
				if ($nMaxTextLen > 0) {
					while (strlen($this->_sText = mb_substr($this->_sText, 0, --$nTextLen, 'UTF-8')) > $nMaxTextLen);
					return $this->getPayload();
				} else {
					throw new Message\Exception(
						"JSON Payload is too long: {$nJSONPayloadLen} bytes. Maximum size is " .
								self::PAYLOAD_MAXIMUM_SIZE . " bytes. The message text can not be auto-adjusted."
					);
				}
			} else {
				throw new Message\Exception(
					"JSON Payload is too long: {$nJSONPayloadLen} bytes. Maximum size is " .
							self::PAYLOAD_MAXIMUM_SIZE . " bytes"
				);
			}
		}

		return $sJSONPayload;
	}

	/**
	 * Set the expiry value.
	 *
	 * @param  integer $nExpiryValue This message will expire in N seconds
	 *         if not successful delivered.
	 * @throws \ApnsPHP\Message\Exception
	 */
	public function setExpiry($nExpiryValue)
	{
		if (!is_int($nExpiryValue)) {
			throw new Message\Exception(
				"Invalid seconds number '{$nExpiryValue}'"
			);
		}
		$this->_nExpiryValue = $nExpiryValue;
	}

	/**
	 * Get the expiry value.
	 *
	 * @return integer The expire message value (in seconds).
	 */
	public function getExpiry()
	{
		return $this->_nExpiryValue;
	}

	/**
	 * Set message priority value.
	 *
	 * @param integer $nPriorityValue Message priority value (integer 5 or 10 are valid values for APNs).
	 * @throws \ApnsPHP\Message\Exception
	 */
	public function setPriority($nPriorityValue)
	{
		if (!in_array($nPriorityValue, array(5, 10), true)) {
			throw new Message\Exception(
				"Invalid priority value '{$nPriorityValue}'"
			);
		}
		$this->_nPriorityValue = $nPriorityValue;
	}

	/**
	 * Get message priority value.
	 *
	 * @return integer Message priority value (5 or 10 are valid values for APNs)
	 */
	public function getPriority()
	{
		return $this->_nPriorityValue;
	}

	/**
	 * Set the custom message identifier.
	 *
	 * The custom message identifier is useful to associate a push notification
	 * to a DB record or an User entry for example. The custom message identifier
	 * can be retrieved in case of error using the getCustomIdentifier()
	 * method of an entry retrieved by the getErrors() method.
	 * This custom identifier, if present, is also used in all status message by
	 * the ApnsPHP\Push class.
	 *
	 * @param mixed $mCustomIdentifier The custom message identifier.
	 */
	public function setCustomIdentifier($mCustomIdentifier)
	{
		$this->_mCustomIdentifier = $mCustomIdentifier;
	}

	/**
	 * Get the custom message identifier.
	 *
	 * @return mixed The custom message identifier.
	 */
	public function getCustomIdentifier()
	{
		return $this->_mCustomIdentifier;
	}
}