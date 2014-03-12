<?php
namespace ApnsPHP;
/**
 * @file
 * \ApnsPHP\Abstract class definition.
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
 * Abstract class: this is the superclass for all Apple Push Notification Service
 * classes.
 *
 * This class is responsible for the connection to the Apple Push Notification Service
 * and Feedback.
 *
 * @see http://tinyurl.com/ApplePushNotificationService
 */
abstract class AbstractClass
{
	/**
	 * @var integer Production environment.
	 */
	const ENVIRONMENT_PRODUCTION = 0;

	/**
	 * @var integer Sandbox environment.
	 */
	const ENVIRONMENT_SANDBOX = 1;

	/**
	 * @var integer Device token length.
	 */
	const DEVICE_BINARY_SIZE = 32;

	/**
	 * @var integer Default write interval in micro seconds.
	 */
	const WRITE_INTERVAL = 10000;

	/**
	 * @var integer Default connect retry interval in micro seconds.
	 */
	const CONNECT_RETRY_INTERVAL = 1000000;

	/**
	 * @var integer Default socket select timeout in micro seconds.
	 */
	const SOCKET_SELECT_TIMEOUT = 1000000;

	/**
	 * @var array Container for service URLs environments.
	 */
	protected $_aServiceURLs = array();

	/**
	 * @var@var integer Active environment.
	 */
	protected $_nEnvironment;

	/**
	 * @var integer Connect timeout in seconds.
	 */
	protected $_nConnectTimeout;

	/**
	 * @var integer Connect retry times.
	 */
	protected $_nConnectRetryTimes = 3;

	/**
	 * @var string Provider certificate file with key (Bundled PEM).
	 */
	protected $_sProviderCertificateFile;

	/**
	 * @var string Provider certificate passphrase.
	 */
	protected $_sProviderCertificatePassphrase;

	/**
	 * @var string Root certification authority file.
	 */
	protected $_sRootCertificationAuthorityFile;

	/**
	 * @var integer Write interval in micro seconds.
	 */
	protected $_nWriteInterval;

	/**
	 * @var integer Connect retry interval in micro seconds.
	 */
	protected $_nConnectRetryInterval;

	/**
	 * @var integer Socket select timeout in micro seconds.
	 */
	protected $_nSocketSelectTimeout;

	/**
	 * @var \ApnsPHP\Log\LogInterface Logger.
	 */
	protected $_logger;

	/**
	 * @var resource SSL Socket.
	 */
	protected $_hSocket;

	/**
	 * Constructor.
	 *
	 * @param  integer $nEnvironment Environment.
	 * @param  string $sProviderCertificateFile Provider certificate file with key (Bundled PEM).
	 * @param  \ApnsPHP\Log\LogInterface $oLogger
	 * @throws \ApnsPHP\Exception if the environment is not
	 *         sandbox or production or the provider certificate file is not readable.
	 */
	public function __construct($nEnvironment, $sProviderCertificateFile, Log\LogInterface $oLogger = null)
	{
		if ($nEnvironment != self::ENVIRONMENT_PRODUCTION && $nEnvironment != self::ENVIRONMENT_SANDBOX) {
			throw new Exception(
				"Invalid environment '{$nEnvironment}'"
			);
		}
		$this->_nEnvironment = $nEnvironment;

		if ($oLogger) {
			$this->setLogger($oLogger);
		}

		if (!is_readable($sProviderCertificateFile)) {
			throw new Exception(
				"Unable to read certificate file '{$sProviderCertificateFile}'"
			);
		}
		$this->_sProviderCertificateFile = $sProviderCertificateFile;

		$this->_nConnectTimeout = ini_get("default_socket_timeout");
		$this->_nWriteInterval = self::WRITE_INTERVAL;
		$this->_nConnectRetryInterval = self::CONNECT_RETRY_INTERVAL;
		$this->_nSocketSelectTimeout = self::SOCKET_SELECT_TIMEOUT;
	}

	/**
	 * Set the Logger instance to use for logging purpose.
	 *
	 * The default logger is \ApnsPHP\Log\Embedded, an instance
	 * of \ApnsPHP\Log\LogInterface that simply print to standard
	 * output log messages.
	 *
	 * To set a custom logger you have to implement \ApnsPHP\Log\LogInterface
	 * and use setLogger, otherwise standard logger will be used.
	 *
	 * @see \ApnsPHP\Log\LogInterface
	 * @see \ApnsPHP\Log\Embedded
	 *
	 * @param  \ApnsPHP\Log\LogInterface $logger Logger instance.
	 * @throws \ApnsPHP\Exception if Logger is not an instance
	 *         of \ApnsPHP\Log\LogInterface.
	 */
	public function setLogger(Log\LogInterface $logger)
	{
		if (!is_object($logger)) {
			throw new Exception(
				"The logger should be an instance of '\\ApnsPHP\\Log\\LogInterface'"
			);
		}
		if (!($logger instanceof Log\LogInterface)) {
			throw new Exception(
				"Unable to use an instance of '" . get_class($logger) . "' as logger: " .
				"a logger must implements \\ApnsPHP\\Log\\LogInterface."
			);
		}
		$this->_logger = $logger;
	}

	/**
	 * Get the Logger instance.
	 *
	 * @return \ApnsPHP\Log\LogInterface Current Logger instance.
	 */
	public function getLogger()
	{
		return $this->_logger;
	}

	/**
	 * Set the Provider Certificate passphrase.
	 *
	 * @param  string $sProviderCertificatePassphrase Provider Certificate passphrase.
	 */
	public function setProviderCertificatePassphrase($sProviderCertificatePassphrase)
	{
		$this->_sProviderCertificatePassphrase = $sProviderCertificatePassphrase;
	}

	/**
	 * Set the Root Certification Authority file.
	 *
	 * Setting the Root Certification Authority file automatically set peer verification
	 * on connect.
	 *
	 * @see http://tinyurl.com/GeneralProviderRequirements
	 * @see http://www.entrust.net/
	 * @see https://www.entrust.net/downloads/root_index.cfm
	 *
	 * @param  string $sRootCertificationAuthorityFile Root Certification
	 *         Authority file.
	 * @throws \ApnsPHP\Exception if Root Certification Authority
	 *         file is not readable.
	 */
	public function setRootCertificationAuthority($sRootCertificationAuthorityFile)
	{
		if (!is_readable($sRootCertificationAuthorityFile)) {
			throw new Exception(
				"Unable to read Certificate Authority file '{$sRootCertificationAuthorityFile}'"
			);
		}
		$this->_sRootCertificationAuthorityFile = $sRootCertificationAuthorityFile;
	}

	/**
	 * Get the Root Certification Authority file path.
	 *
	 * @return string Current Root Certification Authority file path.
	 */
	public function getCertificateAuthority()
	{
		return $this->_sRootCertificationAuthorityFile;
	}

	/**
	 * Set the write interval.
	 *
	 * After each socket write operation we are sleeping for this 
	 * time interval. To speed up the sending operations, use Zero
	 * as parameter but some messages may be lost.
	 *
	 * @param integer $nWriteInterval Write interval in micro seconds.
	 */
	public function setWriteInterval($nWriteInterval)
	{
		$this->_nWriteInterval = (int)$nWriteInterval;
	}

	/**
	 * Get the write interval.
	 *
	 * @return integer Write interval in micro seconds.
	 */
	public function getWriteInterval()
	{
		return $this->_nWriteInterval;
	}

	/**
	 * Set the connection timeout.
	 *
	 * The default connection timeout is the PHP internal value "default_socket_timeout".
	 * @see http://php.net/manual/en/filesystem.configuration.php
	 *
	 * @param integer $nTimeout Connection timeout in seconds.
	 */
	public function setConnectTimeout($nTimeout)
	{
		$this->_nConnectTimeout = (int)$nTimeout;
	}

	/**
	 * Get the connection timeout.
	 *
	 * @return integer Connection timeout in seconds.
	 */
	public function getConnectTimeout()
	{
		return $this->_nConnectTimeout;
	}

	/**
	 * Set the connect retry times value.
	 *
	 * If the client is unable to connect to the server retries at least for this
	 * value. The default connect retry times is 3.
	 *
	 * @param integer $nRetryTimes Connect retry times.
	 */
	public function setConnectRetryTimes($nRetryTimes)
	{
		$this->_nConnectRetryTimes = (int)$nRetryTimes;
	}

	/**
	 * Get the connect retry time value.
	 *
	 * @return integer Connect retry times.
	 */
	public function getConnectRetryTimes()
	{
		return $this->_nConnectRetryTimes;
	}

	/**
	 * Set the connect retry interval.
	 *
	 * If the client is unable to connect to the server retries at least for ConnectRetryTimes
	 * and waits for this value between each attempts.
	 *
	 * @see setConnectRetryTimes
	 *
	 * @param integer $nRetryInterval Connect retry interval in micro seconds.
	 */
	public function setConnectRetryInterval($nRetryInterval)
	{
		$this->_nConnectRetryInterval = (int)$nRetryInterval;
	}

	/**
	 * Get the connect retry interval.
	 *
	 * @return integer Connect retry interval in micro seconds.
	 */
	public function getConnectRetryInterval()
	{
		return $this->_nConnectRetryInterval;
	}

	/**
	 * Set the TCP socket select timeout.
	 *
	 * After writing to socket waits for at least this value for read stream to
	 * change status.
	 *
	 * In Apple Push Notification protocol there isn't a real-time
	 * feedback about the correctness of notifications pushed to the server; so after
	 * each write to server waits at least SocketSelectTimeout. If, during this
	 * time, the read stream change its status and socket received an end-of-file
	 * from the server the notification pushed to server was broken, the server
	 * has closed the connection and the client needs to reconnect.
	 *
	 * @see http://php.net/stream_select
	 *
	 * @param integer $nSelectTimeout Socket select timeout in micro seconds.
	 */
	public function setSocketSelectTimeout($nSelectTimeout)
	{
		$this->_nSocketSelectTimeout = (int)$nSelectTimeout;
	}

	/**
	 * Get the TCP socket select timeout.
	 *
	 * @return integer Socket select timeout in micro seconds.
	 */
	public function getSocketSelectTimeout()
	{
		return $this->_nSocketSelectTimeout;
	}

	/**
	 * Connects to Apple Push Notification service server.
	 *
	 * Retries ConnectRetryTimes if unable to connect and waits setConnectRetryInterval
	 * between each attempts.
	 *
	 * @see setConnectRetryTimes
	 * @see setConnectRetryInterval
	 * @throws \ApnsPHP\Exception if is unable to connect after
	 *         ConnectRetryTimes.
	 */
	public function connect()
	{
		$bConnected = false;
		$nRetry = 0;
		while (!$bConnected) {
			try {
				$bConnected = $this->_connect();
			} catch (Exception $e) {
				$this->_log('ERROR: ' . $e->getMessage());
				if ($nRetry >= $this->_nConnectRetryTimes) {
					throw $e;
				} else {
					$this->_log(
						"INFO: Retry to connect (" . ($nRetry+1) .
						"/{$this->_nConnectRetryTimes})..."
					);
					usleep($this->_nConnectRetryInterval);
				}
			}
			$nRetry++;
		}
	}

	/**
	 * Disconnects from Apple Push Notifications service server.
	 *
	 * @return boolean True if successful disconnected.
	 */
	public function disconnect()
	{
		if (is_resource($this->_hSocket)) {
			$this->_log('INFO: Disconnected.');
			return fclose($this->_hSocket);
		}
		return false;
	}

	/**
	 * Connects to Apple Push Notification service server.
	 *
	 * @throws \ApnsPHP\Exception if is unable to connect.
	 * @return boolean True if successful connected.
	 */
	protected function _connect()
	{
		$sURL = $this->_aServiceURLs[$this->_nEnvironment];
		unset($aURLs);

		$this->_log("INFO: Trying {$sURL}...");

		/**
		 * @see http://php.net/manual/en/context.ssl.php
		 */
		$streamContext = stream_context_create(array('ssl' => array(
			'cafile' => $this->_sRootCertificationAuthorityFile,
			'local_cert' => $this->_sProviderCertificateFile
		)));

		if (!empty($this->_sProviderCertificatePassphrase)) {
			stream_context_set_option($streamContext, 'ssl',
				'passphrase', $this->_sProviderCertificatePassphrase);
		}

		$this->_hSocket = @stream_socket_client($sURL, $nError, $sError,
			$this->_nConnectTimeout, STREAM_CLIENT_CONNECT, $streamContext);

		if (!$this->_hSocket) {
			throw new Exception(
				"Unable to connect to '{$sURL}': {$sError} ({$nError})"
			);
		}

		stream_set_blocking($this->_hSocket, 0);
		stream_set_write_buffer($this->_hSocket, 0);

		$this->_log("INFO: Connected to {$sURL}.");

		return true;
	}

	/**
	 * Logs a message through the Logger.
	 *
	 * @param string $sMessage The message.
	 */
	protected function _log($sMessage)
	{
		if (!isset($this->_logger)) {
			$this->_logger = new Log\Embedded();
		}
		$this->_logger->log($sMessage);
	}
}