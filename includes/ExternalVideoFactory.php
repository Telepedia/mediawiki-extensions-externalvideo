<?php

namespace Telepedia\Extensions\ExternalVideo;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Telepedia\Extensions\ExternalVideo\Providers\ExternalVideoProvider;
use Telepedia\Extensions\ExternalVideo\Providers\YouTubeProvider;

class ExternalVideoFactory {

	public function __construct(
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Construct the relevant object for this video
	 * @param string $url
	 *
	 * @return ExternalVideoProvider
	 * @throws InvalidArgumentException
	 */
	public function newFromUrl( string $url ): ?ExternalVideoProvider {
		$provider = $this->determineProvider( $url );

		if ( !$provider ) {
			$this->logger->warning( "Unable to determine provider for External Video upload.",
			[
				'url' => $url,
			] );
			// not sure if we throw here, or if we just return a StausValue or null and let the caller
			// handle the instance where we couldn't proceed
			return null;
		}

		return $provider;
	}

	/**
	 * Take an incoming URL and return the appropriate handler for this provider so that we can
	 * upload the image from it
	 * At the moment it only handles YouTube.
	 * @todo support other providers? Twitch et al?
	 * @param string $url
	 *
	 * @return ExternalVideoProvider|null
	 */
	private function determineProvider( string $url ): ?ExternalVideoProvider {
		// does this match a YouTube URL?
		if ( preg_match( '#(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_\-]+)#', $url, $m ) ) {
			try {
				return new YouTubeProvider( $m[1] );
			} catch ( RuntimeException $e ) {
				$this->logger->warning( "Failed to create YouTubeProvider: " . $e->getMessage(), [
					'videoId' => $m[1],
				] );
				return null;
			}
		}

		// @TODO: try and think of what else we can support; atp I think Twitch

		return null;
	}
}
