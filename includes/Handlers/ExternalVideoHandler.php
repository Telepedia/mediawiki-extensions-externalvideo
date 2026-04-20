<?php

namespace Telepedia\Extensions\ExternalVideo\Handlers;

use Exception;
use File;
use MediaHandler;
use MediaTransformError;
use Telepedia\Extensions\ExternalVideo\ExternalVideoThumbnail;
use Telepedia\Extensions\ExternalVideo\Providers\ExternalVideoProvider;
use Telepedia\Extensions\ExternalVideo\Providers\YouTubeProvider;
use TransformParameterError;
use Wikimedia\FileBackend\FSFile\FSFile;

class ExternalVideoHandler extends MediaHandler {

	/**
	 * Get the supported wikitext parameters; we only support the width param
	 * @inheritDoc
	 */
	public function getParamMap(): array {
		return [
			'img_width' => 'width'
		];
	}

	/**
	 * Copied from TimedMediaHandler!!
	 * @inheritDoc
	 */
	public function validateParam( $name, $value ): ?int {
		if ( in_array( $name, [ 'width', 'height' ] ) ) {
			return $value > 0;
		}
		return true;
	}

	/**
	 * Create a simple param string for thumbnails
	 * Format: {width}px
	 * @param array $params
	 * @return string
	 */
	public function makeParamString( $params ): string {
		if ( isset( $params['width'] ) && $params['width'] > 0 ) {
			return $params['width'] . 'px';
		}
		return '480px';
	}

	/**
	 * Parse the param string back into parameters
	 * Used by thumb.php to make a thumb
	 * @inheritDoc
	 */
	public function parseParamString( $str ): bool|array {
		$params = [];

		if ( preg_match( '/^(\d+)(px)?$/', $str, $matches ) ) {
			$params['width'] = (int)$matches[1];
			return $params;
		}

		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function normaliseParams( $image, &$params ): bool {
		if ( empty( $params['width'] ) ) {
			$params['width'] = 480;
		}

		$size = [
			'width' => $image->getWidth(),
			'height' => $image->getHeight()
		];

		if ( empty( $params['height'] ) ) {
			$params['height'] = File::scaleHeight( $size['width'], $size['height'], $params['width'] );
		}

		return true;
	}

	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function doTransform( $image, $dstPath, $dstUrl, $params, $flags = 0 ): string|\MediaTransformOutput {
		if ( !$this->normaliseParams( $image, $params ) ) {
			return new TransformParameterError( $params );
		}

		if ( $flags & self::TRANSFORM_LATER ) {
			return new ExternalVideoThumbnail(
				$image,
				$dstUrl,
				false,
				[ 'width' => $params['width'], 'height' => $params['height'] ]
			);
		}

		$videoId = $image->getMetadataItem( 'videoId' );

		if ( !$videoId ) {
			return new MediaTransformError( 'externalvideo-noid', 0, 0 );
		}

		// get an instance of our provider
		$mimeType = $image->getMimeType();
		$provider = $this->getProviderFromMime( $mimeType, $videoId );

		// Check if requested dimensions meet the minimum requirements for embed, each provider
		// may have their own minimum (for example, YouTube recommends 480x360); and the provider
		// should define those. At a size < those, we display the thumbnail
		$meetsMinWidth = $params['width'] >= $provider->getMinimumWidthForEmbed();
		$meetsMinHeight = $params['height'] >= $provider->getMinimumHeightForEmbed();
		$canEmbed = $meetsMinWidth && $meetsMinHeight;

		if ( $canEmbed ) {
			return new ExternalVideoTransformOutput( [
				'provider' => $provider,
				'file' => $image,
				'thumbUrl' => $dstUrl,
				'width' => $params['width'],
				'height' => $params['height'],
				'videoId' => $videoId,
			] );
		}

		$srcPath = $image->getLocalRefPath();

		if ( !$srcPath || !file_exists( $srcPath ) ) {
			return new MediaTransformError( 'externalvideo-nosrc', 0, 0 );
		}

		try {
			$imagick = new \Imagick( $srcPath );

			// using ->thumbnailImage led to some distortion and stretching
			// as imagick retained the aspect ratio, ie a 120x120 would be thumbnailed to
			// 120x90 and then MediaWiki tried to render this at 120x120 and stetched
			// so just crop it to this for now
			$imagick->cropThumbnailImage( $params['width'], $params['height'] );
			$imagick->setImageFormat( 'jpeg' );
			$imagick->setImageCompressionQuality( 100 );
			$imagick->writeImage( $dstPath );
			$imagick->clear();
		} catch ( Exception $e ) {
			wfDebugLog( 'ExternalVideo', "Imagick error for video $videoId: " . $e->getMessage() );
			return new MediaTransformError( 'externalvideo-imagick-error', $params['width'], $params['height'] );
		}

		return new ExternalVideoThumbnail( $image, $dstUrl, $dstPath, $params );
	}

	/**
	 * @inheritDoc
	 */
	public function getThumbType( $ext, $mime, $params = null ): array {
		return [ 'jpg', 'image/jpeg' ];
	}

	/**
	 * @inheritDoc
	 */
	public function isEnabled(): bool {
		return true;
	}

	/**
	 * This MUST be set to METADATA_GOOD, otherwise when the page is purged
	 * MediaWiki will recheck its mime and will change it to jpeg since the thumbnail
	 * linked with this revision in the database is a JPEG. This will in turn cause it to
	 * be handled by the JPEG handler which is not what we want
	 * @param FSFile $image
	 * @return bool|int
	 */
	public function isFileMetadataValid( $image ): bool|int {
		return self::METADATA_GOOD;
	}

	/**
	 * @inheritDoc
	 */
	public function mustRender( $file ): bool {
		return true;
	}

	/**
	 * Return an instance of the provider responsible for handling this mime type
	 * @param string $mimeType the mime type ie video/youtube
	 * @param string $videoId the video id
	 * @return ExternalVideoProvider
	 * @throws Exception
	 */
	private function getProviderFromMime( string $mimeType, string $videoId ): ExternalVideoProvider {
		return match ( $mimeType ) {
			'video/youtube' => new YouTubeProvider( $videoId ),
			default => throw new Exception( "Unsupported video provider for MIME type: $mimeType" ),
		};
	}
}
