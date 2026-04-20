<?php

namespace Telepedia\Extensions\ExternalVideo;

use MediaWiki\Html\Html;
use ThumbnailImage;

class ExternalVideoThumbnail extends ThumbnailImage {

	/**
	 * Wrap the thumbnail so we can add our play button
	 * @param array $options
	 * @return string
	 */
	public function toHtml( $options = [] ): string {
		$options['file-link'] = false;
		$options['desc-link'] = false;
		$link = $this->file->getTitle()->getFullURL();
		return Html::rawElement(
			'a',
			[
				'class' => 'mw-external-video-thumbnail',
				'href' => $link
			],
			parent::toHtml( $options ) . $this->getPlay()
		);
	}

	/**
	 * Get the play button overlay as a data URI image to avoid MediaWiki's
	 * HTML sanitizer mangling inline SVG content.
	 * @return string
	 */
	private function getPlay(): string {
		$width = (int)( $this->width * 0.3 );
		$height = (int)( $this->height * 0.3 );

		// phpcs:disable Generic.Files.LineLength.TooLong
		$svg = '<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linecap:round;stroke-linejoin:round"><path style="fill:none" d="M0 0h100v100H0z"/><clipPath id="a"><path d="M0 0h100v100H0z"/></clipPath><g clip-path="url(#a)"><path d="M3.125 49.992a46.88 46.88 0 0 0 13.729 33.146 46.88 46.88 0 0 0 66.292 0 46.88 46.88 0 0 0 0-66.292A46.875 46.875 0 0 0 3.125 49.992Z" style="fill:#fff;fill-rule:nonzero;stroke:#fff;stroke-width:6.25px"/><path d="M37.5 65.054a6.82 6.82 0 0 0 3.999 6.202 6.818 6.818 0 0 0 7.301-1.073L71.875 50 48.8 29.804a6.816 6.816 0 0 0-7.303-1.078 6.82 6.82 0 0 0-3.997 6.207v30.121Z" style="fill:#d9e111;fill-rule:nonzero;stroke:#d9e111;stroke-width:6.25px"/></g></svg>';
		// phpcs:enable Generic.Files.LineLength.TooLong

		$src = 'data:image/svg+xml;base64,' . base64_encode( $svg );

		return Html::rawElement(
			'span',
			[ 'class' => 'mw-external-video-thumbnail-play' ],
			Html::element( 'img', [ 'src' => $src, 'width' => $width, 'height' => $height, 'alt' => '' ] )
		);
	}
}
