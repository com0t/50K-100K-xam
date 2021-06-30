<?php

namespace FernleafSystems\Wordpress\Services\Utilities\File;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class LocateStrInFile
 * @package FernleafSystems\Wordpress\Services\Utilities\File
 */
class LocateStrInFile {

	/**
	 * @var string
	 */
	private $needle;

	/**
	 * @var string
	 */
	private $content;

	/**
	 * @var string
	 */
	private $path;

	/**
	 * @var string[]
	 */
	private $lines;

	/**
	 * @var bool
	 */
	private $isRegExNeedle;

	/**
	 * @return string[]
	 */
	public function run() :array {
		return $this->isRegEx() ? $this->runAsRegEx() : $this->runAsSimple();
	}

	/**
	 * @return string[] - keys are line numbers
	 */
	protected function runAsRegEx() :array {
		$lines = [];

		if ( preg_match_all( '/('.$this->getNeedle().')/i', $this->getContent(), $matches, PREG_PATTERN_ORDER ) ) {
			foreach ( $matches[ 0 ] as $match ) {
				// use + for numerical index
				$lines = $lines + $this->findLinesFor( $match );
			}
		}
		return $lines;
	}

	protected function findLinesFor( string $for ) :array {
		return array_filter(
			$this->getLines(),
			function ( $line ) use ( $for ) {
				return stripos( $line, $for ) !== false;
			}
		);
	}

	/**
	 * @return string[] - keys are line numbers
	 */
	protected function runAsSimple() :array {
		$lines = [];
		if ( stripos( $this->getContent(), $this->getNeedle() ) !== false ) {
			$lines = $lines + $this->findLinesFor( $this->getNeedle() );
		}
		return $lines;
	}

	/**
	 * @param $sPath
	 * @return int[]
	 * @throws \InvalidArgumentException
	 * @deprecated
	 */
	public function inFile( $sPath ) :array {
		return $this->setPath( $sPath )
					->run();
	}

	/**
	 * @return string[]
	 */
	protected function getLines() :array {
		if ( is_null( $this->lines ) ) {
			$this->lines = array_filter(
				array_map( 'trim', preg_split( '/\r\n|\r|\n/', $this->getContent() ) )
			);
		}
		return $this->lines;
	}

	public function getContent() :string {
		if ( is_null( $this->content ) ) {
			$this->content = Services::WpFs()->getFileContent( $this->getPath() );
		}
		return $this->content;
	}

	public function getNeedle() :string {
		return $this->needle;
	}

	public function getPath() :string {
		return $this->path;
	}

	public function isRegEx() :bool {
		return (bool)$this->isRegExNeedle;
	}

	public function setIsRegEx( bool $isRegEx ) :self {
		$this->isRegExNeedle = $isRegEx;
		return $this;
	}

	public function setNeedle( string $needle ) :self {
		$this->needle = $needle;
		return $this;
	}

	/**
	 * @param string $path
	 * @return $this
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	public function setPath( string $path ) :self {
		if ( !Services::WpFs()->isFile( $path ) ) {
			throw new \InvalidArgumentException( "File doesn't exist" );
		}
		if ( !is_readable( $path ) ) {
			throw new \Exception( "File isn't readable" );
		}
		$this->path = $path;
		$this->getContent();
		return $this->reset();
	}

	protected function reset() :self {
		$this->content = null;
		$this->lines = null;
		return $this;
	}
}