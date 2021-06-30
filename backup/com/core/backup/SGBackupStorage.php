<?php
require_once(SG_BACKUP_PATH.'SGBackup.php');
backupGuardIncludeFile(SG_STORAGE_PATH.'SGGoogleDriveStorage.php');
backupGuardIncludeFile(SG_STORAGE_PATH.'SGDropboxStorage.php');
backupGuardIncludeFile(SG_STORAGE_PATH.'SGOneDriveStorage.php');
backupGuardIncludeFile(SG_STORAGE_PATH.'SGPCloudStorage.php');
backupGuardIncludeFile(SG_STORAGE_PATH.'SGBoxStorage.php');
backupGuardIncludeFile(SG_STORAGE_PATH.'SGFTPManager.php');
backupGuardIncludeFile(SG_STORAGE_PATH.'SGAmazonStorage.php');
backupGuardIncludeFile(SG_STORAGE_PATH.'BackupGuardStorage.php');

class SGBackupStorage implements SGIStorageDelegate
{
	private static $instance = null;
	private $actionId = null;
	private $currentUploadChunksCount = 0;
	private $totalUploadChunksCount = 0;
	private $progressUpdateInterval = 0;
	private $nextProgressUpdate = 0;
	private $backgroundMode = false;
	private $delegate = null;
	private $state = null;
	private $token = null;
	private $pendingStorageUploads = array();
	private $reloadStartTs;

	private function __construct()
	{
		$this->backgroundMode = SGConfig::get('SG_BACKUP_IN_BACKGROUND_MODE');
		$this->progressUpdateInterval = SGConfig::get('SG_ACTION_PROGRESS_UPDATE_INTERVAL');
	}

	private function __clone()
	{

	}

	public function setPendingStorageUploads($pendingStorageUploads)
	{
		$this->pendingStorageUploads = $pendingStorageUploads;
	}

	public function setToken($token)
	{
		$this->token = $token;
	}

	public function setState($state)
	{
		$this->state = $state;
	}

	public function setDelegate($delegate)
	{
		$this->delegate = $delegate;
	}

	public function getPendingStorageUploads()
	{
		return $this->pendingStorageUploads;
	}

	public function getState()
	{
		return $this->state;
	}

	public function getActionId()
	{
		return $this->actionId;
	}

	public function getCurrentUploadChunksCount()
	{
		return $this->currentUploadChunksCount;
	}

	public function reload()
	{
		$this->delegate->reload();
	}

	public function getToken()
	{
		return $this->token;
	}

	public function getProgress()
	{
		return $this->nextProgressUpdate;
	}

	public static function getInstance()
	{
		if (!self::$instance)
		{
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function deleteBackupFromStorage($storageId, $backupName)
	{
		try {
			$uploadFolder = trim(SGConfig::get('SG_STORAGE_BACKUPS_FOLDER_NAME'), '/');

			$storage = $this->storageObjectById($storageId);
			$path = "/".$uploadFolder."/".$backupName.".sgbp";

			if ($storage) {
				$storage->deleteFile($path);
			}
		}
		catch(Exception $e) {
		}
	}

	public function listStorage($storageId)
	{
		$storage = $this->storageObjectById($storageId, $storageName);
		$listOfFiles = $storage->getListOfFiles();

		return $listOfFiles;
	}

	public function downloadBackupArchiveFromCloud($storageId, $archive, $size, $backupId = null)
	{
		$storage = $this->storageObjectById($storageId, $storageName);
		$result = $storage->downloadFile($archive, $size, $backupId);

		return $result?true:false;
	}

	public static function queueBackupForUpload($backupName, $storageId, $options)
	{
		return SGBackup::createAction($backupName, SG_ACTION_TYPE_UPLOAD, SG_ACTION_STATUS_CREATED, $storageId, json_encode($options));
	}

	public function startUploadByActionId($actionId)
	{
		if ($this->state->getAction() == SG_STATE_ACTION_PREPARING_STATE_FILE) {
			$sgdb = SGDatabase::getInstance();

			$res = $sgdb->query('SELECT * FROM '.SG_ACTION_TABLE_NAME.' WHERE id=%d LIMIT 1', array($actionId));

			if (!count($res))
			{
				return false;
			}

			$row = $res[0];

			if ($row['type']!=SG_ACTION_TYPE_UPLOAD)
			{
				return false;
			}

			$this->actionId = $actionId;
			$type = $row['subtype'];
			$backupName = $row['name'];
		}
		else {
			$this->nextProgressUpdate = $this->state->getProgress()?$this->state->getProgress():$this->progressUpdateInterval;
			$this->actionId = $this->state->getActionId();
			$this->currentUploadChunksCount = $this->state->getCurrentUploadChunksCount();
			$type = $this->state->getStorageType();
			$backupName = $this->state->getBackupFileName();
		}

		$storage = $this->storageObjectById($type, $storageName);
		$this->startBackupUpload($backupName, $storage, $storageName);

		return true;
	}

	public function startDownloadByActionId($actionId)
	{
		$sgdb = SGDatabase::getInstance();

		$res = $sgdb->query('SELECT * FROM '.SG_ACTION_TABLE_NAME.' WHERE id=%d LIMIT 1', array($actionId));

		if (!count($res))
		{
			return false;
		}

		$row = $res[0];

		if ($row['type']!=SG_ACTION_TYPE_UPLOAD)
		{
			return false;
		}

		$this->actionId = $actionId;
		$storage = $this->storageObjectById($row['subtype'], $storageName);

		return true;
	}

	private function storageObjectById($storageId, &$storageName = '')
	{
		$res = $this->getStorageInfoById($storageId);
		$storageName = $res['storageName'];
		$storageClassName = $res['storageClassName'];

		if (!$storageClassName) {
			throw new SGExceptionNotFound('Unknown storage');
		}

		return new $storageClassName();
	}

	public function getStorageInfoById($storageId)
	{
		$storageName = '';
		$storageClassName = '';
		$storageId = (int)$storageId;
		$isConnected = true;

		switch ($storageId) {
			case SG_STORAGE_FTP:
				if (SGBoot::isFeatureAvailable('FTP')) {
					$connectionMethod = SGConfig::get('SG_STORAGE_CONNECTION_METHOD');
					$storage = null;

					if($connectionMethod == 'ftp') {
						$storageName = 'FTP';
					}
					else {
						$storageName = 'SFTP';
					}
					$isFtpConnected = SGConfig::get('SG_STORAGE_FTP_CONNECTED');

					if (empty($isFtpConnected)) {
						$isConnected = false;
					}
					$storageClassName = "SGFTPManager";
				}
				break;
			case SG_STORAGE_DROPBOX:
				if (SGBoot::isFeatureAvailable('DROPBOX')) {
					$storageName = 'Dropbox';
					$storageClassName = "SGDropboxStorage";
				}
				$isDropboxConnected = SGConfig::get('SG_DROPBOX_ACCESS_TOKEN');

				if (empty($isDropboxConnected)) {
					$isConnected = false;
				}
				break;
			case SG_STORAGE_GOOGLE_DRIVE:
				if (SGBoot::isFeatureAvailable('GOOGLE_DRIVE')) {
					$storageName = 'Google Drive';
					$storageClassName = "SGGoogleDriveStorage";
				}
				$isGdriveConnected = SGConfig::get('SG_GOOGLE_DRIVE_REFRESH_TOKEN');

				if (empty($isGdriveConnected)) {
					$isConnected = false;
				}
				break;
			case SG_STORAGE_AMAZON:
				if (SGBoot::isFeatureAvailable('AMAZON')) {
					$storageName = 'Amazon S3';
					$storageClassName = "SGAmazonStorage";
				}
				$isAmazonConnected = SGConfig::get('SG_STORAGE_AMAZON_CONNECTED');

				if (empty($isAmazonConnected)) {
					$isConnected = false;
				}
				break;
			case SG_STORAGE_ONE_DRIVE:
				if (SGBoot::isFeatureAvailable('ONE_DRIVE')) {
					$storageName = 'One Drive';
					$storageClassName = "SGOneDriveStorage";
				}
				$isOneDriveConnected = SGConfig::get('SG_ONE_DRIVE_REFRESH_TOKEN');

				if (empty($isOneDriveConnected)) {
					$isConnected = false;
				}
				break;
			case SG_STORAGE_P_CLOUD:
				if (SGBoot::isFeatureAvailable('P_CLOUD')) {
					$storageName = 'pCloud';
					$storageClassName = "SGPCloudStorage";
				}

				$isPCloudConnected = SGConfig::get('SG_P_CLOUD_ACCESS_TOKEN');

				if (empty($isPCloudConnected)) {
					$isConnected = false;
				}
				break;
			case SG_STORAGE_BOX:
				if (SGBoot::isFeatureAvailable('BOX')) {
					$storageName = 'box.com';
					$storageClassName = "SGBoxStorage";
				}

				$isBoxConnected = SGConfig::get('SG_BOX_REFRESH_TOKEN');

				if (empty($isBoxConnected)) {
					$isConnected = false;
				}
				break;
			case SG_STORAGE_BACKUP_GUARD:
				if (SGBoot::isFeatureAvailable('BACKUP_GUARD') && SG_SHOW_BACKUPGUARD_CLOUD) {
					$storageName = 'BackupGuard';
					$storageClassName = "BackupGuard\Storage";
				}
				$isBackupGuardConnected = SGConfig::get('SG_BACKUPGUARD_CLOUD_ACCOUNT')?unserialize(SGConfig::get('SG_BACKUPGUARD_CLOUD_ACCOUNT')):'';

				if (empty($isBackupGuardConnected)) {
					$isConnected = false;
				}
				break;
		}

		$res = array(
			'storageName' => $storageName,
			'storageClassName' => $storageClassName,
			'isConnected' => $isConnected,
		);

		return $res;
	}

	public function shouldUploadNextChunk()
	{
		if (SGBoot::isFeatureAvailable('BACKGROUND_MODE') && $this->backgroundMode)
		{
			SGBackgroundMode::next();
		}

		$this->currentUploadChunksCount++;
		if ($this->updateProgress())
		{
			$this->checkCancellation();
		}
		return true;
	}

	public function willStartUpload($chunksCount)
	{
		$this->totalUploadChunksCount = $chunksCount;

		if ($this->state->getAction() == SG_STATE_ACTION_PREPARING_STATE_FILE) {
			$this->resetProgress();
		}
	}

	public function updateProgressManually($progress)
	{
		if (SGBoot::isFeatureAvailable('BACKGROUND_MODE') && $this->backgroundMode)
		{
			SGBackgroundMode::next();
		}

		if ($this->updateProgress($progress))
		{
			$this->checkCancellation();
		}
	}

	private function updateProgress($progress = null)
	{
		if (!$progress) {
			$progress = (int)ceil($this->currentUploadChunksCount*100.0/$this->totalUploadChunksCount);
		}

		if ($progress >= $this->nextProgressUpdate) {
			$this->nextProgressUpdate += $this->progressUpdateInterval;

			$progress = max($progress, 0);
			$progress = min($progress, 100);
			SGBackup::changeActionProgress($this->actionId, $progress);

			return true;
		}

		return false;
	}

	private function resetProgress()
	{
		$this->currentUploadChunksCount = 0;
		$this->nextProgressUpdate = $this->progressUpdateInterval;
	}

	private function checkCancellation()
	{
		$status = SGBackup::getActionStatus($this->actionId);
		if ($status==SG_ACTION_STATUS_CANCELLING)
		{
			SGBackupLog::write('Upload cancelled');
			throw new SGExceptionSkip();
		}
		elseif ($status==SG_ACTION_STATUS_ERROR) {
			SGBackupLog::write('Upload timeout error');
			throw new SGExceptionExecutionTimeError();
		}
	}

	public function shouldReload()
	{
		$currentTime = time();

		if (($currentTime - $this->reloadStartTs) >= SG_RELOAD_TIMEOUT) {
			return true;
		}

		return false;
	}

	private function startBackupUpload($backupName, SGStorage $storage, $storageName)
	{
		$this->reloadStartTs = time();
		if ($this->state->getAction() == SG_STATE_ACTION_PREPARING_STATE_FILE) {
			$actionStartTs = time();
		}
		else {
			$actionStartTs = $this->state->getActionStartTs();
		}

		SGPing::update();

		$backupPath = SG_BACKUP_DIRECTORY.$backupName;
		$filesBackupPath = $backupPath.'/'.$backupName.'.sgbp';

		if (!is_readable($filesBackupPath)) {
			SGBackup::changeActionStatus($this->actionId, SG_ACTION_STATUS_ERROR);
			throw new SGExceptionNotFound('Backup not found');
		}

		try {
			@session_write_close();

			if ($this->state->getAction() == SG_STATE_ACTION_PREPARING_STATE_FILE) {
				SGBackup::changeActionStatus($this->actionId, SG_ACTION_STATUS_IN_PROGRESS_FILES);

				SGBackupLog::write('-');
				SGBackupLog::writeAction('upload to '.$storageName, SG_BACKUP_LOG_POS_START);
				SGBackupLog::write('Authenticating');
			}

			$storage->setDelegate($this);
			$storage->loadState();
			$storage->connectOffline();

			//get backups container folder
			$backupsFolder = $this->state->getActiveDirectory();

			if ($this->state->getAction() == SG_STATE_ACTION_PREPARING_STATE_FILE) {
				SGBackupLog::write('Preparing folder');

				$folderTree = SG_BACKUP_DEFAULT_FOLDER_NAME;

				if (SGBoot::isFeatureAvailable('SUBDIRECTORIES')){
					$folderTree = SGConfig::get('SG_STORAGE_BACKUPS_FOLDER_NAME');
				}

				//create backups container folder, if needed
				$backupsFolder = $storage->createFolder($folderTree);
			}

			$storage->setActiveDirectory($backupsFolder);

			if ($this->state->getAction() == SG_STATE_ACTION_PREPARING_STATE_FILE) {
				SGBackupLog::write('Uploading file');
			}

			$storage->uploadFile($filesBackupPath);

			SGBackupLog::writeAction('upload to '.$storageName, SG_BACKUP_LOG_POS_END);

			//Writing upload status to report file
			file_put_contents($backupPath.'/'.SG_REPORT_FILE_NAME, 'Uploaded to '.$storageName.": completed\n", FILE_APPEND);
			SGBackupLog::write('Total duration: '.backupGuardFormattedDuration($actionStartTs, time()));

			SGBackup::changeActionStatus($this->actionId, SG_ACTION_STATUS_FINISHED);
		}
		catch (Exception $exception) {
			if ($exception instanceof SGExceptionSkip) {
				SGBackup::changeActionStatus($this->actionId, SG_ACTION_STATUS_CANCELLED);
				//Writing upload status to report file
				file_put_contents($backupPath.'/'.SG_REPORT_FILE_NAME, 'Uploaded to '.$storageName.': canceled', FILE_APPEND);
				SGBackupMailNotification::sendBackupNotification(SG_ACTION_STATUS_CANCELLED, array(
					'flowFilePath' => $backupPath.'/'.SG_REPORT_FILE_NAME,
					'archiveName' => $backupName
				));
			}
			else {
				SGBackup::changeActionStatus($this->actionId, SG_ACTION_STATUS_FINISHED_WARNINGS);

				if (!$exception instanceof SGExceptionExecutionTimeError) {//to prevent log duplication for timeout exception
					SGBackupLog::writeExceptionObject($exception);
				}

				if (SGBoot::isFeatureAvailable('NOTIFICATIONS')) {
					//Writing upload status to report file
					file_put_contents($backupPath.'/'.SG_REPORT_FILE_NAME, 'Uploaded to '.$storageName.': failed', FILE_APPEND);
					SGBackupMailNotification::sendBackupNotification(SG_ACTION_STATUS_ERROR, array(
						'flowFilePath' => $backupPath.'/'.SG_REPORT_FILE_NAME,
						'archiveName' => $backupName
					));
				}
			}

			//delete file inside storage
			$storageId = $this->state->getStorageType();
			$this->deleteBackupFromStorage($storageId, $backupName);

			//delete report file in case of error
			@unlink($backupPath.'/'.SG_REPORT_FILE_NAME);
		}
	}
}
