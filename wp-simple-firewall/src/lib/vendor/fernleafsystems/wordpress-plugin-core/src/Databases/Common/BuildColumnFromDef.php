<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Core\Databases\Common;

use FernleafSystems\Wordpress\Services\Services;

class BuildColumnFromDef {

	const MACROTYPE_PRIMARYID = 'primary_id';
	const MACROTYPE_TIMESTAMP = 'timestamp';
	const MACROTYPE_UNSIGNEDINT = 'unsigned_int';
	const MACROTYPE_HASH = 'hash';
	const MACROTYPE_IP = 'ip';
	const MACROTYPE_META = 'meta';
	const MACROTYPE_TEXT = 'text';
	const MACROTYPE_URL = 'url';
	const MACROTYPE_BOOL = 'bool';
	const MACROTYPE_VARCHAR = 'varchar';

	private $def;

	public function __construct( array $def ) {
		$this->setDef( $def );
	}

	public function setDef( array $def ) {
		$this->def = $def;
		return $this;
	}

	public function build() :string {
		$def = $this->buildStructure();
		return sprintf( '%s%s %s %s %s',
			$def[ 'type' ],
			isset( $def[ 'length' ] ) ? sprintf( '(%s)', $def[ 'length' ] ) : '',
			implode( ' ', $def[ 'attr' ] ?? [] ),
			isset( $def[ 'default' ] ) ? sprintf( "DEFAULT %s", $def[ 'default' ] ) : '',
			isset( $def[ 'comment' ] ) ? sprintf( "COMMENT '%s'", str_replace( "'", '', $def[ 'comment' ] ) ) : ''
		);
	}

	protected function buildStructure() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			$this->getMacroTypeDef( $this->def[ 'macro_type' ] ?? '' ),
			$this->def
		);
	}

	protected function getMacroTypeDef( string $macroType ) :array {
		switch ( $macroType ) {

			case self::MACROTYPE_HASH:
				$def = array_merge(
					$this->getMacroTypeDef( self::MACROTYPE_VARCHAR ),
					[
						'length'  => 40,
						'comment' => 'SHA1 Hash',
					]
				);
				break;

			case self::MACROTYPE_IP:
				$def = [
					'type'    => 'varbinary',
					'length'  => 16,
					'attr'    => [],
					'default' => 'NULL',
					'comment' => 'IP Address',
				];
				break;

			case self::MACROTYPE_META:
				$def = array_merge(
					$this->getMacroTypeDef( self::MACROTYPE_TEXT ),
					[
						'comment' => 'Meta Data',
					]
				);
				break;

			case self::MACROTYPE_TEXT:
				$def = [
					'type' => 'text',
				];
				break;

			case self::MACROTYPE_URL:
				$def = array_merge(
					$this->getMacroTypeDef( self::MACROTYPE_VARCHAR ),
					[
						'comment' => 'Site URL',
					]
				);
				break;

			case self::MACROTYPE_VARCHAR:
				$def = [
					'type'    => 'varchar',
					'length'  => 60,
					'attr'    => [
						'NOT NULL',
					],
					'default' => "''",
				];
				break;

			case self::MACROTYPE_BOOL:
				$def = array_merge(
					$this->getMacroTypeDef( self::MACROTYPE_UNSIGNEDINT ),
					[
						'type'    => 'tinyint',
						'length'  => 1,
						'comment' => 'Boolean',
					]
				);
				break;

			case self::MACROTYPE_TIMESTAMP:
				$def = array_merge(
					$this->getMacroTypeDef( self::MACROTYPE_UNSIGNEDINT ),
					[
						'length'  => 15,
						'comment' => 'Epoch Timestamp',
					]
				);
				break;

			case self::MACROTYPE_UNSIGNEDINT:
				$def = [
					'type'    => 'int',
					'length'  => 11,
					'attr'    => [
						'UNSIGNED',
						'NOT NULL',
					],
					'default' => 0,
				];
				break;

			case self::MACROTYPE_PRIMARYID:
				$def = array_merge(
					$this->getMacroTypeDef( self::MACROTYPE_UNSIGNEDINT ),
					[
						'comment' => 'Primary ID',
					]
				);
				$def[ 'attr' ][] = 'AUTO_INCREMENT';
				break;

			default:
				$def = [];
				break;
		}

		return $def;
	}
}