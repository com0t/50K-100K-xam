<?php
require_once(SG_BACKUP_PATH.'SGBackupLog.php');
require_once(SG_RESTORE_PATH.'SGExternalRestore.php');
require_once(SG_LIB_PATH.'SGState.php');
backupGuardIncludeFile(SG_LIB_PATH.'SGBackgroundMode.php');
require_once(SG_BACKUP_PATH.'SGBackupFiles.php');
require_once(SG_BACKUP_PATH.'SGBackupDatabase.php');
backupGuardIncludeFile(SG_BACKUP_PATH.'SGBackupStorage.php');
backupGuardIncludeFile(SG_BACKUP_PATH.'SGBackupMailNotification.php');
require_once(SG_LOG_PATH.'SGFileLogHandler.php');
require_once(SG_LIB_PATH.'SGReloader.php');
require_once(SG_LIB_PATH.'SGCallback.php');

//close session for writing
@session_write_close();

class SGBackup implements SGIBackupDelegate
{
	private $backupFiles = null;
	private $backupDatabase = null;
	private $actionId = null;
	private $filesBackupAvailable = false;
	private $databaseBackupAvailable = false;
	private $isManual = true;
	private $actionStartTs = 0;
	private $fileName = '';
	private $filesBackupPath = '';
	private $databaseBackupPath = '';
	private $backupLogPath = '';
	private $restoreLogPath = '';
	private $backgroundMode = false;
	private $pendingStorageUploads = array();
	private $state = null;
	private $token = '';
	private $options = array();

	public function __construct()
	{
		$this->backupFiles = new SGBackupFiles();
		$this->backupFiles->setDelegate($this);

		$this->backupDatabase = new SGBackupDatabase();
		$this->backupDatabase->setDelegate($this);
	}

	public function getScheduleParamsById($id)
	{
		$sgdb = SGDatabase::getInstance();
		$res = $sgdb->query('SELECT * FROM '.SG_SCHEDULE_TABLE_NAME.' WHERE id=%d', array($id));
		if (empty($res)) {
			return '';
		}
		return $res[0];
	}

	private function handleBackupExecutionTimeout()
	{
		$this->backupDatabase->setFilePath($this->databaseBackupPath);
		$this->backupDatabase->cancel();

		$this->backupFiles->setFilePath($this->filesBackupPath);
		$this->backupFiles->cancel();

		if (SGBoot::isFeatureAvailable('NOTIFICATIONS')) {
			file_put_contents(dirname($this->filesBackupPath).'/'.SG_REPORT_FILE_NAME, 'Backup: failed', FILE_APPEND);
			SGBackupMailNotification::sendBackupNotification(SG_ACTION_STATUS_ERROR, array(
				'flowFilePath' => dirname($this->filesBackupPath).'/'.SG_REPORT_FILE_NAME,
				'archiveName' => $this->fileName
			));
		}
	}

	private function handleRestoreExecutionTimeout()
	{
		if (SGBoot::isFeatureAvailable('NOTIFICATIONS')) {
			file_put_contents(dirname($this->filesBackupPath).'/'.SG_REPORT_FILE_NAME, 'Restore: failed', FILE_APPEND);
			SGBackupMailNotification::sendRestoreNotification(false, array(
				'flowFilePath' => dirname($this->filesBackupPath).'/'.SG_REPORT_FILE_NAME,
				'archiveName' => $this->fileName
			));
		}
	}

	private function handleUploadExecutionTimeout()
	{
		self::changeActionStatus($this->actionId, SG_ACTION_STATUS_FINISHED_WARNINGS);

		if (SGBoot::isFeatureAvailable('NOTIFICATIONS')) {
			file_put_contents(dirname($this->filesBackupPath).'/'.SG_REPORT_FILE_NAME, 'Upload: failed', FILE_APPEND);

			SGBackupMailNotification::sendUploadNotification(false, array(
				'flowFilePath' => dirname($this->filesBackupPath).'/'.SG_REPORT_FILE_NAME,
				'archiveName' => $this->fileName
			));
		}
	}

	public function handleExecutionTimeout($actionId)
	{
		$this->actionId = $actionId;
		$action = self::getAction($actionId);
		$this->fileName = $action['name'];
		$actionType = $action['type'];
		$backupPath = SG_BACKUP_DIRECTORY.$this->fileName;

		$this->filesBackupPath = $backupPath.'/'.$this->fileName.'.sgbp';
		$this->databaseBackupPath = $backupPath.'/'.$this->fileName.'.sql';

		if ($actionType == SG_ACTION_TYPE_RESTORE) {
			$this->handleRestoreExecutionTimeout();
			$this->prepareRestoreLogFile($backupPath, true);
		}
		elseif ($actionType == SG_ACTION_TYPE_BACKUP) {
			$this->handleBackupExecutionTimeout();
			$this->prepareBackupLogFile($backupPath, true);
		}
		else{
			$this->handleUploadExecutionTimeout();
			$this->prepareBackupLogFile($backupPath, true);
		}

		//Stop all the running actions related to the specific backup, like backup, upload...
		$allActions = self::getRunningActions();
		foreach ($allActions as $action) {
			self::changeActionStatus($action['id'], SG_ACTION_STATUS_ERROR);
		}

		$exception = new SGExceptionExecutionTimeError();
		SGBackupLog::writeExceptionObject($exception);
		SGConfig::set('SG_EXCEPTION_TIMEOUT_ERROR', '1', true);
	}

	public function listStorage($storage)
	{
		if (SGBoot::isFeatureAvailable('DOWNLOAD_FROM_CLOUD')) {
			$listOfFiles = SGBackupStorage::getInstance()->listStorage($storage);
			return $listOfFiles;
		}

		return array();
	}

	public function downloadBackupArchiveFromCloud($archive, $storage, $size, $backupId = null)
	{
		$result = false;
		if (SGBoot::isFeatureAvailable('DOWNLOAD_FROM_CLOUD')) {
			$result = SGBackupStorage::getInstance()->downloadBackupArchiveFromCloud($storage, $archive, $size, $backupId);
		}

		return $result;
	}

	public function getState()
	{
		return $this->state;
	}

	private function prepareFilesStateFile()
	{
		$this->state = new SGFileState();

		$this->state->setRanges(array());
		$this->state->setOffset(0);
		$this->state->setToken($this->token);
		$this->state->setAction(SG_STATE_ACTION_PREPARING_STATE_FILE);
		$this->state->setType(SG_STATE_TYPE_FILE);
		$this->state->setActionId($this->actionId);
		$this->state->setActionStartTs($this->actionStartTs);
		$this->state->setBackupFileName($this->fileName);
		$this->state->setBackupFilePath($this->filesBackupPath);
		$this->state->setPendingStorageUploads($this->pendingStorageUploads);
		$this->state->setCdrCursor(0);
		$this->state->setRestoreMode(@$this->restoreMode);
		$this->state->setRestoreFiles(@$this->restoreFiles);
	}

	private function prepareDBStateFile()
	{
		$this->state = new SGDBState();
		$this->state->setToken($this->token);
		$this->state->setOffset(0);
		$this->state->setAction(SG_STATE_ACTION_PREPARING_STATE_FILE);
		$this->state->setType(SG_STATE_TYPE_DB);
		$this->state->setActionId($this->actionId);
		$this->state->setActionStartTs($this->actionStartTs);
		$this->state->setBackupFileName($this->fileName);
		$this->state->setBackupFilePath($this->filesBackupPath);
		$this->state->setPendingStorageUploads($this->pendingStorageUploads);
		$this->state->setBackedUpTables(array());
		$this->state->setTablesToBackup(@$this->options['SG_BACKUP_TABLES_TO_BACKUP']);
	}

	private function prepareUploadStateFile()
	{
		$this->state = new SGUploadState();
		$this->state->setOffset(0);
		$this->state->setActiveDirectory('');
		$this->state->setCurrentUploadChunksCount(0);
		$this->state->setTotalUploadChunksCount(0);
		$this->state->setUploadId(0);
		$this->state->setParts(array());
		$this->state->setToken($this->token);
		$this->state->setAction(SG_STATE_ACTION_PREPARING_STATE_FILE);
		$this->state->setType(SG_STATE_TYPE_UPLOAD);
		$this->state->setActionId($this->actionId);
		$this->state->setActionStartTs($this->actionStartTs);
		$this->state->setBackupFileName($this->fileName);
		$this->state->setBackupFilePath($this->filesBackupPath);
		$this->state->setPendingStorageUploads($this->pendingStorageUploads);
	}

	public function prepareMigrateStateFile()
	{
		$this->state = new SGMigrateState();
		$this->state->setActionId($this->actionId);
		$this->state->setAction(SG_STATE_ACTION_PREPARING_STATE_FILE);
		$this->state->setInprogress(false);
		$this->state->setTableCursor(0);
		$this->state->setColumnCursor(0);
		$this->state->setToken($this->token);
	}

	public function getNoprivReloadAjaxUrl()
	{
		$url = @$_SERVER['REQUEST_URI'];

		if (SG_ENV_ADAPTER == SG_ENV_WORDPRESS) {
			if (strpos($url, 'wp-cron.php')) {
				$url = substr($url, 0, strpos($url, 'wp-cron.php'));
				$url .= 'wp-admin/admin-ajax.php';
			}

			//this is needed for users that use alternate cron
			if (strpos($url, 'wp-admin/tools.php')) {
				$url = substr($url, 0, strpos($url, 'wp-admin/tools.php'));
				$url .= 'wp-admin/admin-ajax.php';
			}

			$url = explode('?', $url);
			$url = $url[0].'?action=backup_guard_awake&token='.$this->token;
		}

		return $url;
	}

	public function reload()
	{
		$url = $this->getNoprivReloadAjaxUrl();
		$callback = new SGCallback("SGBackup", "reloadCallback");

		SGReloader::didCompleteCallback();
		SGReloader::registerCallback($callback);
		SGReloader::reloadWithAjaxUrl($url);
		die();
	}

	public function reloadCallback($params)
	{
		$actions = self::getRunningActions();
		if (count($actions)) {
			$action = $actions[0];
			$method = $params['method'];

			$this->state = backupGuardLoadStateData();
			if ($action['type'] == SG_ACTION_TYPE_RESTORE) {
				$this->restore($action['name']);
			}
			else {
				$options = json_decode($action['options'], true);
				$this->backup($options, $this->state, $method);
			}
		}
	}

	private function saveStateFile()
	{
		$this->state->save();
	}

	public function getToken()
	{
		return $this->token;
	}

	private function reloadMethodNameByMethodId($method)
	{
		$name = "none";
		switch ($method) {
			case SG_RELOAD_METHOD_STREAM:
				$name = "stream";
				break;
			case SG_RELOAD_METHOD_CURL:
				$name = "curl";
				break;
			case SG_RELOAD_METHOD_SOCKET:
				$name = "socket";
				break;
			case SG_RELOAD_METHOD_AJAX:
				$name = "ajax";
				break;
			default:
				break;
		}

		return $name;
	}

	/* Backup implementation */
	public function backup($options, $state = false, $reloadMethod = null)
	{
		SGPing::update();

		$this->options = $options;
		$this->token = backupGuardGenerateToken();

		$this->filesBackupAvailable = isset($options['SG_ACTION_BACKUP_FILES_AVAILABLE'])?$options['SG_ACTION_BACKUP_FILES_AVAILABLE']:false;
		$this->databaseBackupAvailable = isset($options['SG_ACTION_BACKUP_DATABASE_AVAILABLE'])?$options['SG_ACTION_BACKUP_DATABASE_AVAILABLE']:false;
		$this->backgroundMode = isset($options['SG_BACKUP_IN_BACKGROUND_MODE'])?$options['SG_BACKUP_IN_BACKGROUND_MODE']:false;

		if (!$state) {
			$this->fileName = $this->getBackupFileName();
			$this->prepareBackupFolder(SG_BACKUP_DIRECTORY.$this->fileName);
			$this->prepareForBackup($options);
			$this->prepareBackupReport();

			SGBackupLog::write("Reload method set to ajax");
			SGConfig::set('SG_RELOAD_METHOD', SG_RELOAD_METHOD_AJAX, true);

			if ($this->databaseBackupAvailable) {
				$this->prepareDBStateFile();
			}
			else {
				$this->prepareFilesStateFile();
			}

			$this->saveStateFile();
			SGReloader::reset();
			if (backupGuardIsReloadEnabled()) {
				$this->reload();
			}
		}
		else {
			$this->state = $state;
			$this->fileName = $state->getBackupFileName();
			$this->actionId = $state->getActionId();
			$this->actionStartTs = $state->getActionStartTs();
			$this->pendingStorageUploads = $state->getPendingStorageUploads();

			$this->prepareBackupLogFile(SG_BACKUP_DIRECTORY.$this->fileName, true);
			$this->setBackupPaths();
			$this->prepareAdditionalConfigurations();
		}

		SGPing::update();

		try {
			$rootDirectory = rtrim(SGConfig::get('SG_APP_ROOT_DIRECTORY'), '/').'/';

			if ($this->databaseBackupAvailable) {
				$this->backupDatabase->setFilePath($this->databaseBackupPath);
				$this->backupDatabase->setPendingStorageUploads($this->pendingStorageUploads);

				if (!$this->filesBackupAvailable) {
					$options['SG_BACKUP_FILE_PATHS'] = '';
				}

				if ($this->state->getType() == SG_STATE_TYPE_DB) {
					$this->backupDatabase->backup($this->databaseBackupPath);
					$this->prepareFilesStateFile();
					$this->saveStateFile();
					self::changeActionStatus($this->actionId, SG_ACTION_STATUS_IN_PROGRESS_FILES);

					if (backupGuardIsReloadEnabled()) {
						$this->reload();
					}
				}

				$path = substr($this->databaseBackupPath, strlen($rootDirectory));
				$this->backupFiles->addDontExclude($this->databaseBackupPath);
				$backupItems = $options['SG_BACKUP_FILE_PATHS'];
				$allItems = $backupItems?explode(',', $backupItems):array();
				$allItems[] = $path;
				$options['SG_BACKUP_FILE_PATHS'] = implode(',', $allItems);

				if ($this->state->getType() == SG_STATE_TYPE_DB) {
					$currentStatus = $this->getCurrentActionStatus();
					if ($currentStatus==SG_ACTION_STATUS_CANCELLING || $currentStatus==SG_ACTION_STATUS_CANCELLED) {
						// If action canceled during backup of database, cancelaion handling will happen here
						// in other cases handling will happen in respective classes
						$this->cancel();
					}
				}
			}

			if ($this->state->getType() == SG_STATE_TYPE_FILE) {

				/*
				 * ToDo check this logic
				 * */
				/*
				$treeFilePath = SG_BACKUP_DIRECTORY.$this->fileName.'/'.SG_TREE_FILE_NAME;
				$treeFilePathWithoutRootDir = substr($treeFilePath, strlen($rootDirectory));
				$this->backupFiles->addDontExclude($treeFilePath);
				$backupItems = $options['SG_BACKUP_FILE_PATHS'];
				$allItems = $backupItems?explode(',', $backupItems):array();
				$allItems[] = $treeFilePathWithoutRootDir;
				$options['SG_BACKUP_FILE_PATHS'] = implode(',', $allItems);
				*/

				$this->backupFiles->setPendingStorageUploads($this->pendingStorageUploads);
				$this->backupFiles->backup($this->filesBackupPath, $options, $this->state);
				$this->didFinishBackup();

				SGPing::update();

				$this->prepareUploadStateFile();
				$this->saveStateFile();
			}

			//continue uploading backup to storages
			$this->backupUploadToStorages();

			// Clear temporary files
			$this->clear();
		}
		catch (SGException $exception) {
			if ($exception instanceof SGExceptionSkip) {
				$this->setCurrentActionStatusCancelled();
			}
			else {
				SGBackupLog::writeExceptionObject($exception);

				if ($this->state->getType() != SG_STATE_TYPE_UPLOAD) {
					if ($this->databaseBackupAvailable) {
						$this->backupDatabase->cancel();
					}

					$this->backupFiles->cancel();
				}

				if (SGBoot::isFeatureAvailable('NOTIFICATIONS')) {
					//Writing backup status to report file
					file_put_contents(dirname($this->filesBackupPath).'/'.SG_REPORT_FILE_NAME, 'Backup: failed', FILE_APPEND);
					SGBackupMailNotification::sendBackupNotification(SG_ACTION_STATUS_ERROR, array(
						'flowFilePath' => dirname($this->filesBackupPath).'/'.SG_REPORT_FILE_NAME,
						'archiveName' => $this->fileName
					));
				}

				self::changeActionStatus($this->actionId, SG_ACTION_STATUS_ERROR);
			}

			// Clear temporary files
			$this->clear();
		}
	}

	private function prepareBackupReport()
	{
		file_put_contents(dirname($this->filesBackupPath).'/'.SG_REPORT_FILE_NAME, 'Report for: '.SG_SITE_URL."\n", FILE_APPEND);
	}

	private function shouldDeleteBackupAfterUpload()
	{
		return SGConfig::get('SG_DELETE_BACKUP_AFTER_UPLOAD')?true:false;
	}

	private function backupUploadToStorages()
	{
		//check list of storages to upload if any
		$uploadToStorages = count($this->pendingStorageUploads)?true:false;
		if (SGBoot::isFeatureAvailable('STORAGE') && $uploadToStorages)
		{
			while (count($this->pendingStorageUploads))
			{
				$sgBackupStorage = SGBackupStorage::getInstance();
				$storageId = $this->pendingStorageUploads[0];

				$storageInfo = $sgBackupStorage->getStorageInfoById($storageId);
				if (empty($storageInfo['isConnected'])) {
					SGBackupLog::write($storageInfo['storageName'].' stopped');
					array_shift($this->pendingStorageUploads);
					continue;
				}

				if ($this->state->getAction() == SG_STATE_ACTION_PREPARING_STATE_FILE) {
					// Create action for upload
					$this->actionId = SGBackupStorage::queueBackupForUpload($this->fileName, $storageId, $this->options);
				}
				else {
					// Get upload action id if it does not finished yet
					$this->actionId = $this->state->getActionId();
				}


				$sgBackupStorage->setDelegate($this);
				$sgBackupStorage->setState($this->state);
				$sgBackupStorage->setToken($this->token);
				$sgBackupStorage->setPendingStorageUploads($this->pendingStorageUploads);
				$sgBackupStorage->startUploadByActionId($this->actionId);

				array_shift($this->pendingStorageUploads);
				// Reset state file to defaults for next storage upload
				$this->prepareUploadStateFile();
			}

			$this->didFinishUpload();
		}
	}

	private function didFinishUpload()
	{
		//check if option is enabled
		$isDeleteLocalBackupFeatureAvailable = SGBoot::isFeatureAvailable('DELETE_LOCAL_BACKUP_AFTER_UPLOAD');

		if (SGBoot::isFeatureAvailable('NOTIFICATIONS')) {
			SGBackupMailNotification::sendBackupNotification(SG_ACTION_STATUS_FINISHED, array(
				'flowFilePath' => dirname($this->filesBackupPath).'/'.SG_REPORT_FILE_NAME,
				'archiveName' => $this->fileName
			));
		}

		$status = SGBackup::getActionStatus($this->actionId);

		if ($this->shouldDeleteBackupAfterUpload() && $isDeleteLocalBackupFeatureAvailable && $status == SG_ACTION_STATUS_FINISHED) {
			@unlink(SG_BACKUP_DIRECTORY.$this->fileName.'/'.$this->fileName.'.'.SGBP_EXT);
		}
	}

	// Delete state and flow files after upload
	private function clear()
	{
		@unlink(dirname($this->filesBackupPath).'/'.SG_REPORT_FILE_NAME);
		/// ToDo check this logic
		//@unlink(SG_BACKUP_DIRECTORY.$this->fileName.'/'.SG_TREE_FILE_NAME);
		@unlink(SG_BACKUP_DIRECTORY.SG_STATE_FILE_NAME);
		@unlink(SG_BACKUP_DIRECTORY.SG_RELOADER_STATE_FILE_NAME);
		@unlink(SG_PING_FILE_PATH);
		SGConfig::set("SG_CUSTOM_BACKUP_NAME", '');
	}

	private function cleanUp()
	{
		//delete sql file
		if ($this->databaseBackupAvailable) {
			@unlink($this->databaseBackupPath);
		}
	}

	private function getBackupFileName()
	{
		if (SGConfig::get("SG_CUSTOM_BACKUP_NAME"))  {
			return backupGuardRemoveSlashes(SGConfig::get("SG_CUSTOM_BACKUP_NAME"));
		}

		$sgBackupPrefix = SG_BACKUP_FILE_NAME_DEFAULT_PREFIX;
		if (function_exists('backupGuardGetCustomPrefix') && SGBoot::isFeatureAvailable('CUSTOM_BACKUP_NAME')) {
			$sgBackupPrefix = backupGuardGetCustomPrefix();
		}

		$sgBackupPrefix .= backupGuardGetFilenameOptions($this->options);

		$date = backupGuardConvertDateTimezone(@date('YmdHis'), 'YmdHis');
		return $sgBackupPrefix.($date);
	}

	private function prepareBackupFolder($backupPath)
	{
		if (!is_writable(SG_BACKUP_DIRECTORY))
		{
			throw new SGExceptionForbidden('Permission denied. Directory is not writable: '.$backupPath);
		}

		//create backup folder
		if (!file_exists($backupPath) && !@mkdir($backupPath))
		{
			throw new SGExceptionMethodNotAllowed('Cannot create folder: '.$backupPath);
		}

		if (!is_writable($backupPath))
		{
			throw new SGExceptionForbidden('Permission denied. Directory is not writable: '.$backupPath);
		}

		//create backup log file
		$this->prepareBackupLogFile($backupPath);
	}

	private function extendLogFileHeader($content)
	{
		$isManual = $this->getIsManual();
		if ($isManual) {
			$content .= 'Backup mode: Manual'.PHP_EOL;
		}
		else {
			$content .= 'Backup mode: Schedule'.PHP_EOL;
		}

		return $content;
	}

	private function prepareBackupLogFile($backupPath, $exists = false)
	{
		$file = $backupPath.'/'.$this->fileName.'_backup.log';
		$this->backupLogPath = $file;

		if (!$exists)
		{
			$content = self::getLogFileHeader(SG_ACTION_TYPE_BACKUP, $this->fileName);
			$content = $this->extendLogFileHeader($content);

			$types = array();
			if ($this->filesBackupAvailable)
			{
				$types[] = 'files';
			}
			if ($this->databaseBackupAvailable)
			{
				$types[] = 'database';
			}

			$content .= 'Backup type: '.implode(',', $types).PHP_EOL.PHP_EOL;

			if (!file_put_contents($file, $content))
			{
				throw new SGExceptionMethodNotAllowed('Cannot create backup log file: '.$file);
			}
		}

		//create file log handler
		$fileLogHandler = new SGFileLogHandler($file);
		SGLog::registerLogHandler($fileLogHandler, SG_LOG_LEVEL_LOW, true);
	}

	private function setBackupPaths()
	{
		$this->filesBackupPath = SG_BACKUP_DIRECTORY.$this->fileName.'/'.$this->fileName.'.sgbp';
		$this->databaseBackupPath = SG_BACKUP_DIRECTORY.$this->fileName.'/'.$this->fileName.'.sql';
	}

	private function prepareUploadToStorages($options)
	{
		$uploadToStorages = $options['SG_BACKUP_UPLOAD_TO_STORAGES'];

		if (SGBoot::isFeatureAvailable('STORAGE') && $uploadToStorages) {
			$this->pendingStorageUploads = explode(',', $uploadToStorages);
		}
	}

	private function prepareAdditionalConfigurations()
	{
		$this->backupFiles->setFilePath($this->filesBackupPath);
		SGConfig::set('SG_RUNNING_ACTION', 1, true);
	}

	private function prepareForBackup($options)
	{
		//start logging
		SGBackupLog::writeAction('backup', SG_BACKUP_LOG_POS_START);

		//save timestamp for future use
		$this->actionStartTs = time();

		//create action inside db
		$status = $this->databaseBackupAvailable?SG_ACTION_STATUS_IN_PROGRESS_DB:SG_ACTION_STATUS_IN_PROGRESS_FILES;
		$this->actionId = self::createAction($this->fileName, SG_ACTION_TYPE_BACKUP, $status, 0, json_encode($options));

		//set paths
		$this->setBackupPaths();

		//prepare sgbp file
		@file_put_contents($this->filesBackupPath, '');

		if (!is_writable($this->filesBackupPath))
		{
			throw new SGExceptionForbidden('Could not create backup file: '.$this->filesBackupPath);
		}

		//additional configuration
		$this->prepareAdditionalConfigurations();

		//check if upload to storages is needed
		$this->prepareUploadToStorages($options);
	}

	public function cancel()
	{
		$dir = SG_BACKUP_DIRECTORY.$this->fileName;

		if (SGBoot::isFeatureAvailable('NOTIFICATIONS')) {
			//Writing backup status to report file
			file_put_contents($dir.'/'.SG_REPORT_FILE_NAME, 'Backup: canceled', FILE_APPEND);
			SGBackupMailNotification::sendBackupNotification(SG_ACTION_STATUS_CANCELLED, array(
				'flowFilePath' => dirname($this->filesBackupPath).'/'.SG_REPORT_FILE_NAME,
				'archiveName' => $this->fileName
			));
		}

		if ($dir != SG_BACKUP_DIRECTORY) {
			backupGuardDeleteDirectory($dir);
		}

		$this->clear();
		throw new SGExceptionSkip();
	}

	private function didFinishBackup()
	{
		if(SGConfig::get('SG_REVIEW_POPUP_STATE') != SG_NEVER_SHOW_REVIEW_POPUP)
		{
			SGConfig::set('SG_REVIEW_POPUP_STATE', SG_SHOW_REVIEW_POPUP);
		}

		$action = $this->didFindWarnings()?SG_ACTION_STATUS_FINISHED_WARNINGS:SG_ACTION_STATUS_FINISHED;
		self::changeActionStatus($this->actionId, $action);

		SGBackupLog::writeAction('backup', SG_BACKUP_LOG_POS_END);

		$report = $this->didFindWarnings()?'completed with warnings':'completed';

		//Writing backup status to report file
		file_put_contents(dirname($this->filesBackupPath).'/'.SG_REPORT_FILE_NAME, 'Backup: '.$report."\n", FILE_APPEND);
		if (SGBoot::isFeatureAvailable('NOTIFICATIONS') && !count($this->pendingStorageUploads)) {
			SGBackupMailNotification::sendBackupNotification($action, array(
				'flowFilePath' => dirname($this->filesBackupPath).'/'.SG_REPORT_FILE_NAME,
				'archiveName' => $this->fileName
			));
		}

		SGBackupLog::write('Total duration: '.backupGuardFormattedDuration($this->actionStartTs, time()));
		SGBackupLog::write('Memory pick usage: '.(memory_get_peak_usage(true)/1024/1024).'MB');
		if (function_exists('sys_getloadavg')) {
			SGBackupLog::write('CPU usage: '.implode(' / ', sys_getloadavg()));
		}

		$archiveSizeInBytes = backupGuardRealFilesize($this->filesBackupPath);
		$archiveSize = convertToReadableSize($archiveSizeInBytes);
		SGBackupLog::write("Archive size: ".$archiveSize." (".$archiveSizeInBytes." bytes)");

		$this->cleanUp();
		if (SGBoot::isFeatureAvailable('NUMBER_OF_BACKUPS_TO_KEEP') && function_exists('backupGuardOutdatedBackupsCleanup')) {
			backupGuardOutdatedBackupsCleanup(SG_BACKUP_DIRECTORY);
		}
	}

	public function handleMigrationErrors($exception)
	{
		SGConfig::set('SG_BACKUP_SHOW_MIGRATION_ERROR', 1);
		SGConfig::set('SG_BACKUP_MIGRATION_ERROR', (string)$exception);
	}

	public function getActionId()
	{
		return $this->actionId;
	}

	/* Restore implementation */

	public function restore($backupName, $restoreMode=NULL, $restoreFiles = NULL)
	{
		try
		{
			SGPing::update();
			$this->token = backupGuardGenerateToken();
			if($restoreMode != NULL) {
				$this->restoreMode = $restoreMode;
			}
			if($restoreFiles != NULL) {
				$this->restoreFiles = $restoreFiles;
			}
			$backupName = backupGuardRemoveSlashes($backupName);
			$this->prepareForRestore($backupName);

			if ($this->state && ($this->state->getAction() == SG_STATE_ACTION_RESTORING_DATABASE || $this->state->getAction() == SG_STATE_ACTION_MIGRATING_DATABASE)) {
				$this->didFinishFilesRestore();
			}
			else {
				$this->backupFiles->setFileName($backupName);
				$this->backupFiles->restore($this->filesBackupPath);

				$this->prepareDBStateFile();
				$this->didFinishFilesRestore();
			}
		}
		catch (SGException $exception)
		{
			if (!$exception instanceof SGExceptionSkip)
			{
				SGBackupLog::writeExceptionObject($exception);

				if ($exception instanceof SGExceptionMigrationError) {
					$this->handleMigrationErrors($exception);
				}

				if (SGBoot::isFeatureAvailable('NOTIFICATIONS'))
				{
					SGBackupMailNotification::sendRestoreNotification(false);
				}

				self::changeActionStatus($this->actionId, SG_ACTION_STATUS_ERROR);
			}
			else
			{
				self::changeActionStatus($this->actionId, SG_ACTION_STATUS_CANCELLED);
			}
		}
	}

	private function prepareForRestore($backupName)
	{
		//prepare file name
		$this->fileName = $backupName;

		//set paths
		$restorePath = SG_BACKUP_DIRECTORY.$this->fileName;
		$this->filesBackupPath = $restorePath.'/'.$this->fileName.'.sgbp';
		$this->databaseBackupPath = $restorePath.'/'.$this->fileName.'.sql';

		if (!$this->state) {
			//create action inside db
			$this->actionId = self::createAction($this->fileName, SG_ACTION_TYPE_RESTORE, SG_ACTION_STATUS_IN_PROGRESS_FILES);

			//save current user credentials
			$this->backupDatabase->saveCurrentUser();

			//check if we can run external restore
			$externalRestoreEnabled = SGExternalRestore::getInstance()->prepare($this->actionId);

			//prepare folder
			$this->prepareRestoreFolder($restorePath);

			SGConfig::set('SG_RUNNING_ACTION', 1, true);

			//save timestamp for future use
			$this->actionStartTs = time();

			$this->prepareFilesStateFile();
			$this->saveStateFile();
			SGReloader::reset();

			if ($externalRestoreEnabled) {
				$this->reload();
			}
		}
		else {
			$this->actionId = $this->state->getActionId();
			$this->actionStartTs = $this->state->getActionStartTs();
			$this->prepareRestoreLogFile($restorePath, true);
		}
	}

	private function prepareRestoreFolder($restorePath)
	{
		if (!is_writable($restorePath))
		{
			SGConfig::set('SG_BACKUP_NOT_WRITABLE_DIR_PATH', $restorePath);
			SGConfig::set('SG_BACKUP_SHOW_NOT_WRITABLE_ERROR', 1);
			throw new SGExceptionForbidden('Permission denied. Directory is not writable: '.$restorePath);
		}

		$this->filesBackupAvailable = file_exists($this->filesBackupPath);

		//create restore log file
		$this->prepareRestoreLogFile($restorePath);
	}

	private function prepareRestoreLogFile($backupPath, $exists = false)
	{
		$file = $backupPath.'/'.$this->fileName.'_restore.log';
		$this->restoreLogPath = $file;

		if (!$exists) {
			$content = self::getLogFileHeader(SG_ACTION_TYPE_RESTORE, $this->fileName);

			$content .= PHP_EOL;

			if (!file_put_contents($file, $content)) {
				throw new SGExceptionMethodNotAllowed('Cannot create restore log file: '.$file);
			}
		}

		//create file log handler
		$fileLogHandler = new SGFileLogHandler($file);
		SGLog::registerLogHandler($fileLogHandler, SG_LOG_LEVEL_LOW, true);
	}

	private function didFinishRestore()
	{
		SGBackupLog::writeAction('restore', SG_BACKUP_LOG_POS_END);

		if (SGBoot::isFeatureAvailable('NOTIFICATIONS')) {
			SGBackupMailNotification::sendRestoreNotification(true);
		}

		SGBackupLog::write('Memory pick usage: '.(memory_get_peak_usage(true)/1024/1024).'MB');
		if (function_exists('sys_getloadavg')) {
			SGBackupLog::write('CPU usage: '.implode(' / ', sys_getloadavg()));
		}
		SGBackupLog::write('Total duration: '.backupGuardFormattedDuration($this->actionStartTs, time()));

		$this->cleanUp();
	}

	private function didFinishFilesRestore()
	{
		$this->databaseBackupAvailable = file_exists($this->databaseBackupPath);

		if ($this->databaseBackupAvailable) {
			if ($this->state->getAction() == SG_STATE_ACTION_RESTORING_DATABASE) {
				self::changeActionStatus($this->actionId, SG_ACTION_STATUS_IN_PROGRESS_DB);
			}

			$this->backupDatabase->restore($this->databaseBackupPath);
		}

		$action = $this->didFindWarnings()?SG_ACTION_STATUS_FINISHED_WARNINGS:SG_ACTION_STATUS_FINISHED;

		self::changeActionStatus($this->actionId, $action);

		//we let the external restore to finalize the restore for itself
		if (SGExternalRestore::isEnabled()) {
			return;
		}

		$this->didFinishRestore();
	}

	public function finalizeExternalRestore($actionId)
	{
		$action = self::getAction($actionId);

		$this->state = backupGuardLoadStateData();
		$this->prepareForRestore($action['name']);

		$this->databaseBackupAvailable = file_exists($this->databaseBackupPath);

		if ($this->databaseBackupAvailable) {
			$this->backupDatabase->finalizeRestore();
		}

		$this->didFinishRestore();
	}

	/* General methods */

	public static function getLogFileHeader($actionType, $fileName)
	{
		$pluginCapabilities = backupGuardGetCapabilities();

		$confs = array();
		$confs['sg_backup_guard_version'] = SG_BACKUP_GUARD_VERSION;
		$confs['sg_archive_version'] = SG_ARCHIVE_VERSION;
		$confs['sg_user_mode'] = ($pluginCapabilities != BACKUP_GUARD_CAPABILITIES_FREE)?'pro':'free'; // Check if user is pro or free
		$confs['os'] = PHP_OS;
		$confs['server'] = @$_SERVER['SERVER_SOFTWARE'];
		$confs['php_version'] = PHP_VERSION;
		$confs['sapi'] = PHP_SAPI;
		$confs['mysql_version'] = SG_MYSQL_VERSION;
		$confs['int_size'] = PHP_INT_SIZE;
		$confs['method'] = backupGuardIsReloadEnabled()?'ON':'OFF';

		$confs['dbprefix'] = SG_ENV_DB_PREFIX;
		$confs['siteurl'] = SG_SITE_URL;
		$confs['homeurl'] = SG_HOME_URL;
		$confs['uploadspath'] = SG_UPLOAD_PATH;
		$confs['installation'] = SG_SITE_TYPE;
		$freeSpace = convertToReadableSize(@disk_free_space(SG_APP_ROOT_DIRECTORY));
		$confs['free_space'] = $freeSpace==false?'unknown':$freeSpace;
		$isCurlAvailable = function_exists('curl_version');
		$confs['curl_available'] = $isCurlAvailable ? 'Yes': 'No';
		$confs['email_notifications'] = SGConfig::get('SG_NOTIFICATIONS_ENABLED') ? 'ON': 'OFF';
		$confs['ftp_passive_mode'] = SGConfig::get('SG_FTP_PASSIVE_MODE') ? 'ON': 'OFF';

		if (extension_loaded('gmp')) $lib = 'gmp';
		else if (extension_loaded('bcmath')) $lib = 'bcmath';
		else $lib = 'BigInteger';

		$confs['int_lib'] = $lib;
		$confs['memory_limit'] = SGBoot::$memoryLimit;
		$confs['max_execution_time'] = SGBoot::$executionTimeLimit;
		$confs['env'] = SG_ENV_ADAPTER.' '.SG_ENV_VERSION;

		$content = '';
		$content .= 'Date: '.backupGuardConvertDateTimezone(@date('Y-m-d H:i')).' '.date_default_timezone_get().PHP_EOL;
		$content .= 'Reloads: '.$confs['method'].PHP_EOL;

		if ($actionType == SG_ACTION_TYPE_RESTORE) {
			$confs['restore_method'] = SGExternalRestore::isEnabled()?'external':'standard';
			$content .= 'Restore Method: '.$confs['restore_method'].PHP_EOL;
		}

		$content .= 'User mode: '.backupGuardGetProductName().PHP_EOL;
		$content .= 'BackupGuard version: '.$confs['sg_backup_guard_version'].PHP_EOL;
		$content .= 'Supported archive version: '.$confs['sg_archive_version'].PHP_EOL;

		$content .= 'Database prefix: '.$confs['dbprefix'].PHP_EOL;
		$content .= 'Site URL: '.$confs['siteurl'].PHP_EOL;
		$content .= 'Home URL: '.$confs['homeurl'].PHP_EOL;
		$content .= 'Uploads path: '.$confs['uploadspath'].PHP_EOL;
		$content .= 'Site installation: '.$confs['installation'].PHP_EOL;

		$content .= 'OS: '.$confs['os'].PHP_EOL;
		$content .= 'Server: '.$confs['server'].PHP_EOL;
		$content .= 'User agent: '.@$_SERVER['HTTP_USER_AGENT'].PHP_EOL;
		$content .= 'PHP version: '.$confs['php_version'].PHP_EOL;
		$content .= 'MySQL version: '.$confs['mysql_version'].PHP_EOL;
		$content .= 'Int size: '.$confs['int_size'].PHP_EOL;
		$content .= 'Int lib: '.$confs['int_lib'].PHP_EOL;
		$content .= 'Memory limit: '.$confs['memory_limit'].PHP_EOL;
		$content .= 'Max execution time: '.$confs['max_execution_time'].PHP_EOL;
		$content .= 'Disk free space: '.$confs['free_space'].PHP_EOL;
		$content .= 'CURL available: '.$confs['curl_available'].PHP_EOL;
		$content .= 'Openssl version: '.OPENSSL_VERSION_TEXT.PHP_EOL;
		if ($isCurlAvailable) {
			$cv = curl_version();
			$curlVersionText = $cv['version'].' / SSL: '.$cv['ssl_version'].' / libz: '.$cv['libz_version'];
			$content .= 'CURL version: '.$curlVersionText.PHP_EOL;
		}
		$content .= 'Email notifications: '.$confs['email_notifications'].PHP_EOL;
		$content .= 'FTP passive mode: '.$confs['ftp_passive_mode'].PHP_EOL;
		$content .= 'Exclude paths: '.SGConfig::get('SG_PATHS_TO_EXCLUDE').PHP_EOL;
		$content .= 'Tables to exclude: '.SGConfig::get('SG_TABLES_TO_EXCLUDE').PHP_EOL;
		$content .= 'Number of rows to backup: '.(int)SGConfig::get('SG_BACKUP_DATABASE_INSERT_LIMIT').PHP_EOL;
		$content .= 'AJAX request frequency: '.SGConfig::get('SG_AJAX_REQUEST_FREQUENCY').PHP_EOL;

		if ($actionType == SG_ACTION_TYPE_RESTORE) {
			$archivePath = SG_BACKUP_DIRECTORY.$fileName.'/'.$fileName.'.sgbp';
			$archiveSizeInBytes = backupGuardRealFilesize($archivePath);
			$confs['archiveSize'] = convertToReadableSize($archiveSizeInBytes);
			$content .= 'Archive Size: '.$confs['archiveSize'].' ('.$archiveSizeInBytes.' bytes)'.PHP_EOL;
		}

		$content .= 'Environment: '.$confs['env'].PHP_EOL;

		return $content;
	}

	private function didFindWarnings()
	{
		$warningsDatabase = $this->databaseBackupAvailable?$this->backupDatabase->didFindWarnings():false;
		$warningsFiles = $this->backupFiles->didFindWarnings();
		return ($warningsFiles||$warningsDatabase);
	}

	public static function createAction($name, $type, $status, $subtype = 0, $options = '')
	{
		$sgdb = SGDatabase::getInstance();

		$date = backupGuardConvertDateTimezone(@date('Y-m-d H:i:s'));
		$res = $sgdb->query('INSERT INTO '.SG_ACTION_TABLE_NAME.' (name, type, subtype, status, start_date, options) VALUES (%s, %d, %d, %d, %s, %s)', array($name, $type, $subtype, $status, $date, $options));

		if (!$res) {
			throw new SGExceptionDatabaseError('Could not create action');
		}
		return $sgdb->lastInsertId();
	}

	private function getCurrentActionStatus()
	{
		return self::getActionStatus($this->actionId);
	}

	private function setCurrentActionStatusCancelled()
	{
		$sgdb = SGDatabase::getInstance();
		$date = backupGuardConvertDateTimezone(@date('Y-m-d H:i:s'));
		$sgdb->query('UPDATE '.SG_ACTION_TABLE_NAME.' SET status=%d, update_date=%s WHERE name=%s', array(SG_ACTION_STATUS_CANCELLED, $date, $this->fileName));
	}

	public static function changeActionStatus($actionId, $status)
	{
		$sgdb = SGDatabase::getInstance();

		$progress = '';
		if ($status==SG_ACTION_STATUS_FINISHED || $status==SG_ACTION_STATUS_FINISHED_WARNINGS)
		{
			$progress = 100;
		}
		else if ($status==SG_ACTION_STATUS_CREATED || $status==SG_ACTION_STATUS_IN_PROGRESS_FILES || $status==SG_ACTION_STATUS_IN_PROGRESS_DB)
		{
			$progress = 0;
		}

		if ($progress!=='')
		{
			$progress = ' progress='.$progress.',';
		}

		$date = backupGuardConvertDateTimezone(@date('Y-m-d H:i:s'));
		$res = $sgdb->query('UPDATE '.SG_ACTION_TABLE_NAME.' SET status=%d,'.$progress.' update_date=%s WHERE id=%d', array($status, $date, $actionId));
	}

	public static function changeActionProgress($actionId, $progress)
	{
		$sgdb = SGDatabase::getInstance();
		$date = backupGuardConvertDateTimezone(@date('Y-m-d H:i:s'));
		$sgdb->query('UPDATE '.SG_ACTION_TABLE_NAME.' SET progress=%d, update_date=%s WHERE id=%d', array($progress, $date, $actionId));
	}

	/* Methods for frontend use */

	public static function getAction($actionId)
	{
		$sgdb = SGDatabase::getInstance();
		$res = $sgdb->query('SELECT * FROM '.SG_ACTION_TABLE_NAME.' WHERE id=%d', array($actionId));
		if (empty($res))
		{
			return false;
		}
		return $res[0];
	}

	public static function getActionByName($name)
	{
		$sgdb = SGDatabase::getInstance();
		$res = $sgdb->query('SELECT * FROM '.SG_ACTION_TABLE_NAME.' WHERE name=%s', array($name));
		if (empty($res)) {
			return false;
		}
		return $res[0];
	}

	public static function getActionProgress($actionId)
	{
		$sgdb = SGDatabase::getInstance();
		$res = $sgdb->query('SELECT progress FROM '.SG_ACTION_TABLE_NAME.' WHERE id=%d', array($actionId));
		if (empty($res))
		{
			return false;
		}
		return (int)$res[0]['progress'];
	}

	public static function getActionStatus($actionId)
	{
		$sgdb = SGDatabase::getInstance();
		$res = $sgdb->query('SELECT status FROM '.SG_ACTION_TABLE_NAME.' WHERE id=%d', array($actionId));
		if (empty($res))
		{
			return false;
		}
		return (int)$res[0]['status'];
	}

	public static function deleteActionById($actionId)
	{
		$sgdb = SGDatabase::getInstance();
		$res = $sgdb->query('DELETE FROM '.SG_ACTION_TABLE_NAME.' WHERE id=%d', array($actionId));

		return $res;
	}

	public static function cleanRunningActions($runningActions)
	{
		if (empty($runningActions)) {
			return false;
		}
		foreach ($runningActions as $action) {
			if (empty($action)) {
				continue;
			}
			if ($action['status'] == SG_ACTION_STATUS_IN_PROGRESS_FILES || $action['status'] == SG_ACTION_STATUS_IN_PROGRESS_DB) {
				$id = $action['id'];
				SGBackup::deleteActionById($id);
			}
		}

		return true;
	}

	public static function getRunningActions()
	{
		$sgdb = SGDatabase::getInstance();
		$res = $sgdb->query('SELECT * FROM '.SG_ACTION_TABLE_NAME.' WHERE status=%d OR status=%d OR status=%d ORDER BY status DESC', array(SG_ACTION_STATUS_IN_PROGRESS_FILES, SG_ACTION_STATUS_IN_PROGRESS_DB, SG_ACTION_STATUS_CREATED));
		return $res;
	}

	public static function getBackupFileInfo($file)
	{
		return pathinfo(SG_BACKUP_DIRECTORY.$file);
	}

	public static function autodetectBackups()
	{
		$path = SG_BACKUP_DIRECTORY;
		$files = scandir(SG_BACKUP_DIRECTORY);
		$backupLogPostfix = "_backup.log";
		$restoreLogPostfix = "_restore.log";

		foreach ($files as $file) {
			$fileInfo = self::getBackupFileInfo($file);

			if (!empty($fileInfo['extension']) && $fileInfo['extension'] == SGBP_EXT) {
				@mkdir($path.$fileInfo['filename'], 0777);

				if(file_exists($path.$fileInfo['filename'])) {
					rename($path.$file, $path.$fileInfo['filename'].'/'.$file);
				}

				if(file_exists($path.$fileInfo['filename'].$backupLogPostfix)){
					rename($path.$fileInfo['filename'].$backupLogPostfix, $path.$fileInfo['filename'].'/'.$fileInfo['filename'].$backupLogPostfix);
				}

				if (file_exists($path.$fileInfo['filename'].$restoreLogPostfix)) {
					rename($path.$fileInfo['filename'].$restoreLogPostfix, $path.$fileInfo['filename'].'/'.$fileInfo['filename'].$restoreLogPostfix);
				}
			}
		}
	}

	public static function getAllBackups()
	{
		$backups = array();

		$path = SG_BACKUP_DIRECTORY;
		self::autodetectBackups();
		clearstatcache();

		$action = self::getRunningActions();
		if (SGBoot::isFeatureAvailable('NUMBER_OF_BACKUPS_TO_KEEP') && !count($action) && function_exists('backupGuardOutdatedBackupsCleanup')) {
			backupGuardOutdatedBackupsCleanup($path);
		}

		//remove external restore file
		SGExternalRestore::getInstance()->cleanup();

		if ($handle = @opendir($path)) {
			$sgdb = SGDatabase::getInstance();
			$data = $sgdb->query('SELECT id, name, type, subtype, status, progress, update_date, options FROM '.SG_ACTION_TABLE_NAME);
			$allBackups = array();
			foreach ($data as $row) {
				$allBackups[$row['name']][] = $row;
			}

			while (($entry = readdir($handle)) !== false) {
				if ($entry === '.' || $entry === '..' || !is_dir($path.$entry)) {
					continue;
				}

				$backup = array();
				$backup['name'] = $entry;
				$backup['id'] = '';
				$backup['status'] = '';
				$backup['files'] = file_exists($path.$entry.'/'.$entry.'.sgbp')?1:0;
				$backup['backup_log'] = file_exists($path.$entry.'/'.$entry.'_backup.log')?1:0;
				$backup['restore_log'] = file_exists($path.$entry.'/'.$entry.'_restore.log')?1:0;
				$backup['options'] = '';
				if (!$backup['files'] && !$backup['backup_log'] && !$backup['restore_log']) {
					continue;
				}
				$backupRow = null;
				if (isset($allBackups[$entry])) {
					$skip = false;
					foreach ($allBackups[$entry] as $row) {
						if ($row['status']==SG_ACTION_STATUS_IN_PROGRESS_FILES || $row['status']==SG_ACTION_STATUS_IN_PROGRESS_DB) {
							$backupRow = $row;
							break;
						}
						else if (($row['status']==SG_ACTION_STATUS_CANCELLING || $row['status']==SG_ACTION_STATUS_CANCELLED) && $row['type']!=SG_ACTION_TYPE_UPLOAD) {
							$skip = true;
							break;
						}

						$backupRow = $row;

						if ($row['status']==SG_ACTION_STATUS_FINISHED_WARNINGS || $row['status']==SG_ACTION_STATUS_ERROR) {
							if ($row['type'] == SG_ACTION_TYPE_UPLOAD && file_exists(SG_BACKUP_DIRECTORY.$entry.'/'.$entry.'.sgbp')) {
								$backupRow['status'] = SG_ACTION_STATUS_FINISHED_WARNINGS;
							}
						}
					}

					if ($skip===true) {
						continue;
					}
				}

				if ($backupRow) {
					$backup['active'] = ($backupRow['status']==SG_ACTION_STATUS_IN_PROGRESS_FILES||
					$backupRow['status']==SG_ACTION_STATUS_IN_PROGRESS_DB||
					$backupRow['status']==SG_ACTION_STATUS_CREATED)?1:0;

					$backup['status'] = $backupRow['status'];
					$backup['type'] = (int)$backupRow['type'];
					$backup['subtype'] = (int)$backupRow['subtype'];
					$backup['progress'] = (int)$backupRow['progress'];
					$backup['id'] = (int)$backupRow['id'];
					$backup['options'] = $backupRow['options'];
				}
				else {
					$backup['active'] = 0;
				}

				$size = '';
				if ($backup['files']) {
					$size = number_format(backupGuardRealFilesize($path.$entry.'/'.$entry.'.sgbp')/1000.0/1000.0, 2, '.', '').' MB';
				}

				$backup['size'] = $size;

				$modifiedTime = filemtime($path.$entry.'/.');
				$date = backupGuardConvertDateTimezone(@date('Y-m-d H:i', $modifiedTime));
				$backup['date'] = $date;
				$backup['modifiedTime'] = $modifiedTime;
				$backups[] = $backup;
			}
			closedir($handle);
		}

		usort($backups, array('SGBackup', 'sort'));
		return array_values($backups);
	}

	public static function sort($arg1, $arg2)
	{
		return $arg1['modifiedTime']>$arg2['modifiedTime']?-1:1;
	}

	public static function deleteBackup($backupName, $deleteAction = true)
	{
		$isDeleteBackupFromCloudEnabled = SGConfig::get('SG_DELETE_BACKUP_FROM_CLOUD');
		if($isDeleteBackupFromCloudEnabled) {
			$backupRow = self::getActionByName($backupName);
			if ($backupRow) {
				$options = $backupRow['options'];
				if ($options) {
					$options = json_decode($options, true);

					if (!empty($options['SG_BACKUP_UPLOAD_TO_STORAGES'])) {
						$storages = explode(',', $options['SG_BACKUP_UPLOAD_TO_STORAGES']);
						self::deleteBackupFromCloud($storages, $backupName);
					}
				}
			}
		}

		backupGuardDeleteDirectory(SG_BACKUP_DIRECTORY.$backupName);

		if ($deleteAction) {
			$sgdb = SGDatabase::getInstance();
			$sgdb->query('DELETE FROM '.SG_ACTION_TABLE_NAME.' WHERE name=%s', array($backupName));
		}
	}

	private static function deleteBackupFromCloud($storages, $backupName)
	{
		foreach ($storages as $storage) {
			$storage = (int)$storage;

			$sgBackupStorage = SGBackupStorage::getInstance();
			$sgBackupStorage->deleteBackupFromStorage($storage, $backupName);
		}
	}

	public static function cancelAction($actionId)
	{
		self::changeActionStatus($actionId, SG_ACTION_STATUS_CANCELLING);
	}

	public static function importKeyFile($sgSshKeyFile)
	{
		$filename = $sgSshKeyFile['name'];
		$uploadPath = SG_BACKUP_DIRECTORY.SG_SSH_KEY_FILE_FOLDER_NAME;
		$filename = $uploadPath.$filename;

		if (!@file_exists($uploadPath)) {
			if (!@mkdir($uploadPath)) {
				throw new SGExceptionForbidden('SSH key file folder is not accessible');
			}
		}

		if (!empty($sgSshKeyFile) && $sgSshKeyFile['name'] != '') {
			if (!@move_uploaded_file($sgSshKeyFile['tmp_name'], $filename)) {
				throw new SGExceptionForbidden('Error while uploading ssh key file');
			}
		}
	}

	public static function upload($filesUploadSgbp)
	{
		$filename = str_replace('.sgbp', '', $filesUploadSgbp['name']);
		$backupDirectory = $filename.'/';
		$uploadPath = SG_BACKUP_DIRECTORY.$backupDirectory;
		$filename = $uploadPath.$filename;

		if (!@file_exists($uploadPath))
		{
			if (!@mkdir($uploadPath))
			{
				throw new SGExceptionForbidden('Upload folder is not accessible');
			}
		}

		if (!empty($filesUploadSgbp) && $filesUploadSgbp['name'] != '')
		{
			if ($filesUploadSgbp['type'] != 'application/octet-stream')
			{
				throw new SGExceptionBadRequest('Not a valid backup file');
			}
			if (!@move_uploaded_file($filesUploadSgbp['tmp_name'], $filename.'.sgbp'))
			{
				throw new SGExceptionForbidden('Error while uploading file');
			}
		}
	}

	public static function download($filename, $type)
	{
		$backupDirectory = SG_BACKUP_DIRECTORY.$filename.'/';
		$backupName = $filename;
		$downloadViaPhp = SGConfig::get('SG_DOWNLOAD_VIA_PHP');

		switch ($type)
		{
            case SG_BACKUP_DOWNLOAD_TYPE_SGBP:
                $filename .= '.sgbp';
                if ($downloadViaPhp) {
                    backupGuardDownloadFile($backupDirectory.$filename);
                }
                else {
                    backupGuardDownloadFileSymlink($backupDirectory, $filename);
                }
                break;
            case SG_BACKUP_DOWNLOAD_TYPE_BACKUP_LOG:
                $filename .= '_backup.log';
                backupGuardDownloadFile($backupDirectory.$filename, 'text/plain');
                break;
            case SG_BACKUP_DOWNLOAD_TYPE_RESTORE_LOG:
                $filename .= '_restore.log';
                backupGuardDownloadFile($backupDirectory.$filename, 'text/plain');
                break;
		}

		exit;
	}

	/* SGIBackupDelegate implementation */

	public function isCancelled()
	{
		$status = $this->getCurrentActionStatus();

		if ($status==SG_ACTION_STATUS_CANCELLING)
		{
			$this->cancel();
			return true;
		}

		return false;
	}

	public function didUpdateProgress($progress)
	{
		$progress = max($progress, 0);
		$progress = min($progress, 100);

		self::changeActionProgress($this->actionId, $progress);
	}

	public function isBackgroundMode()
	{
		return $this->backgroundMode;
	}

	public function setIsManual($isManual)
	{
		$this->isManual = $isManual;
	}

	public function getIsManual()
	{
		return $this->isManual;
	}
}
