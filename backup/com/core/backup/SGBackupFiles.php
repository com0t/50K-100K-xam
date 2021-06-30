<?php
require_once(SG_BACKUP_PATH.'SGIBackupDelegate.php');
require_once(SG_BACKUP_PATH.'SGBackup.php');
require_once(SG_LIB_PATH.'SGArchive.php');
require_once(SG_LIB_PATH.'SGReloadHandler.php');
require_once(SG_LIB_PATH.'SGFileState.php');

require_once(SG_LIB_PATH.'SGFileEntry.php');
require_once(SG_LIB_PATH.'SGCdrEntry.php');

class SGBackupFiles implements SGArchiveDelegate
{
	const BUFFER_SIZE = 1000; // max files count to keep in buffer before writing to file tree
	private $rootDirectory = '';
	private $excludeFilePaths = array();
	private $filePath = '';
	private $sgbp = null;
	private $delegate = null;
	private $filesActionStartTs = 0;
	private $nextProgressUpdate = 0;
	private $progressUpdateInterval = 0;
	private $warningsFound = false;
	private $dontExclude = array();
	private $cdrSize = 0;
	private $pendingStorageUploads = array();
	private $fileName = '';
	private $progressCursor = 0;
	private $numberOfEntries = 0;
	private $cursor = 0;
	private $reloadStartTs;

	public function __construct()
	{
		$this->rootDirectory = rtrim(SGConfig::get('SG_APP_ROOT_DIRECTORY'), '/').'/';
		$this->progressUpdateInterval = SGConfig::get('SG_ACTION_PROGRESS_UPDATE_INTERVAL');
	}

	public function setDelegate(SGIBackupDelegate $delegate)
	{
		$this->delegate = $delegate;
	}

	public function setFilePath($filePath)
	{
		$this->filePath = $filePath;
	}

	public function setFileName($fileName)
	{
		$this->fileName = $fileName;
	}

	public function setPendingStorageUploads($pendingStorageUploads)
	{
		$this->pendingStorageUploads = $pendingStorageUploads;
	}

	public function addDontExclude($ex)
	{
		$this->dontExclude[] = $ex;
	}

	public function didExtractArchiveMeta($meta)
	{
		$file = dirname($this->filePath).'/'.$this->fileName.'_restore.log';

		if (file_exists($file)) {
			$archiveVersion = SGConfig::get('SG_CURRENT_ARCHIVE_VERSION');

			$content = '';
			$content .= '---'.PHP_EOL;
			$content .= 'Archive version: '.$archiveVersion.PHP_EOL;
			$content .= 'Archive database prefix: '.$meta['dbPrefix'].PHP_EOL;
			$content .= 'Archive site URL: '.$meta['siteUrl'].PHP_EOL.PHP_EOL;

			file_put_contents($file, $content, FILE_APPEND);
		}
	}

	public function didFindWarnings()
	{
		return $this->warningsFound;
	}

	private function addEntriesInFileTree($entries)
	{
		foreach ($entries as $entry) {
			file_put_contents(dirname($this->filePath).'/'.SG_TREE_FILE_NAME, serialize($entry)."\n", FILE_APPEND);
		}
	}

	private function loadFileTree()
	{
		$allItems = file_get_contents(dirname($this->filePath).'/'.SG_TREE_FILE_NAME);
		return unserialize($allItems);
	}

	public function shouldReload()
	{
		$currentTime = time();

		if (($currentTime - $this->reloadStartTs) >= SG_RELOAD_TIMEOUT) {
			return true;
		}

		return false;
	}

	public function getState()
	{
		return $this->delegate->getState();
	}

	public function backup($filePath, $options, $state)
	{
		$this->reloadStartTs = time();
		if ($state->getAction() == SG_STATE_ACTION_PREPARING_STATE_FILE) {
			$this->filesActionStartTs = time();
			$state->setFilesActionStartTs($this->filesActionStartTs);
			SGBackupLog::writeAction('backup files', SG_BACKUP_LOG_POS_START);
		}

		if (strlen($options['SG_BACKUP_FILE_PATHS_EXCLUDE'])) {
			$excludePaths = $options['SG_BACKUP_FILE_PATHS_EXCLUDE'];
			$userCustomExcludes = SGConfig::get('SG_PATHS_TO_EXCLUDE');
			if (!empty($userCustomExcludes)) {
				$excludePaths .= ','.$userCustomExcludes;
			}

			$this->excludeFilePaths = explode(',', $excludePaths);
		}
		else{
			$this->excludeFilePaths = array();
		}

		$this->filePath = $filePath;
		$backupItems = $options['SG_BACKUP_FILE_PATHS'];
		$allItems = explode(',', $backupItems);

		if (!is_writable($filePath)) {
			throw new SGExceptionForbidden('Could not create backup file: '.$filePath);
		}

		if ($state->getAction() == SG_STATE_ACTION_PREPARING_STATE_FILE) {

			$this->resetProgress();
			$this->prepareFileTree($allItems);

			$this->saveStateData(SG_STATE_ACTION_LISTING_FILES, array(), 0, 0, false, 0);

			SGBackupLog::write('Number of files to backup: '.$this->numberOfEntries);
			SGBackupLog::write('Root path: '.$this->filePath.'/');

			if (backupGuardIsReloadEnabled()) {
				$this->reload();
			}
		}
		else {
			$this->nextProgressUpdate = $state->getProgress();
			$this->warningsFound = $state->getWarningsFound();

			$this->numberOfEntries = $state->getNumberOfEntries();
			$this->progressCursor = $state->getProgressCursor();
			$this->filesActionStartTs = $state->getFilesActionStartTs();
		}

		$this->cdrSize = $state->getCdrSize();
		$this->sgbp = new SGArchive($filePath, 'a', $this->cdrSize);
		$this->sgbp->setDelegate($this);

		$this->cursor = $state->getCursor();

		if (file_exists(dirname($this->filePath).'/'.SG_TREE_FILE_NAME)) {
			$fileTreeHandle = fopen(dirname($this->filePath).'/'.SG_TREE_FILE_NAME, 'r');
			if ($fileTreeHandle) {
				fseek($fileTreeHandle, $this->cursor);
				while (($fileTreeLine = fgets($fileTreeHandle)) !== false) {
					$file = unserialize($fileTreeLine);
					$filePath = str_replace(ABSPATH, '', $file['path']);
					if (!$state->getInprogress()) {
						SGBackupLog::writeAction('backup file: '.$filePath, SG_BACKUP_LOG_POS_START);
					}

					$path = $file['path'];
					$this->addFileToArchive($path);
					SGBackupLog::writeAction('backup file: '.$filePath, SG_BACKUP_LOG_POS_END);

					$this->cursor = ftell($fileTreeHandle);
					$this->cdrSize = $this->sgbp->getCdrFilesCount();
					$this->saveStateData(SG_STATE_ACTION_COMPRESSING_FILES, array(), 0, 0, false, $state->getFileOffsetInArchive());
				}
			}
		}

		$this->sgbp->finalize();
		$this->clear();

		SGBackupLog::writeAction('backup files', SG_BACKUP_LOG_POS_END);
		SGBackupLog::write('backup files total duration: '.backupGuardFormattedDuration($this->filesActionStartTs, time()));
	}

	private function clear()
	{
		@unlink(dirname($this->filePath).'/'.SG_TREE_FILE_NAME);
	}

	public function reload()
	{
		$this->delegate->reload();
	}

	public function getToken()
	{
		return $this->delegate->getToken();
	}

	public function getProgress()
	{
		return $this->nextProgressUpdate;
	}

	public function saveStateData($action, $ranges = array(), $offset = 0, $headerSize = 0, $inprogress = false, $fileOfssetInArchive = 0)
	{
		$sgFileState = $this->delegate->getState();
		$token = $this->getToken();

		$sgFileState->setInprogress($inprogress);
		$sgFileState->setHeaderSize($headerSize);
		$sgFileState->setRanges($ranges);
		$sgFileState->setOffset($offset);
		$sgFileState->setToken($token);
		$sgFileState->setAction($action);
		$sgFileState->setProgress($this->nextProgressUpdate);
		$sgFileState->setWarningsFound($this->warningsFound);
		$sgFileState->setCdrSize($this->cdrSize);
		$sgFileState->setPendingStorageUploads($this->pendingStorageUploads);
		$sgFileState->setNumberOfEntries($this->numberOfEntries);
		$sgFileState->setCursor($this->cursor);
		$sgFileState->setFileOffsetInArchive($fileOfssetInArchive);
		$sgFileState->setProgressCursor($this->progressCursor);

		$sgFileState->save();
	}

	public function didStartRestoreFiles()
	{
		//start logging
		SGBackupLog::writeAction('restore', SG_BACKUP_LOG_POS_START);
		SGBackupLog::writeAction('restore files', SG_BACKUP_LOG_POS_START);
		$this->filesActionStartTs = time();
	}

	public function restore($filePath)
	{
		$this->reloadStartTs = time();
		$state = $this->getState();
		$this->filePath = $filePath;
		$this->resetProgress();
		$this->warningsFound = false;

		if ($state) {
			$this->nextProgressUpdate = $state->getProgress();
			$this->warningsFound = $state->getWarningsFound();
			$this->progressCursor = $state->getCursor();
			$this->numberOfEntries = $state->getCdrSize();
			$this->filesActionStartTs = $state->getFilesActionStartTs();
		}

		$this->extractArchive($filePath);
		SGBackupLog::writeAction('restore files', SG_BACKUP_LOG_POS_END);
		SGBackupLog::write('restore files total duration: '.backupGuardFormattedDuration($this->filesActionStartTs, time()));
	}

	private function extractArchive($filePath)
	{
		$restorePath = $this->rootDirectory;

		$state = $this->getState();
		$sgbp = new SGArchive($filePath, 'r');
		$sgbp->setDelegate($this);
		$sgbp->extractTo($restorePath, $state);
	}

	public function getCorrectCdrFilename($filename)
	{
		$backupsPath = $this->pathWithoutRootDirectory(realpath(SG_BACKUP_DIRECTORY));

		if (strpos($filename, $backupsPath)===0)
		{
			$newPath = dirname($this->pathWithoutRootDirectory(realpath($this->filePath)));
			$filename = substr(basename(trim($this->filePath)), 0, -4); //remove sgbp extension
			return $newPath.'/'.$filename.'sql';
		}

		return $filename;
	}

	public function didStartExtractFile($filePath)
	{
		SGBackupLog::write('Start restore file: '.$filePath);
	}

	public function didExtractFile($filePath)
	{
		//update progress
		$this->progressCursor++;
		$this->updateProgress();

		SGBackupLog::write('End restore file: '.$filePath);
	}

	public function didFindExtractError($error)
	{
		$this->warn($error);
	}

	public function didCountFilesInsideArchive($count)
	{
		$this->numberOfEntries = $count;
		SGBackupLog::write('Number of files to restore: '.$count);
		$state = $this->getState();
		$this->filesActionStartTs = time();
		$state->setFilesActionStartTs($this->filesActionStartTs);
	}

	private function prepareFileTree($allItems)
	{
		$entries = array();
		
		/**
		  * ToDo check this logic
		 */
		//file_put_contents(dirname($this->filePath).'/'.SG_TREE_FILE_NAME, "");

		foreach ($allItems as $item) {
			$path = $this->rootDirectory.$item;
			$this->addDirectoryEntriesInFileTree($path, $entries);
		}

		if (count($entries)) {
			$this->addEntriesInFileTree($entries);
		}
	}

	private function resetProgress()
	{
		$this->progressCursor = 0;
		$this->nextProgressUpdate = $this->progressUpdateInterval;
	}

	private function pathWithoutRootDirectory($path)
	{
		return substr($path, strlen($this->rootDirectory));
	}

	private function shouldExcludeFile($path)
	{
		if (in_array($path, $this->dontExclude)) {
			return false;
		}

		//get the name of the file/directory removing the root directory
		$file = $this->pathWithoutRootDirectory($path);

		//check if file/directory must be excluded
		foreach ($this->excludeFilePaths as $exPath) {
			$exPath = trim($exPath);
			$exPath = trim($exPath, '/');
			if (strpos($file, $exPath)===0) {
				return true;
			}
		}

		return false;
	}

	private function addDirectoryEntriesInFileTree($path, &$entries = array())
	{
		if ($this->shouldExcludeFile($path)) return;
		SGPing::update();
		if (is_dir($path)) {
			if ($handle = @opendir($path)) {
				while (($file = readdir($handle)) !== false) {
					if ($file === '.' || $file === '..') {
						continue;
					}

					if (SG_ENV_ADAPTER == SG_ENV_WORDPRESS) {
						if (($path == $this->rootDirectory || $path == $this->rootDirectory.'wp-content') && strpos($file, 'backup') !== false) {
							continue;
						}
					}

					$this->addDirectoryEntriesInFileTree($path.'/'.$file, $entries);
				}

				closedir($handle);
			}
			else {
				$this->warn('Could not read directory (skipping): '.$path);
			}
		}
		else {
			if (is_readable($path)) {
				$dateModified = filemtime($path);

				$fileEntry = new SGFileEntry();
				$fileEntry->setName(basename($path));
				$fileEntry->setPath($path);
				$fileEntry->setDateModified($dateModified);

				$this->numberOfEntries++;
				array_push($entries, $fileEntry->toArray());

				if (count($entries) > self::BUFFER_SIZE) {
					$this->addEntriesInFileTree($entries);
					$entries = array();
				}
			}
			else {
				$this->warn('Path is not readable (skipping): '.$path);
			}
		}
	}

	public function cancel()
	{
		@unlink($this->filePath);
	}

	private function addFileToArchive($path)
	{
		if ($this->shouldExcludeFile($path)) return true;

		//check if it is a directory
		if (is_dir($path))
		{
			$this->backupDirectory($path);
			return;
		}

		//it is a file, try to add it to archive
		if (is_readable($path))
		{
			$file = substr($path, strlen($this->rootDirectory));
			$file = str_replace('\\', '/', $file);
			$this->sgbp->addFileFromPath($file, $path);
		}
		else
		{
			$this->warn('Could not read file (skipping): '.$path);
		}

		//update progress and check cancellation
		$this->progressCursor++;
		if ($this->updateProgress())
		{
			if ($this->delegate && $this->delegate->isCancelled())
			{
				return;
			}
		}

		if (SGBoot::isFeatureAvailable('BACKGROUND_MODE') && $this->delegate->isBackgroundMode())
		{
			SGBackgroundMode::next();
		}
	}

	private function backupDirectory($path)
	{
		if ($handle = @opendir($path))
		{
			$filesFound = false;
			while (($file = readdir($handle)) !== false)
			{
				if ($file === '.')
				{
					continue;
				}
				if ($file === '..')
				{
					continue;
				}

				$filesFound = true;
				$this->addFileToArchive($path.'/'.$file);
			}

			if (!$filesFound)
			{
				$file = substr($path, strlen($this->rootDirectory));
				$file = str_replace('\\', '/', $file);
				$this->sgbp->addFile($file.'/', ''); //create empty directory
			}

			closedir($handle);
		}
		else
		{
			$this->warn('Could not read directory (skipping): '.$path);
		}
	}

	public function warn($message)
	{
		$this->warningsFound = true;
		SGBackupLog::writeWarning($message);
	}

	private function updateProgress()
	{
		$progress = round($this->progressCursor*100.0/$this->numberOfEntries);

		if ($progress>=$this->nextProgressUpdate)
		{
			$this->nextProgressUpdate += $this->progressUpdateInterval;

			if ($this->delegate)
			{
				$this->delegate->didUpdateProgress($progress);
			}

			return true;
		}

		return false;
	}
}
