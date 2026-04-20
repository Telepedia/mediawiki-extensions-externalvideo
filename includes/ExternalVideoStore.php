<?php

namespace Telepedia\Extensions\ExternalVideo;

use MediaWiki\MediaWikiServices;
use MWFileProps;
use RepoGroup;
use StatusValue;
use Telepedia\Extensions\ExternalVideo\Providers\ExternalVideoProvider;
use Title;
use User;
use Wikimedia\FileBackend\FSFile\FSFile;

class ExternalVideoStore {

	public function __construct(
		private readonly RepoGroup $repoGroup
	) {
	}

	/**
	 * Create a video from a provider and upload it to MediaWiki
	 * @param ExternalVideoProvider $provider
	 * @param User $user
	 * @return StatusValue either a fatal with the error or good with the prefixed file title string
	 */
	public function createFileFromProvider( ExternalVideoProvider $provider, User $user ): StatusValue {
		$thumbUrl = $provider->getThumbnailUrl();
		$thumbData = file_get_contents( $thumbUrl );

		if ( $thumbData === false ) {
			return StatusValue::newFatal( 'externalvideo-thumbnail-download-failed' );
		}

		$tmpBase = tempnam( sys_get_temp_dir(), 'ExternalVideo_' );
		if ( $tmpBase === false ) {
			return StatusValue::newFatal( 'externalvideo-tmp-file-error' );
		}

		$tmpFile = $tmpBase . '.jpg';
		unlink( $tmpBase );

		if ( file_put_contents( $tmpFile, $thumbData ) === false ) {
			return StatusValue::newFatal( 'externalvideo-thumbnail-download-failed' );
		}

		$title = $provider->getTitle();

		$fileTitle = Title::makeTitle( NS_FILE, $title );

		if ( !$fileTitle ) {
			unlink( $tmpFile );
			return StatusValue::newFatal( 'externalvideo-invalid-title' );
		}

		$file = $this->repoGroup->getLocalRepo()->newFile( $fileTitle );

		$mwProps = new MWFileProps( MediaWikiServices::getInstance()->getMimeAnalyzer() );
		$props = $mwProps->getPropsFromPath( $tmpFile, true );

		// override some of the stuff with our custom values - this ensures that the file uses
		// the correct handler by forcing it to video/X rather than that of the thumb
		// and also set the media_type to VIDEO, but also allows us to use the size, sha1, and
		// height/width detected by MediaWiki
		$minor = $provider->getMimeMinor();
		$props['mime'] = 'video/' . $minor;
		$props['media_type'] = 'VIDEO';
		$props['metadata'] = [ 'videoId' => $provider->getId() ];
		$props['file-mime'] = 'video/' . $minor;
		$props['major_mime'] = 'video';
		$props['minor_mime'] = $minor;

		// try and upload the thumbnail
		$status = $file->upload(
			new FSFile( $tmpFile ),
			'Added a video using ExternalProvider',
			'[[Category:Videos]]',
			0,
			$props,
			wfTimestampNow(),
			$user
		);

		// delete the file irregardless of whether the upload was successful or not
		if ( file_exists( $tmpFile ) ) {
			unlink( $tmpFile );
		}

		if ( !$status->isOK() ) {
			return $status;
		}

		return StatusValue::newGood( $fileTitle->getPrefixedText() );
	}
}
